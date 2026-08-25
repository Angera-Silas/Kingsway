/* Collection-route controls are deliberately separate from transaction permissions. */
(function () {
  const controller = window.paymentIntegrationSettingsController;
  if (!controller) return;

  function ensureRouteControls() {
    if (document.getElementById('pisCollectionPurposes')) return;
    const host = document.getElementById('pisCollectionRouteHost');
    if (!host) return;
    const block = document.createElement('div');
    block.className = 'col-12';
    block.innerHTML = '<label class="form-label small fw-semibold">Incoming collection routes</label>' +
      '<div id="pisCollectionPurposes" class="row g-2">' +
      ['fees', 'transport', 'uniforms'].map(code =>
        `<div class="col-md-4"><label class="form-check"><input class="form-check-input pis-collection-purpose" type="checkbox" value="${code}"> ${code}</label>${code === 'fees' ? '<small class="d-block text-muted">Cash is not allowed for fees.</small>' : `<label class="form-check small text-muted"><input class="form-check-input pis-collection-cash" type="checkbox" data-purpose="${code}"> Allow cash receipts</label>`}</div>`
      ).join('') +
      '</div><div class="form-text">Select what this route receives from parents. Cash is available only for transport and uniforms, and must be deposited/reconciled into the selected settlement account.</div>';
    host.appendChild(block);
  }

  function selectedRoutes() {
    return Array.from(document.querySelectorAll('.pis-collection-purpose:checked')).map(x => x.value);
  }

  function channelOverrides() {
    const result = {};
    document.querySelectorAll('.pis-collection-cash').forEach(x => { result[x.dataset.purpose] = { cash: x.checked }; });
    return result;
  }

  const originalOpen = controller.open.bind(controller);
  controller.open = function (row) {
    ensureRouteControls();
    const routeRows = row ? controller.routes.filter(x => Number(x.financial_account_id) === Number(row.id)) : [];
    const products = routeRows.map(x => x.collection_product).filter(Boolean);
    const policies = routeRows.map(x => x.reference_policy).filter(Boolean);
    const editableRow = row ? {
      ...row,
      collection_product: row.collection_product || products[0] || '',
      reference_policy: row.reference_policy || policies[0] || ''
    } : row;
    originalOpen(editableRow);
    const existing = routeRows.map(x => x.purpose);
    document.querySelectorAll('.pis-collection-purpose').forEach(x => { x.checked = existing.includes(x.value); });
    document.querySelectorAll('.pis-collection-cash').forEach(x => { const route = routeRows.find(r => r.purpose === x.dataset.purpose); x.checked = Boolean(route && String(route.route_channels || '').split(',').includes('cash')); });
  };

  const create = window.API.finance.createFinancialAccount;
  const update = window.API.finance.updateFinancialAccount;
  window.API.finance.createFinancialAccount = data => create({ ...data, collection_purposes: selectedRoutes(), collection_channel_overrides: channelOverrides() });
  window.API.finance.updateFinancialAccount = (id, data) => update(id, { ...data, collection_purposes: selectedRoutes(), collection_channel_overrides: channelOverrides() });

  ensureRouteControls();
})();
