/**
 * Lesson Periods page (pages/lesson_periods.php).
 *
 * Edits the school day's periods (time_slots): lessons, breaks, lunch,
 * assembly and games. The day strip at the top redraws on every edit so the
 * whole day can be checked at a glance before saving. Validation runs live
 * and again on the server (PUT /api/schedules/timetable-time-slots).
 */
const LessonPeriodsController = (() => {
  'use strict';

  const TYPES = [
    { code: 'lesson', label: 'Lesson' },
    { code: 'break', label: 'Break' },
    { code: 'lunch', label: 'Lunch' },
    { code: 'assembly', label: 'Assembly' },
    { code: 'games', label: 'Games' },
  ];

  let saved = [];      // rows as last loaded from the server
  let rows = [];       // the editable copy
  let canEdit = false;
  let nextKey = 1;     // local keys for rows not yet saved

  const $ = (id) => document.getElementById(id);

  function minutes(time) {
    const match = /^(\d{1,2}):(\d{2})/.exec(String(time || ''));
    return match ? Number(match[1]) * 60 + Number(match[2]) : null;
  }

  function hhmm(total) {
    const h = Math.floor(total / 60);
    const m = total % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
  }

  function normalize(slot) {
    return {
      key: slot.id ? `id${slot.id}` : `new${nextKey++}`,
      id: slot.id ? Number(slot.id) : null,
      label: String(slot.label || ''),
      slot_type: String(slot.slot_type || 'lesson'),
      start_time: String(slot.start_time || '').slice(0, 5),
      end_time: String(slot.end_time || '').slice(0, 5),
    };
  }

  function sorted(list) {
    return [...list].sort((a, b) => (minutes(a.start_time) ?? 9999) - (minutes(b.start_time) ?? 9999));
  }

  /** Problems keyed by row key, plus whole-day problems. */
  function validate(list) {
    const problems = {};
    const add = (key, text) => { (problems[key] = problems[key] || []).push(text); };
    list.forEach((row) => {
      const start = minutes(row.start_time);
      const end = minutes(row.end_time);
      if (!row.label.trim()) add(row.key, 'Give this period a name.');
      if (start === null || end === null) add(row.key, 'Enter a start and end time.');
      else if (end <= start) add(row.key, 'The end time must be after the start time.');
    });
    const ordered = sorted(list).filter((row) => minutes(row.start_time) !== null && minutes(row.end_time) !== null);
    for (let i = 1; i < ordered.length; i += 1) {
      if (minutes(ordered[i].start_time) < minutes(ordered[i - 1].end_time)) {
        add(ordered[i].key, `Starts before ${ordered[i - 1].label || 'the previous period'} ends.`);
      }
    }
    const general = [];
    if (!list.length) general.push('Add at least one period.');
    else if (!list.some((row) => row.slot_type === 'lesson')) general.push('The school day needs at least one lesson period.');
    return { problems, general };
  }

  function isDirty() {
    const strip = (list) => JSON.stringify(sorted(list).map(({ id, label, slot_type, start_time, end_time }) =>
      ({ id, label: label.trim(), slot_type, start_time, end_time })));
    return strip(rows) !== strip(saved);
  }

  // ---- Rendering ----------------------------------------------------------

  function renderSummary() {
    const valid = sorted(rows).filter((row) => minutes(row.start_time) !== null && minutes(row.end_time) > minutes(row.start_time));
    if (!valid.length) {
      $('lpSummary').textContent = 'No periods yet. Add the first one below.';
      return;
    }
    const lessons = valid.filter((row) => row.slot_type === 'lesson');
    const lengths = lessons.map((row) => minutes(row.end_time) - minutes(row.start_time));
    const common = lengths.length
      ? Number(Object.entries(lengths.reduce((m, l) => ({ ...m, [l]: (m[l] || 0) + 1 }), {}))
        .sort((a, b) => b[1] - a[1])[0][0])
      : 0;
    const allSame = lengths.every((l) => l === common);
    const dayStart = valid[0].start_time;
    const dayEnd = hhmm(Math.max(...valid.map((row) => minutes(row.end_time))));
    const lessonText = lessons.length === 1 ? '1 lesson' : `${lessons.length} lessons`;
    const lengthText = lessons.length ? `, ${allSame ? 'each' : 'mostly'} ${common} minutes` : '';
    $('lpSummary').textContent = `${lessonText}${lengthText}. School runs ${dayStart} to ${dayEnd}.`;
  }

  function renderDay(problemKeys) {
    const day = $('lpDay');
    const axis = $('lpAxis');
    const valid = sorted(rows).filter((row) => minutes(row.start_time) !== null && minutes(row.end_time) > minutes(row.start_time));
    day.innerHTML = '';
    axis.innerHTML = '';
    if (!valid.length) return;
    const first = minutes(valid[0].start_time);
    const last = Math.max(...valid.map((row) => minutes(row.end_time)));
    const span = Math.max(1, last - first);
    let cursor = first;
    const block = (type, from, to, text, title, problem) => {
      const el = document.createElement('div');
      el.className = 'lp-block' + (problem ? ' has-problem' : '');
      el.dataset.type = type;
      el.style.flex = `0 0 ${((to - from) / span) * 100}%`;
      el.title = title;
      if (text && (to - from) / span > 0.045) {
        const label = document.createElement('span');
        label.textContent = text;
        el.appendChild(label);
      }
      day.appendChild(el);
    };
    valid.forEach((row) => {
      const start = minutes(row.start_time);
      const end = minutes(row.end_time);
      if (start > cursor) block('gap', cursor, start, '', `Unassigned time ${hhmm(cursor)} to ${hhmm(start)}`, false);
      const from = Math.max(start, cursor);
      if (end > from) {
        block(row.slot_type, from, end, row.label, `${row.label}: ${row.start_time} to ${row.end_time}`, problemKeys.has(row.key));
      }
      cursor = Math.max(cursor, end);
    });
    const marks = [first, first + span / 4, first + span / 2, first + (span * 3) / 4, last].map((m) => hhmm(Math.round(m / 5) * 5));
    marks.forEach((mark) => {
      const el = document.createElement('span');
      el.textContent = mark;
      axis.appendChild(el);
    });
    day.setAttribute('aria-label', `School day from ${hhmm(first)} to ${hhmm(last)} with ${valid.length} periods.`);
  }

  function renderTable(problems) {
    const body = $('lpBody');
    body.innerHTML = '';
    const ordered = sorted(rows);
    if (!ordered.length) {
      body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No periods yet.</td></tr>';
      return;
    }
    ordered.forEach((row, index) => {
      const tr = document.createElement('tr');
      tr.dataset.key = row.key;
      if (problems[row.key]) tr.classList.add('lp-row-problem');
      const start = minutes(row.start_time);
      const end = minutes(row.end_time);
      const length = start !== null && end !== null && end > start ? `${end - start} min` : '—';
      const disabled = canEdit ? '' : ' disabled';
      tr.innerHTML = `
        <td class="lp-num">${index + 1}</td>
        <td><input type="text" class="form-control form-control-sm" maxlength="50" data-field="label" aria-label="Period name"${disabled}></td>
        <td><select class="form-select form-select-sm" data-field="slot_type" aria-label="Period type"${disabled}>
          ${TYPES.map((t) => `<option value="${t.code}">${t.label}</option>`).join('')}
        </select></td>
        <td><input type="time" class="form-control form-control-sm" step="300" data-field="start_time" aria-label="Start time"${disabled}></td>
        <td><input type="time" class="form-control form-control-sm" step="300" data-field="end_time" aria-label="End time"${disabled}></td>
        <td class="lp-duration">${length}</td>
        <td class="lp-no-print text-end">${canEdit ? '<button type="button" class="btn btn-sm btn-outline-danger" data-remove title="Remove period" aria-label="Remove period"><i class="bi bi-trash"></i></button>' : ''}</td>`;
      tr.querySelector('[data-field="label"]').value = row.label;
      tr.querySelector('[data-field="slot_type"]').value = row.slot_type;
      tr.querySelector('[data-field="start_time"]').value = row.start_time;
      tr.querySelector('[data-field="end_time"]').value = row.end_time;
      if (problems[row.key]) {
        const note = document.createElement('span');
        note.className = 'lp-problem';
        note.textContent = problems[row.key].join(' ');
        tr.children[1].appendChild(note);
      }
      body.appendChild(tr);
    });
  }

  function render({ table = true } = {}) {
    const { problems, general } = validate(rows);
    renderSummary();
    renderDay(new Set(Object.keys(problems)));
    if (table) renderTable(problems);
    const dirty = isDirty();
    const blocked = Object.keys(problems).length > 0 || general.length > 0;
    $('lpSave').disabled = !dirty || blocked;
    $('lpSave').title = blocked ? (general[0] || 'Fix the highlighted periods first.') : '';
    $('lpDiscard').disabled = !dirty;
  }

  // ---- Data -----------------------------------------------------------------

  async function load() {
    try {
      const response = await window.API.schedules.getTimeSlots();
      const list = response?.data || response || [];
      saved = (Array.isArray(list) ? list : []).map(normalize);
      rows = saved.map((row) => ({ ...row }));
    } catch (error) {
      saved = [];
      rows = [];
      window.showNotification?.(error.message || 'Could not load the periods.', 'error');
    }
    render();
  }

  async function save() {
    const button = $('lpSave');
    button.disabled = true;
    button.textContent = 'Saving…';
    try {
      const payload = sorted(rows).map(({ id, label, slot_type, start_time, end_time }) =>
        ({ ...(id ? { id } : {}), label: label.trim(), slot_type, start_time, end_time }));
      const response = await window.API.schedules.saveTimeSlots(payload);
      const list = response?.data || response || [];
      saved = (Array.isArray(list) ? list : []).map(normalize);
      rows = saved.map((row) => ({ ...row }));
      window.showNotification?.('Periods saved.', 'success');
      // The week's lesson-period count bounds each class's weekly lessons.
      window.dispatchEvent(new CustomEvent('kingsway:lesson-periods-saved'));
    } catch (error) {
      window.showNotification?.(error.message || 'Could not save the periods.', 'error');
    } finally {
      button.textContent = 'Save periods';
      render();
    }
  }

  function addPeriod() {
    const ordered = sorted(rows);
    const last = ordered[ordered.length - 1];
    const lastLesson = [...ordered].reverse().find((row) => row.slot_type === 'lesson');
    const length = lastLesson ? minutes(lastLesson.end_time) - minutes(lastLesson.start_time) : 35;
    const start = last && minutes(last.end_time) !== null ? minutes(last.end_time) : 8 * 60;
    const lessonCount = rows.filter((row) => row.slot_type === 'lesson').length;
    rows.push(normalize({
      label: `Period ${lessonCount + 1}`,
      slot_type: 'lesson',
      start_time: hhmm(start),
      end_time: hhmm(Math.min(start + length, 23 * 60 + 55)),
    }));
    render();
    $('lpBody').querySelector('tr:last-child [data-field="label"]')?.focus();
  }

  function exportCsv() {
    const lines = [['Period', 'Name', 'Type', 'Starts', 'Ends', 'Minutes']];
    sorted(saved).forEach((row, index) => {
      lines.push([index + 1, row.label, TYPES.find((t) => t.code === row.slot_type)?.label || row.slot_type,
        row.start_time, row.end_time, minutes(row.end_time) - minutes(row.start_time)]);
    });
    const cell = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const csv = lines.map((line) => line.map(cell).join(',')).join('\r\n');
    window.KingswayFileLifecycle.exportText(`﻿${csv}`, 'lesson_periods.csv', 'text/csv;charset=utf-8')
      .catch((error) => window.showNotification?.(error.message || 'Export failed.', 'error'));
  }

  // ---- Wiring -----------------------------------------------------------------

  function bind() {
    const body = $('lpBody');
    body.addEventListener('input', (event) => {
      const field = event.target.dataset.field;
      const key = event.target.closest('tr')?.dataset.key;
      const row = rows.find((r) => r.key === key);
      if (!row || !field) return;
      row[field] = event.target.value;
      // Times reorder rows and change lengths: re-render the table on change
      // (not on every keystroke) so typing is not interrupted.
      render({ table: false });
    });
    body.addEventListener('change', (event) => {
      if (event.target.dataset.field) render();
    });
    body.addEventListener('click', (event) => {
      if (!event.target.closest('[data-remove]')) return;
      const key = event.target.closest('tr')?.dataset.key;
      rows = rows.filter((r) => r.key !== key);
      render();
    });
    $('lpAdd').addEventListener('click', addPeriod);
    $('lpSave').addEventListener('click', save);
    $('lpDiscard').addEventListener('click', () => { rows = saved.map((row) => ({ ...row })); render(); });
    $('lpExport').addEventListener('click', exportCsv);
    $('lpPrint').addEventListener('click', () => window.print());
    window.addEventListener('beforeunload', (event) => {
      if (canEdit && isDirty()) { event.preventDefault(); event.returnValue = ''; }
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
    if (!$('lpBody') || $('lpBody').dataset.ready) return;
    $('lpBody').dataset.ready = '1';
    await window.AuthContext?.ready?.();
    canEdit = editAllowed();
    $('lpEditActions').hidden = !canEdit;
    // Hard Rule 7: honour the account's export/print rights (editors may
    // always take a copy of what they maintain).
    const auth = window.AuthContext;
    if (!canEdit && auth?.canExport && !auth.canExport('schedules')) $('lpExport').hidden = true;
    if (!canEdit && auth?.canPrint && !auth.canPrint('schedules')) $('lpPrint').hidden = true;
    bind();
    await load();
  }

  return { init };
})();

window.LessonPeriodsController = LessonPeriodsController;
if (window.__APP_BOOTED__) {
  LessonPeriodsController.init();
} else {
  window.addEventListener('kingsway:ready', () => LessonPeriodsController.init(), { once: true });
}
