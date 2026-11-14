<?php
/**
 * Lesson Periods — the school day's periods: lessons, breaks, lunch,
 * assembly and games. Timetables are drafted into these periods (manual and
 * AI drafting alike). Attendance sessions are a separate concern and are
 * configured in View Attendance.
 *
 * Controller: js/pages/lesson_periods.js
 * API: GET/PUT /api/schedules/timetable-time-slots
 */
?>
<style>
    .lp-day {
        --lp-lesson: #2f7a52;
        --lp-break: #e7c66a;
        --lp-lunch: #c9962b;
        --lp-assembly: #5d6b75;
        --lp-games: #3c8f9b;
        position: relative;
        display: flex;
        height: 56px;
        border-radius: 10px;
        overflow: hidden;
        background: #eef2ef;
    }
    .lp-block {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 2px;
        color: #fff;
        font-size: .75rem;
        font-weight: 600;
        line-height: 1.15;
        text-align: center;
        overflow: hidden;
        border-right: 1px solid rgba(255, 255, 255, .55);
    }
    .lp-block:last-child { border-right: 0; }
    .lp-block span { padding: 0 4px; white-space: nowrap; }
    .lp-block[data-type="lesson"] { background: var(--lp-lesson); }
    .lp-block[data-type="break"] { background: var(--lp-break); color: #3b2f0b; }
    .lp-block[data-type="lunch"] { background: var(--lp-lunch); color: #2e2204; }
    .lp-block[data-type="assembly"] { background: var(--lp-assembly); }
    .lp-block[data-type="games"] { background: var(--lp-games); }
    .lp-block[data-type="gap"] { background: repeating-linear-gradient(135deg, #eef2ef 0 4px, #f8faf9 4px 8px); }
    .lp-block.has-problem { box-shadow: inset 0 0 0 3px #b42318; }
    .lp-axis {
        display: flex;
        justify-content: space-between;
        margin-top: .35rem;
        font-size: .75rem;
        color: #5f6b66;
        font-variant-numeric: tabular-nums;
    }
    .lp-legend { display: flex; flex-wrap: wrap; gap: .25rem 1rem; font-size: .8rem; color: #5f6b66; }
    .lp-legend i { display: inline-block; width: .7rem; height: .7rem; border-radius: 2px; margin-right: .35rem; vertical-align: -1px; }
    .lp-summary { font-size: 1rem; color: #1f2d27; margin: 0; }
    .lp-table td { vertical-align: middle; }
    .lp-table .form-control, .lp-table .form-select { min-width: 0; }
    .lp-num { width: 2.5rem; color: #5f6b66; font-variant-numeric: tabular-nums; }
    .lp-duration { white-space: nowrap; font-variant-numeric: tabular-nums; color: #5f6b66; }
    .lp-problem { display: block; font-size: .8rem; color: #b42318; margin-top: .2rem; }
    .lp-row-problem td { background: #fff5f4; }
    .wl-table { font-variant-numeric: tabular-nums; }
    .wl-table th, .wl-table td { white-space: nowrap; vertical-align: middle; }
    .wl-table thead th { font-size: .8rem; text-align: center; }
    .wl-table .wl-area { position: sticky; left: 0; z-index: 1; background: #fff; min-width: 11rem; text-align: left; white-space: normal; }
    .wl-table thead .wl-area { background: #f8f9fa; }
    .wl-table input { width: 3.6rem; margin: 0 auto; text-align: center; padding-left: .25rem; padding-right: .25rem; }
    .wl-table input.wl-unset { border-style: dashed; }
    .wl-table .wl-na { color: #b9c2bd; text-align: center; }
    .wl-table tfoot td { text-align: center; font-weight: 600; }
    .wl-total.is-over { color: #b42318; }
    .wl-total.is-full { color: #2f7a52; }
    .wl-total small { display: block; font-weight: 400; color: #5f6b66; }
    @media print {
        .lp-no-print, .lp-table .btn { display: none !important; }
        .lp-table input, .lp-table select { border: 0 !important; background: none !important; padding: 0 !important; -webkit-appearance: none; appearance: none; }
        .wl-table input { border: 0 !important; background: none !important; padding: 0 !important; width: auto; }
        .wl-table .wl-area { position: static; }
        .wl-card { break-before: page; }
        @page { size: A4 landscape; }
    }
</style>

<div class="lesson-periods-page">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <h4 class="mb-1"><i class="bi bi-clock-history me-2"></i>Lesson Periods</h4>
                    <p class="text-muted mb-0">The school day's lessons, breaks, lunch, assembly and games. Timetables are drafted into these periods.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap lp-no-print">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="lpExport"><i class="bi bi-filetype-csv me-1"></i>Export CSV</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="lpPrint"><i class="bi bi-printer me-1"></i>Print</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <p class="lp-summary mb-3" id="lpSummary">Loading the school day…</p>
            <div class="lp-day" id="lpDay" role="img" aria-label="The school day"></div>
            <div class="lp-axis" id="lpAxis"></div>
            <div class="lp-legend mt-3" aria-hidden="true">
                <span><i style="background:#2f7a52"></i>Lesson</span>
                <span><i style="background:#e7c66a"></i>Break</span>
                <span><i style="background:#c9962b"></i>Lunch</span>
                <span><i style="background:#5d6b75"></i>Assembly</span>
                <span><i style="background:#3c8f9b"></i>Games</span>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0">Periods</h6>
            <div class="d-flex gap-2 lp-no-print" id="lpEditActions" hidden>
                <button type="button" class="btn btn-outline-primary btn-sm" id="lpAdd"><i class="bi bi-plus-lg me-1"></i>Add period</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="lpDiscard" disabled>Discard changes</button>
                <button type="button" class="btn btn-primary btn-sm" id="lpSave" disabled>Save periods</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0 lp-table" id="lpTable" data-export-title="Lesson periods">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Name</th>
                            <th scope="col">Type</th>
                            <th scope="col">Starts</th>
                            <th scope="col">Ends</th>
                            <th scope="col">Length</th>
                            <th scope="col" class="lp-no-print"><span class="visually-hidden">Remove</span></th>
                        </tr>
                    </thead>
                    <tbody id="lpBody">
                        <tr><td colspan="7" class="text-center text-muted py-4">Loading periods…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white small text-muted">
            Timetables place lessons in these periods. Changing a period's times moves the lessons scheduled in it;
            a removed period is kept in the records so existing timetables are not broken.
            Attendance sessions are set up in <a href="<?= htmlspecialchars(app_base_path()) ?>/home.php?route=view_attendance">View Attendance</a>.
        </div>
    </div>
    <div class="card shadow-sm border-0 mt-4 wl-card" id="wlCard">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h6 class="mb-1">Weekly lessons</h6>
                    <p class="small text-muted mb-0" id="wlSummary">How many lessons each learning area gets in a week, per class. Timetable drafts place exactly these.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap align-items-end lp-no-print">
                    <div>
                        <label class="form-label small text-muted mb-1" for="wlYear">Academic year</label>
                        <select class="form-select form-select-sm" id="wlYear" aria-label="Academic year"></select>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="wlExport"><i class="bi bi-filetype-csv me-1"></i>Export CSV</button>
                    <span class="d-flex gap-2" id="wlEditActions" hidden>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="wlDiscard" disabled>Discard changes</button>
                        <button type="button" class="btn btn-primary btn-sm" id="wlSave" disabled>Save weekly lessons</button>
                    </span>
                </div>
            </div>
            <div class="alert alert-warning small py-2 mt-3 mb-0" id="wlNotReady" hidden>
                Weekly lessons can be viewed but not saved yet: the lessons-per-week database update has not been applied.
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 wl-table" id="wlTable" data-export-title="Weekly lessons">
                    <thead class="table-light" id="wlHead"></thead>
                    <tbody id="wlBody"><tr><td class="text-center text-muted py-4">Loading weekly lessons…</td></tr></tbody>
                    <tfoot id="wlFoot"></tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/lesson_periods.js?v=<?php echo asset_version('js/pages/lesson_periods.js'); ?>"></script>
<script src="<?= $appBase ?>/js/pages/weekly_lessons.js?v=<?php echo asset_version('js/pages/weekly_lessons.js'); ?>"></script>
