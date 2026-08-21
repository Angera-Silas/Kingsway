#!/usr/bin/env python3
"""Build + verify the object section (Section 4) so the deliverable imports
cleanly.

Pipeline:
  1. Generate Section 4 lines for every object with a [KEEP]/[REVIEW] flag
     (views, procedures, functions, triggers, events).
  2. Import-test against an isolated scratch database (line-neutral name
     substitution; scratch DB dropped beforehand so error line numbers map
     exactly to the deliverable file).
  3. Any object whose CREATE statement fails at CREATE time is re-emitted as
     a commented-out block with a [DROPPED - <server error>] flag.
  4. Re-import to confirm zero errors.

Side effects:
  - Writes KingWayDatabase_3nf_4nf_implemented.sql (with .bak3 backup).
  - Prints a coverage report.
"""

import importlib.util
import os
import pickle
import re
import shutil

_reauth_spec = importlib.util.spec_from_file_location(
    "db_reauth_views", os.path.join(os.path.dirname(__file__), "db_reauth_views.py")
)
assert _reauth_spec and _reauth_spec.loader
reauth = importlib.util.module_from_spec(_reauth_spec)
_reauth_spec.loader.exec_module(reauth)

_trig_spec = importlib.util.spec_from_file_location(
    "db_reauth_triggers",
    os.path.join(os.path.dirname(__file__), "db_reauth_triggers.py"),
)
assert _trig_spec and _trig_spec.loader
reauth_triggers = importlib.util.module_from_spec(_trig_spec)
_trig_spec.loader.exec_module(reauth_triggers)

_proc_spec = importlib.util.spec_from_file_location(
    "db_reauth_procs",
    os.path.join(os.path.dirname(__file__), "db_reauth_procs.py"),
)
assert _proc_spec and _proc_spec.loader
reauth_procs = importlib.util.module_from_spec(_proc_spec)
_proc_spec.loader.exec_module(reauth_procs)

OBJ = "/tmp/opencode/etl/objects.pkl"
DLV = "/home/prof_angera/Projects/php_pages/Kingsway/database/KingWayDatabase_3nf_4nf_implemented.sql"
MYSQL = "/opt/lampp/bin/mysql"
ROOT = "-padmin123"
SCRATCH = "kwa3nf_verify"
REAL_DB = "KingWayAcademy3nf"
ERR_LOG = "/tmp/opencode/verify_err.log"
TMP_SQL = "/tmp/opencode/verify.sql"

LEGACY_CITE = {
    "class_streams": "docs 01 §1.4/§1.22 -> streams + academic_year_class_streams",
    "academic_terms": "docs 01 §1.4 -> terms + academic_year_terms",
    "payment_transactions": "docs 03 §3.36 -> payments",
    "student_arrears": "docs 03 §3.49 -> derived view (no table)",
    "class_schedules": "docs 06 §6.8 -> timetable_entries + timetable_templates",
    "curriculum_units": "docs 02 §2.5 -> children repoint to learning_areas/strands",
    "school_calendar": "docs 06 §6.16 -> calendar_day_types + academic_year_calendar_days",
    "fee_structures_detailed": "docs 03 §3.23 -> academic_year_fee_schedules",
    "failed_auth_attempts": "docs 10 §10.12 -> login_attempts",
    "communication_templates": "docs 09 §9.9 -> message_templates + template_categories",
    "payment_security_audit": "docs 03 §3.35 -> security_incidents (+ audit_logs)",
    "class_year_assignments": "docs 01 §1.22 -> academic_year_classes + academic_year_class_streams",
    "student_fee_balances": "docs 03 §3.50 -> derived balance view (no table)",
    "staff_class_assignments": "docs 04 §4.25 -> academic_year_class_learning_area_teachers",
    "class_enrollments": "docs 01 §1.18 -> student_academic_enrollments",
    "financial_transactions": "docs 03 §3.30 -> school_transactions",
    "payment_allocations_detailed": "docs 03 §3.33 -> payment_allocations",
    "staff_performance_reviews": "docs 04 §4.46 -> performance_reviews",
    "uniform_sales_summary": "docs 03 §3.65 -> derived view over uniform_sales",
    "staff_payroll": "docs 03 §3.? -> staff_payroll_profiles",
    "inventory_requisitions": "docs 08 §8.11 -> requisitions + requisition_items",
    "total_sales_count": "docs 03 §3.65 -> uniform_sales_summary (view)",
    "student_fee_carryover": "docs 03 §3.? -> fee_carryover derived rows",
    "fee_transition_history": "docs 03 §3.? -> fee_transition_audit derived",
    "staff_onboarding": "docs 04 §4.15 -> workflow_instances + audit_logs",
}

from_clause = re.compile(
    r"\b(?:from|join|update|into|table|delete\s+from)\s+`([^`]+)`", re.I
)
trigger_on = re.compile(r"\bON\s+`([^`]+)`", re.I)
view_ref = re.compile(r"\b(?:from|join)\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?", re.I)


