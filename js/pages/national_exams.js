/**
 * National Assessments page (KPSEA Grade 6, KJSEA Grade 9, Grade 3).
 *
 * A sitting is an exam plus the academic year it was sat ("KJSEA 2026" =
 * Grade 9 of 2026/2027). Its learners are that year's roll, so results
 * released in December/January can be uploaded after promotion and
 * graduation. Upload: a CSV (the template lists the sitting's learners) is
 * read here, checked by the server (dry run: matched / unmatched rows,
 * ignored columns, invalid values) and saved only on confirmation.
 */
const nationalExamsController = {
  state: { sittings: [], sitting: null, data: null, rows: null, fileName: null },

  api(path, method = 'GET', data = null, params = null) { return window.callAPI(path, method, data, params); },
  data(response) { return response?.data?.data ?? response?.data ?? response; },
  esc(v) { return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); },
  el(id) { return document.getElementById(id); },
  pathway(p) { return p ? String(p).replace(/_/g, ' ') : '—'; },

  status(text, error = false) {
    const box = this.el('natStatus');
    box.classList.toggle('d-none', !text);
    box.classList.toggle('text-danger', error);
    box.textContent = text || '';
  },

  key(s) { return `${s.exam_type}:${s.academic_year_id}`; },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.el('natSitting').addEventListener('change', () => this.load());
    this.el('natUploadBtn').addEventListener('click', () => this.openUpload());
    this.el('natTemplateBtn').addEventListener('click', () => this.template());
    this.el('natExportBtn').addEventListener('click', () => this.exportCSV());
    this.el('natPrintBtn').addEventListener('click', () => this.print());
    this.el('natFile').addEventListener('change', (e) => this.readFile(e.target.files?.[0]));
    this.el('natSaveBtn').addEventListener('click', () => this.save());
    try {
      const response = await this.api('/api/academic/national-sittings');
      if (response?.success === false) throw new Error(response.message);
      const payload = this.data(response) || {};
      this.state.sittings = payload.sittings || [];
      const select = this.el('natSitting');
      if (!this.state.sittings.length) {
        select.innerHTML = '<option value="">No sittings</option>';
        this.status('No Grade 6 or Grade 9 learners are recorded for any year.');
        return;
      }
      select.innerHTML = this.state.sittings.map((s) => `<option value="${this.esc(this.key(s))}">${this.esc(s.label)} · ${this.esc(s.class_name)} ${this.esc(s.academic_year)}</option>`).join('');
      const url = new URLSearchParams(window.location.search);
      const wanted = url.get('exam_type') && url.get('academic_year_id') ? `${url.get('exam_type')}:${url.get('academic_year_id')}` : null;
      const def = payload.default ? this.key(payload.default) : null;
      const pick = [wanted, def].find((k) => k && this.state.sittings.some((s) => this.key(s) === k));
      if (pick) select.value = pick;
      await this.load();
    } catch (error) {
      this.status(error?.message || 'National assessments could not be loaded.', true);
    }
    window.APIRealtime?.register?.('national_exams', { refresh: () => this.load() }, ['academic']);
  },

  current() {
    const [examType, yearId] = String(this.el('natSitting').value || '').split(':');
    return examType && yearId ? { exam_type: examType, academic_year_id: Number(yearId) } : null;
  },

  remember(sel) {
    try {
      const url = new URL(window.location.href);
      url.searchParams.set('exam_type', sel.exam_type);
      url.searchParams.set('academic_year_id', String(sel.academic_year_id));
      window.history.replaceState(window.history.state, '', url);
    } catch (_) { /* optional */ }
  },

  async load() {
    const sel = this.current();
    if (!sel) return;
    this.remember(sel);
    this.status('Loading results…');
    const response = await this.api('/api/academic/national-sitting', 'GET', null, sel);
    if (response?.success === false) { this.status(response.message || 'Results could not be loaded.', true); return; }
    this.state.data = this.data(response) || {};
    this.status('');
    this.render();
    window.YearMode?.refresh?.(this.el('nationalExamsPage'));
  },

  render() {
    const d = this.state.data || {};
    const s = d.sitting || {};
    const areas = d.learning_areas || [];
    const rows = d.candidates || [];
    const kj = s.exam_type === 'KJSEA_G9';
    this.el('natCohort').textContent = s.cohort_label ? `${s.label} · ${s.cohort_label} (${s.academic_year})` : '';
    this.el('natStats').innerHTML = `
      <span class="badge bg-primary-subtle text-primary-emphasis p-2">${Number(rows.length)} learners</span>
      <span class="badge ${d.uploaded ? 'bg-success-subtle text-success-emphasis' : 'bg-warning-subtle text-warning-emphasis'} p-2">${Number(d.uploaded || 0)} with results</span>`;
    const head = `<thead class="table-light"><tr><th>#</th><th>Learner</th><th>Adm No</th><th>Assessment No</th><th>Class</th>${areas.map((a) => `<th class="nat-area text-center">${this.esc(a.name)}</th>`).join('')}<th class="text-center">Points</th>${kj ? '<th>Pathway</th>' : ''}</tr></thead>`;
    const body = rows.map((r, i) => `<tr${r.uploaded ? '' : ' class="text-muted"'}><td>${i + 1}</td><td>${this.esc(r.name)}</td><td>${this.esc(r.admission_no || '—')}</td><td>${this.esc(r.assessment_number || '—')}</td><td>${this.esc(r.class_name)}</td>${areas.map((a) => {
      const x = r.results?.[a.learning_area_id];
      return `<td class="text-center">${x ? this.esc(x.grade ?? (x.score != null ? `${x.score}%` : '—')) : '—'}</td>`;
    }).join('')}<td class="text-center fw-semibold">${r.total_points ?? '—'}</td>${kj ? `<td>${this.esc(this.pathway(r.pathway))}</td>` : ''}</tr>`).join('');
    this.el('natResultsTable').innerHTML = head + `<tbody>${body || `<tr><td colspan="${areas.length + 7}" class="text-center text-muted py-4">No learners.</td></tr>`}</tbody>`;
    this.el('natResultsCard').classList.remove('d-none');

    const levels = s.levels || [];
    const bands = d.grade_bands || {};
    const anyBand = Object.values(bands).some((b) => Object.keys(b).length);
    this.el('natBandsCard').classList.toggle('d-none', !anyBand);
    if (anyBand) {
      this.el('natBandsTable').innerHTML = `<thead class="table-light"><tr><th>Learning area</th>${levels.map((l) => `<th class="text-center">${this.esc(l)}</th>`).join('')}</tr></thead><tbody>${areas.map((a) => `<tr><td>${this.esc(a.name)}</td>${levels.map((l) => `<td class="text-center">${Number(bands[a.learning_area_id]?.[l] || 0) || '—'}</td>`).join('')}</tr>`).join('')}</tbody>`;
    }
  },

  csvLine(values) { return values.map((v) => `"${String(v ?? '').replace(/"/g, '""')}"`).join(','); },

  download(lines, name) {
    window.KingswayFileLifecycle?.exportText?.('﻿' + lines.join('\r\n'), `${name.replace(/[^0-9A-Za-z-]+/g, '-').replace(/-+/g, '-')}.csv`, 'text/csv;charset=utf-8');
  },

  template() {
    const d = this.state.data || {};
    const areas = (d.learning_areas || []).map((a) => a.name);
    const kj = d.sitting?.exam_type === 'KJSEA_G9';
    const header = ['Admission No', 'Assessment No', 'Name', ...areas, 'Points', ...(kj ? ['Pathway'] : [])];
    const lines = [this.csvLine(header), ...(d.candidates || []).map((r) => this.csvLine([r.admission_no, r.assessment_number, r.name, ...areas.map(() => ''), '', ...(kj ? [''] : [])]))];
    this.download(lines, `${d.sitting?.label || 'national'}-template`);
  },

  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('results')) return window.showNotification?.('You do not have permission to export results.', 'error');
    const d = this.state.data || {};
    const areas = d.learning_areas || [];
    const kj = d.sitting?.exam_type === 'KJSEA_G9';
    const lines = [this.csvLine(['Admission No', 'Assessment No', 'Name', 'Class', ...areas.map((a) => a.name), 'Points', ...(kj ? ['Pathway'] : [])])];
    (d.candidates || []).forEach((r) => lines.push(this.csvLine([r.admission_no, r.assessment_number, r.name, r.class_name, ...areas.map((a) => r.results?.[a.learning_area_id]?.grade ?? r.results?.[a.learning_area_id]?.score ?? ''), r.total_points ?? '', ...(kj ? [this.pathway(r.pathway)] : [])])));
    this.download(lines, `${d.sitting?.label || 'national'}-results`);
  },

  print() {
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('results')) return window.showNotification?.('You do not have permission to print results.', 'error');
    window.print();
  },

  openUpload() {
    this.state.rows = null;
    this.el('natFile').value = '';
    this.el('natPreview').innerHTML = '';
    this.el('natSaveBtn').disabled = true;
    this.el('natUploadTitle').textContent = `Upload results · ${this.state.data?.sitting?.label || ''}`;
    bootstrap.Modal.getOrCreateInstance(this.el('natUploadModal')).show();
  },

  /** RFC 4180 CSV -> array of objects keyed by the header row. */
  parseCSV(text) {
    const rows = [];
    let row = [];
    let cell = '';
    let quoted = false;
    const src = text.replace(/^﻿/, '');
    for (let i = 0; i < src.length; i += 1) {
      const c = src[i];
      if (quoted) {
        if (c === '"' && src[i + 1] === '"') { cell += '"'; i += 1; } else if (c === '"') quoted = false; else cell += c;
      } else if (c === '"') quoted = true;
      else if (c === ',') { row.push(cell); cell = ''; }
      else if (c === '\n' || c === '\r') {
        if (c === '\r' && src[i + 1] === '\n') i += 1;
        row.push(cell); rows.push(row); row = []; cell = '';
      } else cell += c;
    }
    if (cell !== '' || row.length) { row.push(cell); rows.push(row); }
    const nonEmpty = rows.filter((r) => r.some((v) => String(v).trim() !== ''));
    if (nonEmpty.length < 2) return [];
    const header = nonEmpty[0].map((h) => String(h).trim());
    return nonEmpty.slice(1).map((r) => Object.fromEntries(header.map((h, i) => [h, (r[i] ?? '').trim()]).filter(([h]) => h !== '')));
  },

  async readFile(file) {
    const box = this.el('natPreview');
    this.el('natSaveBtn').disabled = true;
    if (!file) return;
    if (!/\.csv$/i.test(file.name)) { box.innerHTML = '<div class="alert alert-warning">Save the file as CSV first.</div>'; return; }
    this.state.fileName = file.name;
    this.state.rows = this.parseCSV(await file.text());
    if (!this.state.rows.length) { box.innerHTML = '<div class="alert alert-warning">The file has no rows.</div>'; return; }
    box.innerHTML = '<div class="text-muted">Checking…</div>';
    const report = await this.submit(true);
    if (report) this.preview(report);
  },

  async submit(dryRun) {
    const sel = this.current();
    try {
      const response = await this.api('/api/academic/national-sitting-import', 'POST', { ...sel, rows: this.state.rows, file_name: this.state.fileName, dry_run: dryRun });
      if (response?.success === false) throw new Error(response.message);
      return this.data(response);
    } catch (error) {
      this.el('natPreview').innerHTML = `<div class="alert alert-danger">${this.esc(error?.message || 'The file could not be checked.')}</div>`;
      return null;
    }
  },

  preview(r) {
    const list = (items, render) => items.length ? `<ul class="small mb-2">${items.slice(0, 30).map(render).join('')}${items.length > 30 ? `<li>… ${items.length - 30} more</li>` : ''}</ul>` : '';
    this.el('natPreview').innerHTML = `
      <div class="d-flex flex-wrap gap-2 mb-2">
        <span class="badge bg-success p-2">${Number(r.matched)} of ${Number(r.candidates)} learners matched</span>
        ${r.unmatched_rows?.length ? `<span class="badge bg-danger p-2">${r.unmatched_rows.length} rows not matched</span>` : ''}
        ${r.problems?.length ? `<span class="badge bg-warning text-dark p-2">${r.problems.length} problems</span>` : ''}
      </div>
      <div class="small mb-2"><strong>Learning areas:</strong> ${this.esc(Object.values(r.learning_area_columns || {}).join(', ') || 'none')}${r.ignored_columns?.length ? ` · <strong>Ignored columns:</strong> ${this.esc(r.ignored_columns.join(', '))}` : ''}</div>
      ${r.unmatched_rows?.length ? `<div class="small fw-semibold">Rows with no matching learner in this sitting</div>${list(r.unmatched_rows, (u) => `<li>Line ${u.line}: ${this.esc(u.admission_no || u.assessment_number || '—')} ${this.esc(u.name || '')}</li>`)}` : ''}
      ${r.problems?.length ? `<div class="small fw-semibold">Problems (these values are not saved)</div>${list(r.problems, (p) => `<li>Line ${p.line}, ${this.esc(p.learner)}: ${this.esc(p.problem)}</li>`)}` : ''}`;
    this.el('natSaveBtn').disabled = !r.matched;
  },

  async save() {
    if (!this.state.rows?.length) return;
    const btn = this.el('natSaveBtn');
    btn.disabled = true;
    const report = await this.submit(false);
    if (!report?.saved) { btn.disabled = false; return; }
    bootstrap.Modal.getInstance(this.el('natUploadModal'))?.hide();
    window.showNotification?.(`Results saved for ${report.matched} learners.`, 'success');
    await this.load();
  },
};

window.nationalExamsController = nationalExamsController;
window.natExamCtrl = nationalExamsController;
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => nationalExamsController.init().catch(console.error));
} else {
  nationalExamsController.init().catch(console.error);
}
