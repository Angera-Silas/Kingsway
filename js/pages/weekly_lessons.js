/**
 * Weekly lessons (the second section of pages/lesson_periods.php).
 *
 * A grid of learning areas (rows) by classes (columns) for one academic year.
 * Each cell is how many lessons that learning area gets in a week in that
 * class. The footer totals each class against the lesson periods of a school
 * week, because every lesson needs a period. Timetable drafts place exactly
 * these numbers, and Teacher Workload totals them.
 *
 * API: GET/PUT /api/schedules/weekly-lessons
 */
const WeeklyLessonsController = (() => {
  'use strict';

  const MAX = 15;
  let grid = null;           // as last loaded
  let edits = new Map();     // cell id -> value (number or null)
  let canEdit = false;
  let yearId = null;
  let yearLabel = '';
  let loadToken = 0;

  const $ = (id) => document.getElementById(id);
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function cellValue(cell) {
    return edits.has(cell.id) ? edits.get(cell.id) : cell.lessons_per_week;
  }

  function index() {
    const byKey = new Map();
    (grid?.cells || []).forEach((cell) => byKey.set(`${cell.learning_area_id}:${cell.academic_year_class_id}`, cell));
    return byKey;
  }

  function totals() {
    const out = new Map();
    (grid?.classes || []).forEach((c) => out.set(c.academic_year_class_id, { sum: 0, unset: 0 }));
    (grid?.cells || []).forEach((cell) => {
      const t = out.get(cell.academic_year_class_id);
      if (!t) return;
      const v = cellValue(cell);
      if (v === null || v === undefined) t.unset += 1;
      else t.sum += Number(v);
    });
    return out;
  }

  function render() {
    const head = $('wlHead');
    const body = $('wlBody');
    const foot = $('wlFoot');
    if (!grid) return;
    const classes = grid.classes || [];
    const areas = grid.learning_areas || [];
    const limit = Number(grid.lesson_periods_per_week || 0);

    if (!classes.length || !areas.length) {
      head.innerHTML = '';
      foot.innerHTML = '';
      body.innerHTML = `<tr><td class="text-center text-muted py-4">${classes.length
        ? `No learning areas are set up for the classes of ${esc(yearLabel)} yet. Set them up in the academic year workflow first.`
        : `No classes are set up for ${esc(yearLabel)} yet.`}</td></tr>`;
      $('wlSummary').textContent = 'How many lessons each learning area gets in a week, per class.';
      updateButtons();
      return;
    }

    head.innerHTML = `<tr><th scope="col" class="wl-area">Learning area</th>${classes
      .map((c) => `<th scope="col">${esc(c.class_name)}</th>`).join('')}</tr>`;

    const byKey = index();
    const disabled = canEdit && grid.ready ? '' : ' disabled';
    body.innerHTML = areas.map((area) => `<tr><th scope="row" class="wl-area fw-normal">${esc(area.name)}</th>${classes.map((c) => {
      const cell = byKey.get(`${area.learning_area_id}:${c.academic_year_class_id}`);
      if (!cell) return '<td class="wl-na" title="Not taught in this class">·</td>';
      const v = cellValue(cell);
      const unset = v === null || v === undefined;
      return `<td><input type="number" inputmode="numeric" min="0" max="${MAX}" step="1" class="form-control form-control-sm${unset ? ' wl-unset' : ''}"
        data-cell="${cell.id}" value="${unset ? '' : Number(v)}" placeholder="–"
        aria-label="${esc(area.name)}, ${esc(c.class_name)}: lessons per week"${disabled}></td>`;
    }).join('')}</tr>`).join('');

    renderFooter();
  }

  function renderFooter() {
    const classes = grid?.classes || [];
    const limit = Number(grid?.lesson_periods_per_week || 0);
    const t = totals();
    let over = 0;
    let unsetTotal = 0;
    $('wlFoot').innerHTML = `<tr><td class="wl-area text-start">Lessons a week${limit ? ` <small class="text-muted fw-normal">of ${limit} periods</small>` : ''}</td>${classes.map((c) => {
      const { sum, unset } = t.get(c.academic_year_class_id) || { sum: 0, unset: 0 };
      unsetTotal += unset;
      const isOver = limit > 0 && sum > limit;
      if (isOver) over += 1;
      const cls = isOver ? ' is-over' : (limit > 0 && sum === limit && !unset ? ' is-full' : '');
      const note = isOver ? `${sum - limit} too many` : (unset ? `${unset} not set` : (limit ? `${limit - sum} free` : ''));
      return `<td class="wl-total${cls}">${sum}<small>${esc(note)}</small></td>`;
    }).join('')}</tr>`;

    const parts = [];
    if (limit) parts.push(`The school week has ${limit} lesson periods per class.`);
    if (over) parts.push(`${over === 1 ? '1 class has' : `${over} classes have`} more lessons than periods.`);
    if (unsetTotal) parts.push(`${unsetTotal} learning area${unsetTotal === 1 ? '' : 's'} still need a number.`);
    if (!over && !unsetTotal && limit) parts.push('Every learning area has its weekly lessons.');
    $('wlSummary').textContent = parts.join(' ');
    updateButtons(over > 0);
  }

  function dirty() {
    if (!grid) return false;
    const byId = new Map((grid.cells || []).map((c) => [c.id, c]));
    for (const [id, value] of edits) {
      if ((byId.get(id)?.lessons_per_week ?? null) !== value) return true;
    }
    return false;
  }

  function updateButtons(over = false) {
    const isDirty = dirty();
    $('wlSave').disabled = !isDirty || over || !grid?.ready;
    $('wlSave').title = over ? 'Some classes have more lessons than the week has periods.' : '';
    $('wlDiscard').disabled = !isDirty;
  }

  async function load() {
    if (!yearId) return;
    const token = ++loadToken;
    try {
      const response = await window.API.schedules.getWeeklyLessons(yearId);
      if (token !== loadToken) return;
      grid = response?.data || response || null;
      edits = new Map();
      $('wlNotReady').hidden = !!grid?.ready;
    } catch (error) {
      if (token !== loadToken) return;
      grid = null;
      $('wlBody').innerHTML = '<tr><td class="text-center text-muted py-4">Could not load weekly lessons.</td></tr>';
      window.showNotification?.(error.message || 'Could not load weekly lessons.', 'error');
      return;
    }
    render();
  }

  async function save() {
    const button = $('wlSave');
    button.disabled = true;
    button.textContent = 'Saving…';
    try {
      const changes = [...edits].map(([id, value]) => ({ id, lessons_per_week: value }));
      const response = await window.API.schedules.saveWeeklyLessons(yearId, changes);
      grid = response?.data || response || grid;
      edits = new Map();
      window.showNotification?.('Weekly lessons saved.', 'success');
    } catch (error) {
      window.showNotification?.(error.message || 'Could not save weekly lessons.', 'error');
    } finally {
      button.textContent = 'Save weekly lessons';
      render();
    }
  }

  function exportCsv() {
    if (!grid) return;
    const byKey = index();
    const cell = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const classes = grid.classes || [];
    const lines = [['Learning area', ...classes.map((c) => c.class_name)].map(cell).join(',')];
    (grid.learning_areas || []).forEach((area) => {
      lines.push([area.name, ...classes.map((c) => {
        const found = byKey.get(`${area.learning_area_id}:${c.academic_year_class_id}`);
        if (!found) return '';
        const v = found.lessons_per_week;
        return v === null || v === undefined ? 'not set' : v;
      })].map(cell).join(','));
    });
    const t = totals();
    lines.push(['Lessons a week', ...classes.map((c) => t.get(c.academic_year_class_id)?.sum ?? 0)].map(cell).join(','));
    const safe = String(yearLabel || 'year').replace(/[^0-9A-Za-z-]+/g, '-');
    window.KingswayFileLifecycle.exportText(`﻿${lines.join('\r\n')}`, `weekly_lessons_${safe}.csv`, 'text/csv;charset=utf-8')
      .catch((error) => window.showNotification?.(error.message || 'Export failed.', 'error'));
  }

  function bind() {
    $('wlBody').addEventListener('input', (event) => {
      const input = event.target.closest('input[data-cell]');
      if (!input) return;
      const id = Number(input.dataset.cell);
      const raw = input.value.trim();
      let value = raw === '' ? null : Math.round(Number(raw));
      if (value !== null && (!Number.isFinite(value) || value < 0)) value = 0;
      if (value !== null && value > MAX) value = MAX;
      edits.set(id, value);
      input.classList.toggle('wl-unset', value === null);
      renderFooter();
    });
    $('wlBody').addEventListener('change', (event) => {
      const input = event.target.closest('input[data-cell]');
      if (!input) return;
      const value = edits.get(Number(input.dataset.cell));
      input.value = value === null || value === undefined ? '' : String(value);
    });
    $('wlSave').addEventListener('click', save);
    $('wlDiscard').addEventListener('click', () => { edits = new Map(); render(); });
    $('wlExport').addEventListener('click', exportCsv);
    window.addEventListener('kingsway:lesson-periods-saved', () => load());
    window.addEventListener('beforeunload', (event) => {
      if (canEdit && dirty()) { event.preventDefault(); event.returnValue = ''; }
    });
  }

  function editAllowed() {
    const auth = window.AuthContext;
    if (!auth) return false;
    const roles = (auth.getRoles?.() || []).map((r) => String(typeof r === 'object' ? (r.name || r.role_name || '') : r).toLowerCase());
    const leadership = roles.some((r) => /admin|director|headteacher|deputy/.test(r));
    const permitted = ['schedules_create', 'schedules_update', 'schedules_manage', 'schedules_approve']
      .some((code) => auth.hasPermission?.(code));
    return leadership && permitted;
  }

  async function init() {
    const body = $('wlBody');
    if (!body || body.dataset.ready) return;
    body.dataset.ready = '1';
    await window.AuthContext?.ready?.();
    canEdit = editAllowed();
    $('wlEditActions').hidden = !canEdit;
    const auth = window.AuthContext;
    if (!canEdit && auth?.canExport && !auth.canExport('schedules')) $('wlExport').hidden = true;
    bind();
    const select = $('wlYear');
    if (window.AcademicYearFilter && select) {
      await window.AcademicYearFilter.mount(select, {
        onChange: async (id, year) => {
          if (yearId && id !== yearId && dirty()) {
            const discard = await window.confirmAction?.('Unsaved weekly lessons',
              `Discard the unsaved changes for ${yearLabel}?`, { confirmText: 'Discard', danger: true });
            if (!discard) {
              select.value = String(yearId);
              return;
            }
          }
          yearId = id;
          yearLabel = year?.year_name || year?.year_code || '';
          load();
        },
      });
    }
  }

  return { init };
})();

window.WeeklyLessonsController = WeeklyLessonsController;
if (window.__APP_BOOTED__) {
  WeeklyLessonsController.init();
} else {
  window.addEventListener('kingsway:ready', () => WeeklyLessonsController.init(), { once: true });
}
