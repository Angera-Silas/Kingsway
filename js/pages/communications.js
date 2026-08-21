/**
 * Communications — Internal Messages Controller
 *
 * Two-pane internal messaging inbox for system users. Conversations live in
 * the normalised internal_messages schema and are exposed by:
 *   GET  /communications/conversations            → list my conversations
 *   GET  /communications/conversations/{id}       → thread (marks read)
 *   POST /communications/conversations            → create + send first message
 *   POST /communications/conversations/{id}       → reply
 *   GET  /communications/recipients?q={term}      → search users to message
 */

const communicationsController = {
  conversations: [],
  filteredConversations: [],
  activeConversationId: null,
  activeConversation: null,
  messages: [],
  filter: "all",
  search: "",
  selectedRecipients: [],
  recipientResults: [],
  initialized: false,

  // ==========================================================================
  // Lifecycle
  // ==========================================================================
  init: async function () {
    if (this.initialized) return;
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    this.initialized = true;
    this.setupEventListeners();
    await this.loadConversations();
    const requestedConversation = parseInt(new URLSearchParams(window.location.search).get("conversation_id"), 10);
    if (requestedConversation > 0) {
      this.openConversation(requestedConversation);
    }
  },

  setupEventListeners: function () {
    const root = document.getElementById("kwMessaging");
    if (!root) return;

    document.getElementById("btnNewMessage")?.addEventListener("click", () => this.startCompose());
    document.getElementById("btnSendMessage")?.addEventListener("click", () => this.sendNewMessage());
    document.getElementById("btnSendReply")?.addEventListener("click", () => this.sendReply());
    document.getElementById("replyBox")?.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        this.sendReply();
      }
    });
    document.getElementById("msgSearch")?.addEventListener("input", (e) => {
      this.search = e.target.value.trim().toLowerCase();
      this.applyFilters();
    });
    document.getElementById("newRecipientSearch")?.addEventListener("input", (e) => {
      clearTimeout(this._recipientDebounce);
      this._recipientDebounce = setTimeout(() => this.searchRecipients(e.target.value), 300);
    });

    root.querySelectorAll(".kw-msg-filter").forEach((btn) => {
      btn.addEventListener("click", () => {
        this.filter = btn.dataset.filter;
        root.querySelectorAll(".kw-msg-filter").forEach((b) => b.classList.toggle("active", b === btn));
        this.applyFilters();
      });
    });

    root.querySelectorAll("[data-start-compose]").forEach((btn) => {
      btn.addEventListener("click", () => this.startCompose());
    });
    root.querySelector("[data-cancel-compose]")?.addEventListener("click", () => this.cancelCompose());

    root.querySelector("#conversationList")?.addEventListener("click", (e) => {
      const item = e.target.closest("[data-conversation-id]");
      if (item) this.openConversation(parseInt(item.dataset.conversationId, 10));
    });

    root.querySelector("#recipientResults")?.addEventListener("click", (e) => {
      const item = e.target.closest("[data-recipient-id]");
      if (item) this.addRecipient(parseInt(item.dataset.recipientId, 10), item.dataset.name);
    });

    root.querySelector("#selectedRecipients")?.addEventListener("click", (e) => {
      const chip = e.target.closest("[data-remove-recipient]");
      if (chip) this.removeRecipient(parseInt(chip.dataset.removeRecipient, 10));
    });
  },

  // ==========================================================================
  // Conversation list
  // ==========================================================================
  loadConversations: async function () {
    const listEl = document.getElementById("conversationList");
    if (listEl) {
      listEl.innerHTML =
        '<div class="kw-msg-loading text-center py-5"><div class="spinner-border text-success" role="status"></div></div>';
    }
    try {
      const response = await callAPI("/communications/conversations", "GET");
      this.conversations = Array.isArray(response?.data)
        ? response.data
        : Array.isArray(response)
        ? response
        : [];
      this.applyFilters();
    } catch (error) {
      console.error("Error loading conversations:", error);
      if (listEl) {
        listEl.innerHTML =
          '<div class="alert alert-danger m-3">Failed to load conversations. Please refresh.</div>';
      }
      showNotification("Failed to load conversations", "error");
    }
  },

  applyFilters: function () {
    let list = this.conversations;
    if (this.filter === "unread") {
      list = list.filter((c) => parseInt(c.unread_count || 0, 10) > 0);
    } else if (this.filter === "group") {
      list = list.filter((c) => c.conversation_type && c.conversation_type !== "one_on_one");
    }
    if (this.search) {
      const term = this.search;
      list = list.filter((c) =>
        `${c.title || ""} ${c.participant_names || ""} ${c.last_sender_name || ""} ${c.last_message || ""}`
          .toLowerCase()
          .includes(term)
      );
    }
    this.filteredConversations = list;
    this.renderConversationList();
  },

  renderConversationList: function () {
    const listEl = document.getElementById("conversationList");
    if (!listEl) return;

    if (this.filteredConversations.length === 0) {
      listEl.innerHTML =
        '<div class="kw-msg-empty-list">' +
        '<i class="bi bi-inbox"></i>' +
        "<p>No conversations found.</p>" +
        "</div>";
      return;
    }

    listEl.innerHTML = this.filteredConversations
      .map((c) => {
        const id = parseInt(c.id, 10);
        const active = id === this.activeConversationId ? " active" : "";
        const unread = parseInt(c.unread_count || 0, 10);
        const name = this.escapeHtml(c.participant_names || c.title || "Conversation");
        const preview = this.escapeHtml(this.truncate(c.last_message || "No messages yet", 80));
        const time = this.formatListTime(c.last_message_at || c.created_at);
        const sender = c.last_sender_name ? this.escapeHtml(c.last_sender_name) + ": " : "";
        const isGroup = c.conversation_type && c.conversation_type !== "one_on_one";
        const initials = this.avatarInitials(c.participant_names || c.title || "?");
        const colorClass = this.avatarColor(initials);
        const unreadBadge = unread > 0
          ? `<span class="kw-msg-unread">${unread > 99 ? "99+" : unread}</span>`
          : "";
        const groupBadge = isGroup
          ? '<span class="badge rounded-pill kw-badge-group">' + c.conversation_type + "</span>"
          : "";

        return (
          '<div class="kw-msg-item' + active + '" data-conversation-id="' + id + '" role="button" tabindex="0">' +
          '<span class="kw-avatar ' + colorClass + '">' + this.escapeHtml(initials) + "</span>" +
          '<div class="kw-msg-item-body">' +
          '<div class="kw-msg-item-top">' +
          '<span class="kw-msg-item-name">' + name + "</span>" +
          '<span class="kw-msg-item-time">' + time + "</span>" +
          "</div>" +
          '<div class="kw-msg-item-bottom">' +
          '<span class="kw-msg-item-preview">' + sender + preview + "</span>" +
          '<span class="kw-msg-item-meta">' + groupBadge + unreadBadge + "</span>" +
          "</div>" +
          "</div>" +
          "</div>"
        );
      })
      .join("");
  },

  // ==========================================================================
  // Thread
  // ==========================================================================
  openConversation: async function (id) {
    this.activeConversationId = id;
    this.showView("thread");
    this.renderThreadLoading();
    try {
      const response = await callAPI("/communications/conversations/" + id, "GET");
      const data = response?.data || response || {};
      this.activeConversation = data.conversation || {};
      this.messages = Array.isArray(data.messages) ? data.messages : [];
      this.renderThread();
      this.markConversationReadLocally(id);
      this.scrollThreadToBottom();
    } catch (error) {
      console.error("Error loading conversation:", error);
      const el = document.getElementById("threadMessages");
      if (el) el.innerHTML = '<div class="alert alert-danger m-3">Failed to load conversation.</div>';
      showNotification("Failed to load conversation", "error");
    }
  },

  renderThreadLoading: function () {
    const el = document.getElementById("threadMessages");
    if (el) {
      el.innerHTML =
        '<div class="text-center py-5"><div class="spinner-border text-success" role="status"></div></div>';
    }
  },

  renderThread: function () {
    const titleEl = document.getElementById("threadTitle");
    const partsEl = document.getElementById("threadParticipants");
    const msgsEl = document.getElementById("threadMessages");
    if (!titleEl || !partsEl || !msgsEl) return;

    const conv = this.activeConversation || {};
    titleEl.textContent = conv.title || "Conversation";

    const groupBadge = document.getElementById("threadGroupBadge");
    if (groupBadge) {
      const isGroup = conv.conversation_type && conv.conversation_type !== "one_on_one";
      groupBadge.classList.toggle("d-none", !isGroup);
      if (isGroup) groupBadge.textContent = conv.conversation_type;
    }
    partsEl.textContent = conv.participant_names || "";

    if (this.messages.length === 0) {
      msgsEl.innerHTML =
        '<div class="kw-msg-empty-list py-5"><i class="bi bi-chat"></i><p>No messages yet. Say hello!</p></div>';
      return;
    }

    msgsEl.innerHTML = this.messages
      .map((m) => {
        const isMine = parseInt(m.is_mine || 0, 10) === 1;
        const body = this.escapeHtml(m.message_body || m.message || "");
        const bodyHtml = this.linkify(body);
        const sender = this.escapeHtml(m.sender_name || (isMine ? "You" : "Unknown"));
        const time = this.formatBubbleTime(m.created_at);
        const readIcon = isMine
          ? m.read_at
            ? '<i class="bi bi-check2-all kw-msg-read-icon" title="Read"></i>'
            : '<i class="bi bi-check2 kw-msg-read-icon" title="Sent"></i>'
          : "";
        const priorityDot =
          m.priority === "high" || m.priority === "urgent"
            ? '<span class="kw-msg-priority" title="' + this.escapeHtml(m.priority) + '">' +
              (m.priority === "urgent" ? "!!" : "!") +
              "</span>"
            : "";

        return (
          '<div class="kw-msg-row ' + (isMine ? "mine" : "theirs") + '">' +
          '<div class="kw-msg-bubble">' +
          '<div class="kw-msg-bubble-meta">' +
          priorityDot +
          (isMine ? "" : '<span class="kw-msg-bubble-sender">' + sender + "</span>") +
          "</div>" +
          '<div class="kw-msg-bubble-text">' + bodyHtml + "</div>" +
          '<div class="kw-msg-bubble-time">' + time + readIcon + "</div>" +
          "</div>" +
          "</div>"
        );
      })
      .join("");
  },

  markConversationReadLocally: function (id) {
    const conv = this.conversations.find((c) => parseInt(c.id, 10) === id);
    if (conv) conv.unread_count = 0;
    this.renderConversationList();
  },

  sendReply: async function () {
    const box = document.getElementById("replyBox");
    const text = (box?.value || "").trim();
    if (!text || this.activeConversationId === null) return;
    const id = this.activeConversationId;
    try {
      box.value = "";
      await callAPI("/communications/conversations/" + id, "POST", { message: text });
      await this.openConversation(id);
    } catch (error) {
      console.error("Error sending reply:", error);
      box.value = text;
      showNotification("Failed to send reply", "error");
    }
  },

  scrollThreadToBottom: function () {
    const el = document.getElementById("threadMessages");
    if (el) el.scrollTop = el.scrollHeight;
  },

  // ==========================================================================
  // Compose
  // ==========================================================================
  startCompose: function () {
    this.activeConversationId = null;
    this.selectedRecipients = [];
    this.recipientResults = [];
    this.showView("compose");
    document.getElementById("selectedRecipients").innerHTML = "";
    document.getElementById("newRecipientSearch").value = "";
    document.getElementById("newSubject").value = "";
    document.getElementById("newMessage").value = "";
    document.getElementById("newPriority").value = "normal";
    document.getElementById("newRecipientSearch").focus();
  },

  cancelCompose: function () {
    this.showView("empty");
  },

  searchRecipients: async function (term) {
    const panel = document.getElementById("recipientResults");
    if (!panel) return;
    if (!term.trim()) {
      panel.classList.add("d-none");
      return;
    }
    try {
      const response = await callAPI("/communications/recipients", "GET", null, { q: term });
      this.recipientResults = Array.isArray(response?.data)
        ? response.data
        : Array.isArray(response)
        ? response
        : [];
      const selected = new Set(this.selectedRecipients.map((r) => r.id));
      const available = this.recipientResults.filter((r) => !selected.has(parseInt(r.id, 10)));
      if (available.length === 0) {
        panel.innerHTML = '<div class="kw-msg-recipient-empty">No matching users</div>';
      } else {
        panel.innerHTML = available
          .map(
            (r) =>
              '<button type="button" class="kw-msg-recipient-item" data-recipient-id="' +
              parseInt(r.id, 10) +
              '" data-name="' +
              this.escapeHtml(r.full_name || r.username) +
              '">' +
              '<span class="kw-avatar ' + this.avatarColor(r.full_name || r.username) + '">' +
              this.escapeHtml(this.avatarInitials(r.full_name || r.username)) +
              "</span>" +
              '<span class="kw-msg-recipient-item-name">' +
              this.escapeHtml(r.full_name || r.username) +
              "</span>" +
              '<span class="kw-msg-recipient-item-role">' +
              this.escapeHtml(r.roles || "") +
              "</span>" +
              "</button>"
          )
          .join("");
      }
      panel.classList.remove("d-none");
    } catch (error) {
      console.error("Error searching recipients:", error);
      panel.classList.add("d-none");
    }
  },

  addRecipient: function (id, name) {
    if (this.selectedRecipients.some((r) => r.id === id)) return;
    this.selectedRecipients.push({ id, name });
    document.getElementById("recipientResults").classList.add("d-none");
    document.getElementById("newRecipientSearch").value = "";
    this.renderSelectedRecipients();
  },

  removeRecipient: function (id) {
    this.selectedRecipients = this.selectedRecipients.filter((r) => r.id !== id);
    this.renderSelectedRecipients();
  },

  renderSelectedRecipients: function () {
    const el = document.getElementById("selectedRecipients");
    if (!el) return;
    el.innerHTML = this.selectedRecipients
      .map(
        (r) =>
          '<span class="kw-msg-chip">' +
          this.escapeHtml(r.name) +
          '<button type="button" class="kw-msg-chip-x" data-remove-recipient="' +
          r.id +
          '" aria-label="Remove ' +
          this.escapeHtml(r.name) +
          '">&times;</button>' +
          "</span>"
      )
      .join("");
  },

  sendNewMessage: async function () {
    const recipients = this.selectedRecipients.map((r) => r.id);
    const subject = (document.getElementById("newSubject").value || "").trim();
    const message = (document.getElementById("newMessage").value || "").trim();
    const priority = document.getElementById("newPriority").value;

    if (recipients.length === 0) {
      showNotification("Select at least one recipient", "warning");
      return;
    }
    if (!message) {
      showNotification("Type a message", "warning");
      return;
    }

    const sendBtn = document.getElementById("btnSendMessage");
    if (sendBtn) sendBtn.disabled = true;
    try {
      const response = await callAPI("/communications/conversations", "POST", {
        recipients,
        subject,
        message,
        priority,
      });
      const data = response?.data || response || {};
      const conversationId = data.conversation?.id
        ? parseInt(data.conversation.id, 10)
        : this.findNewConversationId();
      showNotification("Message sent", "success");
      this.cancelCompose();
      await this.loadConversations();
      if (conversationId) this.openConversation(conversationId);
    } catch (error) {
      console.error("Error sending message:", error);
      showNotification("Failed to send message: " + (error.message || "Unknown error"), "error");
    } finally {
      if (sendBtn) sendBtn.disabled = false;
    }
  },

  findNewConversationId: function () {
    // The created conversation is normally the newest in the freshly-loaded list.
    const ids = this.conversations.map((c) => parseInt(c.id, 10));
    if (ids.length > 0) return Math.max.apply(null, ids);
    return null;
  },

  showView: function (view) {
    const empty = document.getElementById("threadEmpty");
    const thread = document.getElementById("threadView");
    const compose = document.getElementById("composeView");
    if (empty) empty.classList.toggle("d-none", view !== "empty");
    if (thread) thread.classList.toggle("d-none", view !== "thread");
    if (compose) compose.classList.toggle("d-none", view !== "compose");
    if (view !== "thread") this.activeConversationId = null;
  },

  // ==========================================================================
  // Utilities
  // ==========================================================================
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

  truncate: function (value, length) {
    const s = String(value || "");
    return s.length > length ? s.slice(0, length - 1) + "\u2026" : s;
  },

  linkify: function (escaped) {
    // Input is already HTML-escaped. Wrap http(s) links in safe anchors.
    return escaped.replace(
      /(https?:\/\/[^\s<]+)/g,
      '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
    );
  },

  avatarInitials: function (name) {
    const parts = String(name || "?").trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return "?";
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  },

  avatarColor: function (name) {
    const palette = ["kw-av-green", "kw-av-blue", "kw-av-gold", "kw-av-purple", "kw-av-teal", "kw-av-orange"];
    let hash = 0;
    for (let i = 0; i < String(name).length; i++) {
      hash = (hash * 31 + String(name).charCodeAt(i)) >>> 0;
    }
    return palette[hash % palette.length];
  },

  formatListTime: function (value) {
    if (!value) return "";
    const d = new Date(value);
    if (isNaN(d.getTime())) return "";
    const now = new Date();
    const sameDay = d.toDateString() === now.toDateString();
    if (sameDay) {
      return d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
    }
    return d.toLocaleDateString([], { day: "2-digit", month: "short" });
  },

  formatBubbleTime: function (value) {
    if (!value) return "";
    const d = new Date(value);
    if (isNaN(d.getTime())) return "";
    return (
      d.toLocaleDateString([], { day: "2-digit", month: "short" }) +
      " " +
      d.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
    );
  },
};

// Boot: handle both normal loads and lazy injection (PageShell may append this
// script after DOMContentLoaded has already fired).
(function () {
  function boot() {
    if (document.getElementById("kwMessaging")) {
      communicationsController.init();
    }
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
