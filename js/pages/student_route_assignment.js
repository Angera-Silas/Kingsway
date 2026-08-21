/**
 * student_route_assignment.js — Student Route Assignment Controller (standalone page).
 * Assign/withdraw students to transport routes and stops.
 */
const routeAssignController = {
  state: { routes: [], stops: [], assignments: [] },

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

  fillMonths() {
    const now = new Date();
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    ['assignMonth'].forEach(id => {
      const sel = document.getElementById(id);
      sel.innerHTML = months.map((m, i) => `<option value="${i + 1}">${m}</option>`).join('');
      sel.value = String(now.getMonth() + 1);
    });
    ['assignYear'].forEach(id => {
      const sel = document.getElementById(id);
      sel.innerHTML = '';
      for (let y = now.getFullYear(); y >= now.getFullYear() - 1; y--) {
        sel.innerHTML += `<option value="${y}">${y}</option>`;
      }
      sel.value = String(now.getFullYear());
    });
  },

  async loadRoutes() {
    try {
      const r = await this.API('GET', 'transport/all-routes');
      this.state.routes = r?.data || r?.items || [];
      const opts = this.state.routes.map(x => `<option value="${x.id}">${this.esc(x.name || x.route_name || x.id)}</option>`).join('');
      document.getElementById('assignRoute').innerHTML = opts;
      document.getElementById('viewRoute').innerHTML = opts;
    } catch (_) {}
  },

  async loadStops() {
    try {
      const r = await this.API('GET', 'transport/all-stops');
      this.state.stops = r?.data || r?.items || [];
      document.getElementById('assignStop').innerHTML = '<option value="">— Select stop —</option>' +
        this.state.stops.map(s => `<option value="${s.id}">${this.esc(s.name || s.id)}</option>`).join('');
    } catch (_) {}
  },

  async loadRouteStudents() {
    const body = document.getElementById('routeStudentsBody');
    const routeId = document.getElementById('viewRoute').value;
    body.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    if (!routeId) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">Select a route to view its students.</td></tr>'; return; }
    try {
      const r = await this.API('GET', 'transport/students-by-route', null, { route_id: routeId });
      this.state.assignments = r?.data || [];
      this.renderAssignments();
    } catch (e) {
      body.innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  renderAssignments() {
    const body = document.getElementById('routeStudentsBody');
    if (!this.state.assignments.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No students assigned to this route.</td></tr>'; return; }
    body.innerHTML = this.state.assignments.map(a => `
      <tr>
        <td class="small"><span class="badge bg-light text-dark border">${this.esc(a.admission_no || '\u2014')}</span></td>
        <td class="fw-semibold small">${this.esc(`${a.first_name || ''} ${a.last_name || ''}`.trim() || '\u2014')}</td>
        <td class="small text-muted">${this.esc(a.stop_name || '\u2014')}</td>
        <td class="small text-muted">${this.esc(`${a.month || ''}/${a.year || ''}`.trim() || '\u2014')}</td>
        <td>${a.status === 'active' ? '<span class="badge bg-success">Active</span>' : `<span class="badge bg-secondary">${this.esc(a.status || '\u2014')}</span>`}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-danger rounded-pill px-2" onclick="routeAssignController.withdraw(${a.student_id})" title="Withdraw from route"><i class="bi bi-x-circle"></i> Withdraw</button>
        </td>
      </tr>`).join('');
  },

  async verifyStudent() {
    const q = document.getElementById('assignQuery').value.trim();
    const el = document.getElementById('verifiedStudent');
    el.textContent = '';
    if (!q) return;
    try {
      const r = await this.API('POST', 'transport/verify-student', { admission_no: q });
      const s = r?.data || r;
      if (s && s.id) {
        el.textContent = `Found: ${s.first_name || ''} ${s.last_name || ''} (${s.admission_no || ''})`;
        el.dataset.studentId = s.id;
      } else {
        el.textContent = 'No matching student found.';
        el.classList.add('text-danger'); el.classList.remove('text-success');
        el.dataset.studentId = '';
      }
    } catch (e) {
      el.textContent = e.message || 'Verification failed';
      el.classList.add('text-danger'); el.classList.remove('text-success');
      el.dataset.studentId = '';
    }
  },

  async assign() {
    const el = document.getElementById('verifiedStudent');
    const studentId = el.dataset?.studentId;
    const routeId = document.getElementById('assignRoute').value;
    if (!studentId) return this.notify('Verify a student first.', 'warning');
    if (!routeId) return this.notify('Select a route.', 'warning');
    const stopId  = document.getElementById('assignStop').value || null;
    const month   = document.getElementById('assignMonth').value;
    const year    = document.getElementById('assignYear').value;
    try {
      const r = await this.API('POST', 'transport/assign-student', { student_id: studentId, route_id: routeId, stop_id: stopId, month, year });
      if (r.status === 'success') {
        this.notify('Student assigned to route');
        el.textContent = ''; el.dataset.studentId = '';
        document.getElementById('assignQuery').value = '';
        this.loadRouteStudents();
      } else this.notify(r.message, 'warning');
    } catch (e) { this.notify(e.message, 'danger'); }
  },

  async withdraw(studentId) {
    if (!(await window.confirmAction('Confirm Withdrawal', 'Withdraw this student from the route?'))) return;
    const month = document.getElementById('assignMonth').value;
    const year  = document.getElementById('assignYear').value;
    try {
      const r = await this.API('POST', 'transport/withdraw-assignment', { student_id: studentId, month, year });
      if (r.status === 'success') { this.notify('Assignment withdrawn'); this.loadRouteStudents(); }
      else this.notify(r.message, 'warning');
    } catch (e) { this.notify(e.message, 'danger'); }
  },

  setupEventListeners() {
    document.getElementById('assignRoute')?.addEventListener('change', () => this.loadStops());
    document.getElementById('viewRoute')?.addEventListener('change', () => this.loadRouteStudents());
    const q = document.getElementById('assignQuery');
    if (q) q.addEventListener('change', () => this.verifyStudent());
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.fillMonths();
    await this.loadRoutes();
    this.setupEventListeners();
    await this.loadStops();
    this.loadRouteStudents();
  },
};

window.routeAssignController = routeAssignController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => routeAssignController.init().catch(() => {}));
} else {
  routeAssignController.init().catch(() => {});
}
