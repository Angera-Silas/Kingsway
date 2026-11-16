<?php
/**
 * National Assessments: KPSEA (Grade 6), KJSEA (Grade 9) and the Grade 3
 * assessment, uploaded when KNEC / the Ministry releases the results.
 *
 * Results belong to the sitting (the exam and the academic year it was
 * sat), so the outgoing year's Grade 6 and Grade 9 learners can be uploaded
 * after promotion and graduation. JS: js/pages/national_exams.js.
 * Data: /api/academic/national-sittings, /national-sitting,
 * /national-sitting-import.
 */
?>
<div class="container-fluid py-4" id="nationalExamsPage">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h4 class="mb-0"><i class="bi bi-mortarboard me-2 text-success"></i>National Assessments</h4>
      <small class="text-muted" id="natCohort"></small>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none">
      <button class="btn btn-outline-secondary btn-sm" type="button" id="natTemplateBtn"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Template</button>
      <button class="btn btn-primary btn-sm" type="button" id="natUploadBtn" data-year-write data-permission="academic_manage"><i class="bi bi-upload me-1"></i>Upload results</button>
      <button class="btn btn-outline-success btn-sm" type="button" id="natExportBtn"><i class="bi bi-download me-1"></i>Export CSV</button>
      <button class="btn btn-outline-secondary btn-sm" type="button" id="natPrintBtn"><i class="bi bi-printer me-1"></i>Print</button>
    </div>
  </div>

  <div class="row g-2 align-items-end mb-3 d-print-none">
    <div class="col-md-4">
      <label class="form-label small mb-1" for="natSitting">Sitting</label>
      <select id="natSitting" class="form-select"><option value="">Loading…</option></select>
    </div>
    <div class="col-md-8 d-flex flex-wrap gap-2" id="natStats"></div>
  </div>

  <div id="natStatus" class="text-center text-muted py-4">Loading…</div>

  <div class="card mb-3 d-none" id="natResultsCard">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover align-middle mb-0" id="natResultsTable"></table>
      </div>
    </div>
  </div>

  <div class="card mb-3 d-none" id="natBandsCard">
    <div class="card-header"><strong>Levels per learning area</strong></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0" id="natBandsTable"></table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="natUploadModal" tabindex="-1" aria-labelledby="natUploadTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="natUploadTitle">Upload results</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="file" class="form-control mb-2" id="natFile" accept=".csv,text/csv">
        <div class="small text-muted mb-3">CSV with an admission or assessment number column and one column per learning area. Use the template.</div>
        <div id="natPreview"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="natSaveBtn" disabled data-year-write>Save results</button>
      </div>
    </div>
  </div>
</div>

<style>
  #nationalExamsPage th.nat-area { font-size: .78rem; min-width: 5rem; }
  @media print {
    @page { size: A4 landscape; margin: 10mm; }
    #nationalExamsPage .table { font-size: 9pt; }
  }
</style>

<?php asset_script($appBase, 'js/pages/national_exams.js'); ?>
