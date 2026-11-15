<?php
/**
 * Student Clearance
 *
 * Every learner who leaves in an academic year (graduated, transferred,
 * withdrawn) is cleared by finance, library, uniform, school property and
 * academic records before the leaving certificate is issued. Finance follows
 * the learner's fee account: a balance or an overpayment is settled first.
 * JS: js/pages/student_clearance.js. Data: /api/students/clearance-*.
 */
?>
<section class="card kw-clean" id="clearancePage">
    <div class="card-header">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2">
            <div>
                <h4 class="mb-0">Student clearance</h4>
                <small id="clrPeriod">Learners who leave are cleared before their leaving certificate is issued.</small>
            </div>
            <div class="d-flex gap-2 d-print-none">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="clrExportBtn"><i class="bi bi-download me-1"></i>Export CSV</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="clrPrintBtn"><i class="bi bi-printer me-1"></i>Print list</button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-2 mb-3 d-print-none">
            <div class="col-md-3"><label class="form-label" for="clrYear">Year they left</label><select class="form-select form-select-sm" id="clrYear"><option value="">Loading…</option></select></div>
            <div class="col-md-2"><label class="form-label" for="clrOutcome">Outcome</label>
                <select class="form-select form-select-sm" id="clrOutcome">
                    <option value="">All leavers</option><option value="graduated">Graduated</option><option value="transferred">Transferred</option><option value="withdrawn">Withdrawn</option>
                </select></div>
            <div class="col-md-2"><label class="form-label" for="clrState">Clearance</label>
                <select class="form-select form-select-sm" id="clrState">
                    <option value="">Any</option><option value="not_started">Not started</option><option value="in_progress">In progress</option><option value="blocked">Blocked</option><option value="cleared">Cleared</option>
                </select></div>
            <div class="col-md-5"><label class="form-label" for="clrSearch">Search</label><input type="search" class="form-control form-control-sm" id="clrSearch" placeholder="Name or admission number"></div>
        </div>

        <div class="row mb-3" id="clrSummary"></div>

        <div class="table-responsive">
            <table class="table table-hover align-middle" id="clrTable" data-export-title="Student clearance" data-export-module="students">
                <thead><tr><th>Learner</th><th>Left as</th><th>Class</th><th class="text-end">Fee account</th><th>Checks</th><th>Clearance</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody><tr><td colspan="7" class="text-center text-muted py-4">Loading…</td></tr></tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="clrModal" tabindex="-1" aria-labelledby="clrModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content kw-clean">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="clrModalTitle">Clearance</h5>
                    <small class="text-muted" id="clrModalMeta"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="clrModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm" id="clrCertificateBtn" disabled><i class="bi bi-award me-1"></i>Print leaving certificate</button>
            </div>
        </div>
    </div>
</div>

<div id="clrCertificate" class="d-none d-print-block"></div>

<style>
    #clearancePage .clr-dot { display: inline-block; width: .55rem; height: .55rem; border-radius: 50%; margin-right: .2rem; background: #d0d5dd; }
    #clearancePage .clr-dot.cleared { background: #2f7d5b; }
    #clearancePage .clr-dot.blocked { background: #a23b2a; }
    #clearancePage .clr-dot.pending { background: #c99a2e; }
    #clearancePage .clr-owed { color: #a23b2a; }
    #clearancePage .clr-credit { color: #2f7d5b; }
    #clrModal .clr-check { border-bottom: 1px solid #e3e6eb; padding: .7rem 0; }
    #clrCertificate { font-family: Georgia, 'Times New Roman', serif; color: #111; }
    #clrCertificate .cert { max-width: 720px; margin: 0 auto; padding: 2rem 2.5rem; border: 2px solid #1f2a44; }
    #clrCertificate h1 { font-size: 1.6rem; text-align: center; margin: .5rem 0 1rem; }
    #clrCertificate .cert-school { text-align: center; font-size: 1.1rem; font-weight: 700; }
    #clrCertificate dl { display: grid; grid-template-columns: 12rem 1fr; gap: .4rem 1rem; margin: 1rem 0; }
    #clrCertificate dt { font-weight: 600; }
    #clrCertificate .cert-sign { display: flex; justify-content: space-between; margin-top: 3rem; }
    #clrCertificate .cert-sign div { border-top: 1px solid #111; width: 40%; padding-top: .3rem; text-align: center; font-size: .9rem; }
    @media print {
        body.clr-printing-certificate #main-content-segment > *:not(#clrCertificate) { display: none !important; }
    }
</style>

<?php asset_script($appBase, 'js/pages/student_clearance.js'); ?>
