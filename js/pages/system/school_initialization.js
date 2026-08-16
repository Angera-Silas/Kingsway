/**
 * School Initialization Wizard Controller
 * 10-step provisioning wizard backed by the school configuration endpoint
 * (/system/school-config). Persists the mapped fields and records an audit
 * log entry server-side.
 */
(function () {
  "use strict";

  const STEP_COUNT = 10;
  const STEP_FIELD_MAP = {
    school_name: 1,
    school_code: 1,
    school_details: 2,
    academic_structure: 3,
    academic_year: 4,
    year_start: 4,
    terms: 5,
    classes: 6,
    streams: 7,
    grading: 8,
    admin_name: 9,
    admin_email: 9,
  };

  const config = {
    step: 1,
    initialized: false,
  };

  const $ = (sel, root) => (root || document).querySelector(sel);

  const updateProgress = () => {
    const badge = $("#provisionStepBadge");
    const bar = $("#provisionProgress");
    const prev = $("#provisionPrevious");
    if (badge) badge.textContent = `Step ${config.step} of ${STEP_COUNT}`;
    if (bar) bar.style.width = (config.step / STEP_COUNT) * 100 + "%";
    if (prev) prev.disabled = config.step === 1;
  };

  const showStep = (step) => {
    document.querySelectorAll("#schoolProvisioningForm section[data-step]")
      .forEach((section) => {
        section.hidden = Number(section.dataset.step) !== step;
      });
    config.step = step;
    updateProgress();
    renderReview();
  };

  const collectData = () => {
    const form = $("#schoolProvisioningForm");
    const data = {};
    if (!form) return data;
    new FormData(form).forEach((value, key) => {
      data[key] = value;
    });
    return data;
  };

  const renderReview = () => {
    const pre = $("#provisionReview");
    if (!pre) return;
    const data = collectData();
    const lines = [];
    if (data.school_name) lines.push(`School: ${data.school_name} (${data.school_code || "—"})`);
    if (data.academic_year) lines.push(`Academic year: ${data.academic_year}${data.year_start ? ` (from ${data.year_start})` : ""}`);
    if (data.terms) lines.push(`Terms: ${data.terms.split("\n").length} defined`);
    if (data.classes) lines.push(`Classes: ${data.classes.split("\n").length} defined`);
    if (data.streams) lines.push(`Streams: ${data.streams.split("\n").length} defined`);
    if (data.admin_name) lines.push(`Admin: ${data.admin_name} (${data.admin_email || "—"})`);
    pre.textContent = lines.length ? lines.join("\n") : "Nothing entered yet.";
  };

  const mapToConfigPayload = (data) => {
    const payload = {
      school_name: data.school_name || "",
      school_code: data.school_code || "",
      address: data.school_details || "",
      established_year: data.academic_year ? String(data.academic_year) : "",
      principal_name: data.admin_name || "",
    };
    Object.keys(payload).forEach((key) => {
      if (payload[key] === "") delete payload[key];
    });
    return payload;
  };

  const save = async () => {
    const data = collectData();
    const payload = mapToConfigPayload(data);
    const btn = $("#provisionSave");
    if (btn) btn.disabled = true;
    try {
      await window.API.system.updateSchoolConfig(payload);
      showNotification("School configuration saved", "success");
    } catch (err) {
      console.error("school_initialization: save error", err);
      showNotification(err.message || "Failed to save school configuration", "error");
    } finally {
      if (btn) btn.disabled = false;
    }
  };

  const finish = async () => {
    if (!(await window.confirmAction("Finish Setup", "Finish activates the school configuration and records a full audit trail. Continue?", { confirmText: "Finish Setup" }))) {
      return;
    }
    await save();
  };

  const bindEvents = () => {
    const next = $("#provisionNext");
    const prev = $("#provisionPrevious");
    const saveBtn = $("#provisionSave");
    if (next) next.addEventListener("click", () => {
      if (config.step < STEP_COUNT) showStep(config.step + 1);
    });
    if (prev) prev.addEventListener("click", () => {
      if (config.step > 1) showStep(config.step - 1);
    });
    if (saveBtn) saveBtn.addEventListener("click", save);
    if (next) {
      next.textContent = "Finish";
      next.addEventListener("click", () => {
        if (config.step === STEP_COUNT) finish();
      });
    }
  };

  const init = async () => {
    if (config.initialized) return;
    await window.AuthContext?.ready?.();
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    bindEvents();
    showStep(1);
    config.initialized = true;
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