def order_views(names, get_sql):
    """Topologically order views so referenced views are created first.

    MySQL resolves view dependencies at CREATE time, so a view that selects
    from another view must be emitted after its dependencies.
    """
    pending = set(names)
    emitted = []
    while pending:
        nxt = sorted(
            n
            for n in pending
            if not {
                r
                for r in (m.group(1) for m in view_ref.finditer(get_sql(n)))
                if r in pending and r != n
            }
        )
        if not nxt:
            nxt = [sorted(pending)[0]]  # cycle fallback (order preserved)
        for n in nxt:
            pending.discard(n)
            emitted.append(n)
    return emitted


def referenced_tables(sql, kind):
    refs = set(m.group(1) for m in from_clause.finditer(sql))
    if kind == "trigger":
        refs |= set(m.group(1) for m in trigger_on.finditer(sql))
    return refs


def flag_comment(kind, name, refs, tables):
    missing = sorted(refs - tables)
    if not missing:
        return (
            "-- [KEEP] %s `%s`: references only surviving tables; recreated verbatim."
            % (kind, name)
        )
    cites = "; ".join(
        "%s (%s)" % (t, LEGACY_CITE.get(t, "no mapping cite")) for t in missing
    )
    return (
        "-- [REVIEW] %s `%s`: references legacy table(s) %s renamed/retired in the 3NF build (%s).\n--   Re-author against the target schema before use."
        % (kind, name, ", ".join("`%s`" % t for t in missing), cites)
    )


def build_section(
    objects,
    tables,
    failed,
    lines_out,
    override=None,
    trig_override=None,
    proc_override=None,
    fn_override=None,
):
    """Append Section 4 as individual lines; record (create_start_line, kind, name, live).

    `override` maps view name -> (sql, tag) where tag is "REAUTHORED" or "NEW".
    `trig_override` maps trigger name -> re-authored CREATE TRIGGER sql.
    `proc_override` / `fn_override` map procedure / function name -> re-authored
    CREATE DEFINER... sql.
    Overridden objects are emitted live instead of the legacy statement, and
    are exempt from create-time dropping.
    """
    override = override or {}
    trig_override = trig_override or {}
    proc_override = proc_override or {}
    fn_override = fn_override or {}
    index = []

    def L(s):
        """Append s (each line terminated by \\n) and return the 1-based line
        number it STARTS on."""
        start = len(lines_out) + 1
        lines_out.extend(s.splitlines(keepends=True))
        return start

    L("\n")
    L("-- ------------------------------------------------------------\n")
    L("-- Section 4: database objects (views, procedures, functions, triggers,\n")
    L("-- events) per docs/database_audit/02_objects_inventory.md\n")
    L("-- ------------------------------------------------------------\n")
    for kind in ("view", "procedure", "function", "trigger", "event"):
        L("\n")
        L("-- %s\n" % kind.capitalize())
        L("-- ------------------------------------------------------------\n")
        if kind == "view":
            names = order_views(
                set(objects[kind]) | set(override),
                lambda n: override[n][0] if n in override else objects[kind][n],
            )
        else:
            names = sorted(objects[kind])
        for name in names:
            if kind == "view" and name in override:
                sql, tag = override[name]
                L(
                    "-- [%s] view `%s`: re-authored against the 3NF/4NF schema per mapping docs; recreated verbatim.\n\n"
                    % (tag, name)
                )
                start = L(sql.rstrip() + ";\n\n")
                index.append((start, kind, name, True))
                continue
            if kind == "trigger" and name in trig_override:
                sql = trig_override[name]
                L(
                    "-- [REAUTHORED] trigger `%s`: re-authored against the 3NF/4NF schema per mapping docs; recreated verbatim.\n\n"
                    % name
                )
                L("DROP TRIGGER IF EXISTS `%s`;\n" % name)
                L("DELIMITER $$\n")
                start = L(sql.rstrip() + "$$\n")
                L("DELIMITER ;\n\n")
                index.append((start, kind, name, True))
                continue
            if kind == "procedure" and name in proc_override:
                sql = proc_override[name]
                L(
                    "-- [REAUTHORED] procedure `%s`: re-authored against the 3NF/4NF schema per mapping docs; recreated verbatim.\n\n"
                    % name
                )
                L("DROP PROCEDURE IF EXISTS `%s`;\n" % name)
                L("DELIMITER $$\n")
                start = L(sql.rstrip() + "$$\n")
                L("DELIMITER ;\n\n")
                index.append((start, kind, name, True))
                continue
            if kind == "function" and name in fn_override:
                sql = fn_override[name]
                L(
                    "-- [REAUTHORED] function `%s`: re-authored against the 3NF/4NF schema per mapping docs; recreated verbatim.\n\n"
                    % name
                )
                L("DROP FUNCTION IF EXISTS `%s`;\n" % name)
                L("DELIMITER $$\n")
                start = L(sql.rstrip() + "$$\n")
                L("DELIMITER ;\n\n")
                index.append((start, kind, name, True))
                continue
            sql = objects[kind][name]
            refs = referenced_tables(sql, kind)
            flag = flag_comment(kind, name, refs, tables)
            L(flag + "\n\n")
            if name in failed:
                msg = failed[name].replace("\n", " ")
                L(
                    "-- [DROPPED] %s `%s`: create-time validation failure:\n"
                    % (kind, name)
                )
                L("--   %s\n" % msg)
                L(
                    "--   (re-author against the target schema per mapping docs, then uncomment)\n"
                )
                for ln in sql.splitlines(keepends=True):
                    L("-- " + ln)
                L("\n")
                index.append((None, kind, name, False))
                continue
            if kind == "view":
                start = L(sql.rstrip() + ";\n\n")
            else:
                L("DROP %s IF EXISTS `%s`;\n" % (kind.upper(), name))
                L("DELIMITER $$\n")
                start = L(sql.rstrip() + "$$\n")
                L("DELIMITER ;\n\n")
            index.append((start, kind, name, True))
    return index


