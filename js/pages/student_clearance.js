/**
 * Student clearance page.
 *
 * Lists the learners who left in the selected year (shared year filter,
 * default current) with their outcome, final fee position and the five
 * clearance checks. Opening a learner starts the clearance (finance is set
 * from the fee account) and records each department's decision. The leaving
 * certificate prints only when every mandatory check is cleared.
 */
var StudentClearanceController = {
  state: { yearId: null, data: null, current: null, request: 0 },

  api(path, method = 'GET', data = null, params = null) { return window.callAPI(path, method, data, params); },
  unwrap(r) { return r?.data?.data ?? r?.data ?? r; },
  esc(v) { return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); },
  el(id) { return document.getElementById(id); },
  money(v) { return `KES ${Math.abs(Number(v || 0)).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`; },
  stateLabel: { not_started: 'Not started', in_progress: 'In progress', blocked: 'Blocked', cleared: 'Cleared' },
  statusLabel: { not_started: 'Not started', pending: 'Pending', cleared: 'Cleared', blocked: 'Blocked' },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    ['clrOutcome', 'clrState'].forEach((id) => this.el(id)?.addEventListener('change', () => this.load()));
    let t = null;
    this.el('clrSearch')?.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => this.load(), 300); });
    this.el('clrExportBtn')?.addEventListener('click', () => this.exportCSV());
    this.el('clrPrintBtn')?.addEventListener('click', () => this.printList());
    this.el('clrCertificateBtn')?.addEventListener('click', () => this.printCertificate());
    this.el('clrTable')?.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-clr-open]');
      if (btn) this.open(Number(btn.dataset.clrOpen));
    });
    this.el('clrModalBody')?.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-clr-decide]');
      if (btn) this.decide(btn.dataset.clrType, btn.dataset.clrDecide);
    });
    const yearSelect = this.el('clrYear');
    const mounted = window.AcademicYearFilter && yearSelect
      ? await window.AcademicYearFilter.mount(yearSelect, { onChange: (yearId) => { this.state.yearId = yearId; this.load(); } })
      : null;
    if (!mounted) await this.load();
    window.APIRealtime?.register?.('student_clearance', { refresh: () => this.load() }, ['students', 'finance']);
  },

  params() {
    const p = {};
    if (this.state.yearId) p.academic_year_id = this.state.yearId;
    const outcome = this.el('clrOutcome')?.value; if (outcome) p.outcome = outcome;
    const st = this.el('clrState')?.value; if (st) p.state = st;
    const q = this.el('clrSearch')?.value?.trim(); if (q) p.search = q;
    return p;
  },

  async load() {
    const req = ++this.state.request;
    const body = this.el('clrTable')?.querySelector('tbody');
    try {
      const r = await this.api('/api/students/clearance-leavers', 'GET', null, this.params());
      if (req !== this.state.request) return;
      if (r?.success === false) throw new Error(r.message);
      this.state.data = this.unwrap(r) || {};
      this.render();
    } catch (e) {
      if (body) body.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${this.esc(e?.message || 'Clearance could not be loaded.')}</td></tr>`;
    }
  },

  render() {
    const d = this.state.data || {};
    const s = d.summary || {};
    const y = d.year || {};
    this.el('clrPeriod').textContent = y.code ? `Learners who left in ${y.code}. Settle each check before issuing the leaving certificate.` : '';
    const fig = (label, value, cls = '') => `<div class="col-6 col-md"><div class="card"><div class="card-body text-center"><h6>${label}</h6><h3 class="mb-0 ${cls}">${value}</h3></div></div></div>`;
    this.el('clrSummary').innerHTML = [
      fig('Leavers', Number(s.leavers || 0)),
      fig('Not started', Number(s.not_started || 0)),
      fig('In progress', Number(s.in_progress || 0)),
      fig('Blocked', Number(s.blocked || 0), Number(s.blocked) ? 'text-danger' : ''),
      fig('Cleared', Number(s.cleared || 0)),
      fig('Still owed', this.money(s.owing), Number(s.owing) ? 'text-danger' : ''),
      fig('Overpaid, to refund', this.money(s.overpaid)),
    ].join('');
    const body = this.el('clrTable').querySelector('tbody');
    const list = d.learners || [];
    body.innerHTML = list.length ? list.map((l) => {
      const dots = l.checks.map((c) => `<span class="clr-dot ${c.status === 'not_started' ? '' : c.status}" title="${this.esc(c.label)}: ${this.esc(this.statusLabel[c.status] || c.status)}"></span>`).join('');
      const tone = l.state === 'cleared' ? 'bg-success' : l.state === 'blocked' ? 'bg-danger' : l.state === 'in_progress' ? 'bg-warning' : 'bg-secondary';
      return `<tr>
        <td><strong>${this.esc(l.name)}</strong><div class="small text-muted">${this.esc(l.admission_no)}</div></td>
        <td>${this.esc(l.outcome)}</td>
        <td>${this.esc(l.class)}</td>
        <td class="text-end ${l.balance > 0 ? 'clr-owed' : (l.balance < 0 ? 'clr-credit' : '')}">${this.esc(l.balance_label)}</td>
        <td>${dots}</td>
        <td><span class="badge ${tone}">${this.esc(this.stateLabel[l.state] || l.state)}</span></td>
        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" data-clr-open="${l.student_id}">${l.state === 'not_started' ? 'Start clearance' : 'Open'}</button></td>
      </tr>`;
    }).join('') : `<tr><td colspan="7" class="text-center text-muted py-4">No learner left in ${this.esc(y.code || 'this year')} with these filters.</td></tr>`;
  },

  async open(studentId) {
    const l = (this.state.data?.learners || []).find((x) => x.student_id === studentId);
    if (!l) return;
    try {
      const r = await this.api('/api/students/clearance-start', 'POST', { student_id: studentId, academic_year_id: this.state.data.year.id });
      if (r?.success === false) throw new Error(r.message);
      this.state.current = this.unwrap(r);
    } catch (e) {
      window.showNotification?.(e?.message || 'The clearance could not be opened.', 'error');
      return;
    }
    this.renderModal();
    bootstrap.Modal.getOrCreateInstance(this.el('clrModal')).show();
    this.load();
  },

  renderModal() {
    const l = this.state.current;
    if (!l) return;
    this.el('clrModalTitle').textContent = `Clearance: ${l.name}`;
    this.el('clrModalMeta').textContent = `${l.admission_no}, ${l.outcome}, ${l.class}. ${l.balance_label}.`;
    this.el('clrModalBody').innerHTML = l.checks.map((c) => `
      <div class="clr-check" data-check="${c.type}">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div><strong>${this.esc(c.label)}</strong>${c.mandatory ? '' : ' <small class="text-muted">(optional)</small>'}
            <div class="small text-muted">${this.esc(this.statusLabel[c.status] || c.status)}${c.note ? `: ${this.esc(c.note)}` : ''}${c.checked_at ? `, ${this.esc(c.checked_at)}` : ''}</div></div>
          <div class="d-flex gap-1" data-year-write>
            <button type="button" class="btn btn-sm ${c.status === 'cleared' ? 'btn-success' : 'btn-outline-success'}" data-clr-decide="cleared" data-clr-type="${c.type}">Cleared</button>
            <button type="button" class="btn btn-sm ${c.status === 'blocked' ? 'btn-danger' : 'btn-outline-danger'}" data-clr-decide="blocked" data-clr-type="${c.type}">Blocked</button>
          </div>
        </div>
        <input type="text" class="form-control form-control-sm mt-2" id="clrNote_${c.type}" placeholder="${c.type === 'finance' ? 'How was it settled? (paid, refunded, transferred, written off)' : 'Note (required when blocked)'}" value="">
      </div>`).join('');
    this.el('clrCertificateBtn').disabled = !l.certificate_ready;
    this.el('clrCertificateBtn').title = l.certificate_ready ? '' : 'Every mandatory check must be cleared first.';
    window.YearMode?.refresh?.(this.el('clrModal'));
  },

  async decide(type, status) {
    const l = this.state.current;
    if (!l) return;
    const note = this.el(`clrNote_${type}`)?.value?.trim() || null;
    try {
      const r = await this.api('/api/students/clearance-decide', 'POST', { student_id: l.student_id, academic_year_id: this.state.data.year.id, type, status, note });
      if (r?.success === false) throw new Error(r.message);
      this.state.current = this.unwrap(r);
      this.renderModal();
      this.load();
    } catch (e) {
      window.showNotification?.(e?.message || 'The decision could not be saved.', 'error');
    }
  },

  printCertificate() {
    const l = this.state.current;
    if (!l || !l.certificate_ready) return;
    const school = window.SCHOOL_CONFIG || {};
    const today = new Date().toLocaleDateString('en-KE', { day: 'numeric', month: 'long', year: 'numeric' });
    const host = this.el('clrCertificate');
    host.innerHTML = `<div class="cert">
        <div class="cert-school">${this.esc(school.name || '')}</div>
        <h1>Leaving certificate</h1>
        <p>This is to certify that the learner named below was a pupil of this school and has been cleared by every department.</p>
        <dl>
          <dt>Name</dt><dd>${this.esc(l.name)}</dd>
          <dt>Admission number</dt><dd>${this.esc(l.admission_no)}</dd>
          <dt>Last class</dt><dd>${this.esc(l.class)}</dd>
          <dt>Left as</dt><dd>${this.esc(l.outcome)}</dd>
          <dt>Academic year</dt><dd>${this.esc(this.state.data?.year?.code || '')}</dd>
          <dt>Clearance</dt><dd>${l.checks.filter((c) => c.status === 'cleared').map((c) => this.esc(c.label)).join(', ')}</dd>
          <dt>Date issued</dt><dd>${this.esc(today)}</dd>
        </dl>
        <div class="cert-sign"><div>Headteacher</div><div>School stamp</div></div>
      </div>`;
    host.classList.remove('d-none');
    document.body.classList.add('clr-printing-certificate');
    const done = () => { document.body.classList.remove('clr-printing-certificate'); host.classList.add('d-none'); window.removeEventListener('afterprint', done); };
    window.addEventListener('afterprint', done);
    window.print();
  },

  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('students')) return window.showNotification?.('You do not have permission to export.', 'error');
    const q = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const d = this.state.data || {};
    const checks = Object.values(d.checks || {}).map((c) => c.label);
    const lines = [['Learner', 'Admission no', 'Left as', 'Class', 'Fee account', ...checks, 'Clearance'].map(q).join(',')];
    (d.learners || []).forEach((l) => lines.push([l.name, l.admission_no, l.outcome, l.class, l.balance_label, ...l.checks.map((c) => this.statusLabel[c.status] || c.status), this.stateLabel[l.state]].map(q).join(',')));
    window.KingswayFileLifecycle?.exportText?.('﻿' + lines.join('\r\n'), `student-clearance_${(d.year?.code || '').replace(/[^0-9]+/g, '-')}.csv`, 'text/csv;charset=utf-8');
  },

  printList() {
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('students')) return window.showNotification?.('You do not have permission to print.', 'error');
    window.print();
  },
};

window.StudentClearanceController = StudentClearanceController;
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => StudentClearanceController.init());
else StudentClearanceController.init();
