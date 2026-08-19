<?php
/* View Fee Structure Progress — status tracking and review submission. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-hourglass-split text-success me-2"></i>Pending Director Approval</h4>
      <p class="text-muted small mb-0 mt-1">Track fee structure bundles through review and submit draft bundles for approval.</p>
    </div>
  </div>

  <div class="d-flex gap-2 mb-3 flex-wrap">
    <select id="fspStatus" class="form-select form-select-sm" style="width:220px">
      <option value="">All statuses</option>
      <option value="draft">Draft</option>
      <option value="pending_review">Pending Review</option>
      <option value="reviewed">Reviewed</option>
      <option value="approved">Approved</option>
      <option value="active">Active</option>
    </select>
    <select id="fspYear" class="form-select form-select-sm" style="width:170px"><option value="">All years</option></select>
    <button class="btn btn-outline-success btn-sm" onclick="viewFeeStructureProgressController.load()"><i class="bi bi-arrow-clockwise"></i></button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr>
        <th>Level</th><th>Year</th><th>Term</th><th>Student Type</th><th>Total (KES)</th><th>Items</th><th>Submitted By</th><th>Status</th><th class="text-end">Actions</th>
      </tr></thead>
      <tbody id="fspBody"><tr><td colspan="9" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
    </table>
  </div>

</div>

<script src="<?= $appBase ?>/js/pages/view_fee_structure_progress.js?v=<?= filemtime(APP_BASE_PATH . "/js/pages/view_fee_structure_progress.js") ?>"></script>
