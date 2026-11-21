/**
 * Transport operations insight for the transport administration page.
 *
 * This panel is intentionally an INSIGHT, not an approval queue:
 *  - exactly one card is shown (the most recently generated summary),
 *  - older drafts are never stacked on the page,
 *  - the card carries no "approve" action - the content is advisory and the
 *    transport register remains the authoritative record,
 *  - opening the page refreshes the insight (a fresh one is generated once per
 *    day so the card is current without piling up history).
 */
(function () {
  'use strict';

  const esc = (value) => {
    const node = document.createElement('span');
    node.textContent = String(value ?? '');
    return node.innerHTML;
  };
  const host = () => document.getElementById('aiTransportSummaries');
  let activeJobId = null;
  let pollTimer = null;
  let pollAttempts = 0;
  let refreshing = false;

  function setState(message, kind = 'info') {
    const target = host();
    if (!target) return;
    let state = document.getElementById('aiTransportSummaryState');
    if (!state) {
      state = document.createElement('div');
      state.id = 'aiTransportSummaryState';
      state.className = 'col-12 small';
      state.setAttribute('role', 'status');
      target.before(state);
    }
    state.className = `small mb-2 ${kind === 'danger' ? 'text-danger' : kind === 'success' ? 'text-success' : 'text-muted'}`;
    state.textContent = message;
  }

  /** Collect all transport summaries we may read, de-duplicated by id. */
  async function collectDrafts() {
    const own = await window.API.transport.getAiOperationsSummaries('own');
    let review = { drafts: [] };
    try { review = await window.API.transport.getAiOperationsSummaries('review'); } catch (_) { /* Review access is optional. */ }
    const byId = new Map();
    [...(own?.data?.drafts || own?.drafts || []), ...(review?.data?.drafts || review?.drafts || [])]
      .filter((item) => item.metadata?.subject_type === 'transport_operations_summary')
      .forEach((item) => byId.set(Number(item.id), item));
    return [...byId.values()].sort((a, b) =>
      String(b.created_at || '').localeCompare(String(a.created_at || '')) || Number(b.id) - Number(a.id)
    );
  }

  function isToday(value) {
    const stamp = String(value || '');
    if (stamp.length < 10) return false;
    const local = new Date();
    const today = `${local.getFullYear()}-${String(local.getMonth() + 1).padStart(2, '0')}-${String(local.getDate()).padStart(2, '0')}`;
    return stamp.slice(0, 10) === today;
  }

  function renderInsight(draft) {
    const target = host();
    if (!target || !draft) return;
    const content = draft.draft || {};
    const generated = String(draft.created_at || '').slice(0, 16).replace('T', ' ');
    const steps = Array.isArray(content.next_steps) ? content.next_steps : [];
    target.innerHTML = `<div class="col-12"><article class="border rounded p-3 bg-light" data-transport-insight="${Number(draft.id)}">
      <div class="d-flex justify-content-between align-items-start gap-2">
        <div>
          <div class="small text-uppercase text-muted">Transport operations insight</div>
          <strong class="d-block mt-1">${esc(content.title || 'Transport operations summary')}</strong>
        </div>
        <span class="badge text-bg-info">Auto-generated</span>
      </div>
      <p class="small mt-3 mb-2">${esc(content.body || 'Summary is being prepared.')}</p>
      ${steps.length ? `<div class="small"><div class="fw-semibold">Suggested review questions</div><ul class="mb-2">${steps.map((step) => `<li>${esc(step)}</li>`).join('')}</ul></div>` : ''}
      <div class="small text-muted mt-2">
        Generated ${esc(generated || 'just now')}. Advisory only &mdash; verify against the transport register; no approval is required and no records were changed.
      </div>
    </article></div>`;
  }

  async function load() {
    const target = host();
    if (!target || !window.API?.transport) return;
    try {
      const drafts = await collectDrafts();

      if (activeJobId) {
        const finishedDraft = drafts.find((item) =>
          Number(item.metadata?.queue_job_id) === activeJobId
          && ['pending_approval', 'approved', 'failed'].includes(item.status)
        );
        if (finishedDraft) {
          clearTimeout(pollTimer);
          pollTimer = null;
          pollAttempts = 0;
          activeJobId = null;
          setState(
            finishedDraft.status === 'failed'
              ? 'The transport insight could not be prepared. Try refreshing.'
              : 'Transport insight refreshed.',
            finishedDraft.status === 'failed' ? 'danger' : 'success'
          );
        }
      }

      const latest = drafts.find((item) => item.status !== 'failed') || null;
      if (latest) renderInsight(latest);
      else target.innerHTML = '<div class="col-12 small text-muted">No transport insight has been generated yet.</div>';
      return drafts;
    } catch (error) {
      target.innerHTML = `<div class="col-12 small text-warning">${esc(error.message || 'Transport insight is temporarily unavailable.')}</div>`;
      return [];
    }
  }

  function watchJob(jobId) {
    clearTimeout(pollTimer);
    activeJobId = Number(jobId) || null;
    pollAttempts = 0;
    if (!activeJobId) {
      setState('The transport insight was queued. Refresh this panel to check its status.');
      return;
    }
    setState('Refreshing the transport insight…');
    const poll = async () => {
      pollAttempts += 1;
      let job = null;
      try { job = await window.API.nodeJobs?.status?.(activeJobId); } catch (_) { /* Draft list remains available if status polling is temporarily unavailable. */ }
      const status = String(job?.status || '');
      if (['failed', 'cancelled'].includes(status)) {
        activeJobId = null;
        pollTimer = null;
        pollAttempts = 0;
        setState(status === 'failed' ? 'The transport insight could not be prepared. Try refreshing again.' : 'The transport insight refresh was cancelled.', status === 'failed' ? 'danger' : 'info');
        await load();
        return;
      }
      setState(status === 'running' || status === 'processing' ? 'Preparing the transport insight…' : 'Transport insight queued. Checking for progress…');
      await load();
      if (!activeJobId) return;
      if (status === 'done') {
        activeJobId = null;
        pollTimer = null;
        pollAttempts = 0;
        setState('The job completed but no insight was returned. Refresh this panel.', 'danger');
        return;
      }
      if (pollAttempts < 12) pollTimer = setTimeout(poll, 5000);
      else {
        activeJobId = null;
        pollTimer = null;
        pollAttempts = 0;
        setState(`Transport insight status: ${status || 'unknown'}. Refresh this panel to check again.`);
      }
    };
    pollTimer = setTimeout(poll, 1500);
  }

  async function refresh({ silent = false } = {}) {
    if (refreshing) return;
    refreshing = true;
    try {
      const result = await window.API.transport.queueAiOperationsSummary();
      if (!silent) window.showNotification?.('Transport insight refreshed.', 'success');
      watchJob(result?.data?.job_id ?? result?.job_id);
    } catch (error) {
      if (!silent) window.showNotification?.(error.message || 'Unable to refresh the transport insight.', 'danger');
    } finally {
      refreshing = false;
    }
  }

  async function init() {
    const button = document.getElementById('queueAiTransportSummary');
    button?.addEventListener('click', async () => {
      button.disabled = true;
      await refresh();
      button.disabled = false;
    });

    const drafts = await load();

    // Auto-generate once per day: opening the page on a new day replaces the
    // previous card with a current insight instead of stacking old drafts.
    if (drafts && !drafts.some((item) => isToday(item.created_at))) {
      await refresh({ silent: true });
    }

    window.APIRealtime?.register?.('ai_transport_summary', { refresh: load }, ['transport', 'ai']);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
