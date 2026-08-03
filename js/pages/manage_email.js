(function () {
  "use strict";

  const ManageEmailController = {
    data: [],
    filtered: [],
    contacts: [],
    groups: [],
    templates: [],
    currentPage: 1,
    perPage: 10,
    composeModal: null,

    init: async function () {
      if (!document.getElementById("messagesTable")) return;
      await window.AuthContext?.ready();
      if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
        window.location.href = (window.APP_BASE || "") + "/index.php";
        return;
      }
      this.composeModal = new bootstrap.Modal(document.getElementById("composeModal"));
      this.bindEvents();
      await Promise.all([this.loadData(), this.loadContacts(), this.loadGroups(), this.loadTemplates()]);
    },

    bindEvents: function () {
      const self = this;
      this.on("composeBtn", "click", () => self.openCompose());
      this.on("sendBtn", "click", () => self.submitEmail("sent"));
      this.on("saveDraftBtn", "click", () => self.submitEmail("draft"));
      this.on("templatesBtn", "click", () => self.loadTemplates());
      this.on("recipientType", "change", function () { self.toggleRecipientSection(this.value); });
      this.on("contactSearch", "input", function () { self.filterContactList(this.value); });
      this.on("enableReminder", "change", function () {
        document.getElementById("reminderOptions").style.display = this.checked ? "" : "none";
        if (this.checked && !document.getElementById("reminderAfter").value) {
          document.getElementById("reminderAfter").value = "24";
        }
      });
      this.on("messageType", "change", function () { self.suggestTemplate(this.value); });
      this.on("templateSelect", "change", function () { self.applyTemplate(this.value); });
      this.on("clearFiltersBtn", "click", function () {
        document.getElementById("searchFilter").value = "";
        document.getElementById("statusFilter").value = "";
        document.getElementById("categoryFilter").value = "";
        document.getElementById("recipientFilter").value = "";
        document.getElementById("dateFilter").value = "";
        self.currentPage = 1;
        self.applyFilters();
      });
      ["searchFilter", "statusFilter", "categoryFilter", "recipientFilter", "dateFilter"].forEach(function (id) {
        self.on(id, "input", function () { self.currentPage = 1; self.applyFilters(); });
        self.on(id, "change", function () { self.currentPage = 1; self.applyFilters(); });
      });
    },

    loadData: async function () {
      try {
        const response = await window.API.communications.getMessages({ type: "email" });
        this.data = this.unwrapList(response, ["items", "communications", "messages"]);
      } catch (e) {
        console.error("Failed to load emails:", e);
        this.data = [];
      }
      this.applyFilters();
    },

    loadContacts: async function () {
      try {
        const res = await window.API.communications.getContact();
        this.contacts = Array.isArray(res) ? res : (res?.data || []);
      } catch (e) {
        this.contacts = [];
      }
    },

    loadGroups: async function () {
      try {
        const res = await window.API.communications.getGroup();
        this.groups = Array.isArray(res) ? res : (res?.data || []);
        const sel = document.getElementById("groupSelect");
        if (sel) {
          sel.innerHTML = '<option value="">Select group...</option>' +
            this.groups.map(g => `<option value="${g.id}">${this.esc(g.name)}</option>`).join("");
        }
      } catch (e) {
        this.groups = [];
      }
    },

    loadTemplates: async function () {
      try {
        const res = await window.API.communications.getTemplates();
        this.templates = Array.isArray(res) ? res : (res?.data || []);
        const sel = document.getElementById("templateSelect");
        if (sel) {
          sel.innerHTML = '<option value="">No template</option>' +
            this.templates.filter(t => t.template_type === 'email' || !t.template_type)
              .map(t => `<option value="${t.id}">${this.esc(t.name)}</option>`).join("");
        }
        if (!this.templates.length) {
          this.notify("No saved templates found", "info");
        }
      } catch (e) {
        this.templates = [];
      }
    },

    toggleRecipientSection: function (val) {
      document.getElementById("recipientSelector").style.display = "none";
      document.getElementById("groupSelector").style.display = "none";
      document.getElementById("customEmailsDiv").style.display = "none";
      const sel = document.getElementById("specificRecipients");
      sel.innerHTML = "";
      if (val === "specific_class" || val === "specific_parents") {
        document.getElementById("recipientSelector").style.display = "";
        this.populateRecipientList(val);
      } else if (val === "contact_group") {
        document.getElementById("groupSelector").style.display = "";
      } else if (val === "custom_emails") {
        document.getElementById("customEmailsDiv").style.display = "";
      }
    },

    populateRecipientList: function (type) {
      const sel = document.getElementById("specificRecipients");
      sel.innerHTML = "";
      const items = type === "specific_class"
        ? this.contacts.filter(c => c.contact_type === "student" || c.contact_type === "parent" || c.contact_type === "staff")
        : this.contacts.filter(c => c.contact_type === "parent" || c.contact_type === "staff");
      items.forEach(c => {
        const opt = document.createElement("option");
        opt.value = c.id;
        opt.textContent = c.name + (c.email ? " (" + c.email + ")" : c.phone ? " (" + c.phone + ")" : "");
        sel.appendChild(opt);
      });
    },

    filterContactList: function (query) {
      const sel = document.getElementById("specificRecipients");
      const q = query.toLowerCase();
      Array.from(sel.options).forEach(opt => {
        opt.style.display = opt.textContent.toLowerCase().includes(q) ? "" : "none";
      });
    },

    suggestTemplate: function (msgType) {
      const matching = this.templates.find(t => t.category === msgType || t.name.toLowerCase().includes(msgType));
      if (matching) {
        document.getElementById("templateSelect").value = matching.id;
        this.applyTemplate(matching.id);
      }
    },

    applyTemplate: function (templateId) {
      if (!templateId) return;
      const tmpl = this.templates.find(t => t.id == templateId);
      if (tmpl) {
        if (tmpl.template_body) {
          document.getElementById("messageBody").value = tmpl.template_body;
        }
        if (tmpl.subject && !document.getElementById("emailSubject").value) {
          document.getElementById("emailSubject").value = tmpl.subject;
        }
      }
    },

    openCompose: function () {
      document.getElementById("composeForm").reset();
      document.getElementById("recipientSelector").style.display = "none";
      document.getElementById("groupSelector").style.display = "none";
      document.getElementById("customEmailsDiv").style.display = "none";
      document.getElementById("reminderOptions").style.display = "none";
      const sig = document.getElementById("senderSignature");
      if (sig && !sig.value) {
        sig.value = "Kingsway Preparatory School";
      }
      this.composeModal.show();
    },

    collectFormData: function (requestedStatus) {
      const recipientType = this.value("recipientType");
      let recipients = [];
      if (recipientType === "custom_emails") {
        recipients = this.value("customEmails").split(",").map(s => s.trim()).filter(Boolean);
      } else if (recipientType === "contact_group") {
        recipients = [this.value("groupSelect")];
      } else if (recipientType === "specific_class" || recipientType === "specific_parents") {
        recipients = Array.from(document.getElementById("specificRecipients").selectedOptions).map(o => o.value);
      } else {
        recipients = [recipientType];
      }
      const subject = this.value("emailSubject").trim();
      const message = this.value("messageBody").trim();
      const msgType = this.value("messageType");
      const signature = this.value("senderSignature").trim();
      const fullMessage = signature ? message + "\n\n— " + signature : message;
      const scheduleTime = this.value("scheduleTime");
      const priority = this.value("emailPriority") || "normal";
      const trackOpens = document.getElementById("trackOpens")?.checked || false;

      let reminderAt = null;
      if (document.getElementById("enableReminder").checked) {
        const val = parseInt(this.value("reminderAfter")) || 24;
        const unit = this.value("reminderUnit");
        const ms = unit === "days" ? val * 86400000 : val * 3600000;
        if (scheduleTime) {
          reminderAt = new Date(new Date(scheduleTime).getTime() + ms).toISOString().slice(0, 19).replace("T", " ");
        } else {
          reminderAt = new Date(Date.now() + ms).toISOString().slice(0, 19).replace("T", " ");
        }
      }

      const status = scheduleTime ? "scheduled" : requestedStatus;
      return {
        recipients,
        subject,
        message: fullMessage,
        type: "email",
        category: msgType,
        status,
        priority,
        recipient_type: recipientType,
        scheduled_at: scheduleTime || null,
        reminder_at: reminderAt,
        sender_signature: signature,
        track_opens: trackOpens,
        template_id: this.value("templateSelect") || null,
      };
    },

    submitEmail: async function (requestedStatus) {
      const msg = this.value("messageBody").trim();
      const subject = this.value("emailSubject").trim();
      if (!msg || !subject) { this.notify("Subject and message are required", "warning"); return; }
      if (!this.value("recipientType")) { this.notify("Select recipients", "warning"); return; }
      const data = this.collectFormData(requestedStatus);
      const sendBtn = document.getElementById("sendBtn");
      sendBtn.disabled = true;
      sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';
      try {
        const created = await window.API.communications.createCommunication(data);
        const commId = this.extractId(created);
        await this.uploadAttachments(commId);
        this.composeModal.hide();
        this.notify(requestedStatus === "draft" ? "Draft saved" : "Email queued successfully", "success");
        await this.loadData();
      } catch (e) {
        console.error("Send error:", e);
        this.notify(e.message || "Failed to send", "error");
      } finally {
        sendBtn.disabled = false;
        sendBtn.innerHTML = '<i class="bi bi-send"></i> Send Email';
      }
    },

    uploadAttachments: async function (communicationId) {
      const input = document.getElementById("emailAttachments");
      if (!input || !input.files || !input.files.length || !communicationId) return;
      const uploads = [];
      for (let i = 0; i < input.files.length; i += 1) {
        const formData = new FormData();
        formData.append("communication_id", communicationId);
        formData.append("file", input.files[i]);
        formData.append("file_name", input.files[i].name);
        uploads.push(window.API.communications.createAttachment(formData));
      }
      await Promise.allSettled(uploads);
    },

    applyFilters: function () {
      const search = this.value("searchFilter").toLowerCase();
      const status = this.value("statusFilter").toLowerCase();
      const category = this.value("categoryFilter").toLowerCase();
      const recipient = this.value("recipientFilter").toLowerCase();
      const dateFilter = this.value("dateFilter");

      this.filtered = this.data.filter(item => {
        const s = String(item.content || item.message || item.subject || "").toLowerCase();
        const st = String(item.status || "").toLowerCase();
        const cat = String(item.category || "").toLowerCase();
        const rt = String(item.recipient_type || item.audience || "").toLowerCase();
        const cd = item.created_at ? String(item.created_at).split(" ")[0] : "";
        if (search && !s.includes(search) && !String(item.subject || "").toLowerCase().includes(search)) return false;
        if (status && st !== status) return false;
        if (category && cat !== category) return false;
        if (recipient && !rt.includes(recipient)) return false;
        if (dateFilter && cd !== dateFilter) return false;
        return true;
      });
      this.renderStats();
      this.renderTable();
      this.renderPagination();
    },

    renderStats: function () {
      const all = this.filtered;
      const today = new Date().toISOString().split("T")[0];
      this.setText("sentToday", all.filter(i => (i.status === "sent" || i.status === "delivered") &&
        String(i.created_at || "").startsWith(today)).length);
      this.setText("pendingCount", all.filter(i => i.status === "pending" || i.status === "draft").length);
      this.setText("scheduledCount", all.filter(i => i.status === "scheduled").length);
      this.setText("failedCount", all.filter(i => i.status === "failed").length);
    },

    renderTable: function () {
      const tbody = document.querySelector("#messagesTable tbody");
      if (!tbody) return;
      if (!this.filtered.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No email records found.</td></tr>';
        return;
      }
      const start = (this.currentPage - 1) * this.perPage;
      const page = this.filtered.slice(start, start + this.perPage);
      const self = this;
      tbody.innerHTML = page.map((row, i) => {
        const idx = start + i;
        const subj = row.subject || "(No Subject)";
        const preview = subj.length > 50 ? subj.substring(0, 50) + "..." : subj;
        const cat = row.category ? row.category.replace(/_/g, " ").replace(/\b\w/g, l => l.toUpperCase()) : "—";
        const badge = { sent: "bg-success", delivered: "bg-success", draft: "bg-secondary", pending: "bg-warning text-dark", scheduled: "bg-info text-dark", failed: "bg-danger" };
        const stCls = badge[row.status] || "bg-light text-dark border";
        return `<tr>
          <td class="small">${self.formatDate(row.created_at || row.scheduled_at)}</td>
          <td><span class="badge bg-light text-dark border">${self.esc(cat)}</span></td>
          <td class="small">${self.esc(row.recipient_type || row.recipient_summary || "—")}</td>
          <td class="small text-truncate" style="max-width:220px">${self.esc(preview)}</td>
          <td><span class="badge ${stCls}">${row.status || "unknown"}</span></td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" data-action="view" data-index="${idx}" title="View"><i class="bi bi-eye"></i></button>
              ${row.status === "failed" || row.status === "draft" ? `<button class="btn btn-outline-warning" data-action="resend" data-index="${idx}" title="Resend"><i class="bi bi-arrow-repeat"></i></button>` : ""}
            </div>
          </td>
        </tr>`;
      }).join("");
      tbody.querySelectorAll("button[data-action='view']").forEach(b => b.addEventListener("click", function () {
        self.viewEmail(Number(this.getAttribute("data-index")));
      }));
      tbody.querySelectorAll("button[data-action='resend']").forEach(b => b.addEventListener("click", function () {
        self.resendEmail(Number(this.getAttribute("data-index")));
      }));
    },

    renderPagination: function () {
      const container = document.getElementById("pagination");
      if (!container) return;
      const total = Math.max(1, Math.ceil(this.filtered.length / this.perPage));
      this.currentPage = Math.min(this.currentPage, total);
      container.innerHTML = "";
      const add = (label, page, disabled) => {
        const li = document.createElement("li");
        li.className = "page-item" + (disabled ? " disabled" : "") + (page === this.currentPage ? " active" : "");
        const a = document.createElement("a");
        a.className = "page-link";
        a.href = "#";
        a.textContent = label;
        if (!disabled) a.addEventListener("click", e => { e.preventDefault(); this.currentPage = page; this.renderTable(); });
        li.appendChild(a);
        container.appendChild(li);
      };
      add("«", this.currentPage - 1, this.currentPage <= 1);
      for (let p = 1; p <= total; p++) add(p, p, false);
      add("»", this.currentPage + 1, this.currentPage >= total);
    },

    viewEmail: function (index) {
      const item = this.filtered[index];
      if (!item) return;
      const subject = item.subject || "(No Subject)";
      const content = String(item.content || item.message || "No content").substring(0, 1600);
      const recipient = item.recipient_summary || item.recipient_type || "—";
      alert("Subject: " + subject + "\nRecipients: " + recipient + "\nType: " + (item.category || "—") + "\nStatus: " + (item.status || "—") + "\n\n" + content);
    },

    resendEmail: async function (index) {
      const item = this.filtered[index];
      if (!item || !confirm("Resend this email?")) return;
      const id = item.id || item.communication_id;
      if (!id) { this.notify("Missing ID", "error"); return; }
      try {
        const btn = document.querySelector(`button[data-action="resend"][data-index="${index}"]`);
        if (btn) btn.disabled = true;
        const result = await window.API.communications.resendCommunication({ id });
        if (result?.status === "success") this.notify(result.message || "Resent", "success");
        else this.notify(result?.message || "Resend failed", "error");
        await this.loadData();
      } catch (e) {
        console.error(e);
        this.notify("Failed to resend", "error");
      }
    },

    unwrapList: function (response, keys) {
      if (!response) return [];
      if (Array.isArray(response)) return response;
      if (response.data && Array.isArray(response.data)) return response.data;
      for (const k of (keys || [])) {
        if (Array.isArray(response[k])) return response[k];
        if (response.data && Array.isArray(response.data[k])) return response.data[k];
      }
      return [];
    },

    extractId: function (response) {
      if (!response) return null;
      return response.id || response.communication_id || (response.data && (response.data.id || response.data.communication_id)) || null;
    },

    formatDate: function (v) {
      if (!v) return "—";
      const d = new Date(v);
      return isNaN(d.getTime()) ? v : d.toLocaleString("en-KE", { month: "short", day: "2-digit", hour: "2-digit", minute: "2-digit" });
    },

    esc: function (v) { return String(v || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); },
    value: function (id) { const el = document.getElementById(id); return el ? el.value || "" : ""; },
    setText: function (id, v) { const el = document.getElementById(id); if (el) el.textContent = String(v); },
    on: function (id, ev, fn) { const el = document.getElementById(id); if (el) el.addEventListener(ev, fn); },
    notify: function (msg, type) { if (typeof showNotification === "function") showNotification(msg, type || "info"); else console.log(type + ": " + msg); },
  };

  window.ManageEmailController = ManageEmailController;
  document.addEventListener("DOMContentLoaded", () => ManageEmailController.init());
})();
