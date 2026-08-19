/**
 * manage_admissions_payments.js — Admission Fees Controller (standalone page).
 * Accountant records admission fee payments for newly enrolled students.
 */
const manageAdmissionsPaymentsController = {
  state: { students: [], accounts: [] },

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

  fmtDate(d) {
    if (!d) return '';
    const dt = new Date(d);
    if (isNaN(dt)) return d;
    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`;
  },

  unwrap(r) { return r?.data ?? r; },

  async loadStudents() {
    const sel = document.getElementById('mapStudent');
    try {
      const r = await this.API('GET', 'students/student', null, { limit: 500, status: 'active' });
      const payload = this.unwrap(r);
      this.state.students = Array.isArray(payload?.students) ? payload.students : (Array.isArray(payload) ? payload : []);
      sel.innerHTML = '<option value="">— Select student —</option>' + this.state.students.map(s => {
        const name = s.full_name || `${s.first_name || ''} ${s.last_name || ''}`.trim();
        return `<option value="${s.id}">${this.esc(s.admission_no || 'N/A')} — ${this.esc(name)}</option>`;
      }).join('');
      document.getElementById('mapDate').value = this.fmtDate(new Date());
    } catch (e) { this.notify('Failed to load students', 'danger'); }
  },

  async loadAccounts() {
    const body = document.getElementById('mapBody');
    body.innerHTML = '<tr><td colspan="4" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const r = await this.API('GET', 'finance/students/payment-status', null, { limit: 100 });
      const payload = this.unwrap(r);
      const accounts = payload?.students || payload?.accounts || payload?.data || [];
      this.state.accounts = Array.isArray(accounts) ? accounts : [];
      if (!this.state.accounts.length) { body.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No student accounts found.</td></tr>'; return; }
      body.innerHTML = this.state.accounts.map(a => {
        const name = a.student_name || `${a.first_name || ''} ${a.last_name || ''}`.trim();
        const bal = Number(a.balance ?? a.total_balance ?? 0);
        return `<tr>
          <td class="small">${this.esc(a.admission_no || '\u2014')}</td>
          <td class="small fw-semibold">${this.esc(name)}</td>
          <td class="small text-muted">${this.esc(a.class_name || '\u2014')}</td>
          <td class="small ${bal > 0 ? 'text-danger fw-semibold' : 'text-success'}">KES ${bal.toLocaleString()}</td>
        </tr>`;
      }).join('');
    } catch (e) { body.innerHTML = `<tr><td colspan="4" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  async save() {
    const studentId = document.getElementById('mapStudent').value;
    const amount = parseFloat(document.getElementById('mapAmount').value);
    const date = document.getElementById('mapDate').value;
    const method = document.getElementById('mapMethod').value;
    const reference = document.getElementById('mapRef').value.trim();
    const notes = document.getElementById('mapNotes').value.trim();
    if (!studentId) return this.notify('Select a student.', 'warning');
    if (!amount || amount <= 0) return this.notify('Enter a valid amount.', 'warning');
    if (!date) return this.notify('Select a payment date.', 'warning');
    const user = window.AuthContext?.getUser?.() || {};
    try {
      await this.API('POST', 'finance', {
        type: 'payment',
        student_id: studentId,
        amount,
        payment_method: method,
        reference_no: reference,
        payment_date: `${date} 00:00:00`,
        notes,
        received_by: user.id || user.user_id || 1,
      });
      this.notify('Payment recorded successfully.');
      document.getElementById('mapAmount').value = '';
      document.getElementById('mapRef').value = '';
      document.getElementById('mapNotes').value = '';
      this.loadAccounts();
    } catch (e) { this.notify(e.message || 'Failed to record payment', 'danger'); }
  },

  setupEventListeners() {},

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.loadStudents();
    await this.loadAccounts();
  },
};

window.manageAdmissionsPaymentsController = manageAdmissionsPaymentsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => manageAdmissionsPaymentsController.init().catch(() => {}));
} else {
  manageAdmissionsPaymentsController.init().catch(() => {});
}
