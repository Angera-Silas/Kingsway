/**
 * Statutory Remittances Controller
 * Tracks PAYE (KRA), NHIF, NSSF, and Housing Levy remittances.
 */
const statutoryRemittancesController = {
  remittances: [],
  breakdown: [],

  async init() {
    await window.AuthContext?.ready();
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this.populateYearFilter();
    this.populateMonthSelects();
    this.bind();
    await this.load();
  },

  bind() {
    document.getElementById('srRefreshBtn')?.addEventListener('click', () => this.load());
    document.getElementById('srAgencyFilter')?.addEventListener('change', () => this.render());
    document.getElementById('srYearFilter')?.addEventListener('change', () => this.load());
    document.getElementById('srStatusFilter')?.addEventListener('change', () => this.render());
    document.getElementById('srNewBtn')?.addEventListener('click', () => this.openNew());
    document.getElementById('srSaveBtn')?.addEventListener('click', () => this.save());
    document.getElementById('srAgency')?.addEventListener('change', () => this.fetchDeducted());
    document.getElementById('srMonth')?.addEventListener('change', () => this.fetchDeducted());
    document.getElementById('srYear')?.addEventListener('change', () => this.fetchDeducted());
    document.getElementById('srSubmitPaymentBtn')?.addEventListener('click', () => this.submitPayment());
  },

  populateYearFilter() {
    const sel = document.getElementById('srYearFilter');
    const yr = new Date().getFullYear();
    for (let y = yr; y >= yr - 4; y--) sel.add(new Option(y, y, y === yr, y === yr));
    document.getElementById('srYear').value = yr;
  },

  populateMonthSelects() {
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const sel = document.getElementById('srMonth');
    const cm = new Date().getMonth() + 1;
    months.forEach((m, i) => sel.add(new Option(m, i + 1, i + 1 === cm, i + 1 === cm)));
  },

  async load() {
    const body = document.getElementById('srTableBody');
    body.innerHTML = '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    try {
      const params = new URLSearchParams();
      const yr = document.getElementById('srYearFilter').value;
      const agency = document.getElementById('srAgencyFilter').value;
      const status = document.getElementById('srStatusFilter').value;
      if (yr) params.set('year', yr);
      if (agency) params.set('agency', agency);
      if (status) params.set('status', status);
      const r = await callAPI('/staff/statutory-remittances?' + params.toString(), 'GET');
      const data = r?.data || r;
      this.remittances = Array.isArray(data?.remittances) ? data.remittances : (Array.isArray(data) ? data : []);
      this.breakdown = Array.isArray(data?.breakdown) ? data.breakdown : [];
      this.render();
      this.renderBreakdown();
      this.updateKPIs(data?.summary || {});
    } catch (e) {
      body.innerHTML = `<tr><td colspan="9" class="text-center text-danger py-4">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  render() {
    const body = document.getElementById('srTableBody');
    const agency = document.getElementById('srAgencyFilter').value;
    const status = document.getElementById('srStatusFilter').value;
    let rows = this.remittances;
    if (agency) rows = rows.filter(r => r.agency === agency);
    if (status) rows = rows.filter(r => r.status === status);
    if (!rows.length) { body.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No remittances found.</td></tr>'; return; }
    const fmt = n => 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 });
    const months = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const statusBadge = s => `<span class="badge bg-${s === 'paid' ? 'success' : s === 'overdue' ? 'danger' : s === 'filed' ? 'info' : s === 'partial' ? 'warning' : 'secondary'}">${this.esc(s)}</span>`;
    body.innerHTML = rows.map(r => {
      const bal = Number(r.total_deducted || 0) - Number(r.amount_remitted || 0);
      return `<tr>
        <td><strong>${this.esc(r.agency)}</strong></td>
        <td>${months[Number(r.period_month)]} ${this.esc(r.period_year)}</td>
        <td class="text-end">${fmt(r.total_deducted)}</td>
        <td class="text-end text-success">${fmt(r.amount_remitted)}</td>
        <td class="text-end ${bal > 0 ? 'text-warning fw-bold' : ''}">${fmt(bal)}</td>
        <td>${r.due_date || '—'}</td>
        <td>${r.remittance_date || r.filed_date || '—'}</td>
        <td>${statusBadge(r.status)}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary me-1" onclick="statutoryRemittancesController.viewDetail(${r.id})" title="View"><i class="bi bi-eye"></i></button>
          ${bal > 0 ? `<button class="btn btn-sm btn-outline-success me-1" onclick="statutoryRemittancesController.initiatePayment(${r.id})" title="Submit bank payment"><i class="bi bi-bank"></i></button>` : ''}
          <button class="btn btn-sm btn-outline-secondary" onclick="statutoryRemittancesController.edit(${r.id})" title="Edit"><i class="bi bi-pencil"></i></button>
        </td>
      </tr>`;
    }).join('');
  },

  renderBreakdown() {
    const body = document.getElementById('srBreakdownBody');
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const yr = document.getElementById('srYearFilter').value || new Date().getFullYear();
    if (!this.breakdown.length) { body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No breakdown data for this year.</td></tr>'; return; }
    const fmt = n => 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 });
    body.innerHTML = this.breakdown.map(b => {
      const total = Number(b.kra || 0) + Number(b.nhif || 0) + Number(b.nssf || 0) + Number(b.housing_levy || 0);
      return `<tr>
        <td>${months[b.period_month - 1] || b.period_month} ${yr}</td>
        <td class="text-end">${fmt(b.kra)}</td>
        <td class="text-end">${fmt(b.nhif)}</td>
        <td class="text-end">${fmt(b.nssf)}</td>
        <td class="text-end">${fmt(b.housing_levy)}</td>
        <td class="text-end fw-bold">${fmt(total)}</td>
      </tr>`;
    }).join('');
  },

  updateKPIs(summary) {
    const fmt = n => 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 });
    document.getElementById('srTotalDeducted').textContent = fmt(summary.total_deducted || 0);
    document.getElementById('srTotalRemitted').textContent = fmt(summary.total_remitted || 0);
    document.getElementById('srOutstanding').textContent = fmt(summary.outstanding || 0);
    document.getElementById('srOverdue').textContent = summary.overdue_count || 0;
  },

  openNew() {
    document.getElementById('srEditId').value = '';
    document.getElementById('srModalTitle').textContent = 'New Remittance';
    document.getElementById('srAgency').value = 'KRA';
    document.getElementById('srMonth').value = new Date().getMonth() + 1;
    document.getElementById('srYear').value = new Date().getFullYear();
    document.getElementById('srDeducted').value = '';
    document.getElementById('srRemitted').value = '';
    document.getElementById('srStatus').value = 'pending';
    document.getElementById('srDueDate').value = '';
    document.getElementById('srFiledDate').value = '';
    document.getElementById('srReference').value = '';
    document.getElementById('srNotes').value = '';
    document.getElementById('srStaffBreakdown').style.display = 'none';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('srModal')).show();
  },

  async fetchDeducted() {
    const agency = document.getElementById('srAgency').value;
    const month = document.getElementById('srMonth').value;
    const year = document.getElementById('srYear').value;
    if (!agency || !month || !year) return;
    try {
      const r = await callAPI('/staff/statutory-remittances/calc?agency=' + encodeURIComponent(agency) + '&month=' + month + '&year=' + year, 'GET');
      const data = r?.data || r;
      document.getElementById('srDeducted').value = data.total || 0;
      if (data.staff && data.staff.length) {
        const body = document.getElementById('srStaffBody');
        const fmt = n => 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 });
        body.innerHTML = data.staff.map(s => `<tr><td>${this.esc(s.staff_name || s.staff_no)}</td><td class="text-end">${fmt(s.amount)}</td></tr>`).join('');
        document.getElementById('srStaffBreakdown').style.display = '';
      } else {
        document.getElementById('srStaffBreakdown').style.display = 'none';
      }
    } catch (e) {
      document.getElementById('srDeducted').value = 0;
      document.getElementById('srStaffBreakdown').style.display = 'none';
    }
  },

  async save() {
    const editId = document.getElementById('srEditId').value;
    const payload = {
      agency: document.getElementById('srAgency').value,
      period_month: parseInt(document.getElementById('srMonth').value),
      period_year: parseInt(document.getElementById('srYear').value),
      total_deducted: parseFloat(document.getElementById('srDeducted').value) || 0,
      amount_remitted: parseFloat(document.getElementById('srRemitted').value) || 0,
      status: document.getElementById('srStatus').value,
      due_date: document.getElementById('srDueDate').value || null,
      remittance_date: document.getElementById('srFiledDate').value || null,
      filing_reference: document.getElementById('srReference').value || null,
      notes: document.getElementById('srNotes').value || null,
    };
    if (!payload.period_month || !payload.period_year) return this.notify('Select month and year', 'warning');
    if (payload.amount_remitted <= 0) return this.notify('Enter remittance amount', 'warning');
    const btn = document.getElementById('srSaveBtn');
    btn.disabled = true;
    try {
      if (editId) {
        await callAPI(`/staff/statutory-remittances/${editId}`, 'PUT', payload);
      } else {
        await callAPI('/staff/statutory-remittances', 'POST', payload);
      }
      bootstrap.Modal.getInstance(document.getElementById('srModal'))?.hide();
      this.notify(editId ? 'Remittance updated' : 'Remittance saved', 'success');
      await this.load();
    } catch (e) { this.notify(e.message || 'Save failed', 'danger'); }
    finally { btn.disabled = false; }
  },

  async edit(id) {
    const r = this.remittances.find(x => x.id === id);
    if (!r) return;
    document.getElementById('srEditId').value = r.id;
    document.getElementById('srModalTitle').textContent = 'Edit Remittance';
    document.getElementById('srAgency').value = r.agency;
    document.getElementById('srMonth').value = r.period_month;
    document.getElementById('srYear').value = r.period_year;
    document.getElementById('srDeducted').value = r.total_deducted || 0;
    document.getElementById('srRemitted').value = r.amount_remitted || 0;
    document.getElementById('srStatus').value = r.status;
    document.getElementById('srDueDate').value = r.due_date || '';
    document.getElementById('srFiledDate').value = r.remittance_date || '';
    document.getElementById('srReference').value = r.filing_reference || '';
    document.getElementById('srNotes').value = r.notes || '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('srModal')).show();
  },

  async initiatePayment(id) {
    const remittance = this.remittances.find(r => Number(r.id) === Number(id));
    if (!remittance) return;
    const select = document.getElementById('srAgencyAccount');
    const hint = document.getElementById('srPaymentHint');
    document.getElementById('srPaymentId').value = id;
    select.innerHTML = '<option value="">Loading accounts…</option>';
    hint.textContent = '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('srPaymentModal')).show();
    try {
      const response = await callAPI('/staff/statutory-agency-accounts?agency=' + encodeURIComponent(remittance.agency), 'GET');
      const data = response?.data || response;
      const accounts = Array.isArray(data?.accounts) ? data.accounts : [];
      select.innerHTML = accounts.length
        ? '<option value="">Select receiving account</option>' + accounts.map(a => `<option value="${Number(a.id)}">${this.esc(a.account_name)} · ${this.esc(a.bank_name || 'Configured bank')} · ${this.esc(a.account_number)}</option>`).join('')
        : '<option value="">No active receiving account configured</option>';
      hint.textContent = accounts.length ? `Amount to submit: KES ${Number(remittance.total_deducted - remittance.amount_remitted).toLocaleString('en-KE', {minimumFractionDigits: 2})}` : 'Ask an administrator to configure this agency account first.';
      const sourceResponse = await callAPI('/finance/financial-accounts', 'GET');
      const sourceAccounts = ((sourceResponse?.data || sourceResponse)?.accounts || []).filter(a => String(a.purposes || '').split(',').includes('statutory') && String(a.channels || '').split(',').includes('buni_transfer'));
      document.getElementById('srSourceAccount').innerHTML = sourceAccounts.length
        ? '<option value="">Select school source account</option>' + sourceAccounts.map(a => '<option value="' + Number(a.id) + '">' + this.esc(a.account_name) + ' · ' + this.esc(a.account_identifier) + '</option>').join('')
        : '<option value="">No authorized source account configured</option>';
    } catch (e) {
      select.innerHTML = '<option value="">Unable to load accounts</option>';
      hint.textContent = e.message || 'Account lookup failed';
    }
  },

  async submitPayment() {
    const id = Number(document.getElementById('srPaymentId').value);
    const accountId = Number(document.getElementById('srAgencyAccount').value);
    const sourceAccountId = Number(document.getElementById('srSourceAccount').value);
    if (!id || !accountId || !sourceAccountId) return this.notify('Select both agency receiving and school source accounts.', 'warning');
    const btn = document.getElementById('srSubmitPaymentBtn');
    btn.disabled = true;
    try {
      await callAPI(`/staff/statutory-remittances/${id}/initiate-payment`, 'POST', { agency_account_id: accountId, source_financial_account_id: sourceAccountId });
      bootstrap.Modal.getInstance(document.getElementById('srPaymentModal'))?.hide();
      this.notify('Payment submitted; final status will update from the bank callback.', 'success');
      await this.load();
    } catch (e) { this.notify(e.message || 'Payment submission failed', 'danger'); }
    finally { btn.disabled = false; }
  },

  async viewDetail(id) {
    const r = this.remittances.find(x => x.id === id);
    if (!r) return;
    this.edit(id);
  },

  async notify(msg, type = 'info') {
    if (window.showNotification) window.showNotification(msg, type);
    else alert(msg);
  },

  esc(str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  }
};

window.statutoryRemittancesController = statutoryRemittancesController;
document.addEventListener('DOMContentLoaded', () => statutoryRemittancesController.init().catch(() => {}));
