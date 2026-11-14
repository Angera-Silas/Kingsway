<?php
/**
 * Academic Calendar Page
 *
 * Read-only view of the school calendar for management roles (headteacher,
 * deputy head, etc.). Shows ONE deduplicated events table (term, week, event,
 * from, to) with real filters, defaulting to the current term. Day-type and
 * event management happen on the School Events / Year Calendar pages, which
 * only appear for staff with edit permissions.
 */
?>

<div>
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h4 class="mb-1"><i class="bi bi-calendar-alt me-2"></i>Academic Calendar</h4>
                    <p class="text-muted mb-0">Term dates, holidays, exams and events — one view for the whole year.</p>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 no-print">
                    <label class="text-muted small mb-0 text-nowrap" for="academicYearFilter">Academic year</label>
                    <select class="form-select form-select-sm" id="academicYearFilter" style="min-width: 15rem"><option>Loading…</option></select>
                    <button class="btn btn-sm btn-outline-success" id="exportCalendarCsv">
                        <i class="bi bi-file-csv me-1"></i> Export CSV
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" id="printCalendar">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                    <button class="btn btn-sm btn-outline-primary" id="calendarPdf" title="The official academic calendar for the selected year">
                        <i class="bi bi-file-earmark-pdf me-1"></i> Calendar PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Events Table (filters + rows rendered by AcademicEventsTable component) -->
    <div id="calendarEventsTable"></div>

    <!-- Upcoming Events -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock me-2"></i>Upcoming Events</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush" id="upcomingEvents">
                        <li class="list-group-item text-center text-muted py-4">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading upcoming events...
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/components/academic_events.js'); ?>
<?php asset_script($appBase, 'js/pages/academic_calendar.js'); ?>
