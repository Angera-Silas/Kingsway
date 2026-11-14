<?php
/**
 * Teacher Workload — weekly lessons per teacher for one academic year and
 * term, from the subject-teacher allocations and each class's weekly lessons.
 * Controller: js/pages/teacher_workload.js
 * API: GET /api/schedules/teacher-workload
 */
?>
<style>
    .tw-bar { position: relative; height: .5rem; min-width: 6rem; border-radius: 999px; background: #e9efec; overflow: hidden; }
    .tw-bar span { position: absolute; inset: 0 auto 0 0; border-radius: 999px; background: #2f7a52; }
    .tw-bar.is-over span { background: #b42318; }
    .tw-bar.is-under span { background: #c9962b; }
    .tw-lessons { font-variant-numeric: tabular-nums; font-weight: 600; }
    .tw-list { color: #5f6b66; font-size: .85rem; }
    @media print { .tw-no-print { display: none !important; } }
</style>

<div class="teacher-workload-container">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <h4 class="mb-1"><i class="bi bi-list-check me-2"></i>Teacher Workload</h4>
                    <p class="text-muted mb-0">Lessons a week per teacher, from the subject-teacher allocations and each class's weekly lessons.</p>
                </div>
                <div class="d-flex gap-2 tw-no-print">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="exportWorkload"><i class="bi bi-filetype-csv me-1"></i>Export CSV</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="printWorkload"><i class="bi bi-printer me-1"></i>Print</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3 tw-no-print">
        <div class="col-6 col-md-3">
            <label class="form-label small text-muted mb-1" for="academicYearFilter">Academic year</label>
            <select class="form-select" id="academicYearFilter" aria-label="Academic year"></select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small text-muted mb-1" for="academicTermFilter">Term</label>
            <select class="form-select" id="academicTermFilter" aria-label="Term"></select>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body text-center">
            <h3 class="mb-0" id="totalTeachers">--</h3><p class="text-muted mb-0 small">Teachers</p>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100"><div class="card-body text-center">
            <h3 class="mb-0" id="avgLessons">--</h3><p class="text-muted mb-0 small">Average lessons a week</p>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100 border-danger"><div class="card-body text-center">
            <h3 class="mb-0 text-danger" id="overloaded">--</h3><p class="text-muted mb-0 small">Over 30 a week</p>
        </div></div></div>
        <div class="col-6 col-md-3"><div class="card h-100 border-warning"><div class="card-body text-center">
            <h3 class="mb-0" id="underloaded">--</h3><p class="text-muted mb-0 small">Under 15 a week</p>
        </div></div></div>
    </div>

    <div class="alert alert-warning small" id="workloadGaps" hidden></div>

    <div class="card">
        <div class="card-header bg-white tw-no-print">
            <div class="row g-2">
                <div class="col-md-5">
                    <input type="search" class="form-control" id="searchTeacher" placeholder="Search teacher, class or learning area" aria-label="Search">
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select" id="filterDepartment" aria-label="Department">
                        <option value="">All departments</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select" id="filterWorkload" aria-label="Load">
                        <option value="">Any load</option>
                        <option value="overloaded">Over 30 a week</option>
                        <option value="optimal">15 to 30 a week</option>
                        <option value="underloaded">Under 15 a week</option>
                        <option value="none">Nothing allocated</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="workloadTable" data-export-title="Teacher workload">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Teacher</th>
                            <th scope="col">Department</th>
                            <th scope="col">Learning areas</th>
                            <th scope="col">Classes</th>
                            <th scope="col">Lessons a week</th>
                            <th scope="col" class="tw-no-print" style="width: 22%"><span class="visually-hidden">Load</span></th>
                        </tr>
                    </thead>
                    <tbody><tr><td colspan="6" class="text-center text-muted py-4">Loading workload…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/teacher_workload.js'); ?>
