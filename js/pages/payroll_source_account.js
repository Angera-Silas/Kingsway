(function () {
  'use strict';
  async function init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const paymentModal = document.getElementById('payrollPaymentModeModal');
    if (!paymentModal || !window.API?.finance) return;
    const response = await window.callAPI('/finance/financial-accounts', 'GET').catch(() => null);
    const payload = response?.data || response || {};
    const accounts = (payload.accounts || []).filter(a => a.status === 'active' && String(a.purposes || '').split(',').includes('payroll'));
    const user = window.AuthContext?.getUser?.() || {};
    const roleNames = [];
    if (user.role_name) roleNames.push(user.role_name);
    if (Array.isArray(user.roles)) roleNames.push(...user.roles.map(r => r?.name || r));
    const isAccountant = roleNames.some(r => String(r).toLowerCase() === 'accountant');
    const reviewer = !isAccountant && (window.AuthContext?.hasPermission?.('staff.payroll.approve') || roleNames.some(r => ['director', 'school administrator', 'system administrator'].includes(String(r).toLowerCase())));
    const modal = document.getElementById('processPayrollModal');
    if (modal && reviewer) {
      const wrap = document.createElement('div');
      wrap.className = 'mb-3';
      wrap.innerHTML = '<label class="form-label fw-semibold">Salary source account</label><select class="form-select" id="payrollSourceFinancialAccount"><option value="">Select an authorized payroll account</option></select><div class="form-text">Selected by the administrator/director during payroll review; used when the approved run is released.</div>';
      const body = modal.querySelector('.modal-body');
      if (body) {
        body.prepend(wrap);
        const select = wrap.querySelector('select');
        accounts.forEach(a => { const o = document.createElement('option'); o.value = a.id; o.textContent = `${a.account_name} · ${a.provider_code || 'manual'} · ${a.account_identifier}`; select.appendChild(o); });
        const original = window.API.finance.processPayrollWithDeductions;
        if (original) window.API.finance.processPayrollWithDeductions = async function (data) {
          const sourceId = Number(select.value || 0);
          if (!sourceId) throw new Error('Select the authorized salary source account before saving the review draft.');
          return original(Object.assign({}, data, { source_financial_account_id: sourceId }));
        };
      }
    }
    const paymentBody = paymentModal.querySelector('.modal-body');
    if (!reviewer) return;
    const paymentWrap = document.createElement('div');
    paymentWrap.className = 'mb-3';
    paymentWrap.innerHTML = '<label class="form-label fw-semibold">Salary source account</label><select class="form-select" id="payrollPaymentSourceFinancialAccount"><option value="">Select an authorized payroll account</option></select><div class="form-text">The selected account is debited only when the provider accepts the payment.</div>';
    paymentBody.prepend(paymentWrap);
    const paymentSelect = paymentWrap.querySelector('select');
    accounts.forEach(a => { const o = document.createElement('option'); o.value = a.id; o.textContent = `${a.account_name} · ${a.provider_code || 'manual'} · ${a.account_identifier}`; paymentSelect.appendChild(o); });
    const originalPaid = window.API.finance.markPayrollPaid;
    if (originalPaid) window.API.finance.markPayrollPaid = async function (id, ref, mode) {
      const sourceId = Number(paymentSelect.value || 0);
      if (!sourceId) throw new Error('Select the authorized salary source account.');
      return originalPaid(id, ref, mode, sourceId);
    };
  }
  document.addEventListener('DOMContentLoaded', () => init().catch(() => {}));
})();
