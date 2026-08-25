/**
 * create_lesson_plan.js — Create Lesson Plan Controller (standalone page).
 * Teachers/interns draft a lesson plan and optionally submit for review.
 */
const createLessonPlanController = {
  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),
  state: { teacherScope: null, streams: [], allowedStreams: [], allowedAreas: new Set(), schemes: [], selectedScheme: null, lessonContext: null, currentStaffId: null },

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
      const scope = await window.AuthContext?.getTeacherScope?.();
      this.state.teacherScope = scope || null;
      this.state.currentStaffId = scope?.staff_id || null;
      const yearId = window.AcademicContext?.getAcademicYearId?.();
      const streamsRes = await window.API.schedules.listTimetableStreams({ academic_year_id: yearId });
      this.state.streams = streamsRes?.data || streamsRes || [];
      const classIds = new Set((scope?.class_stream_ids || []).map(Number));
      const subjectPairs = new Set((scope?.subject_assignments || []).map((x) => `${x.stream_id}:${x.learning_area_id}`));
      this.state.allowedStreams = this.state.streams.filter((stream) => {
        const streamId = Number(stream.academic_year_class_stream_id);
        return classIds.has(streamId) || this.state.streams.some((s) => Number(s.academic_year_class_stream_id) === streamId && (s.learning_areas || []).some((a) => subjectPairs.has(`${streamId}:${a.id}`)));
      });
      this.state.allowedAreas = new Set();
      this.state.allowedStreams.forEach((stream) => (stream.learning_areas || []).forEach((area) => {
        const isClass = classIds.has(Number(stream.academic_year_class_stream_id));
        if (isClass || subjectPairs.has(`${stream.academic_year_class_stream_id}:${area.id}`)) this.state.allowedAreas.add(Number(area.id));
      }));
      const [classesRes, areasRes, teachersRes, schemesRes] = await Promise.all([
        this.API('GET', 'academic/classes-list'),
        this.API('GET', 'academic/learning-areas/list'),
        this.API('GET', 'academic/teachers-list'),
        this.API('GET', 'academic/scheme-of-work-get', null, { status: 'approved', teacher_id: this.state.currentStaffId }),
      ]);
      const classes = classesRes?.data?.items || classesRes?.data || [];
      const areas = areasRes?.data?.items || areasRes?.data || [];
      const teachers = teachersRes?.data?.items || teachersRes?.data || [];
      this.state.schemes = schemesRes?.data?.schemes || schemesRes?.schemes || schemesRes?.data || [];
      const schemeSelect = document.getElementById('lpScheme');
      if (schemeSelect) {
        schemeSelect.innerHTML = '<option value="">Select approved week / scheme row</option>' + this.state.schemes.map(s => `<option value="${s.id}" data-start="${s.week_start || ''}" data-end="${s.week_end || ''}">${this.esc(`Week ${s.week_number || '-'} · ${s.class_name || ''} ${s.stream_name || ''} · ${s.learning_area_name || s.subject_name || ''} · ${s.title || ''}`)}</option>`).join('');
        schemeSelect.addEventListener('change', () => this.selectScheme(schemeSelect.value));
      }
      const streamSelect = document.getElementById('lpStream');
      if (streamSelect && this.state.allowedStreams.length) {
        const renderStreams = () => {
          const areaId = Number(document.getElementById('lpLearningArea')?.value || 0);
          const allowed = this.state.allowedStreams.filter((stream) => {
            if (!areaId) return true;
            const streamId = Number(stream.academic_year_class_stream_id);
            const isClass = classIds.has(streamId);
            return isClass || (stream.learning_areas || []).some((area) => Number(area.id) === areaId && subjectPairs.has(`${streamId}:${areaId}`));
          });
          streamSelect.innerHTML = '<option value="">Select Class / Stream</option>' + allowed.map(s => `<option value="${s.academic_year_class_stream_id}" data-class-id="${s.class_id}">${this.esc(`${s.class_name || ''} - ${s.stream_name || ''}`)}</option>`).join('');
          document.getElementById('lpClass').value = '';
        };
        renderStreams();
        streamSelect.addEventListener('change', () => { document.getElementById('lpClass').value = streamSelect.selectedOptions[0]?.dataset.classId || ''; });
        document.getElementById('lpLearningArea')?.addEventListener('change', renderStreams);
        document.getElementById('lpClassField')?.classList.add('teacher-scoped-reference');
        document.getElementById('lpTeacherField')?.classList.add('d-none');
        document.getElementById('lpTeacher').value = this.state.currentStaffId || '';
      } else {
        streamSelect?.removeAttribute('required');
        document.getElementById('lpStream')?.classList.add('d-none');
        document.getElementById('lpClass').outerHTML = '<select name="class_id" id="lpClass" required><option value="">Select Class</option></select>';
        document.getElementById('lpClass').innerHTML = '<option value="">Select Class</option>' + classes.map(c => `<option value="${c.id}">${this.esc(c.name || c.class_name || c.id)}</option>`).join('');
      }
      const permittedAreas = this.state.allowedAreas.size ? areas.filter(a => this.state.allowedAreas.has(Number(a.id))) : areas;
      document.getElementById('lpLearningArea').innerHTML = '<option value="">Select Subject</option>' + permittedAreas.map(a => `<option value="${a.id}">${this.esc(a.name || a.subject_name || a.id)}</option>`).join('');
      document.getElementById('lpTeacher').innerHTML = '<option value="">Select Teacher</option>' + teachers.map(t => `<option value="${t.id}">${this.esc(`${t.first_name || ''} ${t.last_name || ''}`.trim() || t.id)}</option>`).join('');
      if (this.state.currentStaffId) document.getElementById('lpTeacher').value = this.state.currentStaffId;
    } catch (e) { this.notify('Failed to load reference data', 'danger'); }
  },

  async selectScheme(id) {
    const scheme = this.state.schemes.find(s => String(s.id) === String(id));
    this.state.selectedScheme = scheme || null;
    if (!scheme) return;
    const set = (id, value) => { const el = document.getElementById(id); if (el) el.value = value == null ? '' : value; };
    set('lpTopic', scheme.title || '');
    set('lpLearningArea', scheme.learning_area_id || scheme.subject_id);
    set('lpClass', scheme.class_id);
    set('lpStream', scheme.academic_year_class_stream_id);
    set('lpUnit', scheme.sub_strand_id || scheme.strand_id || '');
    const date = document.getElementById('lpDate');
    if (date) { date.min = scheme.week_start || ''; date.max = scheme.week_end || ''; date.value = ''; }
    const hint = document.getElementById('lpSchemeHint');
    if (hint) hint.textContent = `Week ${scheme.week_number || ''}: ${scheme.week_start || ''} to ${scheme.week_end || ''}. ${scheme.learning_area_name || scheme.subject_name || ''} · ${scheme.title || ''}`;
    this.state.lessonContext = null;
    try {
      const response = await window.API.academic.getLessonPlanningContext(id);
      this.state.lessonContext = response?.data || response || {};
      this.renderAtomicChoices(this.state.lessonContext.choices || {});
    } catch (e) { this.notify(e.message || 'Unable to load the approved scheme choices', 'danger'); }
  },

  renderAtomicChoices(choices) {
    const checkList = (id, name, rows, label) => {
      const el = document.getElementById(id); if (!el) return;
      el.innerHTML = (rows || []).length ? rows.map((r, i) => `<label class="d-block small mb-1"><input type="checkbox" class="me-2" name="${name}" value="${this.esc(r.id || '')}" data-text="${this.esc(r.text || r.name || '')}"> ${this.esc(r.text || r.name || '')}</label>`).join('') : '<span class="text-muted small">No configured items.</span>';
    };
    checkList('lpOutcomes', 'lpOutcome', choices.outcomes, 'Outcome');
    checkList('lpExperiences', 'lpExperience', choices.experiences, 'Experience');
    checkList('lpAssessmentTools', 'lpAssessmentTool', choices.assessment_tools);
    checkList('lpAssessmentRubrics', 'lpAssessmentRubric', choices.assessment_rubrics, 'Assessment rubric');
    checkList('lpCompetencies', 'lpCompetency', choices.competencies);
    checkList('lpRubrics', 'lpRubric', choices.rubrics);
    checkList('lpQuestions', 'lpQuestion', choices.inquiry_questions);
    const resources = document.getElementById('lpResources');
    if (resources) resources.innerHTML = (choices.resources || []).length ? choices.resources.map(r => `<label class="d-block small mb-1"><input type="checkbox" class="me-2" name="lpResource" value="${this.esc(r.id)}" data-name="${this.esc(r.name)}" data-type="${this.esc(r.type || '')}" data-url="${this.esc(r.url || '')}"> ${this.esc(r.name)}${r.type ? ` <span class="text-muted">(${this.esc(r.type)})</span>` : ''}</label>`).join('') : '<span class="text-muted small">No configured resources. Add one below.</span>';
    const date = document.getElementById('lpDate');
    if (date && (choices.calendar_days || []).length) { date.min = choices.calendar_days[0].date; date.max = choices.calendar_days[choices.calendar_days.length - 1].date; }
  },

  addAtomicRow(containerId, className, placeholder) {
    const container = document.getElementById(containerId); if (!container) return;
    const row = document.createElement('div'); row.className = 'd-flex gap-2 mb-2';
    row.innerHTML = `<input class="form-control form-control-sm ${className}" placeholder="${placeholder}"><button type="button" class="btn btn-sm btn-outline-danger" aria-label="Remove"><i class="bi bi-x"></i></button>`;
    row.querySelector('button').onclick = () => row.remove(); container.appendChild(row);
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
      stream_id:       document.getElementById('lpStream')?.value || '',
      teacher_id:      document.getElementById('lpTeacher').value,
      lesson_date:     document.getElementById('lpDate').value,
      duration:        parseInt(document.getElementById('lpDuration').value) || 40,
      learning_outcome_ids: [...document.querySelectorAll('input[name="lpOutcome"]:checked')].map(x => Number(x.value)),
      experiences: [...document.querySelectorAll('input[name="lpExperience"]:checked')].map(x => ({ id: Number(x.value), text: x.dataset.text })),
      activities_items: [...document.querySelectorAll('#lpActivities .lp-activity')].map(x => ({ text: x.value.trim() })).filter(x => x.text),
      resources_items: [...document.querySelectorAll('input[name="lpResource"]:checked')].map(x => ({ id: Number(x.value), name: x.dataset.name, type: x.dataset.type, url: x.dataset.url })).concat([...document.querySelectorAll('#lpResources .lp-resource')].map(x => ({ name: x.value.trim(), is_custom: true })).filter(x => x.name)),
      assessment_tool_ids: [...document.querySelectorAll('input[name="lpAssessmentTool"]:checked')].map(x => Number(x.value)),
      assessment_rubric_ids: [...document.querySelectorAll('input[name="lpAssessmentRubric"]:checked')].map(x => Number(x.value)),
      competency_ids: [...document.querySelectorAll('input[name="lpCompetency"]:checked')].map(x => Number(x.value)),
      rubric_ids: [...document.querySelectorAll('input[name="lpRubric"]:checked')].map(x => Number(x.value)),
      inquiry_questions: [...document.querySelectorAll('input[name="lpQuestion"]:checked')].map(x => ({ id: Number(x.value), text: x.dataset.text })),
      coverage_items: [...document.querySelectorAll('#lpCoverage .lp-coverage')].map(x => ({ text: x.value.trim() })).filter(x => x.text),
      status,
      scheme_of_work_id: document.getElementById('lpScheme').value,
    };
    const required = ['scheme_of_work_id', 'topic', 'learning_area_id', 'class_id', 'teacher_id', 'lesson_date'];
    for (const f of required) {
      if (!payload[f] || (f === 'lesson_date' && !payload[f])) {
        const labels = { scheme_of_work_id: 'Approved scheme row', topic: 'Lesson focus', learning_area_id: 'Learning area', class_id: 'Class', teacher_id: 'Teacher', lesson_date: 'Lesson date', objectives: 'Objectives', activities: 'Activities' };
        return this.notify(`${labels[f] || f} is required.`, 'warning');
      }
    }
    if (!payload.learning_outcome_ids.length) return this.notify('Select at least one specific learning outcome.', 'warning');
    if (!payload.experiences.length) return this.notify('Select at least one learning experience.', 'warning');
    if (!payload.activities_items.length) return this.notify('Add at least one lesson activity.', 'warning');
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
    document.getElementById('lpAddActivity')?.addEventListener('click', () => this.addAtomicRow('lpActivities', 'lp-activity', 'What will learners do?'));
    document.getElementById('lpAddResource')?.addEventListener('click', () => this.addAtomicRow('lpResources', 'lp-resource', 'Resource / teaching aid'));
    document.getElementById('lpAddCoverage')?.addEventListener('click', () => this.addAtomicRow('lpCoverage', 'lp-coverage', 'Expected coverage item'));
    document.getElementById('lpDate')?.addEventListener('change', (event) => {
      const s = this.state.selectedScheme;
      if (s && (event.target.value < s.week_start || event.target.value > s.week_end)) {
        event.target.value = '';
        this.notify('The lesson date must be inside the selected scheme week.', 'warning');
      }
    });
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
