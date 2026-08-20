<?php
/**
 * Manage Extra Charges
 * Flexible, database-driven charges that appear on the fee structure printout.
 * Staff decide what bills what, where, and when — charges are informational
 * until explicitly used.
 */
?>
<div class="manager-layout" data-user-role="accountant">

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-receipt-cutoff"></i> Extra Charges</h2>
        <p class="text-muted mb-0">Manage charges that appear on the fee structure (registration, caution, trips, etc.)</p>
    </div>
    <div>
        <button class="btn btn-primary btn-sm" id="btnCreateCharge"><i class="bi bi-plus-lg"></i> New Charge</button>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small">Academic Year</label>
                <select class="form-select form-select-sm" id="filterYear"></select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Status</label>
                <select class="form-select form-select-sm" id="filterStatus">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Scope</label>
                <select class="form-select form-select-sm" id="filterScope">
                    <option value="">All Scopes</option>
                    <option value="all">All Students</option>
                    <option value="student_type">By Student Type</option>
                    <option value="class">By Class</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary btn-sm w-100" id="btnClearFilters">Clear</button>
            </div>
        </div>
    </div>
</div>

<!-- Data Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:5%">#</th>
                        <th style="width:22%">Name</th>
                        <th style="width:12%" class="text-end">Amount (KES)</th>
                        <th style="width:12%">Frequency</th>
                        <th style="width:12%">Scope</th>
                        <th style="width:10%">Applies To</th>
                        <th style="width:10%">Status</th>
                        <th style="width:17%">Actions</th>
                    </tr>
                </thead>
                <tbody id="chargesBody">
                    <tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create / Edit Modal -->
<div class="modal fade" id="chargeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="chargeModalTitle">New Extra Charge</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="chargeForm">
                    <input type="hidden" id="chargeId">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="chargeName" required placeholder="e.g. Registration Fee, Caution Money">
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Amount (KES) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="chargeAmount" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Frequency</label>
                            <select class="form-select" id="chargeFrequency">
                                <option value="one_time">One Time</option>
                                <option value="per_term">Per Term</option>
                                <option value="per_year">Per Year</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Scope</label>
                            <select class="form-select" id="chargeScope">
                                <option value="all">All Students</option>
                                <option value="student_type">By Student Type</option>
                                <option value="class">By Class</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="scopeDetailGroup" style="display:none">
                            <label class="form-label" id="scopeDetailLabel">Apply To</label>
                            <select class="form-select" id="scopeDetailId"></select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="chargeDescription" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Display Order</label>
                        <input type="number" class="form-control" id="chargeDisplayOrder" value="0" min="0">
                        <small class="form-text text-muted">Lower numbers appear first on the fee structure</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSaveCharge">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Extra Charge</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rejectChargeId">
                <div class="mb-3">
                    <label class="form-label">Reason for rejection</label>
                    <textarea class="form-control" id="rejectNotes" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="btnConfirmReject">Reject</button>
            </div>
        </div>
    </div>
</div>

<script src="/js/pages/extra_charges.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] ?? APP_BASE_PATH . '/js/pages/extra_charges.js') ?>"></script>
</div>
