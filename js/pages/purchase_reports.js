/**
 * purchase_reports.js — Purchase Reports Controller (standalone page).
 * Purchase order totals, status breakdown and supplier activity.
 */
const purchaseReportsController = {
  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.orders || r?.suppliers || r || []); },

  badgeClass(s) {
    return { received: 'bg-success', approved: 'bg-primary', ordered: 'bg-info text-dark', pending: 'bg-warning text-dark', draft: 'bg-secondary', cancelled: 'bg-danger' }[s] || 'bg-secondary';
  },

  async load() {
    document.getElementById('prStats').innerHTML = '';
    document.getElementById('prStatusBody').innerHTML = '<div class="spinner-border spinner-border-sm"></div>';
    document.getElementById('prSupBody').innerHTML = '<tr><td colspan="3" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';

    try {
      const orders = [];
      let page = 1, pages = 1;
      do {
        const r = await this.API('GET', '/inventory/purchase-orders-list', null, { page, limit: 100 });
        const payload = r?.data ?? r ?? {};
        const batch = Array.isArray(payload?.orders) ? payload.orders : (Array.isArray(payload) ? payload : []);
        orders.push(...batch);
        pages = Number(payload?.pagination?.total_pages || pages);
        if (!batch.length) break;
        page += 1;
      } while (page <= pages && page <= 5);

      const suppliersRes = await this.API('GET', '/inventory/suppliers-list', null, { page: 1, limit: 200 }).catch(() => null);
      const supPayload = suppliersRes?.data ?? suppliersRes ?? {};
      const suppliers = Array.isArray(supPayload?.suppliers) ? supPayload.suppliers : (Array.isArray(supPayload) ? supPayload : []);

      const totalValue = orders.reduce((s, o) => s + Number(o.total_amount ?? 0), 0);
      const statusCounts = {};
      orders.forEach(o => { const st = (o.status || 'unknown').toLowerCase(); statusCounts[st] = (statusCounts[st] || 0) + 1; });

      document.getElementById('prStats').innerHTML = [
        `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Total Orders</div><div class="fs-4 fw-bold">${this.esc(orders.length)}</div></div></div>`,
        `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Total Value</div><div class="fs-4 fw-bold">KES ${this.esc(Math.round(totalValue).toLocaleString())}</div></div></div>`,
        `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Suppliers</div><div class="fs-4 fw-bold">${this.esc(suppliers.length)}</div></div></div>`,
        `<div class="col-md-3"><div class="border rounded-3 p-3 bg-white"><div class="text-muted small">Avg Order Value</div><div class="fs-4 fw-bold">KES ${this.esc(Math.round(orders.length ? totalValue / orders.length : 0).toLocaleString())}</div></div></div>`,
      ].join('');

      const statusKeys = Object.keys(statusCounts).sort();
      if (!statusKeys.length) { document.getElementById('prStatusBody').innerHTML = '<span class="text-muted">No purchase orders recorded.</span>'; }
      else {
        document.getElementById('prStatusBody').innerHTML = statusKeys.map(k => `
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="badge ${this.badgeClass(k)}">${this.esc(k)}</span>
            <div class="flex-grow-1 mx-3"><div class="progress" style="height: 8px;"><div class="progress-bar" role="progressbar" style="width:${orders.length ? Math.round((statusCounts[k] / orders.length) * 100) : 0}%"></div></div></div>
            <span class="fw-semibold small">${this.esc(statusCounts[k])} (${orders.length ? Math.round((statusCounts[k] / orders.length) * 100) : 0}%)</span>
          </div>`).join('');
      }

      const supplierMap = {};
      orders.forEach(o => {
        const name = o.supplier_name || 'Unknown';
        if (!supplierMap[name]) supplierMap[name] = { count: 0, value: 0 };
        supplierMap[name].count += 1;
        supplierMap[name].value += Number(o.total_amount ?? 0);
      });
      const top = Object.entries(supplierMap).sort((a, b) => b[1].value - a[1].value).slice(0, 10);
      const supBody = document.getElementById('prSupBody');
      if (!top.length) { supBody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted">No supplier orders.</td></tr>'; }
      else {
        supBody.innerHTML = top.map(([name, v]) => `<tr>
          <td class="small fw-semibold">${this.esc(name)}</td>
          <td class="small">${this.esc(v.count)}</td>
          <td class="small">KES ${this.esc(Math.round(v.value).toLocaleString())}</td>
        </tr>`).join('');
      }
    } catch (e) {
      document.getElementById('prSupBody').innerHTML = `<tr><td colspan="3" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.purchaseReportsController = purchaseReportsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => purchaseReportsController.init().catch(() => {}));
} else {
  purchaseReportsController.init().catch(() => {});
}
