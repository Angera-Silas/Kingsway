<?php
/* Student Fees Bill — generate fee invoices/bills for students or classes. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-text text-success me-2"></i>Generate Bills</h4>
      <p class="text-muted small mb-0 mt-1">Generate fee invoices for a whole class or a single student.</p>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-5">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h6 class="fw-bold mb-3">Batch Generation</h6>
          <div class="mb-3"><label class="form-label small fw-semibold">Academic Year *</label><select id="sffYear" class="form-select form-select-sm"></select></div>
          <div class="mb-3"><label class="form-label small fw-semibold">Term *</label><select id="sffTerm" class="form-select form-select-sm"></select></div>
          <div class="mb-3"><label class="form-label small fw-semibold">Class (optional)</label><select id="sffClass" class="form-select form-select-sm"><option value="">All classes</option></select></div>
          <button class="btn btn-success w-100" onclick="studentFeesBillController.generateBatch()"><i class="bi bi-file-earmark-arrow-up me-1"></i>Generate Bills for Class</button>

          <hr>
          <h6 class="fw-bold mb-3">Single Student Bill</h6>
          <div class="mb-3"><label class="form-label small fw-semibold">Student</label><select id="sffStudent" class="form-select form-select-sm"><option value="">— Select student —</option></select></div>
          <button class="btn btn-outline-success w-100" onclick="studentFeesBillController.generateSingle()"><i class="bi bi-file-earmark-plus me-1"></i>Generate Student Bill</button>
        </div>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="bg-white border rounded-3 p-3" id="sffResult">
        <p class="text-muted small mb-0">Generate a bill to see the result here.</p>
      </div>
    </div>
  </div>

</div>

<script src="<?= $appBase ?>/js/pages/student_fees_bill.js?v=<?= filemtime(APP_BASE_PATH . "/js/pages/student_fees_bill.js") ?>"></script>
