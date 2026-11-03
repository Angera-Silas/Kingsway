/** Governed attendance AI summaries backed by the PHP attendance workflows. */
(function () {
  "use strict";

  const esc = (value) => {
    const el = document.createElement("div");
    el.textContent = String(value ?? "");
    return el.innerHTML;
  };
  const notify = (message, type = "info") => window.showNotification?.(message, type);
  const page = document.getElementById("attendanceReportsPage");
  if (!page || !window.API?.attendance) return;

  let initialized = false;
  let pollTimer = null;
  let pollAttempts = 0;
  let activeJobId = null;

  function setState(message, type = "info") {
    let state = document.getElementById("aiAttendanceState");
    if (!state) {
      state = document.createElement("div");
      state.id = "aiAttendanceState";
      state.className = "small mt-2";
      state.setAttribute("role", "status");
      document.querySelector("#aiAttendanceSummaries")?.before(state);
    }
    state.className = `small mt-2 ${type === "danger" ? "text-danger" : type === "success" ? "text-success" : "text-muted"}`;
    state.textContent = message;
  }

  function draftCard(draft, review) {
    const body = draft.draft || {};
    const id = Number(draft.id) || 0;
    const approve = review && draft.status === "pending_approval" && id > 0;
    const followUp = draft.status === "approved" && id > 0;
    const steps = Array.isArray(body.next_steps)
      ? `<ul class="small mb-2">${body.next_steps.map((step) => `<li>${esc(step)}</li>`).join("")}</ul>`
      : "";
    return `<div class="col-12"><article class="border rounded p-2">
      <div class="d-flex justify-content-between gap-2"><strong>${esc(body.title || "Attendance review")}</strong><span class="badge text-bg-secondary">${esc(draft.status || "queued")}</span></div>
      <p class="small mt-2 mb-2">${esc(body.body || "The review is being prepared. This page will refresh automatically.")}</p>${steps}
      ${approve ? `<button type="button" class="btn btn-sm btn-success me-2" data-ai-attendance-approve="${id}">Approve guidance</button>` : ""}
      ${followUp ? `<div class="small text-info mb-2">Approved as staff guidance. Attendance records remain authoritative.</div><button type="button" class="btn btn-sm btn-outline-primary" data-ai-parent-followup="${id}">Draft parent follow-up</button>` : ""}
    </article></div>`;
  }

  function render(rows, error = false) {
    // listForReview returns metadata/status/id on the row and generated copy
    // under row.draft. Keep the envelope intact for rendering and actions.
    const exceptions = rows.filter((item) => item.metadata?.subject_type === "attendance_exception_summary");
    const lateness = rows.filter((item) => item.metadata?.subject_type === "attendance_lateness_pattern");
    const exceptionsHost = document.getElementById("aiAttendanceSummaries");
    const latenessHost = document.getElementById("aiAttendanceLatenessReviews");
    if (exceptionsHost) {
      exceptionsHost.innerHTML = exceptions.length
        ? exceptions.map((item) => draftCard(item, item.review)).join("")
        : `<div class="col-12 small ${error ? "text-warning" : "text-muted"}">${error ? "Attendance reviews could not be loaded." : "No attendance exception review has been requested."}</div>`;
    }
    if (latenessHost) {
      latenessHost.innerHTML = lateness.length
        ? lateness.map((item) => draftCard(item, item.review)).join("")
        : `<div class="col-12 small ${error ? "text-warning" : "text-muted"}">${error ? "Lateness reviews could not be loaded." : "No lateness review has been requested."}</div>`;
    }
  }

  async function refresh() {
    const exceptionsHost = document.getElementById("aiAttendanceSummaries");
    if (!exceptionsHost) return;
    exceptionsHost.innerHTML = '<div class="col-12 small text-muted" role="status">Loading attendance reviews…</div>';
    try {
      const [own, review] = await Promise.all([
        window.API.attendance.getAiExceptionSummaries("own"),
        window.API.attendance.getAiExceptionSummaries("review").catch(() => ({ data: { drafts: [] } })),
      ]);
      const rows = [
        ...((own?.data?.drafts || own?.drafts || []).map((draft) => ({ ...draft, review: false }))),
        ...((review?.data?.drafts || review?.drafts || []).map((draft) => ({ ...draft, review: true }))),
      ];
      render(rows);
      const finishedDraft = activeJobId && rows.find((item) =>
        Number(item.metadata?.queue_job_id) === activeJobId
        && ["pending_approval", "approved", "failed"].includes(item.status)
      );
      if (pollAttempts > 0 && finishedDraft) {
        clearTimeout(pollTimer);
        pollTimer = null;
        pollAttempts = 0;
        activeJobId = null;
        setState(
          finishedDraft.status === "failed"
            ? "Attendance review could not be prepared. Check the job status or try again."
            : "Attendance review is ready for staff review.",
          finishedDraft.status === "failed" ? "danger" : "success",
        );
      }
    } catch (error) {
      render([], true);
      setState(error.message || "Attendance assistance is temporarily unavailable.", "danger");
    }
  }

  function watchForDraft(jobId) {
    clearTimeout(pollTimer);
    pollAttempts = 0;
    activeJobId = Number(jobId) || null;
    if (!activeJobId) {
      setState("The review was queued. Use Refresh to check its status.");
      return;
    }
    const poll = async () => {
      pollAttempts += 1;
      let jobStatus = null;
      if (window.API.nodeJobs?.status) {
        try {
          jobStatus = await window.API.nodeJobs.status(activeJobId);
        } catch (_) {
          // Draft listing remains a fallback if the job-status endpoint is
          // temporarily unavailable; it is still owner-scoped on the server.
        }
      }
      if (["failed", "cancelled"].includes(String(jobStatus?.status || ""))) {
        activeJobId = null;
        pollAttempts = 0;
        pollTimer = null;
        setState(
          jobStatus.status === "failed" ? "Attendance review could not be prepared. Try again or contact support if it repeats." : "Attendance review was cancelled.",
          jobStatus.status === "failed" ? "danger" : "info",
        );
        return;
      }
      await refresh();
      if (!activeJobId) return;
      if (jobStatus?.status === "done") {
        activeJobId = null;
        pollAttempts = 0;
        pollTimer = null;
        setState("The job completed, but its review draft was not returned. Refresh the page or contact support.", "danger");
        return;
      }
      if (pollAttempts < 12) pollTimer = setTimeout(poll, 5000);
      else {
        pollTimer = null;
        pollAttempts = 0;
        activeJobId = null;
        const status = String(jobStatus?.status || "unknown");
        setState(`Attendance review status: ${status}. Use Refresh to check again.`);
      }
    };
    pollTimer = setTimeout(poll, 2000);
  }

  async function dateRange() {
    // Reuse the report controller's academic-term/custom-range calculation so
    // the AI receives the same period currently displayed in the report.
    const reports = window.attendanceReportsController;
    if (document.getElementById("arPeriod")?.value === "this_term" && !reports?.state?.termRange) {
      await reports?.loadTermRange?.();
    }
    const range = reports?.range?.();
    if (range?.date_from && range?.date_to) return range;
    const today = new Date();
    const localDate = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
    const period = document.getElementById("arPeriod")?.value || "this_month";
    if (period === "this_term") throw new Error("The selected academic term could not be loaded. Refresh the attendance report and try again.");
    if (period === "custom") {
      const dateFrom = document.getElementById("arDateFrom")?.value;
      const dateTo = document.getElementById("arDateTo")?.value;
      if (!dateFrom || !dateTo || dateFrom > dateTo) throw new Error("Choose a valid custom attendance date range first.");
      return { date_from: dateFrom, date_to: dateTo };
    }
    const from = period === "this_week"
      ? new Date(today.getFullYear(), today.getMonth(), today.getDate() - ((today.getDay() + 6) % 7))
      : new Date(today.getFullYear(), today.getMonth(), 1);
    return { date_from: localDate(from), date_to: localDate(today) };
  }

  async function withButton(button, task) {
    if (!button || button.disabled) return;
    button.disabled = true;
    try {
      await task();
    } catch (error) {
      setState(error.message || "Attendance review could not be queued.", "danger");
      notify(error.message || "Attendance review could not be queued", "danger");
    } finally {
      button.disabled = false;
    }
  }

  async function onClick(event) {
    const summary = event.target.closest("#queueAiAttendanceSummary");
    if (summary) {
      await withButton(summary, async () => {
        const today = new Date();
        const date = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`;
        const result = await window.API.attendance.queueAiExceptionSummary(date);
        setState("Today’s attendance exception review was queued. This panel will update when it is ready.");
        watchForDraft(result?.data?.job_id ?? result?.job_id);
      });
      return;
    }

    const lateness = event.target.closest("#queueAiAttendanceLateness, #queueAiLatenessPattern");
    if (lateness) {
      await withButton(lateness, async () => {
        const selectedClass = document.getElementById("arClass")?.value || "";
        const result = await window.API.attendance.queueAiLatenessPattern({ ...await dateRange(), class_name: selectedClass });
        setState(`The displayed period${selectedClass ? ` for ${selectedClass}` : ""}’s lateness review was queued. This panel will update when it is ready.`);
        watchForDraft(result?.data?.job_id ?? result?.job_id);
      });
      return;
    }

    const approve = event.target.closest("[data-ai-attendance-approve]");
    if (approve) {
      await withButton(approve, async () => {
        await window.API.attendance.approveAiExceptionSummary(approve.dataset.aiAttendanceApprove);
        notify("Attendance guidance approved", "success");
        await refresh();
      });
      return;
    }

    const followUp = event.target.closest("[data-ai-parent-followup]");
    if (followUp) {
      await withButton(followUp, async () => {
        const id = Number(followUp.dataset.aiParentFollowup);
        const cards = [...document.querySelectorAll("[data-ai-parent-followup]")];
        const card = cards.find((item) => Number(item.dataset.aiParentFollowup) === id)?.closest("article");
        const facts = card?.querySelector("p")?.textContent || "";
        const result = await window.API.apiCall("/communications/ai-message-draft-queue", "POST", {
          purpose: "Attendance follow-up", audience: "selected_parents", tone: "respectful and supportive",
          facts: `Use placeholders such as [learner name], [class], and [date]. Approved staff guidance: ${facts}`,
          deadline: "", channel: "sms",
        });
        notify(`Parent follow-up queued${result?.data?.job_id ? ` (job ${result.data.job_id})` : ""}. Complete recipient review in Communications.`, "info");
      });
    }
  }

  function init() {
    if (initialized) return;
    initialized = true;
    page.addEventListener("click", onClick);
    document.getElementById("aiAttendanceRefresh")?.addEventListener("click", refresh);
    refresh();
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init, { once: true });
  else init();
})();
