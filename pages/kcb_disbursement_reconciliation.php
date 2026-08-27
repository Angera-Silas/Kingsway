<div class="container-fluid py-4" id="kcbReconciliationPage">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h3 class="mb-1"><i class="bi bi-shield-check me-2 text-primary"></i>KCB Disbursement Reconciliation</h3>
      <p class="text-muted mb-0">Recover delayed callbacks, investigate exceptions and prevent duplicate outgoing payments.</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-primary" id="kcbPollDue"><i class="bi bi-arrow-repeat me-1"></i>Check due transfers</button>
      <button class="btn btn-outline-secondary" id="kcbRefresh"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
    </div>
  </div>

  <div class="alert alert-warning small">
    <i class="bi bi-exclamation-triangle me-1"></i>
    Never retry a pending or unresolved transfer. A retry becomes available only after KCB—or an audited senior reconciliation—confirms failure.
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label" for="kcbStatusFilter">Payment state</label>
        <select class="form-select" id="kcbStatusFilter">
          <option value="">All states</option><option value="pending">Pending</option>
          <option value="completed">Completed</option><option value="failed">Failed</option>
        </select>
      </div>
      <div class="col-md-3 form-check ms-md-3 mb-2">
        <input class="form-check-input" type="checkbox" id="kcbExceptionsOnly">
        <label class="form-check-label" for="kcbExceptionsOnly">Exceptions only</label>
      </div>
      <div class="col text-md-end small text-muted" id="kcbQueueSummary">Loading…</div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>ID / purpose</th><th>Recipient</th><th class="text-end">Amount</th><th>KCB reference</th>
          <th>Transfer state</th><th>Reconciliation</th><th>Last checked</th><th class="text-end">Actions</th>
        </tr></thead>
        <tbody id="kcbQueueBody"><tr><td colspan="8" class="text-center py-5"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr></tbody>
      </table>
    </div>
  </div>
</div>
<?php asset_script($appBase, 'js/pages/kcb_disbursement_reconciliation.js'); ?>
