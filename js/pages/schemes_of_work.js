/**
 * Schemes of Work Page Controller
 * Manages schemes of work CRUD and approval workflow using api.js
 * Integrates with AcademicContext for academic year awareness
 */

const SchemesOfWorkController = (() => {
  // Private state
  const state = {
    schemes: [],
    classes: [],
    subjects: [],
    academicYears: [],
    terms: [],
    pagination: { page: 1, limit: 10, total: 0 },
    summary: { total: 0, approved: 0, pending: 0, overdue: 0 },
    currentAcademicYear: null,
    currentTerm: null,
  };

  const filters = {
    term: "",
    subject: "",
    class_id: "",
    status: "",
  };

  // ---- Helpers ----

  function unwrapPayload(response) {
    if (!response) return response;
    if (response.status && response.data !== undefined) return response.data;
    if (response.data && response.data.data !== undefined)
      return response.data.data;
    return response;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  async function showSuccess(message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "success");
    } else {
      await window.infoDialog('Notice', message);
    }
  }

  async function showError(message) {
    if (window.API && window.API.showNotification) {
      window.API.showNotification(message, "error");
    } else {
      await window.infoDialog('Notice', "Error: " + message);
    }
  }

  function statusBadge(status) {
    const map = {
      approved: "success",
      pending: "warning",
      rejected: "danger",
      overdue: "danger",
    };
    return map[status] || "secondary";
  }

  function formatStatus(status) {
    const map = {
      approved: "Approved",
      pending: "Pending Review",
      rejected: "Rejected",
      overdue: "Overdue",
    };
    return map[status] || status || "-";
  }

  // ---- Data Loading ----

  async function loadReferenceData() {
    try {
      const classResp = await window.API.academic.listClasses();
      const classPayload = unwrapPayload(classResp);
      state.classes = Array.isArray(classPayload) ? classPayload : [];
      populateClassDropdowns();
    } catch (error) {
      console.warn("Failed to load classes", error);
    }

    try {
      // Reference data: cache 24h (stale-while-revalidate) to skip DB re-query.
      const subjectResp = await DataStore.fetchPage('subjects', {
        endpoint: '/academic/subjects-list', storeName: 'reference_subjects',
        ttl: DataStore.DEFAULT_TTL.REFERENCE, strategy: 'stale-while-revalidate'
      });
      const subjectPayload = unwrapPayload(subjectResp);
      state.subjects = Array.isArray(subjectPayload) ? subjectPayload : [];
      populateSubjectDropdowns();
    } catch (error) {
      console.warn("Failed to load subjects", error);
    }

    try {
      const yearResp = await window.API.students.getAllAcademicYears();
      const yearPayload = unwrapPayload(yearResp);
      state.academicYears = Array.isArray(yearPayload) ? yearPayload : [];
      populateYearDropdown();
    } catch (error) {
      console.warn("Failed to load academic years", error);
    }

    try {
      const termResp = await window.API.apiCall("/academic/terms-list", "GET");
      const termPayload = unwrapPayload(termResp) || {};
      state.terms = Array.isArray(termPayload) ? termPayload : termPayload.data || [];
    } catch (error) {
      console.warn("Failed to load terms", error);
    }
  }

  function populateClassDropdowns() {
    const filterSelect = document.getElementById("classFilter");
    const formSelect = document.getElementById("schemeClass");

    state.classes.forEach((cls) => {
      const name = cls.name || cls.class_name;
      if (filterSelect) {
        const opt = document.createElement("option");
        opt.value = cls.id;
        opt.textContent = name;
        filterSelect.appendChild(opt);
      }
      if (formSelect) {
        const opt = document.createElement("option");
        opt.value = cls.id;
        opt.textContent = name;
        formSelect.appendChild(opt);
      }
    });
  }

  function populateSubjectDropdowns() {
    const filterSelect = document.getElementById("subjectFilter");
    const formSelect = document.getElementById("schemeSubject");

    state.subjects.forEach((subj) => {
      const name = subj.name || subj.subject_name;
      if (filterSelect) {
        const opt = document.createElement("option");
        opt.value = subj.id;
        opt.textContent = name;
        filterSelect.appendChild(opt);
      }
      if (formSelect) {
        const opt = document.createElement("option");
        opt.value = subj.id;
        opt.textContent = name;
        formSelect.appendChild(opt);
      }
    });
  }

  function populateYearDropdown() {
    const select = document.getElementById("schemeYear");
    if (!select) return;

    state.academicYears.forEach((year) => {
      const yearCode =
        year.year_code || year.year_name || year.academic_year || "";
      const opt = document.createElement("option");
      opt.value = year.id || yearCode;
      opt.textContent = yearCode;
      if (year.is_current === 1 || year.is_current === true)
        opt.selected = true;
      select.appendChild(opt);
    });
  }

  async function loadSchemes(page = 1) {
    try {
      state.pagination.page = page;

      const params = new URLSearchParams({
        page,
        limit: state.pagination.limit,
      });

      if (filters.term) params.append("term", filters.term);
      if (filters.subject) params.append("subject_id", filters.subject);
      if (filters.class_id) params.append("class_id", filters.class_id);
      if (filters.status) params.append("status", filters.status);

      const resp = await window.API.apiCall(
        `/academic/schemes-of-work?${params.toString()}`,
        "GET",
      );

      const payload = unwrapPayload(resp) || {};
      state.schemes = payload.schemes || payload.data || [];
      if (!Array.isArray(state.schemes)) state.schemes = [];

      state.pagination = payload.pagination || state.pagination;
      state.summary = payload.summary || computeSummary(state.schemes);

      renderSummary();
      renderTable();
      renderPagination();
    } catch (error) {
      console.error("Error loading schemes:", error);
      showError("Failed to load schemes of work");
    }
  }

  function computeSummary(schemes) {
    return {
      total: schemes.length,
      approved: schemes.filter((s) => s.status === "approved").length,
      pending: schemes.filter((s) => s.status === "pending").length,
      overdue: schemes.filter((s) => s.status === "overdue").length,
    };
  }

  // ---- Rendering ----

  function renderSummary() {
    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };
    el("totalSchemes", state.summary.total || 0);
    el("approvedSchemes", state.summary.approved || 0);
    el("pendingSchemes", state.summary.pending || 0);
    el("overdueSchemes", state.summary.overdue || 0);
  }

  function renderTable() {
    const tbody = document.querySelector("#schemesTable tbody");
    if (!tbody) return;

    if (!state.schemes.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8" class="text-center text-muted py-4">No schemes of work found</td>
        </tr>`;
      return;
    }

    tbody.innerHTML = state.schemes
      .map((s) => {
        const subjectName = s.subject_name || s.subject || "-";
        const className = s.class_name || s.class || "-";
        const teacherName =
          s.teacher_name ||
          `${s.first_name || ""} ${s.last_name || ""}`.trim() ||
          "-";
        const term = s.term ? `Term ${s.term}` : "-";
        const topicCount = s.topic_count || 0;
        const status = s.status || "pending";
        const lastUpdated = s.updated_at || s.last_updated || "-";

        return `
          <tr>
            <td>${escapeHtml(subjectName)}</td>
            <td>${escapeHtml(className)}</td>
            <td>${escapeHtml(teacherName)}</td>
            <td>${escapeHtml(term)}</td>
            <td>${topicCount}</td>
            <td><span class="badge bg-${statusBadge(status)}">${formatStatus(status)}</span></td>
            <td>${escapeHtml(lastUpdated)}</td>
            <td>
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-info" onclick="SchemesOfWorkController.viewScheme(${s.id})" title="View">
                  <i class="bi bi-eye"></i>
                </button>
                <button class="btn btn-outline-primary" onclick="SchemesOfWorkController.editScheme(${s.id})" title="Edit">
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-outline-danger" onclick="SchemesOfWorkController.deleteScheme(${s.id})" title="Delete">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </td>
          </tr>`;
      })
      .join("");
  }

  function renderPagination() {
    const container = document.getElementById("pagination");
    if (!container) return;

    const { page, total, limit } = state.pagination;
    const totalPages = Math.ceil(total / limit) || 1;

    let html = "";
    for (let i = 1; i <= totalPages; i++) {
      html += `
        <li class="page-item ${i === page ? "active" : ""}">
          <a class="page-link" href="#" onclick="SchemesOfWorkController.loadPage(${i}); return false;">${i}</a>
        </li>`;
    }
    container.innerHTML = html;
  }

  // ---- CRUD Actions ----

  function openSchemeModal(schemeId = null) {
    const modalEl = document.getElementById("schemeModal");
    const form = document.getElementById("schemeForm");
    if (!modalEl || !form) return;

    form.reset();
    document.getElementById("schemeId").value = "";
    document.getElementById("schemeModalTitle").textContent =
      "Upload Scheme of Work";

    if (schemeId) {
      const record = state.schemes.find((s) => s.id == schemeId);
      if (record) {
        document.getElementById("schemeId").value = record.id;
        document.getElementById("schemeModalTitle").textContent =
          "Edit Scheme of Work";
        document.getElementById("schemeSubject").value =
          record.subject_id || "";
        document.getElementById("schemeClass").value = record.class_id || "";
        document.getElementById("schemeTerm").value = record.term || "";
        document.getElementById("schemeTitle").value = record.title || "";
        document.getElementById("schemeTopics").value = record.topics || "";
        document.getElementById("schemeNotes").value = record.notes || "";
      }
    }

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  }

  async function saveScheme() {
    const schemeId = document.getElementById("schemeId").value;

    const payload = {
      subject_id: document.getElementById("schemeSubject").value,
      class_id: document.getElementById("schemeClass").value,
      term: document.getElementById("schemeTerm").value,
      academic_year_id: document.getElementById("schemeYear").value,
      title: document.getElementById("schemeTitle").value,
      topics: document.getElementById("schemeTopics").value,
      notes: document.getElementById("schemeNotes").value,
    };

    if (
      !payload.subject_id ||
      !payload.class_id ||
      !payload.term ||
      !payload.title
    ) {
      showError("Please fill in all required fields");
      return;
    }

    try {
      if (schemeId) {
        await window.API.apiCall(
          `/academic/schemes-of-work/${schemeId}`,
          "PUT",
          payload,
        );
        showSuccess("Scheme updated successfully");
      } else {
        await window.API.apiCall("/academic/schemes-of-work", "POST", payload);
        showSuccess("Scheme uploaded successfully");
      }

      bootstrap.Modal.getInstance(
        document.getElementById("schemeModal"),
      ).hide();
      await loadSchemes(state.pagination.page);
    } catch (error) {
      console.error("Error saving scheme:", error);
      showError(error.message || "Failed to save scheme");
    }
  }

  function viewScheme(schemeId) {
    const record = state.schemes.find((s) => s.id == schemeId);
    if (!record) return;

    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };

    el("viewSubject", record.subject_name || "-");
    el("viewClass", record.class_name || "-");
    el(
      "viewTeacher",
      record.teacher_name ||
        `${record.first_name || ""} ${record.last_name || ""}`.trim() ||
        "-",
    );
    el("viewTerm", record.term ? `Term ${record.term}` : "-");
    el("viewTopicCount", record.topic_count || 0);

    const statusEl = document.getElementById("viewStatus");
    if (statusEl) {
      statusEl.innerHTML = `<span class="badge bg-${statusBadge(record.status)}">${formatStatus(record.status)}</span>`;
    }

    const topicsEl = document.getElementById("viewTopics");
    if (topicsEl) topicsEl.textContent = record.topics || "No topics listed";

    const notesEl = document.getElementById("viewNotes");
    if (notesEl) notesEl.textContent = record.notes || "No notes";

    const fileSection = document.getElementById("viewFileSection");
    if (fileSection && record.file_url) {
      fileSection.style.display = "block";
      document.getElementById("viewFileLink").href = record.file_url;
    } else if (fileSection) {
      fileSection.style.display = "none";
    }

    // Store current scheme ID for approval actions
    state.currentViewId = schemeId;

    const modal = new bootstrap.Modal(
      document.getElementById("viewSchemeModal"),
    );
    modal.show();
  }

  async function approveScheme() {
    if (!state.currentViewId) return;
    try {
      await window.API.apiCall(
        `/academic/schemes-of-work/approve/${state.currentViewId}`,
        "PUT",
      );
      showSuccess("Scheme approved successfully");
      bootstrap.Modal.getInstance(
        document.getElementById("viewSchemeModal"),
      ).hide();
      await loadSchemes(state.pagination.page);
    } catch (error) {
      showError(error.message || "Failed to approve scheme");
    }
  }

  async function rejectScheme() {
    if (!state.currentViewId) return;
    const reason = await window.promptAction('Input', "Enter reason for rejection:");
    if (reason === null) return;

    try {
      await window.API.apiCall(
        `/academic/schemes-of-work/reject/${state.currentViewId}`,
        "PUT",
        { reason },
      );
      showSuccess("Scheme rejected");
      bootstrap.Modal.getInstance(
        document.getElementById("viewSchemeModal"),
      ).hide();
      await loadSchemes(state.pagination.page);
    } catch (error) {
      showError(error.message || "Failed to reject scheme");
    }
  }

  async function deleteScheme(schemeId) {
    if (!(await window.confirmAction('Confirm Deletion', "Are you sure you want to delete this scheme of work?", { confirmText: 'Delete', danger: true })))
      return;

    try {
      await window.API.apiCall(
        `/academic/schemes-of-work/${schemeId}`,
        "DELETE",
      );
      showSuccess("Scheme deleted successfully");
      await loadSchemes(state.pagination.page);
    } catch (error) {
      showError(error.message || "Failed to delete scheme");
    }
  }

  function exportSchemes() {
    if (!state.schemes.length) {
      showError("No data to export");
      return;
    }

    if (!window.PrintManager) {
      showError("PrintManager not available");
      return;
    }

    window.PrintManager.exportToCSV({
      filename: "schemes_of_work",
      columns: [
        { key: "subject_name", label: "Subject" },
        { key: "class_name", label: "Class" },
        { key: "teacher_name", label: "Teacher" },
        { key: "term_label", label: "Term" },
        { key: "status", label: "Status" },
        { key: "updated_at", label: "Last Updated" },
      ],
      rows: state.schemes.map((scheme) => ({
        ...scheme,
        term_label: scheme.term_name || `Term ${scheme.term_number || scheme.term || ""}`.trim(),
      })),
    });
  }

  // ---- Auto-Generate from CBC Curriculum ----

  function openGenerateModal() {
    const modalEl = document.getElementById("generateSchemeModal");
    if (!modalEl) return;

    const form = document.getElementById("generateSchemeForm");
    if (form) form.reset();

    const emptySelect = (id, first) => {
      const sel = document.getElementById(id);
      if (!sel) return;
      sel.innerHTML = "";
      const opt = document.createElement("option");
      opt.value = "";
      opt.textContent = first;
      sel.appendChild(opt);
    };

    emptySelect("genStrand", "All Strands");
    emptySelect("genSubStrand", "All Sub-Strands");

    populateGenerateClass();
    populateGenerateTerm();
    loadLearningAreasForGenerate();

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  }

  function populateGenerateClass() {
    const select = document.getElementById("genClass");
    if (!select) return;
    state.classes.forEach((cls) => {
      const opt = document.createElement("option");
      opt.value = cls.id;
      opt.textContent = cls.name || cls.class_name;
      select.appendChild(opt);
    });
  }

  function populateGenerateTerm() {
    const select = document.getElementById("genTerm");
    if (!select) return;

    state.terms.forEach((term) => {
      const opt = document.createElement("option");
      opt.value = term.id;
      const year = term.year || term.year_code || term.year_name || "";
      opt.textContent = `${term.name || `Term ${term.term_number}`}${year ? ` - ${year}` : ""}`;
      if (state.currentTerm && term.id == state.currentTerm) opt.selected = true;
      select.appendChild(opt);
    });
  }

  async function loadLearningAreasForGenerate() {
    const select = document.getElementById("genLearningArea");
    if (!select) return;

    try {
      const resp = await window.API.academic.listLearningAreas();
      const payload = unwrapPayload(resp) || {};
      const areas = Array.isArray(payload) ? payload : payload.data || [];
      areas.forEach((area) => {
        const opt = document.createElement("option");
        opt.value = area.id;
        opt.textContent = area.name;
        select.appendChild(opt);
      });
    } catch (error) {
      console.warn("Failed to load learning areas", error);
    }
  }

  async function loadStrandsForGenerate(learningAreaId) {
    const strandSelect = document.getElementById("genStrand");
    const subSelect = document.getElementById("genSubStrand");
    if (!strandSelect) return;

    strandSelect.innerHTML = "";
    const all = document.createElement("option");
    all.value = "";
    all.textContent = "All Strands";
    strandSelect.appendChild(all);
    subSelect.innerHTML = "";
    const allSub = document.createElement("option");
    allSub.value = "";
    allSub.textContent = "All Sub-Strands";
    subSelect.appendChild(allSub);

    if (!learningAreaId) return;

    try {
      const resp = await window.API.apiCall(
        `/academic/strands?learning_area_id=${learningAreaId}`,
        "GET",
      );
      const payload = unwrapPayload(resp) || {};
      const strands = Array.isArray(payload) ? payload : payload.data || [];
      strands.forEach((strand) => {
        const opt = document.createElement("option");
        opt.value = strand.id;
        opt.textContent = strand.name;
        strandSelect.appendChild(opt);
      });
    } catch (error) {
      console.warn("Failed to load strands", error);
    }
  }

  async function loadSubStrandsForGenerate(strandId) {
    const select = document.getElementById("genSubStrand");
    if (!select) return;

    select.innerHTML = "";
    const all = document.createElement("option");
    all.value = "";
    all.textContent = "All Sub-Strands";
    select.appendChild(all);

    if (!strandId) return;

    try {
      const resp = await window.API.apiCall(
        `/academic/sub-strands?strand_id=${strandId}`,
        "GET",
      );
      const payload = unwrapPayload(resp) || {};
      const subStrands = Array.isArray(payload) ? payload : payload.data || [];
      subStrands.forEach((sub) => {
        const opt = document.createElement("option");
        opt.value = sub.id;
        opt.textContent = sub.name;
        select.appendChild(opt);
      });
    } catch (error) {
      console.warn("Failed to load sub-strands", error);
    }
  }

  async function generateSchemes() {
    const classId = document.getElementById("genClass")?.value;
    const learningAreaId = document.getElementById("genLearningArea")?.value;
    const termId = document.getElementById("genTerm")?.value;
    const strandId = document.getElementById("genStrand")?.value;
    const subStrandId = document.getElementById("genSubStrand")?.value;

    if (!classId || !learningAreaId || !termId) {
      showError("Please select class, subject and term");
      return;
    }

    const payload = {
      class_id: parseInt(classId, 10),
      learning_area_id: parseInt(learningAreaId, 10),
      term_id: parseInt(termId, 10),
    };
    if (strandId) payload.strand_id = parseInt(strandId, 10);
    if (subStrandId) payload.sub_strand_ids = [parseInt(subStrandId, 10)];

    const btn = document.getElementById("confirmGenerateBtn");
    const originalText = btn?.innerHTML;
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generating...';
    }

    try {
      const resp = await window.API.apiCall(
        "/academic/schemes-of-work/generate",
        "POST",
        payload,
      );
      const result = unwrapPayload(resp) || {};
      const message =
        result.message || resp?.message || "Scheme of work entries generated";
      showSuccess(message);

      bootstrap.Modal.getInstance(
        document.getElementById("generateSchemeModal"),
      )?.hide();
      await loadSchemes(1);
    } catch (error) {
      console.error("Error generating schemes:", error);
      showError(error.message || "Failed to generate schemes of work");
    } finally {
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = originalText;
      }
    }
  }

  // ---- Event Listeners ----

  function attachEventListeners() {
    document
      .getElementById("uploadSchemeBtn")
      ?.addEventListener("click", () => openSchemeModal());

    document
      .getElementById("generateSchemeBtn")
      ?.addEventListener("click", () => openGenerateModal());

    document
      .getElementById("confirmGenerateBtn")
      ?.addEventListener("click", () => generateSchemes());

    document
      .getElementById("genLearningArea")
      ?.addEventListener("change", (e) => loadStrandsForGenerate(e.target.value));

    document
      .getElementById("genStrand")
      ?.addEventListener("change", (e) => loadSubStrandsForGenerate(e.target.value));

    document
      .getElementById("saveSchemeBtn")
      ?.addEventListener("click", () => saveScheme());

    document
      .getElementById("exportSchemesBtn")
      ?.addEventListener("click", () => exportSchemes());

    document
      .getElementById("approveSchemeBtn")
      ?.addEventListener("click", () => approveScheme());

    document
      .getElementById("rejectSchemeBtn")
      ?.addEventListener("click", () => rejectScheme());

    // Filters
    document.getElementById("termFilter")?.addEventListener("change", (e) => {
      filters.term = e.target.value;
      loadSchemes(1);
    });

    document
      .getElementById("subjectFilter")
      ?.addEventListener("change", (e) => {
        filters.subject = e.target.value;
        loadSchemes(1);
      });

    document.getElementById("classFilter")?.addEventListener("change", (e) => {
      filters.class_id = e.target.value;
      loadSchemes(1);
    });

    document.getElementById("statusFilter")?.addEventListener("change", (e) => {
      filters.status = e.target.value;
      loadSchemes(1);
    });
  }

  // ---- Initialization ----

  async function init() {
    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    
    // Initialize Academic Context if available
    if (window.AcademicContext) {
      // Subscribe to context changes
      window.AcademicContext.subscribe((context, event, data) => {
        if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
          // Reload schemes when academic year or term changes
          loadSchemes();
        }
      });
      
      // Ensure context is loaded
      if (!window.AcademicContext.isLoaded()) {
        await window.AcademicContext.init();
      }
      
      // Get current academic context
      state.currentAcademicYear = window.AcademicContext.getAcademicYearId();
      state.currentTerm = window.AcademicContext.getTermId();
      
      // Update filters to use current context
      if (state.currentTerm) {
        filters.term = state.currentTerm;
      }
    }

    attachEventListeners();
    await loadReferenceData();
    await loadSchemes();
  }

  // ---- Public API ----
  return {
    init,
    refresh: loadSchemes,
    loadPage: loadSchemes,
    viewScheme,
    editScheme: openSchemeModal,
    deleteScheme,
    openGenerateModal,
    generateSchemes,
  };
})();

let _initialized = false;

function initializeSchemesOfWork() {
  if (_initialized) return;
  _initialized = true;
  void SchemesOfWorkController.init().catch((error) => {
    console.error("[SchemesOfWorkController] Page initialization failed:", error);
  });
}

if (window.__APP_BOOTED__) {
  initializeSchemesOfWork();
} else {
  window.addEventListener("kingsway:ready", initializeSchemesOfWork, { once: true });
  document.addEventListener("DOMContentLoaded", initializeSchemesOfWork, { once: true });
}

window.SchemesOfWorkController = SchemesOfWorkController;