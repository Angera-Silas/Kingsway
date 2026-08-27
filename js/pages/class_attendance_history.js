const ClassAttendanceHistoryController = {
  async init() {
    await window.AuthContext?.ready?.();
    if (!window.AuthContext?.isAuthenticated()) return;

    const date = document.getElementById('historyDate');
    date.value = this.localDate();
    document.getElementById('historyStream').addEventListener('change', async () => {
      await this.loadSessions();
      await this.load();
    });
    document.getElementById('historySession').addEventListener('change', () => this.load());
    date.addEventListener('change', async () => {
      await this.loadSessions();
      await this.load();
    });

    await this.classes();
  },

  unwrap(response) {
    const value = response?.data?.data || response?.data || response || [];
    return Array.isArray(value) ? value : (value.classes || value.rows || []);
  },

  async classes() {
    try {
      const rows = this.unwrap(await window.API.apiCall('/attendance/classes', 'GET'));
      const select = document.getElementById('historyStream');
      rows.forEach(row => {
        const option = document.createElement('option');
        option.value = row.stream_id;
        option.textContent = `${row.display_name || row.name} (${row.student_count || 0})`;
        select.appendChild(option);
      });
      if (rows.length === 1) {
        select.value = rows[0].stream_id;
        await this.loadSessions();
        await this.load();
      }
    } catch (error) {
      this.message(error.message || 'Unable to load your class streams.', 'danger');
    }
  },

  async loadSessions() {
    try {
      const date = document.getElementById('historyDate').value;
      const stream = document.getElementById('historyStream').value;
      const field = document.getElementById('historySessionField');
      const select = document.getElementById('historySession');
      if (!stream) {
        field.classList.add('d-none');
        select.replaceChildren(new Option('Select your class first', ''));
        return;
      }
      const day = new Date(`${date}T00:00:00`).toLocaleDateString('en-US', { weekday: 'long' });
      const rows = this.unwrap(await window.API.apiCall('/attendance/sessions', 'GET', null, { type: 'academic', date, day, stream_id: stream }));
      select.replaceChildren();
      if (!rows.length) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'No applicable session';
        select.appendChild(option);
        field.classList.add('d-none');
        return;
      }
      rows.forEach(row => {
        const option = document.createElement('option');
        option.value = row.id;
        option.textContent = row.name || row.session_name || 'Class session';
        select.appendChild(option);
      });
      field.classList.toggle('d-none', rows.length <= 1);
    } catch (error) {
      this.message(error.message || 'Unable to load attendance sessions.', 'danger');
    }
  },

  async load() {
    const stream = document.getElementById('historyStream').value;
    const date = document.getElementById('historyDate').value;
    const session = document.getElementById('historySession').value;
    if (!stream || !date) return this.renderMessageRow('Select your class stream and date.');

    try {
      const params = { stream_id: stream, date };
      if (session) params.session_id = session;
      const response = await window.API.attendance.getDailyRegister(params);
      const rows = Array.isArray(response) ? response : (response?.rows || []);
      this.render(rows);
    } catch (error) {
      this.message(error.message || 'Unable to load attendance history.', 'danger');
    }
  },

  render(rows) {
    const body = document.getElementById('historyBody');
    if (!rows.length) {
      this.renderMessageRow('No learners were enrolled in this class stream on this date.');
      return;
    }
    body.innerHTML = rows.map(row => {
      const status = row.status || 'not_marked';
      const style = {
        present: 'success',
        absent: 'danger',
        late: 'warning',
        permission: 'info',
        not_marked: 'secondary',
      }[status] || 'secondary';
      const label = status === 'not_marked' ? 'Not marked' : status.replace(/_/g, ' ');
      return `<tr><td>${this.esc(row.admission_no)}</td><td>${this.esc([row.first_name, row.last_name].filter(Boolean).join(' '))}</td><td><span class="badge text-bg-${style}">${this.esc(label)}</span></td><td>${this.esc(this.markedAt(row.marked_at))}</td><td>${this.esc(row.notes || '—')}</td></tr>`;
    }).join('');
  },

  renderMessageRow(text) {
    const body = document.getElementById('historyBody');
    body.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">${this.esc(text)}</td></tr>`;
  },

  markedAt(value) {
    if (!value) return '—';
    const parsed = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(parsed.getTime()) ? String(value) : parsed.toLocaleString();
  },

  localDate() {
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60000;
    return new Date(now.getTime() - offset).toISOString().slice(0, 10);
  },

  message(text, type) {
    const element = document.getElementById('historyMessage');
    element.textContent = text;
    element.className = `alert alert-${type}`;
  },

  esc(value) {
    const element = document.createElement('div');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
  },
};

document.addEventListener('DOMContentLoaded', () => ClassAttendanceHistoryController.init());
