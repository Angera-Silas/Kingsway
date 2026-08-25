const ClassMarkAttendanceController = {
  students: [],
  classes: [],
  async init() {
    await window.AuthContext?.ready?.();
    if (!window.AuthContext?.isAuthenticated()) return;
    const date = document.getElementById('classAttendanceDate');
    if (date) date.value = new Date().toISOString().slice(0, 10);
    document.getElementById('classAttendanceStream')?.addEventListener('change', () => { this.applySessionPolicy(); this.loadStudents(); });
    document.getElementById('classAttendanceDate')?.addEventListener('change', () => this.loadSessions());
    document.getElementById('saveClassAttendance')?.addEventListener('click', () => this.save());
    await this.loadClasses();
    await this.loadSessions();
  },
  unwrap(response) {
    const value = response?.data?.data || response?.data || response || [];
    if (Array.isArray(value)) return value;
    return value?.classes || value?.streams || value?.students || value?.rows || [];
  },
  async loadSessions() {
    try {
      const date = document.getElementById('classAttendanceDate')?.value;
      const response = await window.API.apiCall('/attendance/sessions', 'GET', null, { type: 'academic', day: new Date(`${date}T00:00:00`).toLocaleDateString('en-US', { weekday: 'long' }) });
      const rows = this.unwrap(response);
      const select = document.getElementById('classAttendanceSession');
      select.innerHTML = rows.length ? rows.map(s => `<option value="${this.esc(s.id)}">${this.esc(s.name || s.session_name || 'Class session')}</option>`).join('') : '<option value="">No applicable session</option>';
      this.stateSessions = rows;
      this.applySessionPolicy();
    } catch (e) { this.message(e.message || 'Unable to load applicable attendance sessions.', 'danger'); }
  },
  async loadClasses() {
    try {
      let data = this.unwrap(await window.API.apiCall('/attendance/classes', 'GET'));
      // Use the canonical teacher-class learner scope as a fallback. This
      // keeps the register aligned with My Students when the legacy
      // attendance class-list permission/context returns no rows.
      if (!Array.isArray(data) || !data.length) {
        const scopedResponse = await window.API.apiCall('/students/context-list', 'GET', null, { context: 'teacher_class' });
        const scopedPayload = scopedResponse?.data?.data || scopedResponse?.data || scopedResponse || {};
        const learners = scopedPayload?.students || [];
        data = [...new Map(learners.filter(s => s.academic_year_class_stream_id).map(s => [String(s.academic_year_class_stream_id), {
          stream_id: s.academic_year_class_stream_id,
          display_name: `${s.class_name || 'Class'}${s.stream_name ? ' - ' + s.stream_name : ''}`,
          student_count: 0
        }])).values()];
        data.forEach(c => { c.student_count = learners.filter(s => String(s.academic_year_class_stream_id) === String(c.stream_id)).length; });
      }
      const select = document.getElementById('classAttendanceStream');
      this.classes = data;
      (Array.isArray(data) ? data : []).forEach(c => { const o = document.createElement('option'); o.value = c.stream_id; o.textContent = `${c.display_name || c.name} (${c.student_count || 0})`; select.appendChild(o); });
      if (data.length === 1) { select.value = data[0].stream_id; await this.loadStudents(); }
    } catch (e) { this.message(e.message || 'Unable to load your class streams.', 'danger'); }
  },
  applySessionPolicy() {
    const select = document.getElementById('classAttendanceStream');
    const session = document.getElementById('classAttendanceSession');
    const wrapper = session?.closest('.col-md-3');
    const selected = this.classes.find(c => String(c.stream_id) === String(select?.value));
    const label = String(selected?.name || selected?.display_name || '').toLowerCase();
    const wholeDay = /playgroup|pp1|pp2|pre.?primary|grade\s*[1-3]\b/.test(label);
    if (wrapper) wrapper.classList.toggle('d-none', wholeDay);
    if (wholeDay && session) {
      const morning = (this.stateSessions || []).find(s => /morning/i.test(String(s.name || s.session_name || '')));
      if (morning) session.value = String(morning.id);
    }
  },
  async loadStudents() {
    const stream = document.getElementById('classAttendanceStream')?.value;
    const date = document.getElementById('classAttendanceDate')?.value;
    if (!stream || !date) return this.message('Select a class stream and date.', 'warning');
    try {
      const data = this.unwrap(await window.API.apiCall(`/attendance/students-by-class/${encodeURIComponent(stream)}?date=${encodeURIComponent(date)}`, 'GET'));
      this.students = Array.isArray(data) ? data : [];
      this.render();
    } catch (e) { this.message(e.message || 'Unable to load your learners.', 'danger'); }
  },
  render() {
    const body = document.getElementById('classAttendanceBody');
    body.innerHTML = this.students.length ? this.students.map(s => `<tr data-id="${s.id}"><td>${this.esc(s.admission_no)}</td><td><strong>${this.esc([s.first_name, s.last_name].filter(Boolean).join(' '))}</strong></td><td><select class="form-select form-select-sm att-status"><option value="present" ${(s.attendance_status || s.stored_status) === 'present' ? 'selected' : ''}>Present</option><option value="absent" ${(s.attendance_status || s.stored_status) === 'absent' ? 'selected' : ''}>Absent</option><option value="late" ${(s.attendance_status || s.stored_status) === 'late' ? 'selected' : ''}>Late</option></select></td><td><input class="form-control form-control-sm att-note" placeholder="Optional note"></td></tr>`).join('') : '<tr><td colspan="4" class="text-center text-muted py-4">No learners are assigned to this stream.</td></tr>';
    this.updateCounts();
    body.querySelectorAll('.att-status').forEach(e => e.addEventListener('change', () => this.updateCounts()));
  },
  updateCounts() { const statuses = [...document.querySelectorAll('.att-status')].map(e => e.value); document.getElementById('classTotal').textContent = statuses.length; document.getElementById('classPresent').textContent = statuses.filter(s => s === 'present').length; document.getElementById('classAbsent').textContent = statuses.filter(s => s === 'absent').length; document.getElementById('classPending').textContent = 0; },
  async save() {
    const stream_id = document.getElementById('classAttendanceStream')?.value; const date = document.getElementById('classAttendanceDate')?.value; const session_id = document.getElementById('classAttendanceSession')?.value || null;
    const attendance = [...document.querySelectorAll('#classAttendanceBody tr[data-id]')].map(row => ({ student_id: Number(row.dataset.id), status: row.querySelector('.att-status').value, note: row.querySelector('.att-note').value }));
    if (!stream_id || !session_id || !attendance.length) return this.message('Select an applicable attendance session before saving.', 'warning');
    try { await window.API.apiCall('/attendance/mark-bulk', 'POST', { stream_id, date, session_id, register_type: 'class', attendance }); this.message('Class attendance saved successfully.', 'success'); await this.loadStudents(); } catch (e) { this.message(e.message || 'Attendance could not be saved.', 'danger'); }
  },
  message(text, type) { const el = document.getElementById('classAttendanceMessage'); el.textContent = text; el.className = `alert alert-${type}`; },
  esc(value) { const d = document.createElement('div'); d.textContent = value == null ? '' : String(value); return d.innerHTML; }
};
document.addEventListener('DOMContentLoaded', () => ClassMarkAttendanceController.init());
