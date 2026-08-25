<?php
/** Dedicated academic/class attendance register for teaching staff. */
if (!isset($appBase)) { $appBase = ''; }
?>
<div class="container-fluid py-4" id="classMarkAttendancePage">
  <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div><h3 class="mb-1"><i class="bi bi-clipboard-check me-2"></i>Class Attendance</h3><p class="text-muted mb-0">Mark attendance for your assigned class streams.</p></div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= $appBase ?>/home.php?route=class_attendance_history"><i class="bi bi-clock-history me-1"></i>Attendance History</a>
  </div>
  <div class="card shadow-sm border-0 mb-3"><div class="card-body"><div class="row g-3 align-items-end">
    <div class="col-md-4"><label class="form-label fw-semibold">Class / Stream</label><select id="classAttendanceStream" class="form-select"><option value="">Select your class stream</option></select></div>
    <div class="col-md-3"><label class="form-label fw-semibold">Attendance Date</label><input id="classAttendanceDate" type="date" class="form-control" readonly><small class="text-muted">Teaching staff can mark today’s register only.</small></div>
    <div class="col-md-3"><label class="form-label fw-semibold">Session</label><select id="classAttendanceSession" class="form-select"><option value="">Loading applicable session…</option></select></div>
    <div class="col-md-2"><span class="small text-muted d-block pb-2"><i class="bi bi-arrow-down-circle me-1"></i>Learners load automatically</span></div>
  </div></div></div>
  <div id="classAttendanceMessage" class="alert d-none"></div>
  <div class="row g-3 mb-3"><div class="col-md-3"><div class="card border-primary"><div class="card-body"><small class="text-muted">Learners</small><h3 id="classTotal" class="mb-0">0</h3></div></div></div><div class="col-md-3"><div class="card border-success"><div class="card-body"><small class="text-muted">Present</small><h3 id="classPresent" class="mb-0">0</h3></div></div></div><div class="col-md-3"><div class="card border-danger"><div class="card-body"><small class="text-muted">Absent</small><h3 id="classAbsent" class="mb-0">0</h3></div></div></div><div class="col-md-3"><div class="card border-warning"><div class="card-body"><small class="text-muted">Pending</small><h3 id="classPending" class="mb-0">0</h3></div></div></div></div>
  <div class="card shadow-sm border-0"><div class="card-header d-flex justify-content-between align-items-center"><h5 class="mb-0">Learner Register</h5><button id="saveClassAttendance" class="btn btn-success btn-sm"><i class="bi bi-check2-circle me-1"></i>Save Attendance</button></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Admission No.</th><th>Learner</th><th>Attendance</th><th>Reason / Note</th></tr></thead><tbody id="classAttendanceBody"><tr><td colspan="4" class="text-center text-muted py-4">Select your class stream and date.</td></tr></tbody></table></div></div>
</div>
<?php asset_script($appBase, 'js/pages/class_mark_attendance.js'); ?>