def import_test(text):
    # line-neutral: only swap the DB name; drop scratch separately
    sub = text.replace("`%s`" % REAL_DB, "`%s`" % SCRATCH).replace(
        "CREATE DATABASE `%s`" % SCRATCH, "CREATE DATABASE IF NOT EXISTS `%s`" % SCRATCH
    )
    with open(TMP_SQL, "w", encoding="utf-8") as fh:
        fh.write(sub)
    os.system(
        "%s -u root %s -e 'DROP DATABASE IF EXISTS `%s`;' > /dev/null 2>&1"
        % (MYSQL, ROOT, SCRATCH)
    )
    os.system(
        "%s -u root %s --force < %s > /dev/null 2> %s" % (MYSQL, ROOT, TMP_SQL, ERR_LOG)
    )
    with open(ERR_LOG, encoding="utf-8", errors="replace") as fh:
        return fh.readlines()


def main():
    objects = pickle.load(open(OBJ, "rb"))
    dlv = open(DLV, encoding="utf-8", errors="replace").read()
    tables = set(re.findall(r"^CREATE TABLE(?: IF NOT EXISTS)? `([^`]+)`", dlv, re.M))
    assert len(tables) == 350, len(tables)

    # base file = everything up to Section 4
    marker = "-- Section 4:"
    if marker in dlv:
        base = dlv.split(marker)[0]
        base = base.rstrip() + "\n"
    else:
        base = dlv.rstrip() + "\n"
        # base must end right after DML; append marker intro by build_section
        base += "\n"

    live_total = {"view": 0, "procedure": 0, "function": 0, "trigger": 0, "event": 0}

    override = {n: (sql, "REAUTHORED") for n, sql in reauth.REAUTHORED_VIEWS.items()}
    override.update({n: (sql, "NEW") for n, sql in reauth.NEW_VIEWS.items()})
    trig_override = dict(reauth_triggers.REAUTHORED_TRIGGERS)
    proc_override = dict(reauth_procs.REAUTHORIZED_PROCEDURES)
    fn_override = dict(reauth_procs.REAUTHORIZED_FUNCTIONS)

    def regenerate(failed):
        lines_out = base.splitlines(keepends=True)
        index = build_section(
            objects,
            tables,
            failed,
            lines_out,
            override,
            trig_override,
            proc_override,
            fn_override,
        )
        return "".join(lines_out), index

    text, index = regenerate({})
    errs = import_test(text)
    errors = [e for e in errs if "ERROR" in e]
    print("round 1 errors:", len(errors))

    # map error line -> object (nearest preceding CREATE-statement start index)
    def obj_at(line_no):
        best = None
        for start, kind, name, live in index:
            if start <= line_no:
                best = (kind, name)
            else:
                break
        return best

    failed = {}
    for e in errors:
        m = re.search(r" at line (\d+): (.*)", e)
        if not m:
            continue
        obj = obj_at(int(m.group(1)))
        if obj:
            failed.setdefault(obj[1], m.group(2).strip())

    print("objects failing CREATE validation:", len(failed))

    text2, index2 = regenerate(failed)
    errs2 = import_test(text2)
    errors2 = [e for e in errs2 if "ERROR" in e]
    print("round 2 errors:", len(errors2))
    for e in errors2[:20]:
        print("  ", e.strip())

    if not errors2:
        shutil.copy2(DLV, DLV + ".bak3")
        with open(DLV, "w", encoding="utf-8") as fh:
            fh.write(text2)
        print("deliverable updated: %d bytes" % len(text2))
        for start, kind, name, live in index2:
            if live:
                live_total[kind] += 1
        print("LIVE objects by kind:", live_total)
        print(
            "TOTAL objects by kind:",
            {
                k: len(objects[k])
                for k in ("view", "procedure", "function", "trigger", "event")
            },
        )
        for kind in ("view", "procedure", "function", "trigger", "event"):
            dropped = [name for (s, k, name, live) in index2 if not live and k == kind]
            print("%s dropped: %d" % (kind, len(dropped)))
            for nm in dropped:
                print("   -", nm)


if __name__ == "__main__":
    main()
