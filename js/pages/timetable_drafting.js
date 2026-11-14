/**
 * Timetable drafting on Manage Timetable (pages/manage_timetable.php).
 *
 *  - Generate: checks readiness (allocations, weekly lessons, lesson periods)
 *    for the page's year and term, then asks the server solver to build a
 *    draft (POST /api/schedules/timetable-auto-draft) and reports what it
 *    could not place and why.
 *  - Review drafts: lists the term's drafts, shows a draft as one grid per
 *    class stream, and moves it through submit → approve (by another person)
 *    → publish. Publishing replaces the live timetable for those streams.
 *  - Conflict check and conflict report for the live timetable.
 */
const TimetableDrafting = (() => {
  'use strict';

  const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
  const STATUS = {
    draft: ['Draft', 'secondary'],
    submitted: ['Waiting for approval', 'info'],
    changes_requested: ['Changes requested', 'warning'],
    approved: ['Approved', 'primary'],
    published: ['Published', 'success'],
    cancelled: ['Cancelled', 'dark'],
  };
  const $ = (id) => document.getElementById(id);
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const unwrap = (response) => (response && typeof response === 'object' && 'data' in response && !Array.isArray(response) && response.status ? response.data : response);
  const notify = (message, type = 'info') => window.showNotification?.(message, type);
  const base = () => window.APP_BASE || '';

  function scope() {
    const year = $('academicYearFilter');
    const term = $('academicTermFilter');
    return {
      yearId: Number(year?.value || 0),
      termId: Number(term?.value || 0),
      label: [year?.selectedOptions?.[0]?.textContent, term?.selectedOptions?.[0]?.textContent].filter(Boolean).join(' · '),
    };
  }

  // ---- Generate -----------------------------------------------------------

  async function refreshReadiness() {
    const box = $('generateReadiness');
    const run = $('generateRun');
    const { yearId, termId, label } = scope();
    $('generateTermLabel').textContent = label ? `For ${label}.` : 'Choose a year and term on the page first.';
    $('generateResult').innerHTML = '';
    $('generateOpenDrafts').classList.add('d-none');
    if (!yearId || !termId) {
      box.innerHTML = '<div class="alert alert-warning mb-0">Choose an academic year and term on the page first.</div>';
      run.disabled = true;
      return;
    }
    box.innerHTML = '<span class="text-muted">Checking the set-up…</span>';
    run.disabled = true;
    try {
      const load = unwrap(await window.API.schedules.getTeachingLoad({ academic_year_id: yearId, academic_year_term_id: termId })) || {};
      const chosen = $('generateScope').value;
      const streams = (load.streams || []).filter((s) => inScope(s.class_name, chosen));
      const streamIds = new Set(streams.map((s) => s.academic_year_class_stream_id));
      const lessons = (load.lessons || []).filter((l) => streamIds.has(l.academic_year_class_stream_id));
      const ready = lessons.filter((l) => !l.problem && l.lessons_per_week > 0);
      const counts = { lessons_not_set: 0, no_teacher: 0, several_teachers: 0 };
      lessons.forEach((l) => { if (l.problem) counts[l.problem] += 1; });
      const toPlace = ready.reduce((sum, l) => sum + Number(l.lessons_per_week), 0);
      const items = [
        `<li><strong>${streams.length}</strong> class stream${streams.length === 1 ? '' : 's'}, <strong>${load.lesson_periods_per_week || 0}</strong> lesson periods a week each.</li>`,
        `<li><strong>${toPlace}</strong> lessons ready to place (${ready.length} learning areas with a teacher and weekly lessons).</li>`,
      ];
      if (!load.lessons_ready) items.push('<li class="text-danger">Weekly lessons cannot be read yet: the lessons-per-week database update has not been applied.</li>');
      if (counts.lessons_not_set) items.push(`<li class="text-warning-emphasis">${counts.lessons_not_set} learning areas have no weekly lessons — <a href="${base()}/home.php?route=lesson_periods">set them in Lesson Periods</a>.</li>`);
      if (counts.no_teacher) items.push(`<li class="text-warning-emphasis">${counts.no_teacher} have no subject teacher — <a href="${base()}/home.php?route=assign_subjects_to_teachers">allocate in Subject Teachers</a>.</li>`);
      if (counts.several_teachers) items.push(`<li class="text-warning-emphasis">${counts.several_teachers} have more than one subject teacher; keep one.</li>`);
      if (counts.lessons_not_set || counts.no_teacher || counts.several_teachers) {
        items.push('<li class="text-muted">These are left out of the draft until they are fixed.</li>');
      }
      box.innerHTML = `<ul class="mb-0 ps-3">${items.join('')}</ul>`;
      run.disabled = !load.lessons_ready || toPlace === 0;
    } catch (error) {
      box.innerHTML = `<div class="alert alert-danger mb-0">${esc(error.message || 'Could not check the set-up.')}</div>`;
    }
  }

  function inScope(className, chosen) {
    if (chosen === 'whole_school') return true;
    const name = String(className || '').toLowerCase();
    const lower = /^(play ?group|pp ?[12]\b|grade ?[1-3]\b)/.test(name);
    return chosen === 'lower_primary' ? lower : !lower;
  }

  async function runGenerate() {
    const { yearId, termId } = scope();
    const run = $('generateRun');
    const result = $('generateResult');
    run.disabled = true;
    run.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Generating…';
    result.innerHTML = '';
    try {
      const data = unwrap(await window.API.schedules.autoDraftTimetable({
        academic_year_id: yearId,
        academic_year_term_id: termId,
        scope: $('generateScope').value,
      })) || {};
      result.innerHTML = renderGenerateResult(data);
      if (data.draft_id) {
        $('generateOpenDrafts').classList.remove('d-none');
        $('generateOpenDrafts').onclick = () => {
          bootstrap.Modal.getInstance($('generateTimetableModal'))?.hide();
          openDraftReview(yearId, termId, data.draft_id);
        };
        notify('Timetable draft generated. Review it before submitting.', 'success');
      }
    } catch (error) {
      result.innerHTML = `<div class="alert alert-danger mb-0">${esc(error.message || 'The draft could not be generated.')}</div>`;
    } finally {
      run.disabled = false;
      run.innerHTML = '<i class="bi bi-magic me-1"></i>Generate draft';
    }
  }

  function renderGenerateResult(data) {
    const unplacedTotal = (data.unplaced || []).reduce((sum, u) => sum + Number(u.missing || 0), 0);
    const parts = [];
    if (data.draft_id) {
      parts.push(`<div class="alert ${unplacedTotal ? 'alert-warning' : 'alert-success'} mb-3">
        <strong>${data.placed} lessons placed</strong> across ${data.streams} class streams${unplacedTotal ? `; <strong>${unplacedTotal}</strong> could not be placed` : ', with no clashes'}.
        The draft is saved; open it to review, then submit it for approval.</div>`);
    } else {
      parts.push('<div class="alert alert-warning mb-3">No draft was made: nothing could be timetabled with the current set-up.</div>');
    }
    if ((data.unplaced || []).length) {
      parts.push(`<h6 class="mb-1">Not placed</h6><p class="small text-muted mb-1">The class or the teacher had no free period left for these. Fewer weekly lessons, another teacher, or more lesson periods will make room.</p>
        <ul class="small">${data.unplaced.map((u) => `<li>${esc(u.class_stream)} · ${esc(u.learning_area)} (${esc(u.teacher)}): ${u.missing} lesson${u.missing === 1 ? '' : 's'}</li>`).join('')}</ul>`);
    }
    if ((data.problems || []).length) {
      parts.push(`<h6 class="mb-1">Left out until the set-up is fixed</h6>
        <ul class="small mb-0">${data.problems.map((p) => `<li><strong>${esc(p.label)}</strong>: ${esc(p.learning_area)} in ${esc(p.class_streams.join(', '))}${p.teachers?.length ? ` (${esc(p.teachers.join(', '))})` : ''}</li>`).join('')}</ul>`);
    }
    return parts.join('');
  }

  function openGenerate() {
    const modal = $('generateTimetableModal');
    if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
  }

  // ---- Review drafts --------------------------------------------------------

  let review = { yearId: 0, termId: 0, drafts: [], openId: null, slots: [] };

  function reviewModal() {
    let el = $('timetableDraftReviewModal');
    if (el) return el;
    el = document.createElement('div');
    el.className = 'modal fade';
    el.id = 'timetableDraftReviewModal';
    el.tabIndex = -1;
    el.setAttribute('aria-labelledby', 'timetableDraftReviewLabel');
    el.innerHTML = `<div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
      <div class="modal-header"><div><h5 class="modal-title" id="timetableDraftReviewLabel"><i class="bi bi-clipboard-check me-2"></i>Timetable drafts</h5>
      <small class="text-muted" id="tdrScope"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body"><div id="tdrList"></div><div id="tdrDetail" class="mt-3"></div></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary d-none" id="tdrPrint"><i class="bi bi-printer me-1"></i>Print draft</button>
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
    </div></div>`;
    document.body.appendChild(el);
    $('tdrPrint').addEventListener('click', printDraft);
    return el;
  }

  async function openDraftReview(yearId, termId, openId = null) {
    const s = scope();
    review = { yearId: Number(yearId || s.yearId), termId: Number(termId || s.termId), drafts: [], openId, slots: [] };
    const modal = reviewModal();
    $('tdrScope').textContent = s.label || '';
    $('tdrDetail').innerHTML = '';
    $('tdrPrint').classList.add('d-none');
    bootstrap.Modal.getOrCreateInstance(modal).show();
    await loadDrafts();
    if (openId) await showDraft(openId);
  }

  async function loadDrafts() {
    const list = $('tdrList');
    if (!review.termId) {
      list.innerHTML = '<p class="text-muted mb-0">Choose a year and term on the page first.</p>';
      return;
    }
    list.innerHTML = '<p class="text-muted mb-0">Loading drafts…</p>';
    try {
      const rows = unwrap(await window.API.schedules.listTimetableDrafts({ academic_year_term_id: review.termId })) || [];
      review.drafts = Array.isArray(rows) ? rows : [];
    } catch (error) {
      list.innerHTML = `<div class="alert alert-danger mb-0">${esc(error.message || 'Could not load drafts.')}</div>`;
      return;
    }
    if (!review.drafts.length) {
      list.innerHTML = '<p class="text-muted mb-0">No drafts for this term yet. Use Generate to make one.</p>';
      return;
    }
    list.innerHTML = `<div class="table-responsive"><table class="table table-sm align-middle mb-0">
      <thead><tr><th scope="col">Draft</th><th scope="col">Lessons</th><th scope="col">Status</th><th scope="col">Updated</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
      <tbody>${review.drafts.map((d) => {
        const [label, tone] = STATUS[d.status] || [d.status, 'secondary'];
        return `<tr${Number(d.id) === Number(review.openId) ? ' class="table-active"' : ''}>
          <td>${esc(d.title)}</td><td>${Number(d.entry_count || 0)}</td>
          <td><span class="badge bg-${tone}">${esc(label)}</span></td>
          <td class="small text-muted">${esc(String(d.updated_at || '').slice(0, 16))}</td>
          <td class="text-end"><div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-primary" data-tdr-open="${Number(d.id)}">View</button>
            ${actionButtons(d)}
          </div></td></tr>`;
      }).join('')}</tbody></table></div>`;
    list.querySelectorAll('[data-tdr-open]').forEach((b) => b.addEventListener('click', () => showDraft(Number(b.dataset.tdrOpen))));
    list.querySelectorAll('[data-tdr-action]').forEach((b) => b.addEventListener('click', () => transition(Number(b.dataset.tdrId), b.dataset.tdrAction)));
  }

  function actionButtons(draft) {
    const me = Number(window.AuthContext?.getUser?.()?.id || window.AuthContext?.getUser?.()?.user_id || 0);
    const mine = Number(draft.created_by) === me;
    const btn = (action, label, tone, title = '') => `<button type="button" class="btn btn-outline-${tone}" data-tdr-id="${Number(draft.id)}" data-tdr-action="${action}"${title ? ` title="${esc(title)}"` : ''}>${label}</button>`;
    switch (draft.status) {
      case 'draft':
      case 'changes_requested':
        return btn('submit', 'Submit', 'success');
      case 'submitted':
        return (mine ? '' : btn('approve', 'Approve', 'success')) + btn('request_changes', 'Request changes', 'warning')
          + (mine ? '<span class="btn btn-outline-secondary disabled" title="Someone else must approve a draft you made">Awaiting another approver</span>' : '');
      case 'approved':
        return btn('publish', 'Publish', 'primary', 'Replaces the live timetable for these class streams');
      default:
        return '';
    }
  }

  async function transition(id, action) {
    const draft = review.drafts.find((d) => Number(d.id) === id);
    const prompts = {
      submit: ['Submit for approval', `Submit “${draft?.title}” for approval? It can no longer be edited unless changes are requested.`, 'Submit', false],
      approve: ['Approve timetable', `Approve “${draft?.title}”? It can then be published.`, 'Approve', false],
      request_changes: ['Request changes', `Send “${draft?.title}” back for changes?`, 'Request changes', false],
      publish: ['Publish timetable', `Publish “${draft?.title}”? It replaces the live timetable for its class streams this term.`, 'Publish', true],
    };
    const [title, text, confirmText, danger] = prompts[action] || [];
    if (!title || !(await window.confirmAction?.(title, text, { confirmText, danger }))) return;
    try {
      await window.API.schedules.transitionTimetableDraft({ id, action });
      notify({ submit: 'Draft submitted for approval.', approve: 'Draft approved.', request_changes: 'Changes requested.', publish: 'Timetable published.' }[action], 'success');
      await loadDrafts();
      if (review.openId === id) await showDraft(id);
      if (action === 'publish') window.timetableController?.loadTimetable?.();
    } catch (error) {
      notify(error.message || 'The draft could not be updated.', 'error');
    }
  }

  async function lessonSlots() {
    if (review.slots.length) return review.slots;
    const rows = unwrap(await window.API.schedules.getTimeSlots()) || [];
    review.slots = (Array.isArray(rows) ? rows : [])
      .map((s) => ({ id: Number(s.id), start: String(s.start_time || '').slice(0, 5), end: String(s.end_time || '').slice(0, 5), type: s.slot_type, label: s.label }))
      .sort((a, b) => a.start.localeCompare(b.start));
    return review.slots;
  }

  async function showDraft(id) {
    review.openId = id;
    const detail = $('tdrDetail');
    detail.innerHTML = '<p class="text-muted">Loading the draft…</p>';
    let draft;
    let slots;
    try {
      [draft, slots] = await Promise.all([window.API.schedules.getTimetableDraft(id).then(unwrap), lessonSlots()]);
    } catch (error) {
      detail.innerHTML = `<div class="alert alert-danger">${esc(error.message || 'Could not load the draft.')}</div>`;
      return;
    }
    const names = draft.names || {};
    const byStream = new Map();
    (draft.entries || []).forEach((e) => {
      const key = Number(e.academic_year_class_stream_id);
      if (!byStream.has(key)) byStream.set(key, new Map());
      byStream.get(key).set(`${e.day_of_week}:${e.time_slot_id}`, e);
    });
    const streamName = (sid) => names.streams?.[sid] || `Stream ${sid}`;
    const streams = [...byStream.keys()].sort((a, b) => streamName(a).localeCompare(streamName(b), undefined, { numeric: true }));
    const grid = (sid) => {
      const cells = byStream.get(sid);
      return `<section class="tdr-stream mb-4"><h6 class="mb-2">${esc(streamName(sid))}</h6>
        <div class="table-responsive"><table class="table table-bordered table-sm mb-0 tdr-grid">
        <thead><tr><th scope="col">Day</th>${slots.map((s) => `<th scope="col" class="text-center${s.type !== 'lesson' ? ' tdr-break' : ''}">${esc(s.start)}<br><small>${esc(s.end)}</small></th>`).join('')}</tr></thead>
        <tbody>${DAYS.map((day, i) => `<tr><th scope="row">${day}</th>${slots.map((s) => {
          if (s.type !== 'lesson') return i === 0 ? `<td class="tdr-break text-center small" rowspan="${DAYS.length}">${esc(s.label || s.type)}</td>` : '';
          const e = cells.get(`${i + 1}:${s.id}`);
          return e ? `<td><strong>${esc(names.learning_areas?.[e.learning_area_id] || 'Lesson')}</strong><br><small class="text-muted">${esc(names.teachers?.[e.teacher_id] || '')}</small></td>` : '<td class="text-muted text-center">·</td>';
        }).join('')}</tr>`).join('')}</tbody></table></div></section>`;
    };
    const [label, tone] = STATUS[draft.status] || [draft.status, 'secondary'];
    detail.innerHTML = `<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <h5 class="mb-0">${esc(draft.title)} <span class="badge bg-${tone} align-middle">${esc(label)}</span></h5>
        ${streams.length > 1 ? `<select class="form-select form-select-sm w-auto" id="tdrStreamPick" aria-label="Class stream"><option value="">All class streams</option>${streams.map((sid) => `<option value="${sid}">${esc(streamName(sid))}</option>`).join('')}</select>` : ''}
      </div>
      <div id="tdrGrids">${streams.length ? streams.map(grid).join('') : '<p class="text-muted">This draft has no lessons.</p>'}</div>`;
    $('tdrStreamPick')?.addEventListener('change', (event) => {
      const pick = Number(event.target.value || 0);
      $('tdrGrids').innerHTML = (pick ? [pick] : streams).map(grid).join('');
    });
    const canPrint = !window.AuthContext?.canPrint || window.AuthContext.canPrint('schedules');
    $('tdrPrint').classList.toggle('d-none', !streams.length || !canPrint);
    await loadDrafts();
  }

  function printDraft() {
    const content = $('tdrDetail')?.innerHTML;
    if (!content) return;
    const w = window.open('', '_blank', 'noopener=no');
    if (!w) {
      notify('Allow pop-ups to print the draft.', 'warning');
      return;
    }
    w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Timetable draft</title><style>
      @page { size: A4 landscape; margin: 10mm; }
      body { font: 11px/1.3 system-ui, sans-serif; color: #111; }
      h5 { font-size: 15px; margin: 0 0 8px; } h6 { font-size: 13px; margin: 0 0 4px; }
      select, .badge { display: none; }
      table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #999; padding: 3px 4px; vertical-align: top; }
      .tdr-break { background: #eee; } .tdr-stream { break-inside: avoid; margin-bottom: 14px; }
    </style></head><body>${content}</body></html>`);
    w.document.close();
    w.focus();
    setTimeout(() => { w.print(); w.close(); }, 300);
  }

  // ---- Live timetable conflicts -------------------------------------------------

  async function checkConflicts() {
    try {
      const data = unwrap(await window.API.schedules.checkTimetableConflicts()) || {};
      const conflicts = data.conflicts || [];
      if (!conflicts.length) {
        notify('No teacher or room is double-booked on the live timetable.', 'success');
        return;
      }
      const text = conflicts.slice(0, 15).map((c) => `• ${c.description}`).join('\n');
      window.infoDialog?.(`${conflicts.length} clash${conflicts.length === 1 ? '' : 'es'} on the live timetable`, text + (conflicts.length > 15 ? `\n…and ${conflicts.length - 15} more.` : ''));
    } catch (error) {
      notify(error.message || 'Could not check the timetable.', 'error');
    }
  }

  async function reportConflict() {
    let el = $('timetableConflictReportModal');
    if (!el) {
      el = document.createElement('div');
      el.className = 'modal fade';
      el.id = 'timetableConflictReportModal';
      el.tabIndex = -1;
      el.innerHTML = `<div class="modal-dialog"><form class="modal-content" id="tcrForm">
        <div class="modal-header"><h5 class="modal-title">Report a timetable problem</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
          <label class="form-label" for="tcrDay">Day</label>
          <select class="form-select mb-3" id="tcrDay"><option value="">Not specific</option>${DAYS.map((d) => `<option value="${d}">${d}</option>`).join('')}</select>
          <label class="form-label" for="tcrText">What is wrong?</label>
          <textarea class="form-control" id="tcrText" rows="4" maxlength="1000" required placeholder="For example: I have Grade 5 Mathematics and Grade 6 Science at 10:25 on Tuesday."></textarea>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Send report</button></div>
      </form></div>`;
      document.body.appendChild(el);
      $('tcrForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const description = $('tcrText').value.trim();
        if (!description) return;
        try {
          await window.API.schedules.reportTimetableConflict({ description, day_of_week: $('tcrDay').value || null, conflict_type: 'other' });
          bootstrap.Modal.getInstance(el)?.hide();
          event.target.reset();
          notify('Thank you. The timetable team has been told.', 'success');
        } catch (error) {
          notify(error.message || 'The report could not be sent.', 'error');
        }
      });
    }
    bootstrap.Modal.getOrCreateInstance(el).show();
  }

  // ---- Planning assistant ---------------------------------------------------
  // One conversation per year, term and class group; the server keeps it.
  const planning = { key: '', sessionId: 0, waiting: false };
  const POLL_MS = 3000;
  const POLL_LIMIT_MS = 120000;

  function planningKey() {
    const { yearId, termId } = scope();
    return `${yearId}:${termId}:${$('generateScope')?.value || ''}`;
  }

  function renderPlanning(messages) {
    const box = $('planningConversation');
    if (!box) return;
    const md = (text) => (window.KingswayMarkdown?.render ? window.KingswayMarkdown.render(text) : `<p>${esc(text)}</p>`);
    box.innerHTML = (messages || []).map((message) => {
      if (message.actor_type !== 'assistant') {
        return `<div class="align-self-end bg-light border rounded px-3 py-2 small" style="max-width:85%">${esc(message.message_text)}</div>`;
      }
      const draft = message.ai_draft?.draft || {};
      const list = (items, title) => (Array.isArray(items) && items.length
        ? `<div class="mt-2"><div class="fw-semibold">${esc(title)}</div><ul class="mb-0 ps-3">${items.map((i) => `<li>${esc(i)}</li>`).join('')}</ul></div>` : '');
      return `<div class="border rounded px-3 py-2 small" style="max-width:95%">${md(draft.body || message.message_text)}`
        + list(draft.questions, 'Questions for you')
        + list(draft.suggestions, 'Suggestions')
        + list(draft.unresolved_constraints, 'Not settled yet')
        + '</div>';
    }).join('');
    box.scrollTop = box.scrollHeight;
  }

  function planningBusy(busy, note = '') {
    planning.waiting = busy;
    const ask = $('planningAsk');
    if (ask) {
      ask.disabled = busy;
      ask.innerHTML = busy
        ? '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Thinking…'
        : '<i class="bi bi-send me-1"></i>Ask';
    }
    if (note) notify(note, 'warning');
  }

  async function loadPlanning() {
    const key = planningKey();
    if (key !== planning.key) {
      planning.key = key;
      planning.sessionId = 0;
      renderPlanning([]);
    }
    if (!planning.sessionId) return null;
    const session = unwrap(await window.API.schedules.getAiTimetablePlanningSession(planning.sessionId)) || {};
    renderPlanning(session.messages || []);
    return session;
  }

  async function askPlanning() {
    const question = ($('planningQuestion')?.value || '').trim();
    const { yearId, termId } = scope();
    if (!question || planning.waiting) return;
    if (!yearId || !termId) { notify('Choose an academic year and term on the page first.', 'warning'); return; }
    await loadPlanning().catch(() => null);
    planningBusy(true);
    try {
      const queued = unwrap(await window.API.schedules.queueAiTimetablePlanning({
        session_id: planning.sessionId || undefined,
        message: question,
        class_band: $('generateScope')?.value || 'upper_primary',
        academic_year_id: yearId,
        academic_year_term_id: termId,
      })) || {};
      planning.sessionId = Number(queued.session_id || planning.sessionId || 0);
      $('planningQuestion').value = '';
      const before = (await loadPlanning())?.messages?.filter((m) => m.actor_type === 'assistant').length || 0;
      const started = Date.now();
      // The answer is generated in the background; check until it arrives.
      while (Date.now() - started < POLL_LIMIT_MS) {
        await new Promise((resolve) => setTimeout(resolve, POLL_MS));
        if (planning.key !== planningKey()) return;
        const session = await loadPlanning().catch(() => null);
        const answers = session?.messages?.filter((m) => m.actor_type === 'assistant').length || 0;
        if (answers > before) { planningBusy(false); return; }
      }
      planningBusy(false, 'The assistant is taking longer than usual. Its answer will appear here when ready; reopen this dialog to check.');
    } catch (error) {
      planningBusy(false);
      notify(error?.message || 'The planning assistant could not take the question.', 'danger');
    }
  }

  function bind() {
    const modal = $('generateTimetableModal');
    if (!modal || modal.dataset.tdBound) return;
    modal.dataset.tdBound = '1';
    modal.addEventListener('show.bs.modal', refreshReadiness);
    modal.addEventListener('show.bs.modal', () => { loadPlanning().catch(() => null); });
    $('generateScope')?.addEventListener('change', refreshReadiness);
    $('generateScope')?.addEventListener('change', () => { loadPlanning().catch(() => null); });
    $('generateRun')?.addEventListener('click', runGenerate);
    $('planningAsk')?.addEventListener('click', askPlanning);
    $('planningQuestion')?.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) askPlanning();
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind, { once: true });
  else bind();

  return { openGenerate, openDraftReview, checkConflicts, reportConflict };
})();

window.TimetableDrafting = TimetableDrafting;
