<?php
/* Counseling Reports — counseling analytics and session trends. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-graph-up text-primary me-2"></i>Counseling Reports</h4>
      <p class="text-muted small mb-0 mt-1">Counseling analytics and session trends.</p>
    </div>
    <button class="btn btn-outline-primary btn-sm" onclick="counselingReportsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="row g-3 mb-4" id="crKpis"></div>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Cases by Type</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Case Type</th><th>Count</th></tr></thead>
            <tbody id="crByType"><tr><td colspan="2" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm"></div></td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Session Trend (Last 5 Months)</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Month</th><th>Sessions</th><th>Bar</th></tr></thead>
            <tbody id="crTrend"><tr><td colspan="3" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm"></div></td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden mt-4">
    <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Follow-ups Due (Next 14 Days)</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Case</th><th>Counselee</th><th>Type</th><th>Priority</th><th>Follow-up</th></tr></thead>
        <tbody id="crFollow"><tr><td colspan="5" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm"></div></td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<script src="<?= $appBase ?>/js/pages/counseling_reports.js?v=<?= filemtime(APP_BASE_PATH . "/js/pages/counseling_reports.js") ?>"></script>
