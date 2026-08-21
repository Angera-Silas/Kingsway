<?php
/* Chapel Schedule — chapel services schedule. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-church text-primary me-2"></i>Chapel Schedule</h4>
      <p class="text-muted small mb-0 mt-1">Upcoming chapel services and events.</p>
    </div>
    <button class="btn btn-outline-primary btn-sm" onclick="chapelScheduleController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden mb-4">
    <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Upcoming Services</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Service</th><th>Date</th><th>Type</th><th>Description</th>
        </tr></thead>
        <tbody id="csUpcoming"><tr><td colspan="4" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">All Chapel Events</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Service</th><th>Date</th><th>Type</th><th>Description</th>
        </tr></thead>
        <tbody id="csAll"><tr><td colspan="4" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/chapel_schedule.js'); ?>
