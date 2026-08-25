const academicContentReconciliation = {
  items: [],
  esc(v) { const d = document.createElement('div'); d.textContent = v == null ? '' : v; return d.innerHTML; },
  async load() {
    const r = await window.callAPI('academic/legacy-content-reconciliation', 'GET');
    const data = r?.data || r || {}; this.items = data.items || [];
    const tbody = document.querySelector('#acrTable tbody');
    if (!this.items.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No unresolved academic content.</td></tr>'; return; }
    tbody.innerHTML = this.items.map((x, i) => {
      const candidates = x.candidates || [];
      const options = candidates.map(c => `<option value="${c.stream_learning_area_id}">${this.esc(`${c.class_name || ''} · ${c.stream_name || ''}`)}</option>`).join('');
      const weeks = (x.available_weeks || []).map(w => `<option value="${w.id}">Week ${w.week_number} · ${w.week_start} to ${w.week_end}</option>`).join('');
      return `<tr><td>${this.esc(x.content_type)}</td><td>#${x.content_id}<br><small>${this.esc(x.title || '')}</small></td><td><span class="badge ${x.status === 'manual_required' ? 'bg-warning text-dark' : 'bg-secondary'}">${this.esc(x.status)}</span><br><small>${this.esc(x.reason)}</small></td><td>${candidates.length ? `<select class="form-select form-select-sm mb-1" id="acr-${i}"><option value="">Select verified stream</option>${options}</select><select class="form-select form-select-sm" id="acr-week-${i}"><option value="">Select verified week</option>${weeks}</select>` : '<span class="text-muted small">No safe candidate. Recreate canonically.</span>'}</td><td>${candidates.length ? `<button class="btn btn-sm btn-success" onclick="academicContentReconciliation.resolve(${i})">Resolve</button>` : '<span class="text-muted small">Quarantine</span>'}</td></tr>`;
    }).join('');
  },
  async resolve(index) {
    const x = this.items[index]; const select = document.getElementById(`acr-${index}`); const week = document.getElementById(`acr-week-${index}`); const option = select?.selectedOptions[0];
    if (!option?.value || !week?.value) return window.showNotification?.('Select and verify both the canonical stream and week.', 'warning');
    const payload = { queue_id: x.id, stream_learning_area_id: Number(option.value), calendar_week_id: Number(week.value) };
    if (x.content_type === 'lesson_plan') payload.scheme_of_work_id = Number(x.scheme_of_work_id || 0);
    try { await window.callAPI('academic/resolve-legacy-academic-content', 'POST', payload); window.showNotification?.('Legacy record reconciled.', 'success'); await this.load(); } catch (e) { window.showNotification?.(e.message || 'Reconciliation failed.', 'danger'); }
  },
  init() { document.getElementById('acrRefresh')?.addEventListener('click', () => this.load()); this.load().catch(e => window.showNotification?.(e.message || 'Unable to load queue.', 'danger')); }
};
window.academicContentReconciliation = academicContentReconciliation;
document.addEventListener('DOMContentLoaded', () => academicContentReconciliation.init());
