<?php
/* Transport Fees — transport fee collection overview for management. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<style>
.tf-table td,.tf-table th { vertical-align:middle;font-size:.85rem; }
</style>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-bus-front text-success me-2"></i>Transport Fees</h4>
      <p class="text-muted small mb-0 mt-1">Transport fee billing, collections and arrears for all assigned students.</p>
    </div>
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#transportEntitlementModal"><i class="bi bi-plus-circle me-1"></i>Add transport coverage</button>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body">
      <div class="text-muted small">Students Assigned</div>
      <div class="fw-bold fs-5" id="statStudents">—</div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body">
      <div class="text-muted small">Total Billed</div>
      <div class="fw-bold fs-5" id="statBilled">—</div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body">
      <div class="text-muted small">Total Collected</div>
      <div class="fw-bold fs-5" id="statCollected">—</div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body">
      <div class="text-muted small">Outstanding Balance</div>
      <div class="fw-bold fs-5 text-danger" id="statBalance">—</div>
    </div></div></div>
  </div>

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2 flex-wrap">
      <select id="routeFilter" class="form-select form-select-sm" style="width:220px">
        <option value="">All routes (summary)</option>
      </select>
      <select id="monthFilter" class="form-select form-select-sm" style="width:120px"></select>
      <select id="yearFilter" class="form-select form-select-sm" style="width:100px"></select>
    </div>
    <button class="btn btn-outline-success btn-sm" onclick="transportFeesController.loadData()">
      <i class="bi bi-arrow-clockwise"></i>
    </button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <table class="table table-hover tf-table mb-0">
      <thead class="table-light"><tr>
        <th scope="col">Adm No.</th><th scope="col">Student</th><th scope="col">Route</th><th class="text-end">Billed (KES)</th><th class="text-end">Paid (KES)</th><th class="text-end">Balance (KES)</th><th scope="col">Status</th>
      </tr></thead>
      <tbody id="feesTableBody"><tr><td colspan="7" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
    </table>
  </div>

</div>

<div class="modal fade" id="transportEntitlementModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
    <form id="transportEntitlementForm">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-calendar2-check text-success me-2"></i>Allocate transport coverage</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"><p class="small text-muted">Choose the exact period the parent is paying for. This is separate from school fees.</p>
        <div class="row g-3"><div class="col-md-6"><label class="form-label">Learner</label><select class="form-select" id="transportEntStudent" required><option value="">Loading learners…</option></select></div><div class="col-md-6"><label class="form-label">Route</label><select class="form-select" id="transportEntRoute" required><option value="">Select route</option></select></div><div class="col-md-4"><label class="form-label">Coverage</label><select class="form-select" id="transportEntType" required><option value="day">One day</option><option value="week">One week</option><option value="month">One month</option><option value="term">Whole term</option><option value="year">Whole year (Terms 1–3)</option></select></div><div class="col-md-4"><label class="form-label">Start date</label><input type="date" class="form-control" id="transportEntStart" required></div><div class="col-md-4"><label class="form-label">End date</label><input type="date" class="form-control" id="transportEntEnd" required></div><div class="col-md-4"><label class="form-label">Amount due (KES)</label><input type="number" min="0" step="0.01" class="form-control" id="transportEntAmount" required></div><div class="col-md-4"><label class="form-label">Payment channel</label><select class="form-select" id="transportEntMethod"><option value="cash">Cash (confirm now)</option><option value="daraja_mpesa">M-Pesa Daraja STK</option><option value="buni_mpesa">M-Pesa via KCB Buni</option><option value="bank_transfer">Bank transfer (reconcile)</option><option value="cheque">Cheque (clearance)</option><option value="bursary">Bursary / waiver</option></select></div><div class="col-md-4"><label class="form-label">Receiving account</label><select class="form-select" id="transportEntFinancialAccount" required><option value="">Loading authorized transport accounts…</option></select></div><div class="col-md-4"><label class="form-label">Parent phone</label><input class="form-control" id="transportEntPhone" placeholder="2547XXXXXXXX"><div class="form-text">Required for an STK request.</div></div><div class="col-md-4"><label class="form-label">Provider/reference</label><input class="form-control" id="transportEntReference" placeholder="Receipt, bank or cheque ref"></div></div>
      </div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success" id="transportEntSubmit"><i class="bi bi-check2-circle me-1"></i>Save coverage</button></div>
    </form>
  </div></div>
</div>

<?php asset_script($appBase, 'js/pages/transport_fees.js'); ?>
