<?php
/* Create Lesson Plan — standalone creation page for teachers/interns. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<style>
.clp-form label { font-size:.82rem;font-weight:600;color:#374151;margin-bottom:4px;display:block; }
.clp-form input,.clp-form select,.clp-form textarea { width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.88rem;outline:none;transition:border-color .15s; }
.clp-form input:focus,.clp-form select:focus,.clp-form textarea:focus { border-color:#198754;box-shadow:0 0 0 3px rgba(25,135,84,.1); }
</style>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-journal-plus text-success me-2"></i>Create Lesson Plan</h4>
      <p class="text-muted small mb-0 mt-1">Draft a lesson plan for one of your assigned learning areas, then submit for review.</p>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-body clp-form">
      <form id="lessonPlanForm">
        <div class="row g-3">
          <div class="col-md-6"><label>Topic *</label><input type="text" name="topic" id="lpTopic" required></div>
          <div class="col-md-6"><label>Subtopic</label><input type="text" name="subtopic" id="lpSubtopic"></div>
          <div class="col-md-4">
            <label>Subject (Learning Area) *</label>
            <select name="learning_area_id" id="lpLearningArea" required><option value="">Select Subject</option></select>
          </div>
          <div class="col-md-4">
            <label>Curriculum Unit *</label>
            <select name="unit_id" id="lpUnit" required><option value="">Select Unit</option></select>
          </div>
          <div class="col-md-4">
            <label>Class *</label>
            <select name="class_id" id="lpClass" required><option value="">Select Class</option></select>
          </div>
          <div class="col-md-4">
            <label>Teacher *</label>
            <select name="teacher_id" id="lpTeacher" required><option value="">Select Teacher</option></select>
          </div>
          <div class="col-md-4"><label>Lesson Date *</label><input type="date" name="lesson_date" id="lpDate" required></div>
          <div class="col-md-4"><label>Duration (minutes) *</label><input type="number" name="duration" id="lpDuration" value="40" min="1" required></div>
          <div class="col-12"><label>Objectives *</label><textarea name="objectives" id="lpObjectives" rows="2" required></textarea></div>
          <div class="col-12"><label>Activities *</label><textarea name="activities" id="lpActivities" rows="3" required></textarea></div>
          <div class="col-md-6"><label>Resources / Teaching Aids</label><textarea name="resources" id="lpResources" rows="2"></textarea></div>
          <div class="col-md-6"><label>Assessment</label><textarea name="assessment" id="lpAssessment" rows="2"></textarea></div>
          <div class="col-12"><label>Homework</label><textarea name="homework" id="lpHomework" rows="2"></textarea></div>
        </div>
        <div class="mt-4 d-flex gap-2">
          <button type="button" class="btn btn-outline-primary" onclick="createLessonPlanController.save('draft')"><i class="bi bi-save me-1"></i>Save as Draft</button>
          <button type="button" class="btn btn-success" onclick="createLessonPlanController.save('submitted')"><i class="bi bi-send me-1"></i>Create &amp; Submit for Review</button>
        </div>
      </form>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/create_lesson_plan.js'); ?>
