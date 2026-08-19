<?php
/* Manage Admission Fees — record admission fee payments for new students. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-clipboard2-check text-success me-2"></i>Admission Fees</h4>
      <p class="text-muted small mb-0 mt-1">Record and track admission fee payments for newly enrolled students.</p>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-5">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h6 class="fw-bold mb-3">Record Admission Fee Payment</h6>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Student</label>
            <select id="mapStudent" class="form-select form-select-sm"><option value="">— Select student —</option></select>
          </div>
          <div class="mb-3"><label class="form-label small fw-semibold">Amount (KES) *</label><input type="number" class="form-control form-control-sm" id="mapAmount" min="0" step="0.01"></div>
          <div class="mb-3"><label class="form-label small fw-semibold">Payment Date *</label><input type="date" class="form-control form-control-sm" id="mapDate"></div>
          <div class="row g-2 mb-3">
            <div class="col-6"><label class="form-label small fw-semibold">Method</label><select class="form-select form-select-sm" id="mapMethod"><option>M-Pesa</option><option>Cash</option><option>Bank Transfer</option><option>Cheque</option><option>Other</option></select></div>
            <div class="col-6"><label class="form-label small fw-semibold">Reference No.</label><input type="text" class="form-control form-control-sm" id="mapRef"></div>
          </div>
          <div class="mb-3"><label class="form-label small fw-semibold">Notes</label><textarea class="form-control form-control-sm" id="mapNotes" rows="2"></textarea></div>
          <button class="btn btn-success w-100" onclick="manageAdmissionsPaymentsController.save()"><i class="bi bi-check-lg me-1"></i>Record Payment</button>
        </div>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Adm No</th><th>Student</th><th>Class</th><th>Balance (KES)</th></tr></thead>
            <tbody id="mapBody"><tr><td colspan="4" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<script src="<?= $appBase ?>/js/pages/manage_admissions_payments.js?v=<?= filemtime(APP_BASE_PATH . "/js/pages/manage_admissions_payments.js") ?>"></script>
