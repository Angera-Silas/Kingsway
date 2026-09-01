/**
 * Console Logger Guard
 *
 * Global, load-first interception that ELIMINATES every `console.*` call from
 * reaching the browser console across the entire application (page controllers,
 * core modules, dashboards, and inline <script> blocks). This is a deliberate
 * production-hardening measure: console output can expose request/response
 * payloads, user data and internal errors to anyone with dev tools open.
 *
 * Behaviour:
 *   - console.error / console.warn  -> sanitized and routed to the central file
 *                                      logger via window.AppLogger (never shown
 *                                      on the browser console).
 *   - console.log/debug/info/table  -> dropped entirely (no-op). These are pure
 *                                      diagnostic/development noise.
 *   - console.trace/assert/count/group/dir/time etc -> no-op.
 *
 * The overrides are applied in place so object identity is preserved, and no
 * branch ever writes to the real console. Unsafe/unknown-typed arguments are
 * coerced defensively so a routing failure can never throw.
 */
(function () {
  "use strict";
  if (window.__KingswayConsoleGuardLoaded) {
    return;
  }
  window.__KingswayConsoleGuardLoaded = true;

  var pending = [];
  var flushing = false;

  function sanitizeText(value) {
    var text = String(value == null ? "" : value);
    return text
      .replace(/\bBearer\s+[A-Za-z0-9._~+\/-]+=*/gi, "Bearer [redacted]")
      .replace(/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/g, "[redacted-jwt]")
      .replace(/([?&](?:token|password|secret|key|otp|code|authorization)=)[^&#\s]*/gi, "$1[redacted]")
      .replace(/\b[A-Fa-f0-9]{40,}\b/g, "[redacted-secret]")
      .replace(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi, "[redacted-email]")
      .replace(/(?:\+?254[\s-]?(?:7|1)\d{8}|0(?:7|1)\d{8}|\+\d[\d ()-]{7,}\d)/g, "[redacted-phone]")
      .slice(0, 1200);
  }

  function safeMessage(args) {
    // Never serialize objects: legacy calls frequently pass entire API/auth
    // responses. Retain only scalar diagnostic text and Error messages.
    var parts = [];
    for (var i = 0; i < args.length && parts.length < 3; i++) {
      var value = args[i];
      if (value instanceof Error) {
        parts.push(value.name || "Error");
        parts.push(value.message || "An error occurred");
      } else if (["string", "number", "boolean"].indexOf(typeof value) !== -1) {
        parts.push(value);
      }
    }
    return sanitizeText(parts.join(" ") || "Suppressed legacy console event");
  }

  function route(level) {
    return function () {
      var args = Array.prototype.slice.call(arguments);
      if (args.length === 0) {
        return;
      }
      var message = safeMessage(args);

      var context = {};
      for (var i = 0; i < args.length; i++) {
        if (args[i] instanceof Error) {
          context.error = sanitizeText(args[i].message || args[i].name);
          if (args[i].stack) {
            context.error_stack = sanitizeText(args[i].stack).slice(0, 4000);
          }
        }
      }
      if (typeof window.AppLogger !== "undefined" && window.AppLogger[level]) {
        try {
          window.AppLogger[level]("console", message, context);
        } catch (e) {
          /* Never let telemetry routing throw. */
        }
      } else if (pending.length < 200) {
        pending.push({ level: level, message: message, context: context });
        scheduleFlush();
      }
    };
  }

  function flush() {
    if (flushing) {
      return;
    }
    if (
      typeof window.AppLogger === "undefined" ||
      typeof window.AppLogger.error !== "function"
    ) {
      return;
    }
    flushing = true;
    var batch = pending.splice(0, pending.length);
    for (var i = 0; i < batch.length; i++) {
      var item = batch[i];
      try {
        window.AppLogger[item.level]("console", item.message, item.context);
      } catch (e) {
        /* ignore */
      }
    }
    flushing = false;
  }

  function scheduleFlush() {
    if (flushing) {
      return;
    }
    if (document.readyState === "complete") {
      flush();
      return;
    }
    var onReady = function () {
      document.removeEventListener("DOMContentLoaded", onReady);
      flush();
    };
    document.addEventListener("DOMContentLoaded", onReady);
    window.setTimeout(flush, 5000);
  }

  var diagnostic = function () {
    /* intentional no-op */
  };
  var handlers = {
    log: diagnostic,
    debug: diagnostic,
    info: diagnostic,
    table: diagnostic,
    trace: diagnostic,
    assert: diagnostic,
    count: diagnostic,
    countReset: diagnostic,
    group: diagnostic,
    groupCollapsed: diagnostic,
    groupEnd: diagnostic,
    dir: diagnostic,
    dirxml: diagnostic,
    time: diagnostic,
    timeEnd: diagnostic,
    timeLog: diagnostic,
    error: route("error"),
    warn: route("warning"),
  };

  var key;
  if (
    typeof window.console === "undefined" ||
    typeof window.console !== "object"
  ) {
    window.console = {};
  }
  for (key in handlers) {
    if (Object.prototype.hasOwnProperty.call(handlers, key)) {
      window.console[key] = handlers[key];
    }
  }
})();
