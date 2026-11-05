<?php
/**
 * Parent Feedback
 *
 * Purpose: View parent feedback messages and ratings
 * Features:
 * - Data display and filtering
 * - Search and export
 */
?>

<div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="bi bi-chats me-2"></i>Parent Feedback</h4>
                    <p class="text-muted mb-0">View parent feedback messages and ratings</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="bi bi-chats text-primary fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Total Feedback</h6><h4 class="mb-0" id="statTotal">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3"><i class="bi bi-star text-warning fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Avg Rating</h6><h4 class="mb-0" id="statAvg">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="bi bi-hand-thumbs-up text-success fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Positive (4+)</h6><h4 class="mb-0" id="statPositive">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3"><i class="bi bi-hand-thumbs-down text-danger fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Negative (2-)</h6><h4 class="mb-0" id="statNegative">0</h4></div>
            </div></div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><input type="text" class="form-control" id="searchInput" placeholder="Search..."></div>
                <div class="col-md-3"><select class="form-select" id="filterSelect"><option value="">All</option></select></div>
                <div class="col-md-3"><input type="date" class="form-control" id="dateFilter"></div>
                <div class="col-md-2"><button class="btn btn-outline-secondary w-100" onclick="ParentFeedbackController.refresh()"><i class="bi bi-arrow-clockwise"></i></button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-table me-2"></i>Parent Feedback</h6>
            <button class="btn btn-sm btn-outline-success" onclick="ParentFeedbackController.exportCSV()"><i class="bi bi-file-csv me-1"></i> Export</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dataTable">
                    <thead class="table-light"><tr><th scope="col">#</th><th scope="col">Parent</th><th scope="col">Student</th><th scope="col">Category</th><th scope="col">Rating</th><th scope="col">Message</th><th scope="col">Date</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead>
                    <tbody id="parentFeedbackTableBody">
                        <tr id="parentFeedbackTableState"><td colspan="9" class="text-center text-muted py-4">Loading feedback...</td></tr>
                    </tbody>
                </table>
                <template id="parentFeedbackRowTemplate">
                    <tr data-feedback-row>
                        <td data-field="number"></td>
                        <td data-field="parent"></td>
                        <td data-field="student"></td>
                        <td><span class="badge bg-info" data-field="category"></span></td>
                        <td><span data-field="rating"></span> <small data-field="rating-number"></small></td>
                        <td data-field="message"></td>
                        <td data-field="date"></td>
                        <td><span class="badge" data-field="status"></span></td>
                        <td><button type="button" class="btn btn-sm btn-outline-primary" data-action="view" title="View"><i class="fas fa-eye"></i></button></td>
                    </tr>
                </template>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="parentFeedbackViewModal" tabindex="-1" aria-labelledby="parentFeedbackViewTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="parentFeedbackViewTitle">Parent Feedback</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <p><strong>Parent:</strong> <span data-view="parent"></span></p>
            <p><strong>Student:</strong> <span data-view="student"></span></p>
            <p><strong>Category:</strong> <span data-view="category"></span></p>
            <p><strong>Rating:</strong> <span data-view="rating"></span></p>
            <p><strong>Date:</strong> <span data-view="date"></span></p>
            <hr>
            <p class="mb-0"><strong>Message</strong></p><div class="text-break" data-view="message"></div>
            <div data-view-response-container hidden><hr><p class="mb-0"><strong>Response</strong></p><div class="text-break" data-view="response"></div></div>
        </div>
    </div></div>
</div>

<script src="<?= $appBase ?>/js/pages/parent_feedback.js?v=<?php echo asset_version('js/pages/parent_feedback.js'); ?>"></script>
