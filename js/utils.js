/**
 * Shared utility functions for Kingsway pages.
 *
 * These helpers were previously duplicated across 10+ page controllers.
 * Import once via home.php; call as KWUtils.escapeHtml(), KWUtils.currency(), etc.
 */

const KWUtils = (() => {
    "use strict";

    // ── HTML escaping ────────────────────────────────────────────────────────

    /**
     * Escape a value for safe HTML insertion.
     * @param {*} s
     * @returns {string}
     */
    function escapeHtml(s) {
        return String(s == null ? "" : s)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    // ── Currency formatting ──────────────────────────────────────────────────

    /**
     * Format a numeric value as KES currency.
     * @param {number|string} val
     * @returns {string} e.g. "KES 12,500.00"
     */
    function currency(val) {
        return "KES " + Number(val || 0).toLocaleString("en-KE", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    // ── Date formatting ──────────────────────────────────────────────────────

    /**
     * Format an ISO date string as a human-readable date.
     * @param {string|null} value  ISO date string or null
     * @param {object}      [opts] Intl.DateTimeFormat options override
     * @returns {string} e.g. "04 Jul 2026" or "—"
     */
    function formatDate(value, opts) {
        if (!value) return "\u2014";
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return escapeHtml(value);
        const defaults = { day: "2-digit", month: "short", year: "numeric" };
        return d.toLocaleDateString("en-GB", opts || defaults);
    }

    /**
     * Format an ISO datetime string as date + time.
     * @param {string|null} value
     * @returns {string}
     */
    function formatDateTime(value) {
        if (!value) return "\u2014";
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return escapeHtml(value);
        return d.toLocaleDateString("en-GB", {
            day: "2-digit", month: "short", year: "numeric",
            hour: "2-digit", minute: "2-digit",
        });
    }

    // ── Toast / notification ─────────────────────────────────────────────────

    /**
     * Show a temporary Bootstrap-style toast alert.
     * Falls back to the global showNotification() from api.js when available.
     * @param {string} message
     * @param {"success"|"error"|"warning"|"info"} [type="success"]
     */
    function showToast(message, type) {
        type = type || "success";
        var bsType = type === "error" ? "danger" : type;
        var el = document.createElement("div");
        el.className = "alert alert-" + bsType +
            " alert-dismissible position-fixed top-0 end-0 m-3";
        el.style.zIndex = "9999";
        el.textContent = message;
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "btn-close";
        btn.setAttribute("data-bs-dismiss", "alert");
        el.appendChild(btn);
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 4000);
    }

    // ── DOM helpers ──────────────────────────────────────────────────────────

    /**
     * Set the textContent of an element by ID.
     * @param {string} id
     * @param {*}      val
     */
    function setText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val == null ? "" : val;
    }

    /**
     * Get the trimmed value of a form element by ID.
     * @param {string} id
     * @returns {string}
     */
    function getVal(id) {
        const el = document.getElementById(id);
        return el ? el.value.trim() : "";
    }

    /**
     * Set the value of a form element by ID.
     * @param {string} id
     * @param {*}      val
     */
    function setVal(id, val) {
        const el = document.getElementById(id);
        if (el) el.value = val == null ? "" : val;
    }

    // ── Auth guard ───────────────────────────────────────────────────────────

    /**
     * Redirect to the login page when the user is not authenticated.
     * @returns {boolean} true if authenticated, false if redirected
     */
    function requireAuth() {
        if (typeof AuthContext !== "undefined" && AuthContext.isAuthenticated()) {
            return true;
        }
        window.location.href = (window.APP_BASE || "") + "/index.php";
        return false;
    }

    // ── Status badge ─────────────────────────────────────────────────────────

    /**
     * Return a Bootstrap badge <span> for common status values.
     * @param {string} status
     * @returns {string} HTML string
     */
    function statusBadge(status) {
        const s = String(status || "unknown").toLowerCase();
        const map = {
            active: "success", approved: "success", paid: "success",
            completed: "success", present: "success",
            pending: "warning", partial: "info", processing: "info",
            inactive: "secondary", draft: "secondary",
            rejected: "danger", overdue: "danger", absent: "danger",
            cancelled: "dark", suspended: "dark",
        };
        const variant = map[s] || "secondary";
        return '<span class="badge bg-' + variant + '">' + escapeHtml(status) + "</span>";
    }

    // ── Public API ───────────────────────────────────────────────────────────

    return {
        escapeHtml: escapeHtml,
        esc: escapeHtml,
        currency: currency,
        formatDate: formatDate,
        formatDateTime: formatDateTime,
        showToast: showToast,
        setText: setText,
        getVal: getVal,
        setVal: setVal,
        requireAuth: requireAuth,
        statusBadge: statusBadge,
    };
})();

window.KWUtils = KWUtils;
