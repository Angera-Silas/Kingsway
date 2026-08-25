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
      wrap.innerHTML = '<label class="form-label fw-semibold">Default salary source account <span class="text-muted">(optional)</span></label><select class="form-select" id="payrollSourceFinancialAccount"><option value="">Use transaction-level allocations</option></select><div class="form-text">Optional fallback for the payroll run. Individual staff transactions may use different accounts.</div>';
      const body = modal.querySelector('.modal-body');
      if (body) {
        body.prepend(wrap);
        const select = wrap.querySelector('select');
        accounts.forEach(a => { const o = document.createElement('option'); o.value = a.id; o.textContent = `${a.account_name} · ${a.provider_code || 'manual'} · ${a.account_identifier}`; select.appendChild(o); });
        const original = window.API.finance.processPayrollWithDeductions;
        if (original) window.API.finance.processPayrollWithDeductions = async function (data) {
          const sourceId = Number(select.value || 0);
          return original(Object.assign({}, data, sourceId ? { source_financial_account_id: sourceId } : {}));
        };
      }
    }
    const paymentBody = paymentModal.querySelector('.modal-body');
    if (!reviewer) return;
    const paymentWrap = document.createElement('div');
    paymentWrap.className = 'mb-3';
    paymentWrap.innerHTML = '<label class="form-label fw-semibold">Default salary source account <span class="text-muted">(optional)</span></label><select class="form-select" id="payrollPaymentSourceFinancialAccount"><option value="">Use transaction-level allocations</option></select><button type="button" class="btn btn-outline-primary btn-sm mt-2" id="payrollConfigureSourceAllocations">Assign accounts per staff/group</button><div class="form-text">A default may be used for the whole run; individual payslip source accounts can also be assigned to one staff member or a group.</div>';
    paymentBody.prepend(paymentWrap);
    const paymentSelect = paymentWrap.querySelector('select');
    accounts.forEach(a => { const o = document.createElement('option'); o.value = a.id; o.textContent = `${a.account_name} · ${a.provider_code || 'manual'} · ${a.account_identifier}`; paymentSelect.appendChild(o); });
    async function configureAllocations(payrollId) {
      const result = await window.API.finance.getPayrollSourceAllocations(payrollId);
      // API responses may be returned either already unwrapped or under one
      // or two `data` envelopes depending on the API helper/controller path.
      // Normalize all supported shapes before deciding whether allocation is
      // mutable.
      const payload = result?.data?.data || result?.data || result || {};
      const runStatus = String(
        payload.run_status || result?.run_status || result?.data?.run_status || ''
      ).toLowerCase();
      const rows = payload.rows || [];
      if (runStatus && runStatus !== 'approved') {
        throw new Error(`Payroll disbursement has already started (${runStatus}); source accounts can no longer be changed.`);
      }
      if (!rows.length) throw new Error('No payroll transactions found for allocation.');
      const options = () => accounts.map(a => `<option value="${Number(a.id)}">${String(a.account_name).replace(/</g, '&lt;')}</option>`).join('');
      const modal = document.createElement('div'); modal.className='modal fade'; modal.innerHTML=`<div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Assign payroll source accounts</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p class="small text-muted">Assign one account to each staff transaction. The same account can be selected for multiple rows.</p><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Staff</th><th>Net salary</th><th>Source account</th></tr></thead><tbody>${rows.map(r=>`<tr><td>${String(r.staff_name||'').replace(/</g,'&lt;')}</td><td>KES ${Number(r.net_salary||0).toLocaleString()}</td><td><select class="form-select form-select-sm psa-account" data-payslip="${Number(r.id)}"><option value="">Select source account</option>${options()}</select></td></tr>`).join('')}</tbody></table></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" id="psaSave">Save allocations</button></div></div></div>`; document.body.appendChild(modal); rows.forEach(r=>{const s=modal.querySelector(`.psa-account[data-payslip="${Number(r.id)}"]`);if(s)s.value=r.source_financial_account_id||'';}); const bs=new bootstrap.Modal(modal); return new Promise((resolve,reject)=>{modal.querySelector('#psaSave').onclick=async()=>{try{const grouped={};modal.querySelectorAll('.psa-account').forEach(s=>{if(s.value)(grouped[s.value]||=[]).push(Number(s.dataset.payslip));});if(!Object.keys(grouped).length)throw new Error('Assign at least one source account.');await window.API.finance.assignPayrollSourceAccounts(payrollId,Object.entries(grouped).map(([account,payslip_ids])=>({source_financial_account_id:Number(account),payslip_ids})));bs.hide();resolve(true);}catch(e){alert(e.message||'Unable to save allocations');}};modal.addEventListener('hidden.bs.modal',()=>{modal.remove();resolve(false)},{once:true});bs.show();});
    }
    document.getElementById('payrollConfigureSourceAllocations').onclick=()=>configureAllocations(Number(paymentSelect.dataset.payrollId||0)).catch(e=>alert(e.message));
    const originalPaid = window.API.finance.markPayrollPaid;
    if (originalPaid) window.API.finance.markPayrollPaid = async function (id, ref, mode, sourceId, selectedIds) {
      paymentSelect.dataset.payrollId=id;
      // Always re-check the server state immediately before payment. A
      // payment modal can remain open while another operator starts the run,
      // so the table's earlier status is not sufficient protection.
      const latest = await window.API.finance.getPayrollSourceAllocations(id);
      const latestPayload = latest?.data?.data || latest?.data || latest || {};
      const latestStatus = String(
        latestPayload.run_status || latest?.run_status || latest?.data?.run_status || ''
      ).toLowerCase();
      if (latestStatus && latestStatus !== 'approved') {
        throw new Error(`Payroll disbursement has already started (${latestStatus}); payment cannot be submitted.`);
      }
      const selectedSourceId = Number(sourceId || paymentSelect.value || 0);
      if (!selectedSourceId && !(await configureAllocations(id))) throw new Error('Payroll source allocation was cancelled.');
      return originalPaid(id, ref, mode, selectedSourceId || null, selectedIds || []);
    };
  }
  document.addEventListener('DOMContentLoaded', () => init().catch(() => {}));
})();
