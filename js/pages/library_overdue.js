/**
 * library_overdue.js — Library Overdue Controller (standalone page).
 * Overdue book loans and pending fines.
 */
const libraryOverdueController = {
  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleDateString();
  },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.issues || r?.fines || r || []); },

  async load() {
    const overdueBody = document.getElementById('loBody');
    const fineBody = document.getElementById('loFineBody');
    overdueBody.innerHTML = '<tr><td colspan="4" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    fineBody.innerHTML = '<tr><td colspan="4" class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></td></tr>';
    try {
      const [overdue, fines] = await Promise.all([
        this.API('GET', '/library/overdue').catch(() => []),
        this.API('GET', '/library/fines').catch(() => []),
      ]);
      const overdueList = this.norm(overdue);
      const fineList = this.norm(fines);

      if (!overdueList.length) { overdueBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No overdue books.</td></tr>'; }
      else {
        overdueBody.innerHTML = overdueList.map(i => `<tr>
          <td class="small fw-semibold">${this.esc(i.book_title || '\u2014')}</td>
          <td class="small">${this.esc(i.borrower_name || '\u2014')} <span class="badge bg-light text-dark">${this.esc(i.borrower_type || '')}</span></td>
          <td class="small">${this.fmtDate(i.due_date)}</td>
          <td><span class="badge bg-danger">${this.esc(i.days_overdue ?? 0)} days</span></td>
        </tr>`).join('');
      }

      if (!fineList.length) { fineBody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No fines recorded.</td></tr>'; }
      else {
        fineBody.innerHTML = fineList.map(f => `<tr>
          <td class="small">${this.esc(f.book_title || '\u2014')}</td>
          <td class="small">${this.esc(f.borrower_name || '\u2014')}</td>
          <td class="small fw-semibold">KES ${Number(f.fine_amount ?? 0).toLocaleString()}</td>
          <td><span class="badge ${(f.fine_status || '') === 'paid' ? 'bg-success' : 'bg-warning text-dark'}">${this.esc(f.fine_status || 'pending')}</span></td>
        </tr>`).join('');
      }
    } catch (e) {
      overdueBody.innerHTML = `<tr><td colspan="4" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.load();
  },
};

window.libraryOverdueController = libraryOverdueController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => libraryOverdueController.init().catch(() => {}));
} else {
  libraryOverdueController.init().catch(() => {});
}
