<?php
/**
 * Manage Calendar Events
 *
 * Purpose: Create and manage school calendar events
 * Features:
 * - Data display and filtering (calendar-synced events)
 * - Search and export
 */
?>

<div>
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><i class="bi bi-calendar-plus me-2"></i>Manage Calendar Events</h4>
                    <p class="text-muted mb-0">Create and manage school calendar events (synced with the academic calendar)</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary" onclick="ManageCalendarEventsController.syncNow()"><i class="bi bi-arrow-repeat me-1"></i> Sync Calendar</button>
                    <button class="btn btn-primary" onclick="ManageCalendarEventsController.showAddModal()"><i class="bi bi-plus-lg me-1"></i> Add New</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3"><i class="bi bi-calendar text-primary fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Total Events</h6><h4 class="mb-0" id="statTotal">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3"><i class="bi bi-clock text-warning fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Upcoming</h6><h4 class="mb-0" id="statUpcoming">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3"><i class="bi bi-calendar-day text-success fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">This Month</h6><h4 class="mb-0" id="statMonth">0</h4></div>
            </div></div></div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="d-flex align-items-center">
                <div class="rounded-circle bg-secondary bg-opacity-10 p-3 me-3"><i class="bi bi-clock-history text-secondary fa-lg"></i></div>
                <div><h6 class="text-muted mb-1">Completed / Past</h6><h4 class="mb-0" id="statPast">0</h4></div>
            </div></div></div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4"><input type="text" class="form-control" id="searchInput" placeholder="Search..."></div>
                <div class="col-md-3"><select class="form-select" id="filterSelect"><option value="">All</option></select></div>
                <div class="col-md-3"><input type="date" class="form-control" id="dateFilter"></div>
                <div class="col-md-2"><button class="btn btn-outline-secondary w-100" onclick="ManageCalendarEventsController.refresh()"><i class="bi bi-arrow-clockwise"></i></button></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-table me-2"></i>School Calendar Events</h6>
            <button class="btn btn-sm btn-outline-success" onclick="ManageCalendarEventsController.exportCSV()"><i class="bi bi-file-csv me-1"></i> Export</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="dataTable">
                    <thead class="table-light"><tr><th scope="col">#</th><th scope="col">Event Name</th><th scope="col">Start</th><th scope="col">End</th><th scope="col">Type</th><th scope="col">Term / Week</th><th scope="col">Status</th><th scope="col">Location</th><th scope="col">Actions</th></tr></thead>
                    <tbody><tr><td colspan="9" class="text-center text-muted py-4">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="formModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="formModalTitle"><i class="bi bi-calendar-plus me-2"></i>Add Event</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><form id="recordForm"><input type="hidden" id="recordId">
        <div class="mb-3"><label class="form-label">Event Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="recordName" required placeholder="e.g., End of Term Examinations"></div>
        <div class="row g-3">
            <div class="col-md-6 mb-3"><label class="form-label">Start Date <span class="text-danger">*</span></label><input type="date" class="form-control" id="recordStartDate" required></div>
            <div class="col-md-6 mb-3"><label class="form-label">Start Time</label><input type="time" class="form-control" id="recordStartTime"></div>
        </div>
        <div class="row g-3">
            <div class="col-md-6 mb-3"><label class="form-label">End Date <span class="text-muted small">(blank = same day)</span></label><input type="date" class="form-control" id="recordEndDate"></div>
            <div class="col-md-6 mb-3"><label class="form-label">End Time</label><input type="time" class="form-control" id="recordEndTime"></div>
        </div>
        <div class="row g-3">
            <div class="col-md-6 mb-3"><label class="form-label">Type</label><select class="form-select" id="recordType">
                <option value="exam">Exam / Examination Day</option>
                <option value="school_holiday">School Holiday</option>
                <option value="holiday">Holiday</option>
                <option value="public_holiday">Public Holiday</option>
                <option value="half_day">Half Day</option>
                <option value="special_event">Special Event</option>
                <option value="opening">Term Opening Day</option>
                <option value="closing">Term Closing Day</option>
                <option value="meeting">Meeting</option>
                <option value="sports">Sports</option>
                <option value="cultural">Cultural</option>
                <option value="general">General</option>
            </select></div>
            <div class="col-md-6 mb-3"><label class="form-label">Location / Venue</label><input type="text" class="form-control" id="recordLocation" placeholder="e.g., Main Hall"></div>
        </div>
        <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" id="recordDescription" rows="3"></textarea></div>
    </form></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary" onclick="ManageCalendarEventsController.saveRecord()"><i class="bi bi-check-lg me-1"></i> Save</button></div>
</div></div></div>

<script src="<?= $appBase ?>/js/pages/manage_calendar_events.js?v=<?php echo time(); ?>"></script>
