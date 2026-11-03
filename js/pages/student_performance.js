/** Student performance overview and individual report controller. */
const studentPerformanceController = (() => {
  const state = { years: [], terms: [], classes: [], streams: [], rows: [], summary: {}, studentId: null, supportJobId: null, supportPollTimer: null, supportPollAttempts: 0 };
  const $ = (id) => document.getElementById(id);
  const payloadOf = (response) => response?.data ?? response ?? {};
  const rowsOf = (response) => {
    const payload = payloadOf(response);
    return Array.isArray(payload) ? payload : Array.isArray(payload.rows) ? payload.rows : [];
  };
  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[character]);
  const setText = (id, value) => { if ($(id)) $(id).textContent = String(value ?? '—'); };

  async function get(path, params = {}) {
    return window.API.apiCall(path, 'GET', null, params, { checkPermission: false });
  }

  function showState({ loading = false, error = '', empty = false } = {}) {
    $('overviewLoading')?.classList.toggle('d-none', !loading);
    $('overviewError')?.classList.toggle('d-none', !error);
    if ($('overviewError')) $('overviewError').textContent = error;
    $('overviewEmpty')?.classList.toggle('d-none', !empty);
    $('overviewCard')?.classList.toggle('d-none', empty && !state.rows.length);
  }

  async function loadReferences() {
    const [years, classes, streams] = await Promise.all([
      get('/academic/years/list'), get('/academic/classes-list'), get('/academic/streams-list'),
    ]);
    state.years = rowsOf(years);
    state.classes = rowsOf(classes);
    state.streams = rowsOf(streams);
    const yearSelect = $('academicYearFilter');
    if (yearSelect) {
      yearSelect.innerHTML = '<option value="">Current Academic Year</option>' + state.years.map((year) => `<option value="${Number(year.id)}">${escapeHtml(year.year_name || year.name || year.year_code)}</option>`).join('');
      const current = state.years.find((year) => Boolean(Number(year.is_current)) || year.status === 'active');
      if (current) yearSelect.value = String(current.id);
    }
    const classSelect = $('classFilter');
    if (classSelect) classSelect.innerHTML = '<option value="">All Classes</option>' + state.classes.map((item) => `<option value="${Number(item.id)}">${escapeHtml(item.name || item.class_name)}</option>`).join('');
    renderStreamOptions();
    await loadTerms();
  }

  function renderStreamOptions() {
    const select = $('streamFilter');
    if (!select) return;
    const classId = $('classFilter')?.value || '';
    const streams = state.streams.filter((stream) => !classId || String(stream.class_id) === classId);
    select.innerHTML = '<option value="">All Streams</option>' + streams.map((stream) => `<option value="${Number(stream.stream_id || stream.id)}">${escapeHtml(stream.stream_name || stream.name)}</option>`).join('');
  }

  async function loadTerms() {
    const yearId = $('academicYearFilter')?.value || '';
    const termsResponse = await get('/academic/terms', yearId ? { academic_year_id: yearId } : {});
    state.terms = rowsOf(termsResponse);
    const select = $('termFilter');
    const previous = select?.value || '';
    if (select) {
      select.innerHTML = '<option value="">All Terms</option>' + state.terms.map((term) => `<option value="${Number(term.id)}">${escapeHtml(term.name || term.term_name)}</option>`).join('');
      if (state.terms.some((term) => String(term.id) === previous)) select.value = previous;
    }
  }

  function currentFilters() {
    const filters = {
      view_mode: $('viewMode')?.value || 'students',
      year_id: $('academicYearFilter')?.value || '',
      term_id: $('termFilter')?.value || '',
      class_id: $('classFilter')?.value || '',
      stream_id: $('streamFilter')?.value || '',
      gender: $('genderFilter')?.value || '',
      search: $('studentSearch')?.value.trim() || '',
      page: 1,
      limit: 500,
    };
    Object.keys(filters).forEach((key) => { if (filters[key] === '') delete filters[key]; });
    return filters;
  }

  async function load() {
    showState({ loading: true });
    const body = $('performanceOverviewBody');
    setOverviewState('Loading performance records…', 'text-center text-muted py-4');
    try {
      const response = await get('/academic/performance-overview', currentFilters());
      const data = payloadOf(response);
      state.rows = Array.isArray(data.rows) ? data.rows : [];
      state.summary = data.summary || {};
      render();
      showState({ empty: state.rows.length === 0 });
    } catch (error) {
      console.error('Student performance overview failed:', error);
      state.rows = [];
      setOverviewState('Could not load performance records.', 'text-center text-danger py-4');
      showState({ error: error.message || 'Could not load performance records.' });
    }
  }

  function setOverviewState(message, tone = 'text-center text-muted py-4') {
    const body = $('performanceOverviewBody');
    const stateRow = $('performanceOverviewState');
    body?.querySelectorAll('[data-performance-row]').forEach((row) => row.remove());
    const cell = stateRow?.querySelector('td');
    if (cell) { cell.className = tone; cell.textContent = message; }
    if (cell) cell.colSpan = ({ students: 9, class: 6, stream: 6, school: 6 }[$('viewMode')?.value || 'students'] || 9);
    if (stateRow) stateRow.hidden = false;
  }

  function render() {
    const view = $('viewMode')?.value || 'students';
    const body = $('performanceOverviewBody');
    const stateRow = $('performanceOverviewState');
    const headers = document.querySelectorAll('[data-performance-head]');
    headers.forEach((row) => { row.hidden = row.dataset.performanceHead !== view; });
    if (!body || !stateRow) return;
    body.querySelectorAll('[data-performance-row]').forEach((row) => row.remove());
    if (!state.rows.length) {
      setOverviewState('No performance records found for the selected filters.');
    } else {
      stateRow.hidden = true;
      const template = document.getElementById('performance' + view.charAt(0).toUpperCase() + view.slice(1) + 'RowTemplate');
      if (!template) return;
      const fragment = document.createDocumentFragment();
      state.rows.forEach((record, index) => {
        const row = template.content.cloneNode(true);
        const fill = (name, value) => { const node = row.querySelector(`[data-fill="${name}"]`); if (node) node.textContent = String(value ?? '—'); };
        const average = record.average_score == null ? '—' : `${Number(record.average_score).toFixed(1)}%`;
        const attendance = `${Number(record.attendance_rate || 0).toFixed(1)}%`;
        if (view === 'students') {
          fill('number', index + 1); fill('admission', record.admission_no || '—'); fill('class', record.class_name || '—'); fill('stream', record.stream_name || '—');
          fill('average', average); fill('grade', record.grade || '—'); fill('attendance', attendance); fill('position', record.position || '—');
          const learner = row.querySelector('[data-fill="student"]'); learner.textContent = record.full_name || '—'; learner.dataset.studentId = String(Number(record.student_id));
        } else if (view === 'class') {
          fill('class', record.class_name || '—'); fill('students', Number(record.total_students || 0)); fill('average', `${Number(record.average_score || 0).toFixed(1)}%`); fill('attendance', attendance); fill('top', record.top_student || '—'); fill('lowest', record.lowest_student || '—');
        } else if (view === 'stream') {
          fill('class', record.class_name || '—'); fill('stream', record.stream_name || '—'); fill('students', Number(record.total_students || 0)); fill('average', `${Number(record.average_score || 0).toFixed(1)}%`); fill('attendance', attendance); fill('top', record.top_student || '—');
        } else {
          fill('scope', record.scope || 'Whole School'); fill('students', Number(record.total_students || 0)); fill('average', `${Number(record.average_score || 0).toFixed(1)}%`); fill('attendance', attendance); fill('top-class', record.top_class || '—'); fill('top-student', record.top_student || '—');
        }
        fragment.appendChild(row);
      });
      body.insertBefore(fragment, stateRow);
    }
    setText('summaryStudents', state.summary.total_students ?? state.rows.reduce((sum, row) => sum + Number(row.total_students ?? 1), 0));
    const average = state.summary.average_score;
    setText('summaryAverage', average == null ? '—' : `${Number(average).toFixed(1)}%`);
    setText('summaryTopStudent', state.summary.top_student || '—');
    setText('summaryBestGroup', state.summary.best_group || '—');
    setText('viewModeBadge', `${view.charAt(0).toUpperCase()}${view.slice(1)} View`);
  }

  async function openStudent(studentId) {
    state.studentId = Number(studentId);
    $('modalLoading')?.classList.remove('d-none');
    $('modalError')?.classList.add('d-none');
    const modal = $('studentPerformanceModal');
    if (modal && window.bootstrap?.Modal) bootstrap.Modal.getOrCreateInstance(modal).show();
    const termSelect = $('modalTerm');
    if (termSelect) {
      termSelect.innerHTML = '<option value="">Current year / all terms</option>' + state.terms.map((term) => `<option value="${Number(term.id)}">${escapeHtml(term.name || term.term_name)}</option>`).join('');
      termSelect.value = $('termFilter')?.value || '';
    }
    try {
      const resultResponse = await get('/academic/student-results', {
        student_id: state.studentId,
        ...(termSelect?.value ? { term_id: termSelect.value } : {}),
      });
      const report = payloadOf(resultResponse);
      const student = report.student || {};
      const summary = report.summary || {};
      const performance = Array.isArray(report.subjects) ? report.subjects : [];
      setText('studentName', student.full_name || student.name || `${student.first_name || ''} ${student.last_name || ''}`.trim());
      setText('admNo', student.admission_no || student.admission_number || '—');
      setText('studentClass', student.class_name || '—');
      setText('stream', student.stream_name || '—');
      setText('overallAvg', summary.percentage == null ? '—' : `${Number(summary.percentage).toFixed(1)}%`);
      setText('overallGrade', summary.grade || '—');
      setText('position', summary.class_rank || summary.stream_rank || '—');
      setText('attendanceRate', summary.attendance_percentage == null ? '—' : `${Number(summary.attendance_percentage).toFixed(1)}%`);
      setText('meanScore', summary.percentage == null ? '—' : `${Number(summary.percentage).toFixed(1)}%`);
      setText('subjectsCount', performance.length);
      const photo = $('studentPhoto');
      if (photo) photo.src = student.photo_url || '';
      const subjects = $('subjectsTableBody');
      const subjectState = $('subjectsTableState');
      const subjectTemplate = $('studentSubjectRowTemplate');
      if (subjects && subjectState && subjectTemplate) {
        subjects.querySelectorAll('[data-student-subject-row]').forEach((row) => row.remove());
        if (!performance.length) { subjectState.hidden = false; subjectState.querySelector('td').textContent = 'No subject results available.'; }
        else {
          subjectState.hidden = true;
          const fragment = document.createDocumentFragment();
          performance.forEach((record) => {
            const row = subjectTemplate.content.cloneNode(true);
            const fill = (name, value) => { row.querySelector(`[data-fill="${name}"]`).textContent = String(value ?? '—'); };
            fill('subject', record.subject_name || record.subject || '—'); fill('score', record.percentage ?? record.score ?? '—'); fill('grade', record.grade || '—'); fill('average', record.class_average ?? '—'); fill('position', record.position ?? '—'); fill('teacher', record.teacher_name || record.teacher || '—'); fill('remarks', record.remarks || '—');
            fragment.appendChild(row);
          });
          subjects.insertBefore(fragment, subjectState);
        }
      }
      $('modalReportContent')?.classList.remove('d-none');
    } catch (error) {
      if ($('modalError')) { $('modalError').textContent = error.message || 'Could not load this learner report.'; $('modalError').classList.remove('d-none'); }
    } finally {
      $('modalLoading')?.classList.add('d-none');
    }
  }

  async function loadAiSupportReviews(scope = 'own') {
    const host = $('aiSupportPlanningReviews');
    const list = $('aiSupportPlanningReviewsList');
    const status = $('aiSupportPlanningReviewsStatus');
    if (!host || !list) return;
    host.hidden = false;
    if (status) status.textContent = 'Loading support review drafts…';
    try {
      const response = await window.API.students.getAiSupportPlanningReviews(scope);
      const drafts = payloadOf(response).drafts || response?.drafts || [];
      list.innerHTML = drafts.length ? drafts.map((draft) => {
        const content = draft.draft || {};
        const canReview = scope === 'review' && draft.status === 'pending_approval';
        return `<div class="col-12"><article class="border rounded p-3 bg-white"><div class="d-flex justify-content-between gap-2"><strong>${escapeHtml(content.title || 'Learner support review')}</strong><span class="badge text-bg-secondary">${escapeHtml(draft.status || 'queued')}</span></div><p class="small mt-2 mb-2">${escapeHtml(content.body || 'Review is being prepared.')}</p>${canReview ? `<button type="button" class="btn btn-sm btn-outline-success" data-ai-support-approve="${Number(draft.id)}">Approve for staff guidance</button>` : ''}<div class="small text-muted mt-2">Advisory only. This does not update learner records.</div></article></div>`;
      }).join('') : '<div class="col-12 small text-muted">No support review drafts in this view.</div>';
      if (status) status.textContent = `${drafts.length} draft${drafts.length === 1 ? '' : 's'} loaded.`;
      list.querySelectorAll('[data-ai-support-approve]').forEach((button) => button.addEventListener('click', async () => {
        button.disabled = true;
        try { await window.API.students.approveAiSupportPlanning(button.dataset.aiSupportApprove); await loadAiSupportReviews('review'); }
        catch (error) { if (status) status.textContent = error.message || 'Unable to approve this review.'; button.disabled = false; }
      }));
      return drafts;
    } catch (error) { list.replaceChildren(); if (status) status.textContent = error.message || 'Unable to load support reviews.'; return []; }
  }

  function watchAiSupportJob(jobId) {
    clearTimeout(state.supportPollTimer);
    state.supportJobId = Number(jobId) || null;
    state.supportPollAttempts = 0;
    const progress = $('aiSupportPlanningStatus');
    if (!state.supportJobId || typeof window.API.nodeJobs?.status !== 'function') {
      if (progress) progress.textContent = 'Support review queued. Refresh your draft list to check its status.';
      return;
    }
    if (progress) progress.textContent = 'Support review queued. Checking for progress…';
    const poll = async () => {
      state.supportPollAttempts += 1;
      let status = '';
      try {
        const response = await window.API.nodeJobs.status(state.supportJobId);
        const job = payloadOf(response);
        status = String(job.status || job.state || '').toLowerCase();
      } catch (_) { /* The review list remains available when status polling is transiently unavailable. */ }
      if (['failed', 'cancelled', 'dead_letter'].includes(status)) {
        state.supportJobId = null;
        state.supportPollTimer = null;
        state.supportPollAttempts = 0;
        await loadAiSupportReviews('own');
        if (progress) progress.textContent = status === 'failed' ? 'The support review could not be completed. You can try again.' : 'The support review was cancelled.';
        return;
      }
      if (progress) progress.textContent = ['running', 'processing'].includes(status) ? 'Preparing the support review…' : 'Support review queued. Checking for progress…';
      const drafts = await loadAiSupportReviews('own');
      if (!state.supportJobId) return;
      const matchingDraft = drafts.find((draft) => Number(draft.metadata?.queue_job_id) === state.supportJobId);
      if (matchingDraft && ['pending_approval', 'approved'].includes(matchingDraft.status)) {
        state.supportJobId = null;
        state.supportPollTimer = null;
        state.supportPollAttempts = 0;
        if (progress) progress.textContent = 'Support review is ready in your draft list.';
        return;
      }
      if (matchingDraft?.status === 'failed') {
        state.supportJobId = null;
        state.supportPollTimer = null;
        state.supportPollAttempts = 0;
        if (progress) progress.textContent = 'The support review could not be completed. You can try again.';
        return;
      }
      if (['done', 'completed'].includes(status)) {
        state.supportJobId = null;
        state.supportPollTimer = null;
        state.supportPollAttempts = 0;
        if (progress) progress.textContent = 'The job completed, but its matching review draft was not returned. Refresh the draft list or contact support.';
        return;
      }
      if (state.supportPollAttempts < 12) state.supportPollTimer = setTimeout(poll, 5000);
      else {
        state.supportJobId = null;
        state.supportPollTimer = null;
        state.supportPollAttempts = 0;
        if (progress) progress.textContent = `Support review status: ${status || 'unknown'}. Refresh your draft list to check again.`;
      }
    };
    state.supportPollTimer = setTimeout(poll, 1500);
  }

  function bindAiSupportPlanning() {
    $('queueAiSupportPlanningBtn')?.addEventListener('click', async (event) => {
      const button = event.currentTarget;
      button.disabled = true;
      if ($('aiSupportPlanningStatus')) $('aiSupportPlanningStatus').textContent = 'Preparing an authorized aggregate support review…';
      try {
        const queued = await window.API.students.queueAiSupportPlanning({ ...currentFilters(), context: 'oversight' });
        const jobId = payloadOf(queued).job_id;
        if ($('aiSupportPlanningStatus')) $('aiSupportPlanningStatus').textContent = jobId ? 'Support review queued…' : 'Support review queued.';
        if (jobId) watchAiSupportJob(jobId);
        else await loadAiSupportReviews('own');
      } catch (error) { if ($('aiSupportPlanningStatus')) $('aiSupportPlanningStatus').textContent = error.message || 'Unable to queue a support review.'; }
      finally { button.disabled = false; }
    });
    $('loadOwnAiSupportReviewsBtn')?.addEventListener('click', () => loadAiSupportReviews('own'));
    $('loadReviewAiSupportReviewsBtn')?.addEventListener('click', () => loadAiSupportReviews('review'));
  }

  function bindFilters() {
    $('viewMode')?.addEventListener('change', load);
    $('academicYearFilter')?.addEventListener('change', async () => { await loadTerms(); await load(); });
    $('termFilter')?.addEventListener('change', load);
    $('classFilter')?.addEventListener('change', () => { renderStreamOptions(); load(); });
    ['streamFilter', 'genderFilter'].forEach((id) => $(id)?.addEventListener('change', load));
    let searchTimer;
    $('studentSearch')?.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(load, 300); });
    $('resetFiltersBtn')?.addEventListener('click', async () => {
      if ($('viewMode')) $('viewMode').value = 'students';
      ['academicYearFilter', 'termFilter', 'classFilter', 'streamFilter', 'genderFilter', 'studentSearch'].forEach((id) => { if ($(id)) $(id).value = ''; });
      await loadTerms();
      await load();
    });
  }

  function exportOverview() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport()) return;
    const rows = state.rows;
    if (!rows.length) return;
    const view = $('viewMode')?.value || 'students';
    const columns = view === 'students'
      ? [['Admission No.', 'admission_no'], ['Learner', 'full_name'], ['Class', 'class_name'], ['Stream', 'stream_name'], ['Average', 'average_score'], ['Grade', 'grade'], ['Attendance', 'attendance_rate'], ['Position', 'position']]
      : Object.keys(rows[0]).map((key) => [key.replaceAll('_', ' '), key]);
    const cell = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
    const csv = [columns.map(([label]) => label), ...rows.map((row) => columns.map(([, key]) => row[key]))].map((row) => row.map(cell).join(',')).join('\r\n');
    window.KingswayFileLifecycle?.exportText?.(csv, 'student_performance.csv', 'text/csv;charset=utf-8;');
  }

  function bindActions() {
    $('exportOverviewBtn')?.addEventListener('click', exportOverview);
    $('printOverviewBtn')?.addEventListener('click', () => {
      if (window.AuthContext?.canPrint && !window.AuthContext.canPrint()) return;
      setText('printDate', new Date().toLocaleDateString());
      window.print();
    });
    $('printStudentReportBtn')?.addEventListener('click', () => {
      if (window.AuthContext?.canPrint && !window.AuthContext.canPrint()) return;
      window.print();
    });
    $('reloadStudentReportBtn')?.addEventListener('click', () => state.studentId && openStudent(state.studentId));
    $('performanceOverviewBody')?.addEventListener('click', (event) => {
      const learner = event.target.closest('[data-student-id]');
      if (learner) openStudent(learner.dataset.studentId);
    });
  }

  async function init() {
    await window.AuthContext?.ready?.();
    bindFilters();
    bindActions();
    bindAiSupportPlanning();
    try {
      await loadReferences();
      await load();
    } catch (error) {
      console.error('Student performance page initialization failed:', error);
      showState({ error: error.message || 'Could not prepare performance filters.' });
    }
    window.APIRealtime?.register?.('student_performance', { refresh: load }, ['students', 'academic']);
  }

  return { init, load, openStudent };
})();

window.studentPerformanceController = studentPerformanceController;
document.addEventListener('DOMContentLoaded', () => studentPerformanceController.init());
