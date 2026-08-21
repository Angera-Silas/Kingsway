(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', async function () {
    const page = document.getElementById('parentRefundsPage');
    if (!page || !window.callAPI) return;
    const response = await window.callAPI('/finance/financial-accounts', 'GET').catch(() => null);
    const data = response?.data || response || {};
    const accounts = (data.accounts || []).filter(a => a.status === 'active' && String(a.purposes || '').split(',').includes('refunds'));
    const box = document.createElement('div');
    box.className = 'alert alert-light border d-flex align-items-center gap-3';
    box.innerHTML = '<label class="fw-semibold mb-0" for="refundSourceFinancialAccount">Refund source account</label><select class="form-select" style="max-width:420px" id="refundSourceFinancialAccount"><option value="">Select authorized source account</option></select>';
    page.querySelector('.alert-warning')?.after(box);
    const select = box.querySelector('select');
    accounts.forEach(a => { const o=document.createElement('option'); o.value=a.id; o.textContent=`${a.account_name} · ${a.provider_code || 'manual'} · ${a.account_identifier}`; select.appendChild(o); });
    const original = window.callAPI;
    window.callAPI = async function (url, method, body, ...rest) {
      if (url === '/finance/parent-refund-requests' && String(method).toUpperCase() === 'POST') {
        const sourceId = Number(select.value || 0);
        if (!sourceId) throw new Error('Select the authorized refund source account.');
        body = Object.assign({}, body, { items: (body?.items || []).map(item => Object.assign({}, item, { source_financial_account_id: sourceId })) });
      }
      return original(url, method, body, ...rest);
    };
  });
})();
