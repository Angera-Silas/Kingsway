/**
 * School Settings Page Controller
 * Loads all school configuration from /system/school-config and saves
 * each settings section independently.
 * Loaded by school_settings.php
 */

(function () {
    "use strict";

    // ── Helpers ────────────────────────────────────────────────────────────────

    function showToast(msg, type) {
        type = type || "success";
        var el = document.createElement("div");
        el.className = "alert alert-" + (type === "error" ? "danger" : type) + " alert-dismissible position-fixed top-0 end-0 m-3";
        el.style.zIndex = "9999";
        el.innerHTML = String(msg) + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 4000);
    }

    function setVal(id, value) {
        var el = document.getElementById(id);
        if (!el) return;
        if (el.type === "checkbox") {
            // Accept boolean, "1", "true", 1
            el.checked = (value === true || value === 1 || value === "1" || value === "true");
        } else {
            el.value = (value !== null && value !== undefined) ? String(value) : "";
        }
    }

    function getVal(id) {
        var el = document.getElementById(id);
        if (!el) return null;
        if (el.type === "checkbox") return el.checked ? 1 : 0;
        return el.value.trim();
    }

    function showSpinner(formId, show) {
        var form = document.getElementById(formId);
        if (!form) return;
        var btn = form.querySelector('[type="submit"]');
        if (!btn) return;
        if (show) {
            btn.disabled = true;
            btn.dataset.originalText = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        } else {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.originalText || "Save Changes";
        }
    }

    // ── Field mapping: API key -> form field id ────────────────────────────────

    var FIELD_MAP = {
        // General Settings
        school_name:            "schoolName",
        school_code:            "schoolCode",
        email:                  "schoolEmail",
        phone:                  "schoolPhone",
        address:                "schoolAddress",

        // Academic Settings
        academic_year:          "academicYear",
        calendar_type:          "academicCalendar",
        grading_scale:          "gradingScale",
        pass_mark:              "passMark",

        // Fees Settings
        currency:               "currency",
        tax_rate:               "taxRate",
        bank_account:           "bankAccount",
        bank_name:              "bankName",

        // System Settings
        backup_frequency:       "backupFrequency",
        session_timeout:        "sessionTimeout",
        email_notifications:    "enableNotifications",
        sms_notifications:      "enableSMS",
        admission_no_format:    "admissionNoFormat",
        admission_no_start_sequence: "admissionNoStartSequence",
        staff_no_format:        "staffNoFormat",
        staff_no_start_sequence: "staffNoStartSequence"
    };

    // Which fields belong to each form (for building the save payload)
    var FORM_FIELDS = {
        generalSettingsForm: [
            "school_name", "school_code", "email", "phone",
            "address"
        ],
        academicSettingsForm: [
            "academic_year", "calendar_type", "grading_scale", "pass_mark"
        ],
        feesSettingsForm: [
            "currency", "tax_rate", "bank_account", "bank_name"
        ],
        systemSettingsForm: [
            "backup_frequency", "session_timeout",
            "email_notifications", "sms_notifications"
        ],
        admissionNumberSettingsForm: [
            "admission_no_format", "admission_no_start_sequence"
        ],
        staffNumberSettingsForm: [
            "staff_no_format", "staff_no_start_sequence"
        ]
    };

    // ── Controller ─────────────────────────────────────────────────────────────

    var Controller = {
        data: {},

        init: async function () {
            await window.AuthContext?.ready();
            if (typeof AuthContext !== "undefined" && !AuthContext.isAuthenticated()) {
                window.location.href = (window.APP_BASE || "") + "/index.php";
                return;
            }
            this.bindForms();
            await this.loadData();
            await this.loadAdmissionNumberSettings();
            await this.loadStaffNumberSettings();
        },

        loadAdmissionNumberSettings: async function () {
            try {
                var response = await window.API.apiCall("/website/settings", "GET");
                var items = response?.data?.items || response?.items || [];
                var values = {};
                items.forEach(function (item) { values[item.setting_key] = item.setting_value; });
                setVal("admissionNoFormat", values.admission_no_format || "KPS{seq}");
                setVal("admissionNoStartSequence", values.admission_no_start_sequence || "1");
            } catch (err) {
                console.error("school_settings: admission number settings error", err);
                showToast("Admission number settings could not be loaded.", "error");
            }
        },

        loadStaffNumberSettings: async function () {
            try {
                var response = await window.API.apiCall("/website/settings", "GET");
                var items = response?.data?.items || response?.items || [];
                var values = {};
                items.forEach(function (item) { values[item.setting_key] = item.setting_value; });
                setVal("staffNoFormat", values.staff_no_format || "KPST#{seq}");
                setVal("staffNoStartSequence", values.staff_no_start_sequence || "1");
            } catch (err) {
                console.error("school_settings: staff number settings error", err);
                showToast("Staff number settings could not be loaded.", "error");
            }
        },

        bindForms: function () {
            var self = this;

            Object.keys(FORM_FIELDS).forEach(function (formId) {
                var form = document.getElementById(formId);
                if (!form) return;
                form.addEventListener("submit", function (e) {
                    e.preventDefault();
                    self.saveForm(formId);
                });
            });
        },

        loadData: async function () {
            // Show loading state on all forms
            Object.keys(FORM_FIELDS).forEach(function (formId) {
                var form = document.getElementById(formId);
                if (form) form.classList.add("opacity-50", "pe-none");
            });

            try {
                var response = await window.API.system.getSchoolConfig({});
                // Unwrap standard API envelope
                var config = null;
                if (response && typeof response === "object") {
                    config = response.data || response.config || response.settings || response;
                }
                this.data = config && typeof config === "object" ? config : {};
                this.populateForms();
            } catch (err) {
                console.error("school_settings: loadData error", err);
                showToast("Failed to load settings. Please refresh and try again.", "error");
            } finally {
                Object.keys(FORM_FIELDS).forEach(function (formId) {
                    var form = document.getElementById(formId);
                    if (form) form.classList.remove("opacity-50", "pe-none");
                });
            }
        },

        populateForms: function () {
            var self = this;
            Object.entries(FIELD_MAP).forEach(function (entry) {
                var apiKey = entry[0];
                var fieldId = entry[1];
                var value = self.data[apiKey];
                if (value !== undefined) setVal(fieldId, value);
            });
        },

        saveForm: async function (formId) {
            var self = this;
            var apiKeys = FORM_FIELDS[formId];
            if (!apiKeys) return;

            var payload = {};
            apiKeys.forEach(function (apiKey) {
                var fieldId = FIELD_MAP[apiKey];
                if (fieldId) {
                    var val = getVal(fieldId);
                    if (val !== null) payload[apiKey] = val;
                }
            });

            if (formId === "admissionNumberSettingsForm") {
                var format = String(payload.admission_no_format || "").trim();
                if (!format || format.length > 40 || !format.includes("{seq")) {
                    showToast("Format must contain {seq} or {seq:NN}.", "error");
                    return;
                }
            }

            if (formId === "staffNumberSettingsForm") {
                var staffFormat = String(payload.staff_no_format || "").trim();
                if (!staffFormat || staffFormat.length > 40 || !staffFormat.includes("{seq")) {
                    showToast("Format must contain {seq} or {seq:NN}.", "error");
                    return;
                }
            }

            showSpinner(formId, true);

            try {
                if (formId === "admissionNumberSettingsForm") {
                    await window.API.apiCall("/website/settings", "PUT", {key: "admission_no_format", value: payload.admission_no_format});
                    await window.API.apiCall("/website/settings", "PUT", {key: "admission_no_start_sequence", value: payload.admission_no_start_sequence});
                } else if (formId === "staffNumberSettingsForm") {
                    await window.API.apiCall("/website/settings", "PUT", {key: "staff_no_format", value: payload.staff_no_format});
                    await window.API.apiCall("/website/settings", "PUT", {key: "staff_no_start_sequence", value: payload.staff_no_start_sequence});
                } else if (formId === "generalSettingsForm") {
                    // General settings go to school_profile
                    await window.API.system.updateSchoolConfig(payload);
                } else {
                    // Academic/fees/system settings go to school_settings as key-value pairs
                    for (var key in payload) {
                        if (payload.hasOwnProperty(key)) {
                            await window.API.apiCall("/website/settings", "PUT", {key: key, value: payload[key]});
                        }
                    }
                }
                // Merge saved values back into local cache
                Object.assign(self.data, payload);
                showToast("Settings saved successfully", "success");
            } catch (err) {
                console.error("school_settings: saveForm error", err);
                showToast(err.message || "Failed to save settings. Please try again.", "error");
            } finally {
                showSpinner(formId, false);
            }
        }
    };

    window.SchoolSettingsController = Controller;
    window.APIRealtime?.register?.('SchoolSettingsController', Controller);
    document.addEventListener("DOMContentLoaded", function () { Controller.init(); });

})();
