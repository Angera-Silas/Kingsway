/** Class-teacher-scoped assessment results for report-card preparation. */
const classReportCardsController = {
  state: { columns: [], rows: [], request: 0 },
  api(method, endpoint, data = null, params = null) { return window.callAPI(endpoint, method, data, params); },
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]); },
  rows(response) { const data = response?.data?.data ?? response?.data ?? response ?? []; return Array.isArray(data) ? data : []; },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    ['classFilter'].forEach((id) => document.getElementById(id)?.addEventListener('change', () => this.load()));
    document.getElementById('studentFilter')?.addEventListener('input', () => this.render());
    document.getElementById('exportBtn')?.addEventListener('click', () => this.exportCSV());
    document.getElementById('printBtn')?.addEventListener('click', () => this.print());
    try {
      const classesResponse = await this.api('GET', '/api/academic/classes-list');
      this.fill('classFilter', this.rows(classesResponse), 'All Assigned Classes', (row) => row.name || row.class_name);
    } catch (error) {
      this.showError(error?.message || 'Reference data could not be loaded.');
    }
    // The shared year/term selector (current year, running term by default)
    // loads the results now and on every change.
    const mounted = await window.AcademicYearFilter?.mount(document.getElementById('yearFilter'), {
      termSelect: document.getElementById('termFilter'),
      allTermsLabel: 'All terms',
      allTermsDefault: false,
      onChange: (yearId, year, termId) => {
        this.state.yearId = yearId;
        this.state.termId = termId;
        this.state.yearLabel = year?.year_name || year?.year_code || '';
        this.load();
      },
    });
    if (!mounted) await this.load();
    window.APIRealtime?.register?.('class_report_cards', { refresh: () => this.load() }, ['reports', 'academic']);
  },

  fill(id, rows, firstLabel, label) {
    const select = document.getElementById(id);
    if (!select) return;
    select.innerHTML = `<option value="">${this.esc(firstLabel)}</option>` + rows.map((row) => `<option value="${Number(row.id)}">${this.esc(label(row))}</option>`).join('');
  },

  async load() {
    const request = ++this.state.request;
    this.setLoading(true); this.hideError();
    const params = {
      report_type: 'performance',
      class_teacher_only: '1',
      academic_year_id: this.state.yearId || '',
      year_id: this.state.yearId || '',
      term_id: this.state.termId || '',
      class_id: document.getElementById('classFilter')?.value || '',
    };
    Object.keys(params).forEach((key) => { if (!params[key]) delete params[key]; });
    try {
      const response = await this.api('GET', '/api/academic/reports', null, params);
      if (request !== this.state.request) return;
      if (response?.success === false || response?.error) throw new Error(response.message || response.error || 'Results could not be loaded.');
      const payload = response?.data?.data ?? response?.data ?? response ?? {};
      this.state.columns = Array.isArray(payload.columns) ? payload.columns : [];
      this.state.rows = Array.isArray(payload.rows) ? payload.rows : [];
      this.render();
    } catch (error) {
      if (request !== this.state.request) return;
      this.state.columns = []; this.state.rows = []; this.render();
      this.showError(error?.message || 'Class results could not be loaded.');
    } finally {
      if (request === this.state.request) this.setLoading(false);
    }
  },

  render() {
    const body = document.getElementById('studentsTableBody');
    const query = (document.getElementById('studentFilter')?.value || '').trim().toLocaleLowerCase();
    const rows = this.state.rows.filter((row) => !query || `${row.student_name || ''} ${row.admission_no || ''}`.toLocaleLowerCase().includes(query));
    if (body) body.innerHTML = rows.length ? rows.map((row) => `<tr>${this.state.columns.map((column) => `<td>${this.esc(row[column.key] ?? '—')}</td>`).join('')}</tr>`).join('') : `<tr><td colspan="${Math.max(1, this.state.columns.length)}" class="text-center text-muted py-4">No assessment results match these filters for your assigned classes.</td></tr>`;
    const students = new Set(rows.map((row) => row.student_name).filter(Boolean));
    this.text('totalStudents', students.size);
    this.text('generatedCount', rows.length);
  },

  setLoading(loading) {
    const node = document.getElementById('reportCardsLoading');
    const content = document.getElementById('reportCardsContent');
    if (node) node.classList.toggle('d-none', !loading);
    if (content) content.style.display = loading ? 'none' : '';
  },
  showError(message) { const node = document.getElementById('reportCardsError'); if (node) { node.textContent = message; node.classList.remove('d-none'); } },
  hideError() { document.getElementById('reportCardsError')?.classList.add('d-none'); },
  text(id, value) { const node = document.getElementById(id); if (node) node.textContent = String(value); },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('results')) return window.showNotification?.('You do not have permission to export results.', 'error');
    const quote = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`;
    const csv = [this.state.columns.map((column) => column.label || column.key), ...this.state.rows.map((row) => this.state.columns.map((column) => row[column.key]))].map((row) => row.map(quote).join(',')).join('\r\n');
    if (!this.state.rows.length) return window.showNotification?.('There are no assessment results to export.', 'info');
    window.KingswayFileLifecycle?.exportText?.(csv, `class-report-card-results-${String(this.state.yearLabel || '').replace(/[\/\s]+/g, '-')}.csv`, 'text/csv;charset=utf-8;');
  },
  print() {
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('results')) return window.showNotification?.('You do not have permission to print results.', 'error');
    window.print();
  },
};

window.classReportCardsController = classReportCardsController;
document.addEventListener('DOMContentLoaded', () => classReportCardsController.init().catch((error) => classReportCardsController.showError(error?.message || 'Class results could not be initialized.')));
