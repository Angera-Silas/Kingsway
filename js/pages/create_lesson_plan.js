/**
 * create_lesson_plan.js — Create Lesson Plan Controller (standalone page).
 * Teachers/interns draft a lesson plan and optionally submit for review.
 */
const createLessonPlanController = {
  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  notify(msg, type = 'success') {
    if (window.showNotification) { showNotification(msg, type); return; }
    const el = document.createElement('div');
    el.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow`;
    el.style.zIndex = 99999;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  },

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  async loadReference() {
    try {
      const [classesRes, areasRes, teachersRes] = await Promise.all([
        this.API('GET', 'academic/classes-list'),
        this.API('GET', 'academic/learning-areas/list'),
        this.API('GET', 'academic/teachers-list'),
      ]);
      const classes = classesRes?.data?.items || classesRes?.data || [];
      const areas = areasRes?.data?.items || areasRes?.data || [];
      const teachers = teachersRes?.data?.items || teachersRes?.data || [];
      document.getElementById('lpClass').innerHTML = '<option value="">Select Class</option>' + classes.map(c => `<option value="${c.id}">${this.esc(c.name || c.class_name || c.id)}</option>`).join('');
      document.getElementById('lpLearningArea').innerHTML = '<option value="">Select Subject</option>' + areas.map(a => `<option value="${a.id}">${this.esc(a.name || a.subject_name || a.id)}</option>`).join('');
      document.getElementById('lpTeacher').innerHTML = '<option value="">Select Teacher</option>' + teachers.map(t => `<option value="${t.id}">${this.esc(`${t.first_name || ''} ${t.last_name || ''}`.trim() || t.id)}</option>`).join('');
    } catch (e) { this.notify('Failed to load reference data', 'danger'); }
  },

  async loadUnits() {
    const areaId = document.getElementById('lpLearningArea').value;
    const unitSel = document.getElementById('lpUnit');
    if (!areaId) { unitSel.innerHTML = '<option value="">Select Unit</option>'; return; }
    unitSel.innerHTML = '<option value="">Loading…</option>';
    try {
      const r = await this.API('GET', 'academic/curriculum-units-list', null, { learning_area_id: areaId });
      const units = r?.data?.items || r?.data || [];
      unitSel.innerHTML = '<option value="">Select Unit</option>' + units.map(u => `<option value="${u.id}">${this.esc(u.name || u.title || u.id)}</option>`).join('');
    } catch (_) { unitSel.innerHTML = '<option value="">No units available</option>'; }
  },

  async save(status) {
    const payload = {
      topic:           document.getElementById('lpTopic').value.trim(),
      subtopic:        document.getElementById('lpSubtopic').value.trim(),
      learning_area_id: document.getElementById('lpLearningArea').value,
      unit_id:         document.getElementById('lpUnit').value,
      class_id:        document.getElementById('lpClass').value,
      teacher_id:      document.getElementById('lpTeacher').value,
      lesson_date:     document.getElementById('lpDate').value,
      duration:        parseInt(document.getElementById('lpDuration').value) || 40,
      objectives:      document.getElementById('lpObjectives').value.trim(),
      activities:      document.getElementById('lpActivities').value.trim(),
      resources:       document.getElementById('lpResources').value.trim(),
      assessment:      document.getElementById('lpAssessment').value.trim(),
      homework:        document.getElementById('lpHomework').value.trim(),
      status,
    };
    const required = ['topic', 'learning_area_id', 'unit_id', 'class_id', 'teacher_id', 'lesson_date', 'objectives', 'activities'];
    for (const f of required) {
      if (!payload[f]) {
        const labels = { topic: 'Topic', learning_area_id: 'Learning area', unit_id: 'Curriculum unit', class_id: 'Class', teacher_id: 'Teacher', lesson_date: 'Lesson date', objectives: 'Objectives', activities: 'Activities' };
        return this.notify(`${labels[f] || f} is required.`, 'warning');
      }
    }
    try {
      const r = await this.API('POST', 'academic/lesson-plans-create', payload);
      if (r.status === 'success') {
        this.notify(status === 'draft' ? 'Lesson plan saved as draft' : 'Lesson plan submitted for review');
        document.getElementById('lessonPlanForm').reset();
        document.getElementById('lpDuration').value = '40';
        document.getElementById('lpUnit').innerHTML = '<option value="">Select Unit</option>';
      } else this.notify(r.message || 'Save failed', 'danger');
    } catch (e) { this.notify(e.message || 'Save failed', 'danger'); }
  },

  setupEventListeners() {
    document.getElementById('lpLearningArea')?.addEventListener('change', () => this.loadUnits());
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await this.loadReference();
  },
};

window.createLessonPlanController = createLessonPlanController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => createLessonPlanController.init().catch(() => {}));
} else {
  createLessonPlanController.init().catch(() => {});
}
