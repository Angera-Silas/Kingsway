/**
 * Reusable Academic Calendar Components
 *
 * Shared across dashboards and calendar pages so every surface shows the SAME
 * deduplicated, merged view of the school calendar:
 *
 *  - CalendarEventsTable   : full events table with real filters (scope, term,
 *                            week, type, search). Read-only by default; pass
 *                            { editable: true, onEdit, onDelete } to show row
 *                            actions (used by editors like school admins).
 *  - UpcomingEventsWidget  : compact "Upcoming Events" list. Expects ALREADY
 *                            merged rows (one row per logical event) and renders
 *                            a friendly date range instead of one entry per day.
 *
 * Both are driven by the unified-events endpoint (academic/custom?action=
 * unified-events) which returns one row per logical event with a full
 * start -> end range, term/week context and status.
 */
(function (global) {
    "use strict";

    // ---------------------------------------------------------------- helpers

    function esc(v) {
        if (v === null || v === undefined) return "";
        var div = document.createElement("div");
        div.textContent = String(v);
        return div.innerHTML;
    }

    function parseDate(v) {
        if (!v) return null;
        if (typeof v === "string" && /^\d{4}-\d{2}-\d{2}/.test(v)) {
            var parts = v.slice(0, 10).split("-");
            var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
            return isNaN(d.getTime()) ? null : d;
        }
        var dt = new Date(v);
        return isNaN(dt.getTime()) ? null : dt;
    }

    function fmtDate(v) {
        var d = parseDate(v);
        if (!d) return "—";
        return d.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
    }

    function fmtDateShort(v) {
        var d = parseDate(v);
        if (!d) return "—";
        return d.toLocaleDateString("en-GB", { day: "2-digit", month: "short" });
    }

    function fmtDateSlash(v) {
        var d = parseDate(v);
        if (!d) return "—";
        return d.toLocaleDateString("en-GB", { day: "2-digit", month: "2-digit", year: "numeric" });
    }

    function to12h(hm) {
        if (!hm) return "";
        var parts = String(hm).split(":");
        var h = Number(parts[0]);
        var m = parts[1] ? parts[1].slice(0, 2) : "00";
        if (isNaN(h)) return String(hm);
        var suffix = h >= 12 ? "pm" : "am";
        var hour12 = h % 12 || 12;
        return hour12 + ":" + m + " " + suffix;
    }

    var TYPE_COLORS = {
        academic: "primary",
        opening: "success",
        closing: "secondary",
        exam: "danger",
        holiday: "warning",
        school_holiday: "warning",
        public_holiday: "warning",
        half_day: "warning",
        special_event: "info",
        event: "info",
        meeting: "secondary",
        sports: "success",
        other: "dark",
    };

    var STATUS_COLORS = {
        upcoming: "primary",
        scheduled: "primary",
        ongoing: "active",
        active: "success",
        past: "secondary",
        completed: "secondary",
        cancelled: "danger",
    };

    function typeColor(t) {
        return TYPE_COLORS[t || "other"] || "secondary";
    }

    function statusColor(s) {
        return STATUS_COLORS[s || "scheduled"] || "secondary";
    }

    function humanType(t) {
        if (!t) return "Event";
        return t.charAt(0).toUpperCase() + t.slice(1).replace(/_/g, " ");
    }

    function rangeLabel(ev) {
        var start = parseDate(ev.start_date || ev.date || ev.event_date);
        var end = parseDate(ev.end_date);
        if (!start) return "—";
        var startT = to12h(ev.start_time);
        var endT = to12h(ev.end_time);
        var out;
        if (end && end.getTime() !== start.getTime()) {
            out = fmtDateShort(start) + " – " + fmtDateShort(end);
            if (start.getFullYear() !== end.getFullYear()) {
                out = fmtDate(start) + " – " + fmtDate(end);
            } else if (start.getMonth() !== end.getMonth()) {
                out = fmtDateShort(start) + " – " + fmtDate(end);
            }
        } else {
            out = fmtDate(start);
        }
        if (startT || endT) {
            out += " · " + (startT ? startT : "—") + " – " + (endT ? endT : "—");
        }
        return out;
    }

    // ---------------------------------------------------- UpcomingEventsWidget

    /**
     * Renders a compact upcoming-events list into a container element.
     * Rows must already be merged (one row per logical event).
     * @param {HTMLElement} container
     * @param {Array} events
     * @param {Object} opts { max, emptyText, onOpen }
     */
    function renderUpcoming(container, events, opts) {
        if (!container) return;
        opts = opts || {};
        var max = opts.max || 6;
        var emptyText = opts.emptyText || "No upcoming events.";

        if (!events || !events.length) {
            container.innerHTML =
                '<li class="list-group-item text-center text-muted py-4"><i class="bi bi-calendar-x me-2"></i>' +
                esc(emptyText) + "</li>";
            return;
        }

        container.innerHTML = events.slice(0, max).map(function (ev) {
            var title = ev.title || ev.name || ev.event_name || "Untitled Event";
            var type = ev.type || ev.event_type || "event";
            var badge = '<span class="badge bg-' + typeColor(type) + ' text-white" style="min-width:72px">' + esc(humanType(type)) + "</span>";
            var body =
                '<div class="flex-grow-1">' +
                '<div class="fw-semibold">' + esc(title) + "</div>" +
                '<small class="text-muted"><i class="bi bi-calendar me-1"></i>' + esc(rangeLabel(ev)) + "</small>" +
                "</div>";
            var li = document.createElement("li");
            li.className = "list-group-item d-flex align-items-center gap-2";
            li.innerHTML = badge + body;
            if (typeof opts.onOpen === "function") {
                li.style.cursor = "pointer";
                li.addEventListener("click", function () {
                    opts.onOpen(ev);
                });
            }
            return li.outerHTML;
        }).join("");
    }

    // ----------------------------------------------------- CalendarEventsTable

    /**
     * Creates a filterable events table. Options:
     *   containerId  {string}   DOM id of the container to render into
     *   editable     {boolean}  show Edit/Delete actions (default false)
     *   onEdit       {fn(row)}  called when Edit is clicked (editable only)
     *   onDelete     {fn(row)}  called when Delete is clicked (editable only)
     *   onReady      {fn(inst)} called after first successful load
     */
    function createTable(options) {
        var container = document.getElementById(options.containerId);
        if (!container) return null;

        var inst = {
            state: {
                allEvents: [],
                events: [],
                context: null,
                scope: "current_term",
                termId: null,
                weekNumber: null,
                type: "",
                search: "",
            },
            apiMethod: options.apiMethod || function (params) {
                return window.API.academic.getCustom({ action: "unified-events", ...params });
            },
            // Fetch the full-year feed ONCE; every filter is applied client-side
            // so switching scope/term/week/type is instant.
            load: function () {
                var self = this;
                self.setLoading(true);
                return Promise.resolve(self.apiMethod({ scope: "year" }))
                    .then(function (res) {
                        var data = res && res.data ? res.data : res;
                        var events = (data && data.events) || (Array.isArray(data) ? data : []);
                        self.state.allEvents = events;
                        self.state.context = (data && data.context) || null;
                        self.applyContext();
                        self.applyFilters();
                        self.setLoading(false);
                        if (typeof options.onReady === "function") options.onReady(self);
                    })
                    .catch(function (err) {
                        self.setLoading(false);
                        self.renderError();
                        console.error("CalendarEventsTable load failed:", err);
                    });
            },
            applyFilters: function () {
                var self = this;
                var st = self.state;
                var today = (st.context && st.context.today) || new Date().toISOString().slice(0, 10);
                var out = st.allEvents.slice();

                if (st.scope === "current_term") {
                    var tid = st.termId || (st.context && st.context.current_term_id);
                    if (tid) {
                        out = out.filter(function (ev) {
                            return ev.term_id !== null && String(ev.term_id) === String(tid);
                        });
                    }
                } else if (st.scope === "upcoming") {
                    out = out.filter(function (ev) {
                        return (ev.start_date || ev.date || "") >= today;
                    });
                }

                if (st.termId && st.scope !== "current_term") {
                    out = out.filter(function (ev) {
                        return ev.term_id !== null && String(ev.term_id) === String(st.termId);
                    });
                }
                if (st.weekNumber) {
                    out = out.filter(function (ev) {
                        return ev.week_number !== null && String(ev.week_number) === String(st.weekNumber);
                    });
                }
                if (st.type && st.type !== "all") {
                    out = out.filter(function (ev) {
                        return (ev.type || ev.event_type || "") === st.type;
                    });
                }
                if (st.search) {
                    var needle = st.search.toLowerCase();
                    out = out.filter(function (ev) {
                        return (ev.title || "").toLowerCase().indexOf(needle) !== -1
                            || (ev.description || "").toLowerCase().indexOf(needle) !== -1;
                    });
                }

                out.sort(function (a, b) {
                    return String(a.start_date || a.date).localeCompare(String(b.start_date || b.date));
                });
                st.events = out;
                self.render();
            },
            applyContext: function () {
                var ctx = this.state.context;
                if (!ctx) return;
                var termSelect = container.querySelector("[data-role='term']");
                if (termSelect && ctx.terms && ctx.terms.length) {
                    var current = termSelect.value;
                    termSelect.innerHTML =
                        '<option value="">All Terms</option>' +
                        ctx.terms
                            .map(function (t) {
                                return '<option value="' + esc(t.id) + '">' + esc(t.name) + "</option>";
                            })
                            .join("");
                    if (this.state.scope === "current_term") {
                        termSelect.value = ctx.current_term_id || "";
                        termSelect.disabled = true;
                    } else {
                        termSelect.value = current || "";
                        termSelect.disabled = false;
                    }
                }
                var weekSelect = container.querySelector("[data-role='week']");
                if (weekSelect && ctx.weeks && ctx.weeks.length) {
                    weekSelect.innerHTML =
                        '<option value="">All Weeks</option>' +
                        ctx.weeks
                            .map(function (w) {
                                return '<option value="' + esc(w) + '">Week ' + esc(w) + "</option>";
                            })
                            .join("");
                }
            },
            render: function () {
                var thead = container.querySelector("[data-role='tablehead']");
                var tbody = container.querySelector("[data-role='table']");
                if (!thead || !tbody) return;
                var events = this.state.events;
                var editable = !!options.editable;
                var cols = "Term, Week, Event, From, To, Type, Status" + (editable ? ", Actions" : "");

                thead.innerHTML =
                    "<tr>" +
                    cols.split(",").map(function (c) {
                        return "<th class='text-nowrap'>" + esc(c.trim()) + "</th>";
                    }).join("") +
                    "</tr>";

                if (!events.length) {
                    tbody.innerHTML =
                        '<tr><td colspan="' + (editable ? 8 : 7) + '" class="text-center text-muted py-4">No events match the current filters.</td></tr>';
                    return;
                }

                var rows = events
                    .map(function (ev) {
                        var start = fmtDateSlash(ev.start_date || ev.date);
                        var end = fmtDateSlash(ev.end_date);
                        var startT = to12h(ev.start_time);
                        var endT = to12h(ev.end_time);
                        var from = start + (startT ? "<br><small class='text-muted'>" + esc(startT) + "</small>" : "");
                        var to = end !== "—" && end !== start ? end + (endT ? "<br><small class='text-muted'>" + esc(endT) + "</small>" : "") : (endT && endT !== startT ? esc(endT) : "—");
                        var actions = "";
                        if (editable) {
                            actions =
                                "<td class='text-nowrap'>" +
                                '<button class="btn btn-sm btn-outline-primary me-1" data-action="edit" data-id="' + esc(ev.id || "") + '" title="Edit"><i class="bi bi-pencil"></i></button>' +
                                '<button class="btn btn-sm btn-outline-danger" data-action="delete" data-id="' + esc(ev.id || "") + '" title="Delete"><i class="bi bi-trash"></i></button>' +
                                "</td>";
                        }
                        return (
                            "<tr>" +
                            "<td class='text-nowrap'>" + esc(ev.term_name || "—") + "</td>" +
                            "<td>" + (ev.week_number != null ? "Week " + esc(ev.week_number) : "—") + "</td>" +
                            "<td class='fw-semibold'>" + esc(ev.title || ev.name) +
                            (ev.description ? "<br><small class='text-muted'>" + esc(String(ev.description).substring(0, 60)) + "</small>" : "") +
                            "</td>" +
                            "<td class='text-nowrap'>" + from + "</td>" +
                            "<td class='text-nowrap'>" + to + "</td>" +
                            "<td><span class='badge bg-" + typeColor(ev.type) + "'>" + esc(humanType(ev.type)) + "</span></td>" +
                            "<td><span class='badge bg-" + statusColor(ev.status) + "'>" + esc(ev.status || "—") + "</span></td>" +
                            actions +
                            "</tr>"
                        );
                    })
                    .join("");

                tbody.innerHTML = rows;

                if (editable) {
                    var self = this;
                    tbody.querySelectorAll("[data-action='edit']").forEach(function (btn) {
                        btn.addEventListener("click", function () {
                            var ev = self.findEvent(btn.getAttribute("data-id"));
                            if (ev && typeof options.onEdit === "function") options.onEdit(ev);
                        });
                    });
                    tbody.querySelectorAll("[data-action='delete']").forEach(function (btn) {
                        btn.addEventListener("click", function () {
                            var ev = self.findEvent(btn.getAttribute("data-id"));
                            if (ev && typeof options.onDelete === "function") options.onDelete(ev);
                        });
                    });
                }
            },
            findEvent: function (id) {
                if (id === null || id === "") return null;
                return this.state.events.find(function (ev) {
                    return String(ev.id) === String(id);
                }) || null;
            },
            setLoading: function (show) {
                var sp = container.querySelector("[data-role='loading']");
                if (sp) sp.style.display = show ? "block" : "none";
            },
            renderError: function () {
                var table = container.querySelector("[data-role='table']");
                if (table) table.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Failed to load calendar events.</td></tr>';
            },
            setScope: function (scope) {
                this.state.scope = scope;
                if (scope === "current_term") {
                    var ctx = this.state.context;
                    this.state.termId = (ctx && ctx.current_term_id) || null;
                } else if (scope === "upcoming") {
                    this.state.termId = null;
                    var termSelect = container.querySelector("[data-role='term']");
                    if (termSelect) termSelect.value = "";
                }
                this.applyContext();
                this.applyFilters();
            },
        };

        container.innerHTML =
            '<div class="card mb-3">' +
            '<div class="card-body">' +
            '<div class="row g-2 align-items-end">' +
            '<div class="col-auto" data-role="scope"></div>' +
            '<div class="col-auto"><label class="form-label small mb-1">Term</label><select class="form-select form-select-sm" data-role="term"></select></div>' +
            '<div class="col-auto"><label class="form-label small mb-1">Week</label><select class="form-select form-select-sm" data-role="week"><option value="">All Weeks</option></select></div>' +
            '<div class="col-auto"><label class="form-label small mb-1">Event Type</label><select class="form-select form-select-sm" data-role="type">' +
            '<option value="">All Types</option>' +
            Object.keys(TYPE_COLORS).map(function (t) {
                return '<option value="' + esc(t) + '">' + esc(humanType(t)) + "</option>";
            }).join("") +
            "</select></div>" +
            '<div class="col-md-3"><label class="form-label small mb-1">Search</label><input type="search" class="form-control form-control-sm" data-role="search" placeholder="Search events..."></div>' +
            "</div>" +
            "</div>" +
            "</div>" +
            '<div class="d-none text-center py-3" data-role="loading"><div class="spinner-border text-primary"></div></div>' +
            '<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">' +
            '<thead data-role="tablehead"></thead>' +
            '<tbody data-role="table"><tr><td colspan="8" class="text-center text-muted py-4">Loading events...</td></tr></tbody>' +
            "</table></div></div>";

        // Scope segmented control
        var scopeEl = container.querySelector("[data-role='scope']");
        if (scopeEl) {
            scopeEl.innerHTML =
                '<label class="form-label small mb-1 d-block">View</label>' +
                '<div class="btn-group btn-group-sm" role="group" data-role="scopeBtns">' +
                '<button type="button" class="btn btn-outline-primary" data-scope="current_term">Current Term</button>' +
                '<button type="button" class="btn btn-outline-primary" data-scope="year">This Year</button>' +
                '<button type="button" class="btn btn-outline-primary" data-scope="upcoming">Upcoming</button>' +
                "</div>";
            scopeEl.querySelectorAll("[data-scope]").forEach(function (btn) {
                btn.addEventListener("click", function (e) {
                    var scope = e.currentTarget.getAttribute("data-scope");
                    container.querySelectorAll("[data-role='scopeBtns'] [data-scope]").forEach(function (b) {
                        b.classList.remove("active");
                    });
                    e.currentTarget.classList.add("active");
                    inst.setScope(scope);
                });
            });
            var activeBtn = scopeEl.querySelector("[data-scope='current_term']");
            if (activeBtn) activeBtn.classList.add("active");
        }

        var termSelect = container.querySelector("[data-role='term']");
        if (termSelect) {
            termSelect.addEventListener("change", function () {
                inst.state.termId = termSelect.value || null;
                inst.applyFilters();
            });
        }
        var weekSelect = container.querySelector("[data-role='week']");
        if (weekSelect) {
            weekSelect.addEventListener("change", function () {
                inst.state.weekNumber = weekSelect.value || null;
                inst.applyFilters();
            });
        }
        var typeSelect = container.querySelector("[data-role='type']");
        if (typeSelect) {
            typeSelect.addEventListener("change", function () {
                inst.state.type = typeSelect.value || "";
                inst.applyFilters();
            });
        }
        var searchInput = container.querySelector("[data-role='search']");
        if (searchInput) {
            var debounce;
            searchInput.addEventListener("input", function () {
                clearTimeout(debounce);
                debounce = setTimeout(function () {
                    inst.state.search = searchInput.value.trim();
                    inst.applyFilters();
                }, 250);
            });
        }

        inst.load();
        return inst;
    }

    global.AcademicEventsTable = { create: createTable, typeColor: typeColor, statusColor: statusColor };
    global.UpcomingEventsWidget = {
        render: renderUpcoming,
        rangeLabel: rangeLabel,
    };
})(window);
