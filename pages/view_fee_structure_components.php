<?php
/* Fee Structure Components — manage fee types/components catalog. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-list-check text-success me-2"></i>Fee Components</h4>
      <p class="text-muted small mb-0 mt-1">Manage fee items used in fee structures. Create, edit, activate/deactivate components.</p>
    </div>
    <button class="btn btn-success btn-sm" onclick="viewFeeStructureComponentsController.openCreateModal()">
      <i class="bi bi-plus-circle me-1"></i>Add Fee Component
    </button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr>
        <th>Code</th><th>Component Name</th><th>Category</th><th>Type</th><th>Description</th><th style="width:130px">Actions</th>
      </tr></thead>
      <tbody id="fscBody"><tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
    </table>
  </div>

</div>

<!-- Create/Edit Fee Component Modal -->
<div class="modal fade" id="fscModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title fw-bold" id="fscModalTitle">Add Fee Component</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="fscEditId">
      <div class="mb-3">
        <label class="form-label small fw-semibold">Code *</label>
        <input type="text" class="form-control form-control-sm" id="fscCode" placeholder="e.g. TUITION">
      </div>
      <div class="mb-3">
        <label class="form-label small fw-semibold">Name *</label>
        <input type="text" class="form-control form-control-sm" id="fscName" placeholder="e.g. Tuition Fee">
      </div>
      <div class="mb-3">
        <label class="form-label small fw-semibold">Category *</label>
        <select class="form-select form-select-sm" id="fscCategory">
          <option value="tuition">Tuition</option>
          <option value="boarding">Boarding</option>
          <option value="activity">Activity</option>
          <option value="infrastructure">Infrastructure</option>
          <option value="other">Other</option>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label small fw-semibold">Description</label>
        <textarea class="form-control form-control-sm" id="fscDescription" rows="2"></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label small fw-semibold">Mandatory</label>
        <select class="form-select form-select-sm" id="fscMandatory">
          <option value="1">Yes</option>
          <option value="0">No</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
      <button class="btn btn-success btn-sm" onclick="viewFeeStructureComponentsController.save()">Save</button>
    </div>
  </div></div>
</div>

<script src="<?= $appBase ?>/js/pages/view_fee_structure_components.js?v=<?= filemtime(APP_BASE_PATH . "/js/pages/view_fee_structure_components.js") ?>"></script>
