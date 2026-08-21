<?php
/* Discipline Reports — disciplinary analytics and case trends. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-shield-exclamation text-danger me-2"></i>Discipline Reports</h4>
      <p class="text-muted small mb-0 mt-1">Disciplinary case statistics and monthly trends.</p>
    </div>
    <button class="btn btn-outline-danger btn-sm" onclick="disciplineReportsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="row g-3 mb-4" id="drKpis"></div>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="bg-white border rounded-3 overflow-hidden h-100">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Cases by Type & Status</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Type</th><th>Status</th><th>Count</th></tr></thead>
            <tbody id="drByType"><tr><td colspan="3" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm"></div></td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="bg-white border rounded-3 overflow-hidden h-100">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Monthly Trend</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Month</th><th>Count</th><th>Bar</th></tr></thead>
            <tbody id="drTrend"><tr><td colspan="3" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm"></div></td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden mt-4">
    <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Recent Discipline Cases</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Student</th><th>Adm No</th><th>Class</th><th>Type</th><th>Severity</th><th>Date</th><th>Status</th>
        </tr></thead>
        <tbody id="drBody"><tr><td colspan="7" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/discipline_reports.js'); ?>
