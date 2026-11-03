/** AI exception summaries for the Boarding Reports page. */
(function () {
  'use strict';

  const esc = (value) => { const node = document.createElement('span'); node.textContent = String(value ?? ''); return node.innerHTML; };
  const host = () => document.getElementById('aiBoardingSummaries');
  let initialized = false;
  let pollTimer = null;
  let pollAttempts = 0;
  let activeJobId = null;
  let isPolling = false;

  function setState(message, type = 'info') {
    let state = document.getElementById('aiBoardingSummaryState');
    if (!state) {
      state = document.createElement('div');
      state.id = 'aiBoardingSummaryState';
      state.className = 'small mt-2';
      state.setAttribute('role', 'status');
      host()?.before(state);
    }
    state.className = `small mt-2 ${type === 'danger' ? 'text-danger' : type === 'success' ? 'text-success' : 'text-muted'}`;
    state.textContent = message;
  }

  async function load() {
    const target = host();
    if (!target || !window.API?.boarding) return [];
    target.innerHTML = '<div class="col-12 small text-muted" role="status">Loading boarding reviews…</div>';
    try {
      const own = await window.API.boarding.getAiExceptionSummaries('own');
      let review = { drafts: [] };
      try { review = await window.API.boarding.getAiExceptionSummaries('review'); } catch (_) { /* Review access is optional. */ }
      const drafts = [
        ...(own?.data?.drafts || own?.drafts || []).map((item) => ({ ...item, canReview: false })),
        ...(review?.data?.drafts || review?.drafts || []).map((item) => ({ ...item, canReview: true })),
      ].filter((item) => item.metadata?.subject_type === 'boarding_exception_summary');
      if (pollAttempts > 0 && activeJobId && drafts.some((item) => item.metadata?.queue_job_id && Number(item.metadata.queue_job_id) === activeJobId && item.status === 'pending_approval')) {
        clearTimeout(pollTimer);
        pollTimer = null;
        pollAttempts = 0;
        activeJobId = null;
        isPolling = false;
        setState('Boarding review is ready for staff review.', 'success');
      }
      target.innerHTML = drafts.length ? drafts.map((item) => {
        const content = item.draft || {};
        const canApprove = item.canReview && item.status === 'pending_approval';
        return `<div class="col-12"><article class="border rounded p-2">
          <div class="d-flex justify-content-between"><strong>${esc(content.title || 'Boarding exception review')}</strong><span class="badge text-bg-secondary">${esc(item.status || 'queued')}</span></div>
          <p class="small mt-2 mb-2">${esc(content.body || 'Summary is being prepared for review.')}</p>
          ${Array.isArray(content.next_steps) && content.next_steps.length ? `<ul class="small mb-2">${content.next_steps.map((step) => `<li>${esc(step)}</li>`).join('')}</ul>` : ''}
          ${canApprove ? `<button class="btn btn-sm btn-success" data-boarding-summary-approve="${Number(item.id)}">Approve guidance</button>` : ''}
          ${item.status === 'approved' ? '<div class="small text-info">Approved for staff guidance. Boarding records remain authoritative.</div>' : ''}
        </article></div>`;
      }).join('') : '<div class="col-12 small text-muted">No boarding exception summary has been requested today.</div>';
      target.querySelectorAll('[data-boarding-summary-approve]').forEach((button) => button.addEventListener('click', async () => {
        button.disabled = true;
        try {
          await window.API.boarding.approveAiExceptionSummary(button.dataset.boardingSummaryApprove);
          window.showNotification?.('Boarding guidance approved.', 'success');
          await load();
        } catch (error) {
          window.showNotification?.(error.message || 'Unable to approve boarding guidance.', 'danger');
          button.disabled = false;
        }
      }));
      return drafts;
    } catch (error) {
      target.innerHTML = `<div class="col-12 small text-warning">${esc(error.message || 'Boarding assistance is temporarily unavailable.')}</div>`;
      setState(error.message || 'Boarding assistance is temporarily unavailable.', 'danger');
      return [];
    }
  }

  function watchForDraft(jobId) {
    clearTimeout(pollTimer);
    pollAttempts = 0;
    activeJobId = Number(jobId) || null;
    isPolling = true;
    const poll = async () => {
      pollAttempts += 1;
      await load();
      if (!isPolling) return;
      if (pollAttempts < 12) pollTimer = setTimeout(poll, 5000);
      else {
        pollTimer = null;
        pollAttempts = 0;
        activeJobId = null;
        isPolling = false;
        setState('The job is still processing. Use Refresh reviews to check again.');
      }
    };
    pollTimer = setTimeout(poll, 2000);
  }

  async function init() {
    if (initialized) return;
    initialized = true;
    const button = document.getElementById('queueAiBoardingSummary');
    button?.addEventListener('click', async () => {
      button.disabled = true;
      try {
        const dateFrom = document.getElementById('aiBoardingDateFrom')?.value || '';
        const dateTo = document.getElementById('aiBoardingDateTo')?.value || '';
        if (!dateFrom || !dateTo || dateFrom > dateTo) throw new Error('Choose a valid roll-call date range.');
        const result = await window.API.boarding.queueAiExceptionSummary(dateFrom, dateTo);
        const jobId = result?.data?.job_id ?? result?.job_id;
        setState(`Boarding review for ${dateFrom} to ${dateTo} was queued. This panel will update when it is ready.`);
        watchForDraft(jobId);
      } catch (error) {
        setState(error.message || 'Unable to queue the boarding summary.', 'danger');
        window.showNotification?.(error.message || 'Unable to queue the boarding summary.', 'danger');
      } finally { button.disabled = false; }
    });
    document.getElementById('refreshAiBoardingSummary')?.addEventListener('click', load);
    await load();
    window.APIRealtime?.register?.('ai_boarding_summary', { refresh: load }, ['boarding', 'ai']);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
