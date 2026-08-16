/**
 * Year Transition / Rollover Controller
 * Drives the backend AcademicYearTransitionWorkflow (6 stages):
 *   1. prepare_calendar   - create new year + 3 terms + 14/14/10 week calendar
 *   2. setup_new_year     - clone classes one grade ahead, seed streams + learning areas
 *   3. archive_data       - archive previous year data
 *   4. execute_promotions - bulk promotions summary
 *   5. migrate_baselines  - carry competency baselines forward
 *   6. validate_readiness - final checks, then activate Term 1 + switch the system
 *
 * API:
 *   POST /academic/year-transition/start-workflow
 *   POST /academic/year-transition/setup-new-year
 *   POST /academic/year-transition/archive-data
 *   POST /academic/year-transition/execute-promotions
 *   POST /academic/year-transition/migrate-competency-baselines
 *   POST /academic/year-transition/validate-readiness
 *   PUT  /academic/years/set-current/{id}
 *   GET  /academic/workflow/status?workflow_type=year-transition&instance_id=
 */
const yearRolloverController = {

  _currentYear: null,
  _terms: [],
  _instanceId: null,
  _workflowData: null,
  _running: false,
  _log: [],

  _stages: [
    {
      code: 'prepare_calendar',
      label: 'Create New Year & Calendar',
      desc: 'New year record, 3 terms and the 14/14/10 week calendar',
      icon: 'bi-calendar-plus',
      color: 'primary',
    },
    {
      code: 'setup_new_year',
      label: 'Setup New Year Structure',
      desc: 'Clone classes one grade ahead, seed streams and learning areas',
      icon: 'bi-diagram-3-fill',
      color: 'info',
    },
    {
      code: 'archive_data',
      label: 'Archive Previous Year Data',
      desc: 'Archive assessments, attendance and competency records of the old year',
      icon: 'bi-archive-fill',
      color: 'secondary',
    },
    {
      code: 'execute_promotions',
      label: 'Execute Promotions',
      desc: 'Record bulk promotions to the next grade (fine-tune via Student Promotion)',
      icon: 'bi-person-up',
      color: 'warning',
    },
    {
      code: 'migrate_baselines',
      label: 'Migrate Competency Baselines',
      desc: 'Carry competency baselines into the new year for continued tracking',
      icon: 'bi-graph-up-arrow',
      color: 'primary',
    },
    {
      code: 'validate_readiness',
      label: 'Validate & Activate',
      desc: 'Final checks, then activate Term 1 and switch the whole system to the new year',
      icon: 'bi-play-circle-fill',
      color: 'success',
    },
  ],

  _STORE_KEY: 'kingsway_yr_transition_instance',

  // ── Init ──────────────────────────────────────────────────────────────────
  init: async function () {
    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await Promise.all([this._loadStatus(), this._loadYears()]);
    await this._resumeInstance();
    this._render();
  },

  // ── Load data ─────────────────────────────────────────────────────────────
  _loadStatus: async function () {
    try {
      const r = await callAPI('/academic/year-rollover-status', 'GET');
      this._status = r?.data || r;
    } catch (e) {
      this._status = {};
    }
  },

  _loadYears: async function () {
    try {
      const r = await callAPI('/academic/years/list', 'GET');
      const years = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._years = years;
      this._currentYear = years.find(y => y.is_current) || years.find(y => (y.status || '').toLowerCase() === 'active') || years[0] || null;
      if (this._currentYear) {
        this._loadTermsForPrefill(this._currentYear.id);
      }
    } catch (e) {
      console.warn('Failed to load academic years:', e);
    }
  },

  _loadTermsForPrefill: async function (yearId) {
    try {
      // /academic/terms returns terms across ALL years (getAcademicTerms ignores
      // academic_year_id), so filter client-side on the `year` field.
      const r = await callAPI('/academic/terms', 'GET');
      const all = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._terms = all.filter(t => Number(t.year) === Number(yearId));
      this._prefillForm();
    } catch (e) {
      console.warn('Failed to load terms for prefill:', e);
    }
  },

  _resumeInstance: async function () {
    const stored = localStorage.getItem(this._STORE_KEY);
    if (!stored) return;
    try {
      const r = await callAPI(
        '/academic/workflow/status?workflow_type=year-transition&instance_id=' + stored,
        'GET',
      );
      const inst = r?.data || r;
      if (!inst || !inst.id) {
        localStorage.removeItem(this._STORE_KEY);
        return;
      }
      this._instanceId = inst.id;
      this._workflowData = inst.data || {};
      this._importWorkflowLog();
    } catch (e) {
      console.warn('Could not resume transition instance:', e);
      localStorage.removeItem(this._STORE_KEY);
    }
  },

  _prefillForm: function () {
    const cur = this._currentYear;
    if (!cur) return;
    const toYear = (Number(cur.year_code) + 1) || '';
    const set = (id, v) => { const el = document.getElementById(id); if (el && !el.value) el.value = v; };
    set('yrToYear', toYear);

    const sorted = [...this._terms].sort((a, b) => Number(a.term_id) - Number(b.term_id));
    const defaults = { 1: 14, 2: 14, 3: 10 };
    sorted.forEach((t, idx) => {
      const n = idx + 1;
      const shift = (d) => {
        if (!d) return '';
        const dt = new Date(d);
        dt.setFullYear(dt.getFullYear() + 1);
        return dt.toISOString().slice(0, 10);
      };
      set(`yrT${n}Start`, shift(t.start_date || t.opening_date));
      set(`yrT${n}End`, shift(t.end_date || t.closing_date));
      set(`yrT${n}Weeks`, defaults[n] || 14);
    });
    set('yrStartDate', document.getElementById('yrT1Start')?.value || '');
    set('yrEndDate', document.getElementById('yrT3End')?.value || '');
  },

  // ── Rendering ─────────────────────────────────────────────────────────────
  _render: function () {
    this._renderPreflight();
    this._renderExistingYearWarning();

    const hasInstance = !!this._instanceId;
    const createForm = document.getElementById('yrCreateForm');
    const stageWrap = document.getElementById('yrStageWrap');
    if (createForm) createForm.classList.toggle('d-none', hasInstance);
    if (stageWrap) stageWrap.classList.toggle('d-none', !hasInstance);

    const info = document.getElementById('yrWfInfo');
    if (info) {
      info.textContent = this._instanceId
        ? `#${this._instanceId} · ${this._workflowData?.from_year || ''} → ${this._workflowData?.to_year || ''}`
        : '';
    }

    if (hasInstance) this._renderStages();
    this._renderLog();
  },

  _renderPreflight: function () {
    const s = this._status || {};
    const badge = document.getElementById('yrCurrentYearBadge');
    if (badge) badge.textContent = s.current_year?.year_name || this._currentYear?.year_name || '—';

    const setCheck = (iconId, statusId, ok, trueText, falseText) => {
      const icon = document.getElementById(iconId);
      const stat = document.getElementById(statusId);
      if (icon) { icon.style.color = ok ? 'green' : 'red'; icon.className = 'bi ' + (ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill') + ' fs-3 mb-1'; }
      if (stat) stat.textContent = ok ? trueText : falseText;
    };

    setCheck('yrTermsIcon', 'yrTermsStatus', s.all_terms_complete, 'All Closed', 'Incomplete Terms');
    setCheck('yrResultsIcon', 'yrResultsStatus', (s.pending_results || 0) === 0, 'Finalised', `${s.pending_results || 0} Pending`);
    setCheck('yrPromotionsIcon', 'yrPromotionsStatus', (s.pending_promotions || 0) === 0, 'Done', `${s.pending_promotions || 0} Pending`);

    const feesIcon = document.getElementById('yrFeesIcon');
    const feesStatus = document.getElementById('yrFeesStatus');
    if (feesIcon) {
      feesIcon.style.color = (s.students_with_fees || 0) > 0 ? 'orange' : 'green';
      feesIcon.className = 'bi bi-cash-coin fs-3 mb-1';
    }
    if (feesStatus) feesStatus.textContent = (s.students_with_fees || 0) > 0 ? `${s.students_with_fees} students` : 'All Cleared';

    const notReady = document.getElementById('yrNotReady');
    if (notReady) notReady.classList.toggle('d-none', !!s.all_terms_complete);
  },

  _renderExistingYearWarning: function () {
    const el = document.getElementById('yrExistingYear');
    if (!el) return;
    const toYear = this._currentYear ? Number(this._currentYear.year_code) + 1 : null;
    const exists = this._years && this._years.some(y => Number(y.year_code) === toYear);
    if (exists && !this._instanceId) {
      el.classList.remove('d-none');
    } else {
      el.classList.add('d-none');
    }
  },

  _renderStages: function () {
    const container = document.getElementById('yrStages');
    if (!container) return;

    const done = {};
    this._log.forEach(l => { if (l.status === 'completed') done[l.stage] = true; });

    container.innerHTML = this._stages.map(stage => {
      const completed = !!done[stage.code];
      return `<div class="list-group-item d-flex align-items-center gap-3 py-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:42px;height:42px;background:var(--bs-${completed ? 'success' : stage.color}-bg-subtle)">
          <i class="bi ${stage.icon} text-${completed ? 'success' : stage.color} fs-5"></i>
        </div>
        <div class="flex-grow-1">
          <div class="fw-semibold">${stage.label}</div>
          <div class="text-muted small">${stage.desc}</div>
        </div>
        <div class="flex-shrink-0">
          ${completed
            ? `<span class="badge bg-success"><i class="bi bi-check2 me-1"></i>Done</span>`
            : stage.code === 'prepare_calendar'
              ? `<span class="badge bg-primary">Created</span>`
              : `<button class="btn btn-sm btn-${stage.color}" onclick="yearRolloverController.runStage('${stage.code}')">
                   <i class="bi bi-play-fill me-1"></i>Run
                 </button>`
          }
        </div>
      </div>`;
    }).join('');
  },

  _renderLog: function () {
    const tbody = document.getElementById('yrLogBody');
    if (!tbody) return;
    if (!this._log.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">No transition activity yet.</td></tr>';
      return;
    }
    const colors = { completed: 'success', failed: 'danger', in_progress: 'warning', pending: 'secondary' };
    tbody.innerHTML = this._log.map(l => `<tr>
      <td>${this._esc(l.stage)}</td>
      <td><span class="badge bg-${colors[l.status] || 'secondary'}">${l.status || '—'}</span></td>
      <td class="small">${this._esc(l.details)}</td>
      <td class="small">${l.performed ? new Date(l.performed).toLocaleString('en-KE') : '—'}</td>
    </tr>`).join('');
  },

  // ── Stage 1: create new year ──────────────────────────────────────────────
  createNewYear: async function () {
    if (this._running) return;
    const toYear = Number(document.getElementById('yrToYear')?.value);
    const startDate = document.getElementById('yrStartDate')?.value;
    const endDate = document.getElementById('yrEndDate')?.value;
    const msg = document.getElementById('yrCreateMsg');

    if (!toYear || !startDate || !endDate) {
      if (msg) msg.textContent = 'New year code, start and end dates are required.';
      showNotification('New year code, start and end dates are required.', 'warning');
      return;
    }
    const terms = [1, 2, 3].map(n => ({
      term_name: `Term ${n}`,
      start_date: document.getElementById(`yrT${n}Start`)?.value,
      end_date: document.getElementById(`yrT${n}End`)?.value,
      weeks: Number(document.getElementById(`yrT${n}Weeks`)?.value) || (n === 3 ? 10 : 14),
    }));
    if (terms.some(t => !t.start_date || !t.end_date)) {
      showNotification('Please provide start and end dates for all three terms.', 'warning');
      return;
    }

    this._running = true;
    if (msg) msg.textContent = 'Creating new year and generating calendar…';
    try {
      const data = await callAPI('/academic/year-transition/start-workflow', 'POST', {
        from_year: Number(this._currentYear?.year_code),
        to_year: toYear,
        year_start_date: startDate,
        year_end_date: endDate,
        terms,
        transition_notes: `Rollover from ${this._currentYear?.year_code} prepared by ${AuthContext.getUser?.().username || 'admin'}`,
      });
      this._instanceId = data.instance_id;
      this._workflowData = data.workflow_data || {};
      localStorage.setItem(this._STORE_KEY, String(this._instanceId));
      this._log.push({ stage: 'prepare_calendar', status: 'completed', details: `${data.terms_created} terms created · calendar generated`, performed: new Date().toISOString() });
      showNotification(`New academic year ${toYear} created with ${data.terms_created} terms.`, 'success');
      await this._loadStatus();
      this._render();
    } catch (e) {
      if (msg) msg.textContent = '';
      showNotification(e.message || 'Failed to create the new academic year.', 'danger');
    } finally {
      this._running = false;
    }
  },

  // ── Stages 2-6 ────────────────────────────────────────────────────────────
  runStage: async function (code) {
    if (this._running) return;
    const stage = this._stages.find(s => s.code === code);
    const confirmMsg = `Run stage: "${stage?.label || code}"?\n\nThis action modifies database records.`;
    if (!await window.confirmAction('Confirm', confirmMsg)) return;

    this._running = true;
    this._setRunning(code, true);
    try {
      let data = null;
      if (code === 'setup_new_year') {
        data = await callAPI('/academic/year-transition/setup-new-year', 'POST', {
          instance_id: this._instanceId,
          from_year: Number(this._workflowData?.from_year || this._currentYear?.year_code),
          capacity: 40,
          default_ecd_streams: 2,
        });
        const la = data.learning_area_setup || {};
        this._log.push({
          stage: 'setup_new_year', status: 'completed',
          details: `${data.classes_created} classes · ${(la.classes || []).length} classes with learning areas`,
          performed: new Date().toISOString(),
        });
        showNotification(`New year structure created: ${data.classes_created} classes (mode: ${data.mode}).`, 'success');
      } else if (code === 'archive_data') {
        data = await callAPI('/academic/year-transition/archive-data', 'POST', {
          instance_id: this._instanceId,
          archive_assessments: true, archive_attendance: true, archive_reports: true, archive_competencies: true,
        });
        this._log.push({
          stage: 'archive_data', status: 'completed',
          details: `${data.assessments_archived ?? 0} assessments · ${data.attendance_records_archived ?? 0} attendance · ${data.competency_records_archived ?? 0} competencies`,
          performed: new Date().toISOString(),
        });
        showNotification('Previous year data archived.', 'success');
      } else if (code === 'execute_promotions') {
        data = await callAPI('/academic/year-transition/execute-promotions', 'POST', {
          instance_id: this._instanceId,
        });
        this._log.push({
          stage: 'execute_promotions', status: 'completed',
          details: `${data.promoted ?? 0} promoted · ${data.retained ?? 0} retained · ${data.graduated ?? 0} graduated (of ${data.total_students ?? 0})`,
          performed: new Date().toISOString(),
        });
        showNotification(`Promotions: ${data.promoted ?? 0} promoted, ${data.graduated ?? 0} graduated.`, 'success');
      } else if (code === 'migrate_baselines') {
        data = await callAPI('/academic/year-transition/migrate-competency-baselines', 'POST', {
          instance_id: this._instanceId,
        });
        this._log.push({
          stage: 'migrate_baselines', status: 'completed',
          details: `${data.total_baselines ?? 0} baselines · ${data.students_tracked ?? 0} students · ${data.competencies_tracked ?? 0} competencies`,
          performed: new Date().toISOString(),
        });
        showNotification(`Migrated ${data.total_baselines ?? 0} competency baselines.`, 'success');
      } else if (code === 'validate_readiness') {
        await this._validateAndActivate();
        await this._loadStatus();
        this._running = false;
        this._setRunning(code, false);
        this._render();
        return;
      }
      this._renderStages();
      this._renderLog();
    } catch (e) {
      // Readiness validation can legitimately fail on partial prep — show the
      // detailed results instead of a bare error.
      if (code === 'validate_readiness' && e.response?.data?.validation_results) {
        this._log.push({
          stage: 'validate_readiness', status: 'failed',
          details: this._validationSummary(e.response.data.validation_results),
          performed: new Date().toISOString(),
        });
      } else {
        this._log.push({ stage: code, status: 'failed', details: e.message || 'Stage failed', performed: new Date().toISOString() });
      }
      showNotification(e.message || `Stage "${code}" failed.`, 'danger');
      this._renderLog();
    } finally {
      this._running = false;
      this._setRunning(code, false);
    }
  },

  _validateAndActivate: async function () {
    const res = await callAPI('/academic/year-transition/validate-readiness', 'POST', {
      instance_id: this._instanceId,
    });
    const ready = !!res.ready_for_new_year;
    const checks = res.validation_results || {};
    if (!ready) {
      const err = new Error('New academic year is not ready yet');
      err.response = { data: { validation_results: checks } };
      throw err;
    }
    // Activate: switch the whole system to the new year.
    const newYearId = this._workflowData?.academic_year_id || this._years?.find(y => Number(y.year_code) === Number(this._workflowData?.to_year))?.id;
    if (newYearId) {
      await callAPI(`/academic/years/set-current/${newYearId}`, 'PUT');
    }
    this._log.push({
      stage: 'validate_readiness', status: 'completed',
      details: 'All checks passed · Term 1 activated · system switched to new year',
      performed: new Date().toISOString(),
    });
    if (window.AcademicContext) await window.AcademicContext.refresh();
    if (window.DataStore) window.DataStore.invalidateMany(['academic', 'attendance', 'finance']).catch(() => {});
    showNotification('New academic year is now active! Term 1 is current.', 'success');

    // Workflow is complete — clear the persisted instance so the page returns
    // to a clean, ready-to-create state on next load.
    this._instanceId = null;
    this._workflowData = null;
    localStorage.removeItem(this._STORE_KEY);
  },

  _validationSummary: function (checks) {
    const fails = [];
    for (const [key, check] of Object.entries(checks || {})) {
      if (check && check.status === 'fail') {
        fails.push(key.replace(/_/g, ' '));
      }
    }
    return fails.length ? 'Not ready — fix: ' + fails.join(', ') : 'All checks passed';
  },

  _setRunning: function (code, running) {
    document.querySelectorAll('#yrStages .btn').forEach(b => { if (b && !b.disabled) b.disabled = running; });
  },

  _importWorkflowLog: function () {
    const d = this._workflowData || {};
    if (d.archive_summary) this._log.push({ stage: 'archive_data', status: 'completed', details: 'Previously archived', performed: d.archive_summary.archived_at || null });
    if (d.promotion_summary) this._log.push({ stage: 'execute_promotions', status: 'completed', details: 'Previously executed', performed: null });
    if (d.new_classes && d.new_classes.length) this._log.push({ stage: 'setup_new_year', status: 'completed', details: `${d.new_classes.length} classes`, performed: null });
    if (d.migrated_baselines) this._log.push({ stage: 'migrate_baselines', status: 'completed', details: 'Previously migrated', performed: null });
    if (d.ready_for_new_year) this._log.push({ stage: 'validate_readiness', status: 'completed', details: 'Already validated', performed: null });
  },

  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => yearRolloverController.init());
