#!/usr/bin/env python3
"""
Generate docs/frontend_matrix/EXECUTION_PLAN.md — the module-grouped frontend cleanup plan.

Reads page_review_status.csv (Phase 3) and index.md (Phase 2) and groups the fix backlog
into module batches, so work can proceed module-by-module (backend-first, verified across
all 20 roles) per docs/database_audit/16_FRONTEND_REVAMP_PLAN.md.

Regenerate after each module:  python3 scripts/frontend_matrix/generate_execution_plan.py
"""

import re
import csv
from pathlib import Path
from collections import OrderedDict, Counter

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "docs" / "frontend_matrix"

# Ordered keyword -> module classifier (first match wins; stems so plurals match).
MODULE_RULES = [
    (
        "admissions",
        r"admission|applications|enrollment|new_applications|placement_test",
    ),
    (
        "students",
        r"students|student|alumni|parent(?!s?_meeting|_feedback|_notification)|family_group|pta|view_student_info",
    ),
    ("transport", r"transport|route|vehicle|passenger"),
    ("boarding", r"boarding|dormitor|exeat|sick_bay|food_store"),
    ("attendance", r"attend|absentee|truancy|late_arrival|roll_call"),
    (
        "staff",
        r"staff|teacher|appointment|onboarding|lifecycle|id_card|complete_staff_profile|"
        r"import_existing|supervision_roster|performance_reviews|role_assignments",
    ),
    (
        "finance",
        r"financ|fee|payment|bank|mpesa|budget|expense|cash|vendor|invoice|purchase_|asset|"
        r"depreciat|adjustment|payroll|payslip|salary|balance|defaulter|unmatched|reconcil|"
        r"settlement|accounts|allowance|transaction|approval|petty_cash",
    ),
    (
        "academics",
        r"academ|class|result|report|scheme|exam|grade|lesson|curriculum|cbc|term|timetable|"
        r"subject|learning_area|my_cats|competenc|grading|formative|year_|view_syllabus|"
        r"view_class|performance|assessment|progress|calendar|event|enter_marks|enter_results|"
        r"add_results|past_papers|national_exams|intern_assigned|intern_schedule|generate_|"
        r"my_classes_taught|my_schemes|my_subject|my_students_list",
    ),
    (
        "counseling",
        r"counsel|mentor|welfare|intervention|special_needs|behavior|sanction|reward|"
        r"reflection|observation|parent_meeting|parent_feedback|growth|improvement|"
        r"learning_goals|timeline|portfolio",
    ),
    ("discipline", r"discipline|conduct"),
    ("activities", r"activit|club|sport|competition|assembl"),
    ("chapel", r"chapel"),
    ("library", r"library|teaching_materi|teaching_resource"),
    (
        "communications",
        r"communication|announcement|sms|email|whatsapp|message|notification|send_",
    ),
    ("website", r"website"),
    ("inventory", r"inventory|stock|requisition|uniform"),
    ("catering", r"catering|menu_planning"),
    (
        "system",
        r"system|setting|role|permission|audit|security|whitelist|blacklist|rate_limit|token|"
        r"feature|migration|backup|diagnostic|system_health|maintenance_mode|job_inspector|"
        r"background_jobs|registry|webhook|sidebar|api_explorer|api_metrics|reset_default|"
        r"account_status|active_sessions|initialization|data_import|data_retention|"
        r"domain_isolation|time_bound|module_enablement|policy|error_logs|failed_login|"
        r"authentication_logs|navigation|manage_menus|route_access|widget",
    ),
    ("users", r"users\b|manage_users|account_settings"),
    ("portal", r"portal|profile"),
    ("dashboard", r"dashboard"),
]

_RULE_RE = [(m, re.compile(p, re.I)) for m, p in MODULE_RULES]


def classify(route):
    for module, rx in _RULE_RE:
        if rx.search(route):
            return module
    return "other"


