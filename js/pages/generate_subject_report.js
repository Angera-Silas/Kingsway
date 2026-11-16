/** Subject report viewer backed by the academic reports endpoint. */
const generateSubjectReportController = {
  state: { years: [], terms: [], classes: [], subjects: [], columns: [], rows: [], summary: {} },
  API: (method, endpoint, data = null, params = null) => window.callAPI(endpoint, method, data, params),
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[char]); },
  rows(response) { const data = response?.data?.data ?? response?.data ?? response ?? []; return Array.isArray(data) ? data : Array.isArray(data?.rows) ? data.rows : []; },
  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const [yearsResponse, classesResponse, subjectsResponse] = await Promise.all([
      this.API('GET', '/api/academic/years/list'),
      this.API('GET', '/api/academic/classes-list'),
      this.API('GET', '/api/academic/learning-areas/list'),
    ]);
    this.state.years = this.rows(yearsResponse); this.state.classes = this.rows(classesResponse); this.state.subjects = this.rows(subjectsResponse);
    this.fill('classFilter', this.state.classes, 'All Classes', (item) => item.name || item.class_name);
    this.fill('subjectFilter', this.state.subjects, 'All Learning Areas', (item) => item.name || item.learning_area || item.subject_name);
    // The shared year/term selector loads the report now and on every change.
    await this.mountYearFilter();
    document.getElementById('reportType')?.addEventListener('change', () => this.load());
    ['classFilter','subjectFilter'].forEach((id) => document.getElementById(id)?.addEventListener('change', () => this.load()));
    document.getElementById('refreshBtn')?.addEventListener('click', () => this.load());
    document.getElementById('printBtn')?.addEventListener('click', () => this.print());
    document.getElementById('exportBtn')?.addEventListener('click', () => this.exportCSV());

    window.APIRealtime?.register?.('generate_subject_report', { refresh: () => this.load() }, ['reports','academic']);
  },
  fill(id, items, label, format) {
    const select = document.getElementById(id); if (!select) return;
    select.innerHTML = `<option value="">${this.esc(label)}</option>` + items.map((item) => `<option value="${Number(item.id)}">${this.esc(format(item))}</option>`).join('');
  },
  async mountYearFilter() {
    const mounted = await window.AcademicYearFilter?.mount(document.getElementById('yearFilter'), {
      termSelect: document.getElementById('termFilter'),
      allTermsLabel: 'All terms',
      onChange: (yearId, year, termId) => {
        this.state.yearId = yearId;
        this.state.termId = termId;
        this.state.yearLabel = year?.year_name || year?.year_code || '';
        this.load();
      },
    });
    if (!mounted) await this.load();
  },
  async load() {
    this.setLoading(true); this.hideError();
    const params = {
      report_type: document.getElementById('reportType')?.value || 'performance',
      academic_year_id: this.state.yearId || '',
      year_id: this.state.yearId || '',
      term_id: this.state.termId || '',
      class_id: document.getElementById('classFilter')?.value || '',
      subject_id: document.getElementById('subjectFilter')?.value || '',
      subject_teacher_only: '1',
    };
    Object.keys(params).forEach((key) => { if (!params[key]) delete params[key]; });
    try {
      const response = await this.API('GET', '/api/academic/reports', null, params);
      const data = response?.data?.data ?? response?.data ?? response ?? {};
      this.state.columns = Array.isArray(data.columns) ? data.columns : []; this.state.rows = Array.isArray(data.rows) ? data.rows : []; this.state.summary = data.summary || {};
      this.render();
    } catch (error) { this.state.columns = []; this.state.rows = []; this.state.summary = {}; this.render(); this.showError(error.message || 'The subject report could not be loaded.'); }
    finally { this.setLoading(false); }
  },
  setLoading(loading) { document.getElementById('reportLoading')?.classList.toggle('d-none', !loading); const content = document.getElementById('reportContent'); if (content) content.style.display = loading ? 'none' : ''; },
  showError(message) { const error = document.getElementById('reportError'); if (error) { error.textContent = message; error.classList.remove('d-none'); } },
  hideError() { document.getElementById('reportError')?.classList.add('d-none'); },
  render() {
    const head = document.getElementById('reportsTableHead'); const body = document.getElementById('reportsTableBody'); const summary = document.getElementById('reportSummary');
    if (head) head.innerHTML = this.state.columns.map((column) => `<th scope="col">${this.esc(column.label || column.key)}</th>`).join('');
    if (body) body.innerHTML = this.state.rows.length ? this.state.rows.map((row) => `<tr>${this.state.columns.map((column) => `<td>${this.esc(row[column.key] ?? '—')}</td>`).join('')}</tr>`).join('') : `<tr><td colspan="${Math.max(1,this.state.columns.length)}" class="text-center text-muted py-4">No report data matches these filters.</td></tr>`;
    if (summary) summary.innerHTML = Object.entries(this.state.summary).map(([label,value]) => `<span class="badge text-bg-light border">${this.esc(label)}: ${Number(value || 0).toLocaleString()}</span>`).join(' ');
  },
  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('reports')) return window.showNotification?.('You do not have permission to export reports.', 'error');
    if (!this.state.columns.length || !this.state.rows.length) return window.showNotification?.('There is no report data to export.', 'info');
    const quote = (value) => `"${String(value ?? '').replaceAll('"','""')}"`;
    const csv = [this.state.columns.map((column) => column.label), ...this.state.rows.map((row) => this.state.columns.map((column) => row[column.key]))].map((row) => row.map(quote).join(',')).join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, `subject-report-${String(this.state.yearLabel || '').replace(/[\/\s]+/g, '-')}.csv`, 'text/csv;charset=utf-8;');
  },
  print() { if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('reports')) return window.showNotification?.('You do not have permission to print reports.', 'error'); window.print(); },
};
window.generateSubjectReportController = generateSubjectReportController;
document.addEventListener('DOMContentLoaded', () => generateSubjectReportController.init().catch((error) => { generateSubjectReportController.setLoading(false); generateSubjectReportController.showError(error.message || 'Subject report page setup failed.'); }));
