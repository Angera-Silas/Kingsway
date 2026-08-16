/**
 * Manage Communications Hub Page Controller
 * Shows overview of all communication channels with summary stats and recent activity.
 */

(function () {
  "use strict";

  const CommunicationsHubController = {
    data: null,

    init: async function () {
      if (!this.pageExists()) return;

      await window.AuthContext?.ready();
      if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
        window.location.href = (window.APP_BASE || "") + "/index.php";
        return;
      }

      this.bindEvents();
      await this.loadStatistics();
    },

    pageExists: function () {
      return !!document.getElementById("totalCommunications");
    },

    bindEvents: function () {
      const self = this;

      document.getElementById("refreshActivities")?.addEventListener("click", async function () {
        self.loadStatistics();
      });

      document.getElementById("composeMessageBtn")?.addEventListener("click", function () {
        window.location.href = (window.APP_BASE || "") + "/home.php?route=communications/messages_inbox";
      });

      document.getElementById("createInternalRequestBtn")?.addEventListener("click", async function () {
        const { default: Modal } = await import("bootstrap/dist/js/bootstrap.bundle.min.js");
        self.openInternalRequestModal();
      });

      document.querySelectorAll(".channel-card").forEach(function (card) {
        card.addEventListener("mouseenter", function () {
          this.style.transform = "translateY(-2px)";
        });
        card.addEventListener("mouseleave", function () {
          this.style.transform = "";
        });
      });
    },

    loadStatistics: async function () {
      try {
        const response = await window.API.communications.index();
        this.data = response?.data || null;

        this.updateStats();
        this.renderRecentActivity();
      } catch (error) {
        console.error("Communications hub load error:", error);
        this.showNotification("Failed to load statistics", "error");
      }
    },

    updateStats: function () {
      if (!this.data || !this.data.totals) return;

      const t = this.data.totals;

      this.setText("totalCommunications", t.communications || 0);
      this.setText("totalAnnouncements", t.announcements?.total || 0);
      this.setText("unreadConversations", t.messaging?.unread || 0);
      this.setText("scheduledCount", t.by_status?.scheduled || 0);

      const byType = t.by_type || {};
      this.setText("messagesCount", byType.internal || 0);
      this.setText("announcementsCount", byType.notification || 0);
      this.setText("smsCount", byType.sms || 0);
      this.setText("emailCount", byType.email || 0);
      this.setText("whatsappCount", byType.whatsapp || 0);
    },

    renderRecentActivity: function () {
      const tbody = document.getElementById("activitiesTablebody");
      if (!tbody || !this.data?.recent) {
        if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3">No recent activity</td></tr>';
        return;
      }

      const recent = this.data.recent;
      tbody.innerHTML = recent
        .map((item) => {
          const typeLabel = {
            email: "Email",
            sms: "SMS",
            whatsapp: "WhatsApp",
            notification: "Announcement",
            internal: "Message",
            [item.type]: item.type.charAt(0).toUpperCase() + item.type.slice(1),
          }[item.type] || item.type;

          const statusClass = {
            sent: "success",
            draft: "secondary",
            scheduled: "warning",
            failed: "danger",
          }[item.status] || "primary";

          return `
            <tr>
              <td class="fw-medium">${this.escapeHtml(item.subject || "—")}</td>
              <td><span class="badge bg-light text-dark border">${this.escapeHtml(typeLabel)}</span></td>
              <td><span class="badge bg-${statusClass}">${this.escapeHtml(item.status)}</span></td>
              <td class="d-none d-md-table-cell">${this.formatDate(item.created_at)}</td>
            </tr>
          `;
        })
        .join("");

      if (!recent.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3">No recent activity</td></tr>';
      }
    },

    openInternalRequestModal: function () {
      const modalEl = document.getElementById("conversationModal");
      if (!modalEl) return;
      const modal = new bootstrap.Modal(modalEl);
      modal.show();

      document.getElementById("conversationContent").innerHTML = `
        <div class="mb-3">
          <label class="form-label">Request Title</label>
          <input type="text" class="form-control" id="requestTitle" placeholder="e.g. Warning Notice to Student...">
        </div>
        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea class="form-control" id="requestBody" rows="3" placeholder="Describe the issue..."></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Recipient</label>
          <select class="form-select" id="requestRecipient">
            <option value="">Select...</option>
            <option value="staff">All Staff</option>
            <option value="parents">All Parents</option>
          </select>
        </div>
      `;
    },

    setText: function (id, text) {
      const el = document.getElementById(id);
      if (el) el.textContent = text;
    },

    escapeHtml: function (value) {
      if (value === null || value === undefined) return "";
      return String(value).replace(/[&<>"']/g, (m) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      })[m]);
    },

    formatDate: function (value) {
      if (!value) return "";
      const d = new Date(value);
      if (isNaN(d.getTime())) return "";
      return d.toLocaleString();
    },

    showNotification: function (message, type = "info") {
      if (typeof Kingsway !== "undefined" && Kingsway.showNotification) {
        Kingsway.showNotification(message, type);
      }
    },
  };

  // Auto init when page loads
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
      CommunicationsHubController.init();
    });
  } else {
    CommunicationsHubController.init();
  }

  // Expose for debugging
  window.CommunicationsHubController = CommunicationsHubController;
})();