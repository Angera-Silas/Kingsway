(function () {
  'use strict';
  async function init() {
    const modal = document.getElementById('processPayrollModal');
    if (!modal || !window.API?.finance?.processPayrollWithDeductions) return;
    const response = await window.callAPI('/finance/financial-accounts', 'GET').catch(() => null);
    const payload = response?.data || response || {};
    const accounts = (payload.accounts || []).filter(a => a.status === 'active' && String(a.purposes || '').split(',').includes('payroll'));
    const wrap = document.createElement('div');
    wrap.className = 'mb-3';
    wrap.innerHTML = '<label class="form-label fw-semibold">Salary source account</label><select class="form-select" id="payrollSourceFinancialAccount"><option value="">Select an authorized payroll account</option></select><div class="form-text">The approved school account used for this payroll disbursement.</div>';
    const body = modal.querySelector('.modal-body');
    if (!body) return;
    body.prepend(wrap);
    const select = wrap.querySelector('select');
    accounts.forEach(a => { const o = document.createElement('option'); o.value = a.id; o.textContent = `${a.account_name} · ${a.provider_code || 'manual'} · ${a.account_identifier}`; select.appendChild(o); });
    const original = window.API.finance.processPayrollWithDeductions;
    window.API.finance.processPayrollWithDeductions = async function (data) {
      const sourceId = Number(select.value || 0);
      if (!sourceId) throw new Error('Select the authorized salary source account.');
      return original(Object.assign({}, data, { source_financial_account_id: sourceId }));
    };
  }
  document.addEventListener('DOMContentLoaded', () => init().catch(() => {}));
})();
