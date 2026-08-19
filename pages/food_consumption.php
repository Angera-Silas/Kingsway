<?php
/* Food Consumption — quantities used and wasted across a date range. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-egg-fried text-success me-2"></i>Food Consumption</h4>
      <p class="text-muted small mb-0 mt-1">Food usage and waste recorded per item.</p>
    </div>
    <div class="d-flex gap-2 align-items-end flex-wrap">
      <div><label class="form-label small fw-semibold">From</label><input type="date" class="form-control form-control-sm" id="fcFrom"></div>
      <div><label class="form-label small fw-semibold">To</label><input type="date" class="form-control form-control-sm" id="fcTo"></div>
      <button class="btn btn-outline-success btn-sm" onclick="foodConsumptionController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
    </div>
  </div>

  <div id="fcStats" class="row g-3 mb-4"></div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Date</th><th>Item</th><th>Unit</th><th>Used</th><th>Waste</th><th>Cost (KES)</th>
        </tr></thead>
        <tbody id="fcBody"><tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<script src="<?= $appBase ?>/js/pages/food_consumption.js?v=<?= filemtime(APP_BASE_PATH . "/js/pages/food_consumption.js") ?>"></script>
