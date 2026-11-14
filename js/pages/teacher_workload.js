/**
 * Teacher Workload (pages/teacher_workload.php)
 *
 * Lessons a week per teacher for the chosen academic year and term. The
 * numbers come from GET /api/schedules/teacher-workload, which totals each
 * teacher's allocated learning areas by the class's weekly lessons (a stream
 * allocation overrides the class allocation). Every teacher is listed, so
 * teachers with nothing allocated stand out too.
 */
const TeacherWorkloadController = {
  OVER: 30,
  UNDER: 15,

  state: {
    rows: [],
    teachers: [],
    periodsPerWeek: 0,
    yearId: null,
    termId: null,
    yearLabel: '',
    termLabel: '',
    loadToken: 0,
  },

  async init() {
    await window.AuthContext?.ready?.();
    if (!window.AuthContext?.isAuthenticated?.()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this.bindEvents();
    const auth = window.AuthContext;
    if (auth?.canExport && !auth.canExport('staff')) document.getElementById('exportWorkload').hidden = true;
    if (auth?.canPrint && !auth.canPrint('staff')) document.getElementById('printWorkload').hidden = true;

    const teachersPromise = window.API.staff.getTeachers({}).catch(() => []);
    const yearSelect = document.getElementById('academicYearFilter');
    const termSelect = document.getElementById('academicTermFilter');
    let mounted = false;
    if (window.AcademicYearFilter && yearSelect) {
      await window.AcademicYearFilter.mount(yearSelect, {
        termSelect,
        onChange: (yearId, year, termId, term) => {
          this.state.yearId = yearId;
          this.state.termId = termId;
          this.state.yearLabel = year?.year_name || year?.year_code || '';
          this.state.termLabel = term?.name || '';
          if (mounted) this.load();
        },
      });
    }
    mounted = true;
    const teachers = await teachersPromise;
    this.state.teachers = Array.isArray(teachers) ? teachers : (teachers?.data || []);
    await this.load();
  },

  bindEvents() {
    document.getElementById('searchTeacher')?.addEventListener('input', () => this.renderTable());
    document.getElementById('filterDepartment')?.addEventListener('change', () => this.renderTable());
    document.getElementById('filterWorkload')?.addEventListener('change', () => this.renderTable());
    document.getElementById('exportWorkload')?.addEventListener('click', () => this.exportCsv());
    document.getElementById('printWorkload')?.addEventListener('click', () => window.print());
  },

  async load() {
    const tbody = document.querySelector('#workloadTable tbody');
    if (!this.state.yearId || !this.state.termId) {
      if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">This year has no terms yet.</td></tr>';
      return;
    }
    const token = ++this.state.loadToken;
    try {
      const response = await window.API.schedules.getTeacherWorkload({
        academic_year_id: this.state.yearId,
        academic_year_term_id: this.state.termId,
      });
      if (token !== this.state.loadToken) return;
      const data = response?.data || response || {};
      this.state.periodsPerWeek = Number(data.lesson_periods_per_week || 0);
      this.state.rows = this.merge(Array.isArray(data.teachers) ? data.teachers : []);
      this.renderGaps(data);
    } catch (error) {
      if (token !== this.state.loadToken) return;
      this.state.rows = [];
      window.showNotification?.(error.message || 'Could not load the workload.', 'error');
    }
    this.populateDepartments();
    this.renderStats();
    this.renderTable();
  },

  /** Every teacher, with their allocated load (zero when nothing is allocated). */
  merge(loads) {
    const byId = new Map(loads.map((row) => [Number(row.teacher_id), row]));
    const rows = this.state.teachers.map((t) => {
      const load = byId.get(Number(t.id));
      byId.delete(Number(t.id));
      return {
        teacher_id: Number(t.id),
        teacher_name: `${t.first_name || ''} ${t.last_name || ''}`.trim() || load?.teacher_name || `Staff #${t.id}`,
        department_name: t.department_name || load?.department_name || '',
        lessons_per_week: Number(load?.lessons_per_week || 0),
        unset_lessons: Number(load?.unset_lessons || 0),
        allocations: Number(load?.allocations || 0),
        streams: load?.streams || [],
        learning_areas: load?.learning_areas || [],
      };
    });
    byId.forEach((load) => rows.push({ ...load, lessons_per_week: Number(load.lessons_per_week || 0) }));
    return rows.sort((a, b) => b.lessons_per_week - a.lessons_per_week || a.teacher_name.localeCompare(b.teacher_name));
  },

  band(row) {
    if (!row.allocations) return 'none';
    if (row.lessons_per_week > this.OVER) return 'overloaded';
    if (row.lessons_per_week < this.UNDER) return 'underloaded';
    return 'optimal';
  },

  renderGaps(data) {
    const box = document.getElementById('workloadGaps');
    if (!box) return;
    const p = data.problems || {};
    const base = window.APP_BASE || '';
    const parts = [];
    if (p.lessons_not_set) parts.push(`${p.lessons_not_set} class learning area${p.lessons_not_set === 1 ? ' has' : 's have'} no weekly lessons yet — <a href="${base}/home.php?route=lesson_periods">set them in Lesson Periods</a>`);
    if (p.no_teacher) parts.push(`${p.no_teacher} have no teacher — <a href="${base}/home.php?route=assign_subjects_to_teachers">allocate in Subject Teachers</a>`);
    if (p.several_teachers) parts.push(`${p.several_teachers} have more than one subject teacher, so their lessons are not counted for anyone`);
    box.innerHTML = parts.length ? `<strong>Totals are incomplete for ${this.escapeHtml(this.state.termLabel)}:</strong> ${parts.join('; ')}.` : '';
    box.hidden = !parts.length;
  },

  populateDepartments() {
    const select = document.getElementById('filterDepartment');
    if (!select) return;
    const previous = select.value;
    const names = [...new Set(this.state.rows.map((r) => r.department_name).filter(Boolean))].sort();
    select.innerHTML = '<option value="">All departments</option>' + names.map((n) => `<option value="${this.escapeHtml(n)}">${this.escapeHtml(n)}</option>`).join('');
    select.value = names.includes(previous) ? previous : '';
  },

  renderStats() {
    const allocated = this.state.rows.filter((r) => r.allocations > 0);
    const total = allocated.reduce((sum, r) => sum + r.lessons_per_week, 0);
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    set('totalTeachers', this.state.rows.length);
    set('avgLessons', allocated.length ? Math.round(total / allocated.length) : 0);
    set('overloaded', this.state.rows.filter((r) => this.band(r) === 'overloaded').length);
    set('underloaded', this.state.rows.filter((r) => this.band(r) === 'underloaded').length);
  },

  filtered() {
    const q = (document.getElementById('searchTeacher')?.value || '').trim().toLowerCase();
    const dept = document.getElementById('filterDepartment')?.value || '';
    const band = document.getElementById('filterWorkload')?.value || '';
    return this.state.rows.filter((r) => {
      if (dept && r.department_name !== dept) return false;
      if (band && this.band(r) !== band) return false;
      if (q && ![r.teacher_name, ...r.streams, ...r.learning_areas].some((v) => String(v).toLowerCase().includes(q))) return false;
      return true;
    });
  },

  renderTable() {
    const tbody = document.querySelector('#workloadTable tbody');
    if (!tbody) return;
    const rows = this.filtered();
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No teachers match.</td></tr>';
      return;
    }
    const scale = Math.max(this.state.periodsPerWeek || 0, this.OVER + 5, ...rows.map((r) => r.lessons_per_week));
    tbody.innerHTML = rows.map((r) => {
      const band = this.band(r);
      const pct = Math.min(100, Math.round((r.lessons_per_week / scale) * 100));
      const barClass = band === 'overloaded' ? ' is-over' : (band === 'underloaded' ? ' is-under' : '');
      const lessons = r.allocations
        ? `${r.lessons_per_week}${r.unset_lessons ? ` <small class="text-muted fw-normal">+ ${r.unset_lessons} not set</small>` : ''}`
        : '<span class="text-muted fw-normal">Nothing allocated</span>';
      return `<tr>
        <td><strong>${this.escapeHtml(r.teacher_name)}</strong></td>
        <td>${this.escapeHtml(r.department_name || '—')}</td>
        <td class="tw-list">${this.escapeHtml(r.learning_areas.join(', ') || '—')}</td>
        <td class="tw-list">${this.escapeHtml(r.streams.join(', ') || '—')}</td>
        <td class="tw-lessons">${lessons}</td>
        <td class="tw-no-print"><div class="tw-bar${barClass}" role="img" aria-label="${r.lessons_per_week} lessons a week"><span style="width:${pct}%"></span></div></td>
      </tr>`;
    }).join('');
  },

  async exportCsv() {
    const rows = this.filtered();
    if (!rows.length) {
      window.showNotification?.('Nothing to export', 'info');
      return;
    }
    const cell = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const lines = [['Academic year', 'Term', 'Teacher', 'Department', 'Learning areas', 'Classes', 'Lessons a week', 'Allocations without weekly lessons'].map(cell).join(',')];
    rows.forEach((r) => lines.push([this.state.yearLabel, this.state.termLabel, r.teacher_name, r.department_name,
      r.learning_areas.join('; '), r.streams.join('; '), r.lessons_per_week, r.unset_lessons].map(cell).join(',')));
    const safe = `${this.state.yearLabel}_${this.state.termLabel}`.replace(/[^0-9A-Za-z-]+/g, '-');
    try {
      await window.KingswayFileLifecycle.exportText('﻿' + lines.join('\r\n'), `teacher_workload_${safe}.csv`, 'text/csv;charset=utf-8');
    } catch (error) {
      window.showNotification?.(error.message || 'Export failed', 'error');
    }
  },

  escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  },
};

document.addEventListener('DOMContentLoaded', () => TeacherWorkloadController.init());
window.TeacherWorkloadController = TeacherWorkloadController;
