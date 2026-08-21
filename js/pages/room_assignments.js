/**
 * room_assignments.js — Room Assignments Controller (standalone page).
 * Dormitory occupancy cards and student bed assignments.
 */
const roomAssignmentsController = {
  state: { dorms: [], students: [] },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.dormitories || r?.students || r?.occupancy || r || []); },

  async load() {
    const body = document.getElementById('raBody');
    document.getElementById('raDorms').innerHTML = '';
    body.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const [dormsRes, studentsRes] = await Promise.all([
        this.API('GET', '/boarding/dormitories').catch(() => []),
        this.API('GET', '/boarding/students').catch(() => []),
      ]);
      this.state.dorms = this.norm(dormsRes);
      this.state.students = this.norm(studentsRes);
      this.renderDorms();
      this.renderStudents();
    } catch (e) { body.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`; }
  },

  renderDorms() {
    const container = document.getElementById('raDorms');
    if (!this.state.dorms.length) { container.innerHTML = '<div class="col-12 text-muted text-center py-4">No dormitories found.</div>'; return; }
    container.innerHTML = this.state.dorms.map(d => {
      const capacity = Number(d.capacity ?? 0);
      const occupied = Number(d.occupied ?? d.current_occupancy ?? 0);
      const available = Math.max(0, capacity - occupied);
      const pct = capacity ? Math.round((occupied / capacity) * 100) : 0;
      return `<div class="col-xl-3 col-md-6"><div class="border rounded-3 p-3 bg-white h-100">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <h6 class="fw-semibold mb-0">${this.esc(d.name || '\u2014')}</h6>
          <span class="badge ${pct >= 90 ? 'bg-danger' : pct >= 70 ? 'bg-warning text-dark' : 'bg-success'}">${this.esc(pct)}%</span>
        </div>
        <div class="small text-muted">${this.esc(d.gender || '')} &middot; ${this.esc(d.patron_name || d.location || '')}</div>
        <div class="progress my-2" style="height: 8px;"><div class="progress-bar" style="width:${this.esc(pct)}%"></div></div>
        <div class="d-flex justify-content-between small"><span class="text-muted">Occupied: <strong>${this.esc(occupied)}</strong></span><span class="text-muted">Available: <strong>${this.esc(available)}</strong></span></div>
        <div class="small text-muted">Capacity: ${this.esc(capacity)}</div>
      </div></div>`;
    }).join('');
  },

  renderStudents() {
    const body = document.getElementById('raBody');
    const list = this.state.students;
    if (!list.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No assigned boarders found.</td></tr>'; return; }
    body.innerHTML = list.map(s => `<tr>
      <td class="small">${this.esc(s.admission_no || '\u2014')}</td>
      <td class="small fw-semibold">${this.esc(s.student_name || '\u2014')}</td>
      <td class="small text-muted">${this.esc(s.gender || '\u2014')}</td>
      <td class="small">${this.esc(s.class_name || '\u2014')}</td>
      <td class="small">${this.esc(s.dormitory_name || '\u2014')}</td>
      <td class="small fw-semibold">${this.esc(s.bed_number || '\u2014')}</td>
    </tr>`).join('');
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.roomAssignmentsController = roomAssignmentsController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => roomAssignmentsController.init().catch(() => {}));
} else {
  roomAssignmentsController.init().catch(() => {});
}
