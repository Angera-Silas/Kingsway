<?php
/**
 * Class Records
 *
 * One class stream in one academic year ("Grade 1 A · Class of 2026"): every
 * learner on that year's roll against every learning area, Terms 1-3, with
 * class averages; and the same learners followed over the years ("Over the
 * years"), with the class and class teacher of each year.
 *
 * Teachers see the classes they teach or have taught (any year); whole-school
 * roles see every class. Read-only. Data: /api/academic/class-record-streams,
 * /api/academic/class-record/{id}, /api/academic/class-cohort-history/{id}.
 */
?>
<div class="container-fluid py-4" id="classRecordsPage">
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h5 class="card-title mb-0"><i class="bi bi-journal-bookmark me-2"></i>Class Records</h5>
                <small class="text-muted" id="crScopeNote"></small>
            </div>
            <div class="d-flex gap-2 d-print-none">
                <button id="crExportBtn" class="btn btn-outline-success btn-sm" type="button"><i class="bi bi-download me-1"></i>Export CSV</button>
                <button id="crPrintBtn" class="btn btn-outline-secondary btn-sm" type="button"><i class="bi bi-printer me-1"></i>Print</button>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 d-print-none">
                <div class="col-md-3">
                    <label class="form-label" for="crYear">Academic year</label>
                    <select id="crYear" class="form-select"><option value="">Loading…</option></select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="crClass">Class</label>
                    <select id="crClass" class="form-select"><option value="">Loading…</option></select>
                </div>
                <div class="col-md-2" id="crTermWrap">
                    <label class="form-label" for="crTerm">Term</label>
                    <select id="crTerm" class="form-select"></select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="btn-group w-100" role="group" aria-label="View">
                        <input type="radio" class="btn-check" name="crView" id="crViewYear" value="year" checked>
                        <label class="btn btn-outline-primary" for="crViewYear">This year</label>
                        <input type="radio" class="btn-check" name="crView" id="crViewCohort" value="cohort">
                        <label class="btn btn-outline-primary" for="crViewCohort">Over the years</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="crStatus" class="text-center text-muted py-4">Loading…</div>

    <div id="crYearView" class="d-none">
        <div class="card mb-3">
            <div class="card-body">
                <h4 class="mb-1" id="crClassLabel"></h4>
                <div class="text-muted" id="crClassMeta"></div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header"><strong>Learners</strong> <small class="text-muted" id="crTermLabel"></small></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0 align-middle" id="crLearnersTable"></table>
                </div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header"><strong>Learning areas</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 align-middle" id="crAreasTable"></table>
                </div>
            </div>
        </div>
        <div class="card mb-3 d-none" id="crLeftCard">
            <div class="card-header"><strong>Left during the year</strong></div>
            <div class="card-body" id="crLeftList"></div>
        </div>
    </div>

    <div id="crCohortView" class="d-none">
        <div class="card mb-3">
            <div class="card-body">
                <h4 class="mb-1" id="crCohortLabel"></h4>
                <div class="text-muted" id="crCohortMeta"></div>
            </div>
        </div>
        <div id="crCohortYears"></div>
    </div>
</div>

<style>
    #classRecordsPage .cr-score { white-space: nowrap; }
    #classRecordsPage th.cr-area { min-width: 6rem; font-size: .8rem; }
    @media print {
        @page { size: A4 landscape; margin: 10mm; }
        #classRecordsPage .table { font-size: 9pt; }
        #classRecordsPage .card { border: 0; }
    }
</style>

<?php asset_script($appBase, 'js/pages/class_records.js'); ?>
