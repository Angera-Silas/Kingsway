<?php
/**
 * Student Clearance
 *
 * Every learner who leaves in an academic year (graduated, transferred,
 * withdrawn) is cleared by finance, library, uniform, school property and
 * academic records before the exit document is issued: the school leaving
 * certificate for graduates and withdrawals, the transfer letter (NEMIS UPI,
 * receiving school) for transfers, after which the transfer is completed.
 * Finance follows the learner's fee account: a balance or an overpayment is
 * settled first.
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
                    <option value="">All leavers</option><option value="graduating">Graduating</option><option value="graduated">Graduated</option><option value="transferring">Transferring</option><option value="transferred">Transferred</option><option value="withdrawn">Withdrawn</option>
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
            <div class="modal-body">
                <div id="clrModalBody"></div>
                <div id="clrDocStatus" class="clr-doc-status mt-3"></div>
                <div id="clrTransfer" class="clr-transfer mt-3 d-none">
                  <div id="clrTransferDone" class="d-none"></div>
                  <div id="clrTransferForm" data-year-write>
                    <strong>Complete the transfer</strong>
                    <div class="small text-muted mb-2">After the transfer letter is issued: the learner&rsquo;s enrolment and status become transferred. Record the date the learner was released on NEMIS, if done.</div>
                    <div class="d-flex flex-wrap align-items-end gap-2">
                        <div><label class="form-label small mb-0" for="clrNemisDate">Released on NEMIS</label><input type="date" class="form-control form-control-sm" id="clrNemisDate"></div>
                        <button type="button" class="btn btn-sm btn-success" id="clrTransferBtn"><i class="bi bi-box-arrow-right me-1"></i>Complete transfer</button>
                    </div>
                  </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm" id="clrCertificateBtn" disabled><i class="bi bi-award me-1"></i>Issue leaving certificate</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="clrDocModal" tabindex="-1" aria-labelledby="clrDocTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content kw-clean" id="clrDocForm" novalidate>
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0" id="clrDocTitle">School leaving certificate</h5>
                    <small class="text-muted" id="clrDocMeta"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-2 small" id="clrDocFacts"></div>
                <div id="clrDocDestination" class="d-none">
                    <h6 class="mt-2">Receiving school</h6>
                    <div class="row g-2">
                        <div class="col-md-8"><label class="form-label" for="clrDestSchool">School name <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" id="clrDestSchool" maxlength="200"></div>
                        <div class="col-md-4"><label class="form-label" for="clrDestCode">School code</label><input type="text" class="form-control form-control-sm" id="clrDestCode" maxlength="40"></div>
                        <div class="col-md-6"><label class="form-label" for="clrDestCounty">County</label><input type="text" class="form-control form-control-sm" id="clrDestCounty" maxlength="100"></div>
                        <div class="col-md-6"><label class="form-label" for="clrDestSubCounty">Sub-county</label><input type="text" class="form-control form-control-sm" id="clrDestSubCounty" maxlength="100"></div>
                        <div class="col-12"><label class="form-label" for="clrDestReason">Reason for transfer</label><input type="text" class="form-control form-control-sm" id="clrDestReason" maxlength="500"></div>
                    </div>
                </div>
                <h6 class="mt-3">Head teacher&rsquo;s report</h6>
                <div class="row g-2">
                    <div class="col-md-4"><label class="form-label" for="clrDocLeaving">Date of leaving</label><input type="date" class="form-control form-control-sm" id="clrDocLeaving"></div>
                    <div class="col-12"><label class="form-label" for="clrDocAcademic">Academic ability <span class="text-danger">*</span></label><textarea class="form-control form-control-sm" id="clrDocAcademic" rows="2" maxlength="2000"></textarea></div>
                    <div class="col-12"><label class="form-label" for="clrDocConduct">Conduct <span class="text-danger">*</span></label><textarea class="form-control form-control-sm" id="clrDocConduct" rows="2" maxlength="2000"></textarea></div>
                    <div class="col-12"><label class="form-label" for="clrDocCo">Co-curricular activities</label><textarea class="form-control form-control-sm" id="clrDocCo" rows="2" maxlength="2000"></textarea></div>
                </div>
                <div class="small text-muted mt-2">The report is kept with the document, so a reprint carries the same serial number and wording. Sign, then stamp, after printing.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="clrDocSubmit"><i class="bi bi-file-earmark-pdf me-1"></i>Issue and open PDF</button>
            </div>
        </form>
    </div>
</div>

<style>
    #clearancePage .clr-dot { display: inline-block; width: .55rem; height: .55rem; border-radius: 50%; margin-right: .2rem; background: #d0d5dd; }
    #clearancePage .clr-dot.cleared { background: #2f7d5b; }
    #clearancePage .clr-dot.blocked { background: #a23b2a; }
    #clearancePage .clr-dot.pending { background: #c99a2e; }
    #clearancePage .clr-owed { color: #a23b2a; }
    #clearancePage .clr-credit { color: #2f7d5b; }
    #clrModal .clr-check { border-bottom: 1px solid #e3e6eb; padding: .7rem 0; }
    #clrModal .clr-doc-status, #clrModal .clr-transfer { border: 1px solid #e3e6eb; border-radius: .4rem; padding: .7rem .8rem; }
    #clrDocModal .clr-fact dt { font-weight: 600; font-size: .75rem; color: #5b6472; }
    #clrDocModal .clr-fact dd { margin: 0; }
</style>

<?php asset_script($appBase, 'js/pages/student_clearance.js'); ?>
