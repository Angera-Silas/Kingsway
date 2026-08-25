<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-3"><div><h4 class="mb-1"><i class="bi bi-shield-check text-success me-2"></i>Academic Content Reconciliation</h4><p class="text-muted mb-0">Resolve legacy schemes and lesson plans only where their canonical stream context can be proven.</p></div><button class="btn btn-outline-success btn-sm" id="acrRefresh"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button></div>
  <div class="alert alert-warning small">Unresolved records are preserved and excluded from current teacher and parent workflows. Never select a stream or week without confirming the original record.</div>
  <div class="table-responsive card shadow-sm"><table class="table table-hover mb-0" id="acrTable"><thead class="table-light"><tr><th>Type</th><th>Record</th><th>Reason</th><th>Possible contexts</th><th>Action</th></tr></thead><tbody><tr><td colspan="5" class="text-center py-4">Loading reconciliation queue…</td></tr></tbody></table></div>
</div>
<?php asset_script($appBase ?? '', 'js/pages/academic_content_reconciliation.js'); ?>
