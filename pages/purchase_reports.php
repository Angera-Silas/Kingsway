<?php
/* Purchase Reports — purchase order summaries and status breakdown. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-receipt-cutoff text-primary me-2"></i>Purchase Reports</h4>
      <p class="text-muted small mb-0 mt-1">Purchase order totals, status breakdown and supplier activity.</p>
    </div>
    <button class="btn btn-outline-primary btn-sm" onclick="purchaseReportsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div id="prStats" class="row g-3 mb-4"></div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Orders by Status</div>
        <div class="p-3" id="prStatusBody">Loading…</div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Top Suppliers</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Supplier</th><th>Orders</th><th>Value (KES)</th></tr></thead>
            <tbody id="prSupBody"><tr><td colspan="3" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/purchase_reports.js'); ?>
