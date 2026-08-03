/**
 * Fee Structure Controller
 * Handles fee structure listing, filtering, creating, editing, and managing
 * Permission-aware data display for different user roles
 */

class FeeStructureController {
  constructor() {
    this.currentPage = 1;
    this.itemsPerPage = 20;
    this.currentFilters = {};
    this.editingStructureId = null;
    this.deleteStructureId = null;
    this.duplicateStructureId = null;
    this.feeStructureModal = null;
    this.deleteConfirmModal = null;
    this.duplicateModal = null;
    this.userRole = window.AuthContext?.getRoles?.()?.[0] || "guest";
  }

  /**
   * Initialize the controller
   */
  static async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const controller = new FeeStructureController();
    controller.setupEventListeners();
    controller.loadAcademicYears();
    controller.loadClasses();
    controller.loadFeeStructures();
  }

  /**
   * Setup all event listeners
   */
  setupEventListeners() {
    // Filter buttons
    document
      .getElementById("academicYearFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("classFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("statusFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("searchFeeStructure")
      ?.addEventListener("keyup", () => this.applyFilters());

    // Action buttons
    document
      .getElementById("addFeeStructureBtn")
      ?.addEventListener("click", () => this.openNewStructureModal());
    document
      .getElementById("exportFeeStructuresBtn")
      ?.addEventListener("click", () => this.exportStructures());

    // Select all checkbox
    document
      .getElementById("selectAllCheckbox")
      ?.addEventListener("change", (e) => {
        document.querySelectorAll(".structure-checkbox").forEach((cb) => {
          cb.checked = e.target.checked;
        });
      });

    // Modal buttons
    document
      .getElementById("saveStructureBtn")
      ?.addEventListener("click", () => this.saveStructure());
    document
      .getElementById("confirmDeleteBtn")
      ?.addEventListener("click", () => this.confirmDelete());
    document
      .getElementById("confirmDuplicateBtn")
      ?.addEventListener("click", () => this.confirmDuplicate());

    // Initialize modals
    const modalElement = document.getElementById("feeStructureModal");
    if (modalElement) {
      this.feeStructureModal = new bootstrap.Modal(modalElement);
    }

    const deleteModalElement = document.getElementById("deleteConfirmModal");
    if (deleteModalElement) {
      this.deleteConfirmModal = new bootstrap.Modal(deleteModalElement);
    }

    const duplicateModalElement = document.getElementById(
      "duplicateStructureModal",
    );
    if (duplicateModalElement) {
      this.duplicateModal = new bootstrap.Modal(duplicateModalElement);
    }
  }

  /**
   * Load academic years for filter dropdown
   */
  async loadAcademicYears() {
    try {
      const response = await API.apiCall("/academic/years/list", "GET", null, {});
      if (response.years) {
        const select = document.getElementById("academicYearFilter");
        response.years.forEach((year) => {
          const option = document.createElement("option");
          option.value = year.id;
          option.textContent = year.year;
          select.appendChild(option);
        });
      }
    } catch (error) {
      console.error("Failed to load academic years:", error);
    }
  }

  /**
   * Load classes for filter dropdown
   */
  async loadClasses() {
    try {
      const response = await API.apiCall("/academics/classes/list", "GET", null, {});
      if (response.classes) {
        const select = document.getElementById("classFilter");
        response.classes.forEach((cls) => {
          const option = document.createElement("option");
          option.value = cls.id;
          option.textContent = cls.name;
          select.appendChild(option);
        });
      }
    } catch (error) {
      console.error("Failed to load classes:", error);
    }
  }

  /**
   * Load fee structures with current filters
   */
  async loadFeeStructures(page = 1) {
    const filters = {
      page: page,
      limit: this.itemsPerPage,
      academic_year: document.getElementById("academicYearFilter")?.value || "",
      class_id: document.getElementById("classFilter")?.value || "",
      status: document.getElementById("statusFilter")?.value || "",
      search: document.getElementById("searchFeeStructure")?.value || "",
    };

    // Remove empty filters
    Object.keys(filters).forEach((key) => {
      if (filters[key] === "" || filters[key] === null) {
        delete filters[key];
      }
    });

    try {
      const response = await API.apiCall("/finance/fee-structures/list", "GET", null, filters);
      this.renderFeeStructures(response.fee_structures);
      this.updateStats(response);
      this.renderPagination(response.pagination);
    } catch (error) {
      console.error("Error loading fee structures:", error);
      this.showError("Error loading fee structures");
    }
  }

  /**
   * Render fee structures table
   */
  renderFeeStructures(structures) {
    const tbody = document.getElementById("feeStructuresBody");
    if (!structures || structures.length === 0) {
      tbody.innerHTML = `
                <tr class="text-center">
                    <td colspan="10" class="text-muted py-4">
                        <i class="bi bi-inbox"></i> No fee structures found
                    </td>
                </tr>
            `;
      return;
    }

    tbody.innerHTML = structures
      .map(
        (structure) => `
            <tr>
                <td>
                    <input type="checkbox" class="form-check-input structure-checkbox" value="${structure.id}">
                </td>
                <td>
                    <strong>${structure.level_name || "N/A"}</strong>
                </td>
                <td>${structure.level_name || "N/A"}</td>
                <td>${structure.academic_year || "N/A"}</td>
                <td>${structure.term || "Annual"}</td>
                <td class="text-end">
                    <strong>KES ${this.formatCurrency(structure.total_amount || 0)}</strong>
                </td>
                <td>
                    <span class="badge bg-info">${structure.item_count || 0} items</span>
                </td>
                <td>
                    <span class="status-badge status-${structure.status}">
                        ${this.formatStatus(structure.status)}
                    </span>
                </td>
                <td>${this.formatDate(structure.effective_date || structure.created_at)}</td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" title="View" 
                            onclick="window.FeeStructureController.viewStructure(${structure.id})">
                            <i class="bi bi-eye"></i>
                        </button>
                        ${
                          !["archived", "active"].includes(structure.status)
                            ? `
                            <button class="btn btn-outline-warning" title="Edit" 
                                onclick="window.FeeStructureController.editStructure(${structure.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                        `
                            : ""
                        }
                        ${
                          this.canDeleteStructure(structure)
                            ? `
                            <button class="btn btn-outline-danger" title="Delete" 
                                onclick="window.FeeStructureController.deleteStructure(${structure.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        `
                            : ""
                        }
                        <button class="btn btn-outline-success" title="Duplicate" 
                            onclick="window.FeeStructureController.duplicateStructure(${structure.id})">
                            <i class="bi bi-copy"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `,
      )
      .join("");
  }

  /**
   * View a fee structure
   */
  async viewStructure(structureId) {
    try {
      const response = await API.apiCall(`/api/finance/fee-structures/${structureId}`, "GET");
      this.displayStructureModal(
        response.structure,
        response.fee_items,
        true,
      );
    } catch (error) {
      console.error("Error loading structure:", error);
      this.showError("Error loading structure");
    }
  }

  /**
   * Edit a fee structure
   */
  async editStructure(structureId) {
    try {
      const response = await API.apiCall(`/api/finance/fee-structures/${structureId}`, "GET");
      this.editingStructureId = structureId;
      this.displayStructureModal(
        response.structure,
        response.fee_items,
        false,
      );
    } catch (error) {
      console.error("Error loading structure:", error);
      this.showError("Error loading structure");
    }
  }

  /**
   * Display structure modal with form or view
   */
  displayStructureModal(structure, feeItems, isViewOnly = true) {
    const modalBody = document.getElementById("structureModalBody");
    const saveBtn = document.getElementById("saveStructureBtn");

    const itemsHtml = feeItems
      .map(
        (item) => `
            <div class="fee-structure-item">
                <div>
                    <strong>${item.name}</strong>
                    <p class="text-muted mb-0 small">${item.description || ""}</p>
                </div>
                <div class="text-end">
                    <strong class="text-primary">KES ${this.formatCurrency(item.amount)}</strong>
                </div>
            </div>
        `,
      )
      .join("");

    if (isViewOnly) {
      modalBody.innerHTML = `
                <div class="structure-details">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Level</label>
                            <p class="form-control-plaintext">${structure.level_name}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Academic Year</label>
                            <p class="form-control-plaintext">${structure.academic_year}</p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Status</label>
                            <p class="form-control-plaintext">
                                <span class="status-badge status-${structure.status}">
                                    ${this.formatStatus(structure.status)}
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Total Amount</label>
                            <p class="form-control-plaintext"><strong>KES ${this.formatCurrency(structure.total_amount)}</strong></p>
                        </div>
                    </div>
                    <hr>
                    <h6>Fee Items</h6>
                    <div class="border rounded">
                        ${itemsHtml}
                    </div>
                </div>
            `;
      saveBtn.style.display = "none";
    } else {
      modalBody.innerHTML = `
                <form id="structureForm">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Class</label>
                            <input type="text" class="form-control" value="${structure.level_name}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Academic Year</label>
                            <input type="text" class="form-control" value="${structure.academic_year}" disabled>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="structureStatus">
                                <option value="draft" ${structure.status === "draft" ? "selected" : ""}>Draft</option>
                                <option value="pending_review" ${structure.status === "pending_review" ? "selected" : ""}>Pending Review</option>
                                <option value="active" ${structure.status === "active" ? "selected" : ""}>Active</option>
                                <option value="archived" ${structure.status === "archived" ? "selected" : ""}>Archived</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="structureNotes" rows="2">${structure.notes || ""}</textarea>
                        </div>
                    </div>
                    <hr>
                    <h6>Fee Items</h6>
                    <div class="border rounded mb-3" id="feeItemsList">
                        ${itemsHtml}
                    </div>
                </form>
            `;
      saveBtn.style.display = "block";
    }

    if (this.feeStructureModal) {
      this.feeStructureModal.show();
    }
  }

  /**
   * Save structure changes
   */
  async saveStructure() {
    if (!this.editingStructureId) {
      this.showError("No structure selected for editing");
      return;
    }

    const data = {
      status: document.getElementById("structureStatus")?.value,
      notes: document.getElementById("structureNotes")?.value,
    };

    try {
      await API.apiCall(`/api/finance/fee-structures/${this.editingStructureId}`, "PUT", data);
      this.showSuccess("Fee structure updated successfully");
      this.feeStructureModal?.hide();
      this.loadFeeStructures(this.currentPage);
    } catch (error) {
      console.error("Error saving structure:", error);
      this.showError("Error saving structure");
    }
  }

  /**
   * Delete a fee structure
   */
  deleteStructure(structureId) {
    this.deleteStructureId = structureId;
    if (this.deleteConfirmModal) {
      this.deleteConfirmModal.show();
    }
  }

  /**
   * Confirm delete action
   */
  async confirmDelete() {
    if (!this.deleteStructureId) return;

    try {
      await API.apiCall(`/api/finance/fee-structures/${this.deleteStructureId}`, "DELETE");
      this.showSuccess("Fee structure deleted successfully");
      this.deleteConfirmModal?.hide();
      this.loadFeeStructures(1);
    } catch (error) {
      console.error("Error deleting structure:", error);
      this.showError("Error deleting structure");
    }
  }

  /**
   * Duplicate a fee structure
   */
  async duplicateStructure(structureId) {
    this.duplicateStructureId = structureId;

    // Load available academic years for duplication
    try {
      const response = await API.apiCall("/academic/years/list", "GET", null, {});
      const select = document.getElementById("duplicateYear");
      select.innerHTML = '<option value="">Select Year</option>';
      response.years?.forEach((year) => {
        const option = document.createElement("option");
        option.value = year.id;
        option.textContent = year.year;
        select.appendChild(option);
      });
    } catch (error) {
      console.error("Failed to load academic years:", error);
    }

    if (this.duplicateModal) {
      this.duplicateModal.show();
    }
  }

  /**
   * Confirm duplicate action
   */
  async confirmDuplicate() {
    if (!this.duplicateStructureId) return;

    const targetYear = document.getElementById("duplicateYear")?.value;
    const adjustment = parseFloat(
      document.getElementById("priceAdjustment")?.value || 0,
    );

    if (!targetYear) {
      this.showError("Please select a target academic year");
      return;
    }

    const data = {
      target_academic_year: targetYear,
      price_adjustment: adjustment,
    };

    try {
      const response = await API.apiCall(
        `/api/finance/fee-structures/${this.duplicateStructureId}/duplicate`,
        "POST",
        data,
      );
      this.showSuccess(
        `Fee structure duplicated successfully (${response.items_copied} items)`,
      );
      this.duplicateModal?.hide();
      this.loadFeeStructures(1);
    } catch (error) {
      console.error("Error duplicating structure:", error);
      this.showError("Error duplicating structure");
    }
  }

  /**
   * Open new structure modal
   */
  openNewStructureModal() {
    this.showInfo("Feature coming soon: Create new fee structure");
  }

  /**
   * Apply filters
   */
  applyFilters() {
    this.currentPage = 1;
    this.loadFeeStructures(1);
  }

  /**
   * Export structures to CSV
   */
  async exportStructures() {
    const filters = {
      academic_year: document.getElementById("academicYearFilter")?.value || "",
      class_id: document.getElementById("classFilter")?.value || "",
      status: document.getElementById("statusFilter")?.value || "",
      search: document.getElementById("searchFeeStructure")?.value || "",
    };

    Object.keys(filters).forEach((key) => {
      if (filters[key] === "") delete filters[key];
    });

    try {
      const response = await API.apiCall("/finance/fee-structures/export", "GET", null, filters);
      this.downloadCSV(response);
    } catch (error) {
      console.error("Export error:", error);
    }
  }

  /**
   * Update statistics cards
   */
  updateStats(data) {
    if (data.stats) {
      document.getElementById("totalStructures").textContent =
        data.stats.total || 0;
      document.getElementById("activeStructures").textContent =
        data.stats.active || 0;
      document.getElementById("pendingStructures").textContent =
        data.stats.pending || 0;
      document.getElementById("totalRevenue").textContent =
        "KES " + this.formatCurrency(data.stats.total_revenue || 0);
    }
  }

  /**
   * Render pagination
   */
  renderPagination(pagination) {
    const container = document.getElementById("paginationContainer");
    if (!pagination || pagination.pages <= 1) {
      container.innerHTML = "";
      return;
    }

    let html = "";
    const maxPages = 5;
    const startPage = Math.max(1, pagination.page - Math.floor(maxPages / 2));
    const endPage = Math.min(pagination.pages, startPage + maxPages - 1);

    if (pagination.page > 1) {
      html += `<li class="page-item"><a class="page-link" href="#" onclick="window.FeeStructureController.loadFeeStructures(1)">First</a></li>`;
      html += `<li class="page-item"><a class="page-link" href="#" onclick="window.FeeStructureController.loadFeeStructures(${pagination.page - 1})">Previous</a></li>`;
    }

    for (let i = startPage; i <= endPage; i++) {
      html += `<li class="page-item ${i === pagination.page ? "active" : ""}">
                <a class="page-link" href="#" onclick="window.FeeStructureController.loadFeeStructures(${i})">${i}</a>
            </li>`;
    }

    if (pagination.page < pagination.pages) {
      html += `<li class="page-item"><a class="page-link" href="#" onclick="window.FeeStructureController.loadFeeStructures(${pagination.page + 1})">Next</a></li>`;
      html += `<li class="page-item"><a class="page-link" href="#" onclick="window.FeeStructureController.loadFeeStructures(${pagination.pages})">Last</a></li>`;
    }

    container.innerHTML = html;
    this.currentPage = pagination.page;
  }

  /**
   * Helper: Check if user can delete structure
   */
  canDeleteStructure(structure) {
    return (
      ["draft"].includes(structure.status) &&
      (window.AuthContext?.hasRole?.("school_admin") || window.AuthContext?.hasRole?.("director_owner"))
    );
  }

  /**
   * Helper: Format currency
   */
  formatCurrency(amount) {
    return new Intl.NumberFormat("en-KE", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount);
  }

  /**
   * Helper: Format status
   */
  formatStatus(status) {
    const statuses = {
      draft: "Draft",
      pending_review: "Pending Review",
      active: "Active",
      archived: "Archived",
    };
    return statuses[status] || status;
  }

  /**
   * Helper: Format date
   */
  formatDate(date) {
    if (!date) return "N/A";
    return new Date(date).toLocaleDateString("en-KE", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  }

  /**
   * Show error message
   */
  showError(message) {
    this.showAlert(message, "danger");
  }

  /**
   * Show success message
   */
  showSuccess(message) {
    this.showAlert(message, "success");
  }

  /**
   * Show info message
   */
  showInfo(message) {
    this.showAlert(message, "info");
  }

  /**
   * Show alert
   */
  showAlert(message, type = "info") {
    const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

    const container = document.querySelector(".card-body");
    if (container) {
      const alertElement = document.createElement("div");
      alertElement.innerHTML = alertHtml;
      container.insertBefore(
        alertElement.firstElementChild,
        container.firstChild,
      );

      // Auto-dismiss after 5 seconds
      setTimeout(() => {
        const alert = container.querySelector(".alert");
        if (alert) {
          const bsAlert = new bootstrap.Alert(alert);
          bsAlert.close();
        }
      }, 5000);
    }
  }

  /**
   * Download CSV file
   */
  downloadCSV(data) {
    const csv = this.convertToCSV(data);
    KingswayFileLifecycle.exportText(csv, "fee-structures.csv", "text/csv");
  }

  /**
   * Convert data to CSV format
   */
  convertToCSV(data) {
    // Implement CSV conversion
    return "";
  }
}

window.FeeStructureController = FeeStructureController;

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => FeeStructureController.init().catch(() => {}));
} else {
  FeeStructureController.init().catch(() => {});
}