def main():
    status = list(csv.DictReader(open(OUT / "page_review_status.csv")))
    index = (OUT / "index.md").read_text(encoding="utf-8")

    modules = OrderedDict()
    for r in status:
        mod = classify(r["page"])
        m = modules.setdefault(
            mod,
            {
                "pages": [],
                "fix": [],
                "no_ctrl": 0,
                "xss": 0,
                "unres": 0,
                "param_warn": 0,
            },
        )
        m["pages"].append(r)
        flags = (r["flags"] or "").split(";")
        if r["fix_task"]:
            m["fix"].append(r)
        if "NO_CONTROLLER_FILE" in flags:
            m["no_ctrl"] += 1
        if "XSS_NO_ESCAPE" in flags:
            m["xss"] += 1
        if "UNRESOLVED_ENDPOINT" in flags:
            m["unres"] += 1
        if r["params_match"] == "warn":
            m["param_warn"] += 1

    order = [
        "transport",
        "admissions",
        "students",
        "finance",
        "academics",
        "staff",
        "attendance",
        "boarding",
        "counseling",
        "discipline",
        "activities",
        "chapel",
        "library",
        "communications",
        "website",
        "inventory",
        "catering",
        "system",
        "users",
        "portal",
        "other",
    ]
    order = [o for o in order if o in modules]

    def table(headers, rows):
        out = ["| " + " | ".join(headers) + " |", "|" + "---|" * len(headers)]
        for r in rows:
            out.append("| " + " | ".join(str(c).replace("|", "\\|") for c in r) + " |")
        return "\n".join(out)

    L = []
    L.append("# Frontend Cleanup — Execution Plan")
    L.append("")
    L.append(
        "Generated by `scripts/frontend_matrix/generate_execution_plan.py` from "
        "`page_review_status.csv` (Phase 3) and `index.md` (Phase 2). **Regenerate after every "
        "module** so the backlog stays in sync with the fixes."
    )
    L.append("")
    L.append("## Strategy")
    L.append("")
    L.append(
        "- **Fix module-by-module** (vertical slices), **verify horizontally** across all 20 roles."
    )
    L.append(
        "- **Backend is the source of truth** (locked rule): the frontend adapts to backend names/shapes."
    )
    L.append("- Each module passes the **7 gates** below before it is marked done.")
    L.append("")
    L.append("### The 7 gates (per module)")
    L.append("")
    L.append(
        "1. **Contract** — every endpoint resolves (`0` 404s/no_api_method/dynamic); fix `api.js` paths "
        "or add convention-correct backend aliases; remove dead `api.js` methods."
    )
    L.append(
        "2. **Parameters** — page payload keys == backend `$data`/`$_POST`/`$_GET` keys."
    )
    L.append(
        "3. **Response** — pages consume the unwrapped `data` payload; null/empty handled; no `res.success` checks."
    )
    L.append(
        "4. **Controllers** — every page has a working `js/pages/<page>.js` controller."
    )
    L.append(
        "5. **RBAC / sidebar** — every sidebar item for every role resolves to a working, permission-guarded page; "
        "no orphan menu items / orphan role ids."
    )
    L.append(
        "6. **UI** — `escapeHtml()` on all interpolated `innerHTML`; Bootstrap 5 modals; loading/empty/error states."
    )
    L.append(
        "7. **Delete dead pages** — pages with no sidebar link, no role route, and no inbound reference are archived."
    )
    L.append("")
    L.append("## Module order")
    L.append("")
    L.append(
        "**Transport is the pilot** (smallest module: 10 pages, 7 fix tasks) to prove the fix → "
        "regenerate → verify loop, then roll to the big modules in dependency order:"
    )
    L.append("")
    L.append(
        table(
            [
                "#",
                "Module",
                "Pages",
                "Fix tasks",
                "No-controller",
                "XSS",
                "Unresolved",
                "Param warn",
            ],
            [
                [
                    i + 1,
                    m,
                    len(modules[m]["pages"]),
                    len(modules[m]["fix"]),
                    modules[m]["no_ctrl"],
                    modules[m]["xss"],
                    modules[m]["unres"],
                    modules[m]["param_warn"],
                ]
                for i, m in enumerate(order)
            ],
        )
    )
    L.append("")
    L.append("## Backlog by module")
    L.append("")
    for m in order:
        d = modules[m]
        L.append(f"### {m}")
        L.append("")
        L.append(
            f"Pages `{len(d['pages'])}` · fix tasks `{len(d['fix'])}` · no-controller `{d['no_ctrl']}` · "
            f"XSS `{d['xss']}` · unresolved `{d['unres']}` · param-warn `{d['param_warn']}`"
        )
        L.append("")
        if d["fix"]:
            L.append(
                table(
                    ["Task", "Page", "Controller", "Flags", "Params"],
                    [
                        [
                            r["fix_task"],
                            r["page"],
                            r["controller"],
                            r["flags"],
                            r["params_match"],
                        ]
                        for r in d["fix"]
                    ],
                )
            )
        else:
            L.append("_(clean — no fix tasks)_")
        L.append("")
    L.append("## Cross-cutting backlog (not module-scoped)")
    L.append("")
    L.append(
        "- **Legacy `callAPI` arg-order** — `js/pages/manage_website.js` uses `this.API('VERB','path')`; "
        "`window.callAPI = apiCall(endpoint, method)` takes them reversed. Fix + verify (website module)."
    )
    L.append(
        "- **Orphan role ids in `role_routes`** — 65, 66, 67, 68, 69, 70 reference no `roles` row; decide "
        "reparent or delete."
    )
    L.append(
        "- **Unreferenced `routes_registry` entries** — 108 of 381 are referenced by neither a sidebar item "
        "nor `role_routes`; archive or wire up."
    )
    L.append(
        "- **Unresolved endpoints** — see `index.md` `## Unresolved endpoints` (6 distinct today): "
        "`/attendance/summary`, `/academic/results-list`, `/dashboard/system-admin/api-load`, "
        "`GET /` root dispatch, `GET|DELETE /website/` dynamic legacy."
    )
    L.append("")
    L.append("## Definition of done (per module)")
    L.append("")
    L.append("- `0` sidebar items (any role) without a working destination page.")
    L.append("- `0` pages without a controller; `0` dead pages.")
    L.append("- `0` unresolved endpoints; `0` XSS flags; `0` param-warn pages.")
    L.append("- `0` orphan menu items / route refs.")
    L.append("- Role matrix + page reviews regenerated and the diff reviewed.")
    L.append("")
    L.append("## Verification loop")
    L.append("")
    L.append("```bash")
    L.append(
        "python3 scripts/frontend_matrix/generate_role_matrix.py     # unresolved/shared/role matrix"
    )
    L.append(
        "python3 scripts/frontend_matrix/generate_page_review.py     # per-page flags + FIX-3 ids"
    )
    L.append(
        "python3 scripts/frontend_matrix/generate_e2e_traces.py      # phase 4 traces (next)"
    )
    L.append(
        "python3 scripts/frontend_matrix/generate_execution_plan.py  # this backlog"
    )
    L.append("```")
    L.append("")
    (OUT / "EXECUTION_PLAN.md").write_text("\n".join(L), encoding="utf-8")
    print("written docs/frontend_matrix/EXECUTION_PLAN.md")

    # quick coverage sanity: any unclassified routes?
    un = [r["page"] for r in status if classify(r["page"]) == "other"]
    if un:
        print("unclassified:", un)


if __name__ == "__main__":
    main()
