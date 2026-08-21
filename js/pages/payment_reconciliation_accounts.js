document.addEventListener('DOMContentLoaded', async () => {
  const controller = window.paymentReconciliationController;
  if (!controller) return;
  const response = await window.callAPI('/finance/financial-accounts', 'GET').catch(() => null);
  const accounts = ((response?.data || response)?.accounts || []);
  const options = accounts.map(a => '<option value="' + Number(a.id) + '">' + controller.esc(a.account_name) + ' · ' + controller.esc(a.account_identifier) + '</option>').join('');

  const routeForm = document.getElementById('collectionRouteForm');
  if (routeForm) {
    const label = document.createElement('label');
    label.className = 'col-md-3';
    label.innerHTML = '<span class="small text-muted">Mapped financial account</span><select class="form-select" id="routeFinancialAccount" required><option value="">Select account</option>' + options + '</select>';
    routeForm.insertBefore(label, routeForm.lastElementChild);
    const originalSave = window.API.finance.savePaymentCollectionRoute;
    window.API.finance.savePaymentCollectionRoute = data => originalSave(Object.assign({}, data, {
      financial_account_id: Number(document.getElementById('routeFinancialAccount')?.value || 0)
    }));
  }

  const modal = document.querySelector('#routingResolveModal .modal-body');
  if (modal) {
    const label = document.createElement('label');
    label.className = 'form-label';
    label.innerHTML = 'Receiving financial account<select class="form-select mb-3" id="routingFinancialAccount" required><option value="">Select account</option>' + options + '</select>';
    modal.appendChild(label);
    const originalResolve = window.API.finance.resolvePaymentRoutingCase;
    window.API.finance.resolvePaymentRoutingCase = (id, data) => originalResolve(id, Object.assign({}, data, {
      financial_account_id: Number(document.getElementById('routingFinancialAccount')?.value || 0)
    }));
  }
});
