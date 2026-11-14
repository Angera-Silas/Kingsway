(function () {
  "use strict";
  const SmsConfigController = {
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
      this.on("smsRefresh", "click", () => this.load({ userInitiated: true }));
      this.on("smsSaveBtn", "click", () => this.save());
      this.on("smsTestBtn", "click", () => this.test());
      this.on("smsExportCsv", "click", () => this.exportCsv());
      this.on("smsPrint", "click", () => window.PrintManager?.printPage({ title: "SMS Configurations", orientation: "landscape" }) ?? window.print());
    },

    on(id, evt, fn) { const el = document.getElementById(id); if (el) el.addEventListener(evt, fn); },

    hasUnsavedEdits() {
      const originalValues = new Map(this.fields.map((field) => [field.key, String(field.value ?? "")]));
      return Array.from(document.querySelectorAll(".sms-field-input")).some((input) => (
        String(input.value) !== (originalValues.get(input.dataset.key) ?? "")
      ));
    },

    async load(options = {}) {
      if (this.hasUnsavedEdits()) {
        if (options.userInitiated) {
          this.showMsg("Save your SMS settings before refreshing. Your unsaved edits are still on this page.", "warning");
        }
        return;
      }

      const state = document.getElementById("smsState");
      this.setState(state, "Loading SMS settings...", "info");
      try {
        const res = await window.API.system.getCommunicationConfigs();
        this.fields = res?.sms || [];
        this.updateFields();
        this.setState(state, "SMS configuration loaded.", "success");
      } catch (e) {
        console.error("[SmsConfig] load failed", e);
        this.setState(state, "Failed to load SMS configuration.", "danger");
      }
    },

    updateFields() {
      this.fields.forEach((field) => {
        const row = Array.from(document.querySelectorAll("[data-sms-field]"))
          .find((element) => element.dataset.smsField === field.key);
        if (!row) return;

        const label = row.querySelector("[data-field-label]");
        const key = row.querySelector("[data-field-key]");
        const value = row.querySelector("[data-field-value]");
        const source = row.querySelector("[data-field-source]");
        const secretStatus = row.querySelector("[data-secret-status]");
        const input = row.querySelector(".sms-field-input");

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
      const state = document.getElementById("smsState");
      const inputs = document.querySelectorAll(".sms-field-input");
      const fields = Array.from(inputs).map(i => ({ key: i.dataset.key, value: i.value.trim() }));
      if (!fields.length) { this.showMsg("No editable fields to save.", "warning"); return; }
      this.setState(state, "Saving...", "info");
      try {
        const res = await window.API.system.saveCommunicationConfig("sms", fields);
        this.fields = res?.sms || [];
        this.updateFields();
        this.setState(state, "SMS settings saved successfully.", "success");
      } catch (e) {
        console.error("[SmsConfig] save failed", e);
        this.setState(state, "Failed to save SMS settings.", "danger");
      }
    },

    async test() {
      const result = document.getElementById("smsTestResult");
      const phone = document.getElementById("smsTestPhone")?.value?.trim();
      if (!phone) { this.setTestResult(result, "Enter a test phone number.", "danger"); return; }
      this.setTestResult(result, "Testing...", "info");
      try {
        const res = await window.API.system.testCommunicationConfig("sms", phone);
        const message = res?.success
          ? `${res.message || "Test passed."}${res.balance != null ? ` (Balance: ${res.balance})` : ""}`
          : (res?.message || "Test failed.");
        this.setTestResult(result, message, res?.success ? "success" : "danger");
      } catch (e) {
        this.setTestResult(result, e.message || "Test failed.", "danger");
      }
    },

    exportCsv() { if (this.fields.length) window.KingswayFileLifecycle?.exportText?.(this.csvContent(), "sms-config.csv", "text/csv"); },
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
  window.SmsConfigController = SmsConfigController;
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", () => SmsConfigController.init());
  else SmsConfigController.init();
})();
