const MyAttendanceController = {
    staffId: null,
    records: [],

    async init() {
        if (window.AuthContext?.ready) await window.AuthContext.ready();
        const user = window.AuthContext?.getUser?.();
        this.staffId = user?.staff_id || null;
        if (!this.staffId) {
            window.showNotification?.('No staff profile is linked to your account.', 'error');
            return;
        }
        const now = new Date();
        const from = new Date(now.getFullYear(), now.getMonth(), 1);
        document.getElementById('myAttendanceFrom').value = from.toISOString().slice(0, 10);
        document.getElementById('myAttendanceTo').value = now.toISOString().slice(0, 10);
        document.getElementById('myAttendanceRefresh')?.addEventListener('click', () => this.load());
        document.getElementById('myAttendanceApply')?.addEventListener('click', () => this.load());
        await this.load();
    },

    async load() {
        const body = document.getElementById('myAttendanceBody');
        body.innerHTML = '<tr><td colspan="6" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>';
        try {
            const params = {
                start_date: document.getElementById('myAttendanceFrom').value,
                end_date: document.getElementById('myAttendanceTo').value
            };
            const [history, summary] = await Promise.all([
                API.attendance.getStaffHistory(this.staffId, params),
                API.attendance.getStaffSummary(this.staffId, params)
            ]);
            this.records = Array.isArray(history) ? history : (history?.records || history?.data || []);
            const s = summary?.summary || summary || {};
            document.getElementById('myPresent').textContent = s.present ?? this.records.filter(r => r.status === 'present').length;
            document.getElementById('myLate').textContent = s.late ?? this.records.filter(r => r.status === 'late').length;
            document.getElementById('myAbsent').textContent = s.absent ?? this.records.filter(r => r.status === 'absent').length;
            const total = Number(s.total ?? this.records.length);
            const present = Number(s.present ?? this.records.filter(r => ['present', 'late'].includes(r.status)).length);
            document.getElementById('myRate').textContent = total ? Math.round((present / total) * 100) + '%' : '0%';
            body.innerHTML = this.records.length ? this.records.map(r => `<tr><td>${this.esc(r.date)}</td><td>${this.esc(r.shift || 'full_day')}</td><td><span class="badge bg-${r.status === 'present' ? 'success' : r.status === 'late' ? 'warning text-dark' : 'danger'}">${this.esc(r.status)}</span></td><td>${this.esc(r.check_in || '—')}</td><td>${this.esc(r.check_out || '—')}</td><td>${this.esc(r.notes || '—')}</td></tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted py-4">No attendance records found.</td></tr>';
        } catch (e) {
            body.innerHTML = `<tr><td colspan="6" class="text-danger text-center py-4">${this.esc(e.message)}</td></tr>`;
        }
    },

    esc(v) {
        const d = document.createElement('div');
        d.textContent = String(v ?? '');
        return d.innerHTML;
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => MyAttendanceController.init().catch(() => {}));
} else {
    MyAttendanceController.init().catch(() => {});
}

window.MyAttendanceController = MyAttendanceController;
