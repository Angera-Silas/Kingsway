/**
 * student_fees_bill.js — Generate Bills Controller (standalone page).
 * Accountant generates fee invoices for a class or a single student.
 */
const studentFeesBillController = {
  state: { years: [], terms: [], classes: [], students: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  notify(msg, type = 'success') {
    if (window.showNotification) { showNotification(msg, type); return; }
    const el = document.createElement('div');
    el.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow`;
    el.style.zIndex = 99999;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  },

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data?.items || r?.data || []); },

  currentYearId() {
    const cur = this.state.years.find(y => y.is_current || y.is_current_year);
    return cur ? cur.id : (this.state.years[0]?.id || null);
  },

  currentTermId() {
    const yid = this.currentYearId();
    const terms = this.state.terms.filter(t => !yid || Number(t.academic_year_id) === Number(yid));
    const cur = terms.find(t => t.is_current) || terms[0];
    return cur ? cur.id : null;
  },

  async loadReference() {
    try {
      const [years, terms, classes, students] = await Promise.all([
        this.API('GET', 'academic/years/list'),
        this.API('GET', 'academic/terms-list'),
        this.API('GET', 'academic/classes-list', null, { limit: 200 }),
        this.API('GET', 'students/student', null, { limit: 500, status: 'active' }),
      ]);
      this.state.years = this.norm(years);
      this.state.terms = this.norm(terms);
      this.state.classes = this.norm(classes);
      const sr = this.norm(students);
      this.state.students = Array.isArray(sr?.students) ? sr.students : (Array.isArray(sr) ? sr : []);
      const yearSel = document.getElementById('sffYear');
      yearSel.innerHTML = this.state.years.map(y => `<option value="${y.id}" ${y.id == this.currentYearId() ? 'selected' : ''}>${this.esc(y.year_code || y.year || y.id)}</option>`).join('');
      const clsSel = document.getElementById('sffClass');
      clsSel.innerHTML = '<option value="">All classes</option>' + this.state.classes.map(c => `<option value="${c.id}">${this.esc(c.name || c.class_name)}</option>`).join('');
      const stuSel = document.getElementById('sffStudent');
      stuSel.innerHTML = '<option value="">— Select student —</option>' + this.state.students.map(s => {
        const name = s.full_name || `${s.first_name || ''} ${s.last_name || ''}`.trim();
        return `<option value="${s.id}">${this.esc(s.admission_no || 'N/A')} — ${this.esc(name)}</option>`;
      }).join('');
      this.populateTerms();
      yearSel.addEventListener('change', () => this.populateTerms());
    } catch (e) { this.notify('Failed to load reference data', 'danger'); }
  },

  populateTerms() {
    const yid = document.getElementById('sffYear').value;
    const termSel = document.getElementById('sffTerm');
    const terms = this.state.terms.filter(t => !yid || Number(t.academic_year_id) === Number(yid));
    termSel.innerHTML = terms.map(t => `<option value="${t.id}" ${t.id == this.currentTermId() ? 'selected' : ''}>${this.esc(t.name || t.term_name || t.id)}</option>`).join('');
  },

  showResult(html) { document.getElementById('sffResult').innerHTML = html; },

  async generateBatch() {
    const yearId = document.getElementById('sffYear').value;
    const termId = document.getElementById('sffTerm').value;
    const classId = document.getElementById('sffClass').value;
    if (!yearId || !termId) return this.notify('Select academic year and term.', 'warning');
    this.showResult('<div class="text-muted small py-3"><div class="spinner-border spinner-border-sm me-2"></div>Generating bills…</div>');
    try {
      const r = await this.API('POST', 'finance/fee-invoices-generate-batch', { academic_year_id: yearId, term_id: termId, class_id: classId || null });
      const d = r?.data ?? r ?? {};
      const generated = d.generated || d.total || d.count || (r?.message ? 1 : 0);
      this.showResult(`<div class="alert alert-success mb-0"><i class="bi bi-check-circle me-2"></i><strong>Bills generated.</strong><br><span class="small">${this.esc(d.message || r?.message || 'Invoices created successfully')}${generated ? ` (${generated})` : ''}</span></div>`);
      this.notify('Bills generated successfully');
    } catch (e) { this.showResult(`<div class="alert alert-danger mb-0">${this.esc(e.message || 'Generation failed')}</div>`); }
  },

  async generateSingle() {
    const studentId = document.getElementById('sffStudent').value;
    const yearId = document.getElementById('sffYear').value;
    const termId = document.getElementById('sffTerm').value;
    if (!studentId) return this.notify('Select a student.', 'warning');
    if (!yearId || !termId) return this.notify('Select academic year and term.', 'warning');
    this.showResult('<div class="text-muted small py-3"><div class="spinner-border spinner-border-sm me-2"></div>Generating bill…</div>');
    try {
      const r = await this.API('POST', 'finance/fee-invoices-generate', { student_id: studentId, academic_year_id: yearId, term_id: termId });
      const d = r?.data ?? r ?? {};
      this.showResult(`<div class="alert alert-success mb-0"><i class="bi bi-check-circle me-2"></i><strong>Bill generated.</strong><br><span class="small">${this.esc(d.message || r?.message || 'Invoice created')}</span></div>`);
      this.notify('Student bill generated');
    } catch (e) { this.showResult(`<div class="alert alert-danger mb-0">${this.esc(e.message || 'Generation failed')}</div>`); }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReference();
  },
};

window.studentFeesBillController = studentFeesBillController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => studentFeesBillController.init().catch(() => {}));
} else {
  studentFeesBillController.init().catch(() => {});
}
