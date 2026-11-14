(function () {
  "use strict";
  const WhatsappConfigController = {
    fields: [],
    initialized: false,

    async init() {
      if (this.initialized) return;
      this.initialized = true;
      try {
        await window.AuthContext?.ready();
        if (!window.AuthContext?.isAuthenticated?.()) {
          window.location.href = (window.APP_BASE || "") + "/index.php";
          return;
        }
      } catch (e) { console.warn("Auth init failed", e); }
      this.bind();
      await this.load();
    },

    bind() {
      this.on("whatsappRefresh", "click", () => this.load({ userInitiated: true }));
      this.on("whatsappSaveBtn", "click", () => this.save());
      this.on("whatsappTestBtn", "click", () => this.test());
      this.on("whatsappExportCsv", "click", () => this.exportCsv());
      this.on("whatsappPrint", "click", () => window.PrintManager?.printPage({ title: "WhatsApp Configurations", orientation: "landscape" }) ?? window.print());
    },

    on(id, evt, fn) { const el = document.getElementById(id); if (el) el.addEventListener(evt, fn); },

    hasUnsavedEdits() {
      const originalValues = new Map(this.fields.map((field) => [field.key, String(field.value ?? "")]));
      return Array.from(document.querySelectorAll(".whatsapp-field-input")).some((input) => (
        String(input.value) !== (originalValues.get(input.dataset.key) ?? "")
      ));
    },

    async load(options = {}) {
      if (this.hasUnsavedEdits()) {
        if (options.userInitiated) {
          this.showMsg("Save your WhatsApp settings before refreshing. Your unsaved edits are still on this page.", "warning");
        }
        return;
      }

      const state = document.getElementById("whatsappState");
      this.setState(state, "Loading WhatsApp settings...", "info");
      try {
        const res = await window.API.system.getCommunicationConfigs();
        this.fields = res?.whatsapp || [];
        this.updateFields();
        this.setState(state, "WhatsApp configuration loaded.", "success");
      } catch (e) {
        console.error("[WhatsappConfig] load failed", e);
        this.setState(state, "Failed to load WhatsApp configuration.", "danger");
      }
    },

    updateFields() {
      this.fields.forEach((field) => {
        const row = Array.from(document.querySelectorAll("[data-whatsapp-field]"))
          .find((element) => element.dataset.whatsappField === field.key);
        if (!row) return;

        const label = row.querySelector("[data-field-label]");
        const key = row.querySelector("[data-field-key]");
        const value = row.querySelector("[data-field-value]");
        const source = row.querySelector("[data-field-source]");
        const secretStatus = row.querySelector("[data-secret-status]");
        const input = row.querySelector(".whatsapp-field-input");

        if (label) label.textContent = field.label || field.key;
        if (key) key.textContent = field.key;
        if (value) value.textContent = field.value || "—";
        if (input) input.value = field.value || "";
        if (source) {
          const fromDatabase = field.source === "db";
          source.textContent = fromDatabase ? "Database" : "Environment";
          source.classList.toggle("bg-primary", fromDatabase);
          source.classList.toggle("bg-secondary", !fromDatabase);
        }
        if (secretStatus) {
          const configured = Boolean(field.configured);
          secretStatus.textContent = configured ? "Configured" : "Not set";
          secretStatus.classList.toggle("bg-success", configured);
          secretStatus.classList.toggle("bg-secondary", !configured);
        }
      });
    },

    async save() {
      const state = document.getElementById("whatsappState");
      const inputs = document.querySelectorAll(".whatsapp-field-input");
      const fields = Array.from(inputs).map(i => ({ key: i.dataset.key, value: i.value.trim() }));
      if (!fields.length) { this.showMsg("No editable fields to save.", "warning"); return; }
      this.setState(state, "Saving...", "info");
      try {
        const res = await window.API.system.saveCommunicationConfig("whatsapp", fields);
        this.fields = res?.whatsapp || [];
        this.updateFields();
        this.setState(state, "WhatsApp settings saved successfully.", "success");
      } catch (e) {
        console.error("[WhatsappConfig] save failed", e);
        this.setState(state, "Failed to save WhatsApp settings.", "danger");
      }
    },

    async test() {
      const result = document.getElementById("whatsappTestResult");
      this.setTestResult(result, "Testing...", "info");
      try {
        const res = await window.API.system.testCommunicationConfig("whatsapp");
        this.setTestResult(result, res?.message || (res?.success ? "Test passed." : "Test failed."), res?.success ? "success" : "danger");
      } catch (e) {
        this.setTestResult(result, e.message || "Test failed.", "danger");
      }
    },

    exportCsv() { if (this.fields.length) window.KingswayFileLifecycle?.exportText?.(this.csvContent(), "whatsapp-config.csv", "text/csv"); },
    csvContent() { return "Key,Label,Value,Source\n" + this.fields.map(f => `${f.key},"${(f.label||'').replace(/"/g,'""')}","${(f.value||'').replace(/"/g,'""')}",${f.source}`).join("\n"); },

    showMsg(msg, type = "info") { window.showNotification?.(msg, type); },
    setState(element, message, type) {
      if (!element) return;
      element.textContent = message;
      element.className = `alert alert-${type}${type === "success" ? " py-2" : ""}`;
    },
    setTestResult(element, message, type) {
      if (!element) return;
      element.textContent = message;
      element.className = `small text-${type === "success" ? "success" : type === "danger" ? "danger" : "info"}`;
    },
  };
  window.WhatsappConfigController = WhatsappConfigController;
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", () => WhatsappConfigController.init());
  else WhatsappConfigController.init();
})();
