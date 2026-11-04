/** AI operations summaries for the transport administration page. */
(function () {
  'use strict';

  const esc = (value) => { const node = document.createElement('span'); node.textContent = String(value ?? ''); return node.innerHTML; };
  const host = () => document.getElementById('aiTransportSummaries');
  let activeJobId = null;
  let pollTimer = null;
  let pollAttempts = 0;

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

  async function load() {
    const target = host();
    if (!target || !window.API?.transport) return;
    try {
      const own = await window.API.transport.getAiOperationsSummaries('own');
      let review = { drafts: [] };
      try { review = await window.API.transport.getAiOperationsSummaries('review'); } catch (_) { /* Review access is optional. */ }
      const drafts = [
        ...(own?.data?.drafts || own?.drafts || []).map((item) => ({ ...item, canReview: false })),
        ...(review?.data?.drafts || review?.drafts || []).map((item) => ({ ...item, canReview: true })),
      ].filter((item) => item.metadata?.subject_type === 'transport_operations_summary');
      const finishedDraft = activeJobId && drafts.find((item) =>
        Number(item.metadata?.queue_job_id) === activeJobId
        && ['pending_approval', 'approved', 'failed'].includes(item.status)
      );
      if (finishedDraft) {
        clearTimeout(pollTimer);
        pollTimer = null;
        pollAttempts = 0;
        activeJobId = null;
        setState(
          finishedDraft.status === 'failed' ? 'The transport summary could not be prepared. Check the job status or try again.' : 'The transport summary is ready for review.',
          finishedDraft.status === 'failed' ? 'danger' : 'success'
        );
      }
      target.innerHTML = drafts.length ? drafts.map((item) => {
        const content = item.draft || {};
        const canApprove = item.canReview && item.status === 'pending_approval';
        return `<div class="col-12"><article class="border rounded p-2">
          <div class="d-flex justify-content-between"><strong>${esc(content.title || 'Transport operations review')}</strong><span class="badge text-bg-secondary">${esc(item.status || 'queued')}</span></div>
          <p class="small mt-2 mb-2">${esc(content.body || 'Summary is being prepared for review.')}</p>
          ${Array.isArray(content.next_steps) && content.next_steps.length ? `<ul class="small mb-2">${content.next_steps.map((step) => `<li>${esc(step)}</li>`).join('')}</ul>` : ''}
          ${canApprove ? `<button class="btn btn-sm btn-success" data-transport-summary-approve="${Number(item.id)}">Approve guidance</button>` : ''}
          ${item.status === 'approved' ? '<div class="small text-info">Approved for staff guidance. Transport records remain authoritative.</div>' : ''}
        </article></div>`;
      }).join('') : '<div class="col-12 small text-muted">No transport operations summary has been requested today.</div>';
      target.querySelectorAll('[data-transport-summary-approve]').forEach((button) => button.addEventListener('click', async () => {
        button.disabled = true;
        try {
          await window.API.transport.approveAiOperationsSummary(button.dataset.transportSummaryApprove);
          window.showNotification?.('Transport guidance approved.', 'success');
          await load();
        } catch (error) {
          window.showNotification?.(error.message || 'Unable to approve transport guidance.', 'danger');
          button.disabled = false;
        }
      }));
    } catch (error) {
      target.innerHTML = `<div class="col-12 small text-warning">${esc(error.message || 'Transport assistance is temporarily unavailable.')}</div>`;
    }
  }

  function watchJob(jobId) {
    clearTimeout(pollTimer);
    activeJobId = Number(jobId) || null;
    pollAttempts = 0;
    if (!activeJobId) {
      setState('The transport summary was queued. Refresh this panel to check its status.');
      return;
    }
    setState('Transport summary queued. Checking for progress…');
    const poll = async () => {
      pollAttempts += 1;
      let job = null;
      try { job = await window.API.nodeJobs?.status?.(activeJobId); } catch (_) { /* Draft list remains available if status polling is temporarily unavailable. */ }
      const status = String(job?.status || '');
      if (['failed', 'cancelled'].includes(status)) {
        activeJobId = null;
        pollTimer = null;
        pollAttempts = 0;
        setState(status === 'failed' ? 'The transport summary could not be prepared. Try again or contact support if it repeats.' : 'The transport summary was cancelled.', status === 'failed' ? 'danger' : 'info');
        await load();
        return;
      }
      setState(status === 'running' || status === 'processing' ? 'Transport summary is being prepared…' : 'Transport summary queued. Checking for progress…');
      await load();
      if (!activeJobId) return;
      if (status === 'done') {
        activeJobId = null;
        pollTimer = null;
        pollAttempts = 0;
        setState('The job completed, but its review draft was not returned. Refresh the page or contact support.', 'danger');
        return;
      }
      if (pollAttempts < 12) pollTimer = setTimeout(poll, 5000);
      else {
        activeJobId = null;
        pollTimer = null;
        pollAttempts = 0;
        setState(`Transport summary status: ${status || 'unknown'}. Refresh this panel to check again.`);
      }
    };
    pollTimer = setTimeout(poll, 1500);
  }

  async function init() {
    const button = document.getElementById('queueAiTransportSummary');
    button?.addEventListener('click', async () => {
      button.disabled = true;
      try {
        const result = await window.API.transport.queueAiOperationsSummary();
        window.showNotification?.('Transport operations summary queued for review.', 'success');
        watchJob(result?.data?.job_id ?? result?.job_id);
        await load();
      } catch (error) {
        window.showNotification?.(error.message || 'Unable to queue the transport summary.', 'danger');
      } finally { button.disabled = false; }
    });
    await load();
    window.APIRealtime?.register?.('ai_transport_summary', { refresh: load }, ['transport', 'ai']);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
