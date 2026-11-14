/**
 * Frontend Logger
 * Lightweight, non-blocking browser telemetry that writes to the SAME central
 * JSON-lines log files as the backend (category `client`), correlated with the
 * same request ID and browser session ID used on the server.
 *
 * Usage:
 *   AppLogger.info("payment", "Card form submitted", { amount: 4200 });
 *   AppLogger.error("auth", "Login modal failed", { step: "otp" });
 *   AppLogger.flush(); // force send a pending batch
 *
 * Events are buffered and delivered in batches to POST /system/client-log.
 * Failures never throw and never impact the page.
 */
const AppLogger = (() => {
  "use strict";

  const MAX_BUFFER = 50;
  const FLUSH_INTERVAL_MS = 4000;
  const MAX_EVENT_SIZE = 4000;
  const SENSITIVE_KEYS = /(?:password|pass|pwd|token|secret|authorization|credential|cookie|otp|pin|email|phone|mobile|address|national.?id|date.?of.?birth|dob|card|account.?number|medical|diagnosis)/i;

  // Delivery backoff. When the server rejects a batch (most often a 429 from
  // the telemetry rate bucket), retrying the SAME batch immediately re-creates
  // the burst that got us limited, and each rejection is itself an error that
  // console routing forwards back into this logger - so the client ends up
  // re-POSTing its own telemetry forever and starves the bucket. Retries are
  // therefore bounded, carry only the newest events, and pause for a cooldown.
  const KEEP_EVENTS_PER_RETRY = 5;
  const COOLDOWN_MS = 30000;
  const COOLDOWN_RATE_LIMITED_MS = 120000;

  const state = {
    buffer: [],
    flushTimer: null,
    flushInProgress: false,
    enabled: true,
    initialized: false,
    lastPageKey: "",
    cooldownUntil: 0,
  };

  function log(name, ...args) {
    // Diagnostic helper intentionally suppressed: nothing may reach the browser
    // console. Telemetry goes only to the central file logger.
    void name;
    void args;
  }

  function getCorrelation() {
    try {
      return {
        request_id:
          (typeof window.API !== "undefined" && window.API.getRequestId
            ? window.API.getRequestId()
            : "") || "",
        browser_session_id:
          (typeof window.API !== "undefined" && window.API.getBrowserSessionId
            ? window.API.getBrowserSessionId()
            : "") || "",
      };
    } catch (_) {
      return { request_id: "", browser_session_id: "" };
    }
  }

  function sanitizeText(value) {
    return String(value == null ? "" : value)
      .replace(/\bBearer\s+[A-Za-z0-9._~+\/-]+=*/gi, "Bearer [redacted]")
      .replace(/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/g, "[redacted-jwt]")
      .replace(/([?&](?:token|password|secret|key|otp|code|authorization)=)[^&#\s]*/gi, "$1[redacted]")
      .replace(/\b[A-Fa-f0-9]{40,}\b/g, "[redacted-secret]")
      .replace(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi, "[redacted-email]")
      .replace(/(?:\+?254[\s-]?(?:7|1)\d{8}|0(?:7|1)\d{8}|\+\d[\d ()-]{7,}\d)/g, "[redacted-phone]");
  }

  function redact(value, depth = 0, seen = new WeakSet()) {
    if (depth > 5) return "[max-depth]";
    if (typeof value === "string") return sanitizeText(value).slice(0, 1200);
    if (value == null || ["number", "boolean"].includes(typeof value)) return value;
    if (value instanceof Error) {
      return { name: value.name, message: sanitizeText(value.message), stack: sanitizeText(value.stack || "").slice(0, 3000) };
    }
    if (typeof value !== "object") return `[${typeof value}]`;
    if (seen.has(value)) return "[circular]";
    seen.add(value);
    const output = Array.isArray(value) ? [] : {};
    Object.entries(value).slice(0, 50).forEach(([key, item]) => {
      output[key] = SENSITIVE_KEYS.test(key) ? "[redacted]" : redact(item, depth + 1, seen);
    });
    return output;
  }

  function pageContext() {
    const route = new URLSearchParams(window.location.search).get("route") || "";
    return {
      url: window.location.pathname,
      route: sanitizeText(route).slice(0, 160),
    };
  }

  function currentPageKey() {
    const page = pageContext();
    return `${page.url}|${page.route}`;
  }

  function enqueue(level, category, message, context) {
    if (!state.enabled) return;
    const payload = {
      level,
      category: String(category || "app").slice(0, 40),
      message: sanitizeText(message || "").slice(0, 1200),
      context:
        context && typeof context === "object"
          ? JSON.stringify(redact(context)).slice(0, MAX_EVENT_SIZE)
          : undefined,
      occurred_at: new Date().toISOString(),
      ...getCorrelation(),
      ...pageContext(),
    };
    state.buffer.push(payload);
    if (state.buffer.length >= MAX_BUFFER) {
      void flush();
      return;
    }
    scheduleFlush();
  }

  function scheduleFlush() {
    if (state.flushTimer) return;
    // Never schedule a retry while delivery is backing off: a timer here would
    // keep firing on the same rejected batch every interval.
    if (state.cooldownUntil > Date.now()) return;
    state.flushTimer = window.setTimeout(() => {
      state.flushTimer = null;
      void flush();
    }, FLUSH_INTERVAL_MS);
  }

  function canDeliver() {
    if (
      typeof window.API !== "undefined" &&
      window.API.system &&
      typeof window.API.system.logFromClient === "function"
    ) {
      // Public/portal pages (KINGSWAY_PUBLIC_PAGE=true) must not flush
      // telemetry through the staff-protected /system/logs endpoint: with no
      // staff session, apiCall falls through to handleSessionExpired() and
      // hard-redirects the page to index.php. Only deliver when a real staff
      // session exists, or when this is not a public page.
      if (window.KINGSWAY_PUBLIC_PAGE) {
        return Boolean(
          window.AuthContext && window.AuthContext.hasSession &&
          window.AuthContext.hasSession()
        );
      }
      return true;
    }
    return false;
  }

  function isRateLimitedError(error) {
    const code = Number(error?.code ?? error?.response?.status ?? 0);
    if (code === 429) return true;
    return /too many requests|rate limit/i.test(String(error?.message || ""));
  }

  function trimBufferToLimit() {
    if (state.buffer.length > MAX_BUFFER) {
      // Keep the newest events: they carry the error the operator just hit.
      state.buffer = state.buffer.slice(-MAX_BUFFER);
    }
  }

  function flush() {
    if (state.flushTimer) {
      window.clearTimeout(state.flushTimer);
      state.flushTimer = null;
    }
    if (state.flushInProgress) return Promise.resolve();
    if (state.buffer.length === 0) return Promise.resolve();
    if (!canDeliver()) {
      // No authenticated API client yet; drop rather than risk ever-growing
      // memory. A future page action will flush new events.
      state.buffer = [];
      return Promise.resolve();
    }
    // Deliveries are backing off (the server is rejecting them). Bound the
    // buffer, then wait: a timer scheduled here would keep re-POSTing the
    // rejected batch, and the failure of this very request is an error that
    // would re-enter the logger and restart the storm.
    if (state.cooldownUntil > Date.now()) {
      if (state.buffer.length >= MAX_BUFFER) {
        state.buffer = state.buffer.slice(-KEEP_EVENTS_PER_RETRY);
      }
      return Promise.resolve();
    }

    const batch = state.buffer.splice(0);
    state.flushInProgress = true;
    return window.API.system
      .logFromClient({ events: batch })
      .then(
        () => {
          state.cooldownUntil = 0;
        },
        (error) => {
          // The server rejected the batch, so re-sending all of it only widens
          // the burst. Retain a bounded, newest-first slice and pause.
          const rateLimited = isRateLimitedError(error);
          state.cooldownUntil =
            Date.now() + (rateLimited ? COOLDOWN_RATE_LIMITED_MS : COOLDOWN_MS);
          state.buffer = [
            ...state.buffer,
            ...batch.slice(-KEEP_EVENTS_PER_RETRY),
          ].slice(-MAX_BUFFER);
          // Intentionally silent: this logger's own delivery failure must
          // never be re-routed back into the telemetry it just failed to
          // deliver (console.error -> AppLogger.error -> flush -> 429 -> ...).
        },
      )
      .finally(() => {
        state.flushInProgress = false;
        trimBufferToLimit();
      });
  }

  const api = {
    init(options) {
      state.enabled = options?.enabled !== false;
      if (state.initialized) return api;
      if (typeof window !== "undefined") {
        state.lastPageKey = currentPageKey();
        enqueue("info", "presence", "Page opened", { event: "page_view" });
        window.addEventListener("error", (event) => {
          enqueue("error", "client_errors", "Unhandled browser error", {
            event: "window_error",
            error: event.error || event.message,
            source: event.filename ? new URL(event.filename, window.location.href).pathname : "",
            line: event.lineno || null,
            column: event.colno || null,
          });
        });
        window.addEventListener("unhandledrejection", (event) => {
          enqueue("error", "client_errors", "Unhandled promise rejection", {
            event: "unhandled_rejection",
            error: event.reason,
          });
        });
        window.setInterval(() => {
          if (!document.hidden) enqueue("info", "presence", "Page active", { event: "page_heartbeat" });
        }, 30000);
        window.setInterval(() => {
          const pageKey = currentPageKey();
          if (pageKey !== state.lastPageKey) {
            state.lastPageKey = pageKey;
            enqueue("info", "presence", "Page changed", { event: "page_view" });
          }
        }, 5000);
        document.addEventListener("visibilitychange", () => {
          enqueue("info", "presence", document.hidden ? "Page hidden" : "Page visible", {
            event: document.hidden ? "page_hidden" : "page_visible",
          });
        });
        window.addEventListener("pagehide", () => {
          // Best-effort last synchronous-ish delivery on navigation. Fetch with
          // keepalive is attempted via the API layer; if it can't deliver the
          // buffer is dropped.
          enqueue("info", "presence", "Page closed", { event: "page_leave" });
          if (state.buffer.length > 0) {
            void flush();
          }
        });
      }
      state.initialized = true;
      log("info", "initialized");
      return api;
    },
    debug: (category, message, context) =>
      enqueue("debug", category, message, context),
    info: (category, message, context) =>
      enqueue("info", category, message, context),
    success: (category, message, context) =>
      enqueue("success", category, message, context),
    warn: (category, message, context) =>
      enqueue("warning", category, message, context),
    error: (category, message, context) =>
      enqueue("error", category, message, context),
    audit: (action, entity, entityId, message, context) =>
      enqueue(
        "audit",
        "audit",
        message,
        Object.assign({}, context, { action, entity, entity_id: entityId }),
      ),
    flush,
    setEnabled(value) {
      state.enabled = value !== false;
      return api;
    },
  };

  return api;
})();

window.AppLogger = AppLogger;
