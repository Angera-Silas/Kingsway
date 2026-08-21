#!/usr/bin/env python3
"""Rebuild the deliverable to cover ALL database objects, not just tables.

* Removes the 84 empty `vw_*/v_*` projection tables.
* Appends Section 4: real views, procedures, functions, triggers, events
  extracted from the source dump (objects.pkl), each with a disposition
  flag and a mapping-doc citation.
* Updates the header notes.

Output: KingWayDatabase_3nf_4nf_implemented.sql (in place, with .bak).
"""

import pickle
import re
import shutil

OBJ = "/tmp/opencode/etl/objects.pkl"
DLV = "/home/prof_angera/Projects/php_pages/Kingsway/database/KingWayDatabase_3nf_4nf_implemented.sql"

# legacy table -> mapping doc citation (10_NORMALIZATION_MAPPING files)
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
}

from_clause = re.compile(
    r"\b(?:from|join|update|into|table|delete\s+from)\s+`([^`]+)`", re.I
)
trigger_on = re.compile(r"\bON\s+`([^`]+)`", re.I)


def referenced_tables(sql, kind):
    refs = set(m.group(1) for m in from_clause.finditer(sql))
    if kind == "trigger":
        refs |= set(m.group(1) for m in trigger_on.finditer(sql))
    return refs


def flag_comment(kind, name, refs, tables):
    missing = sorted(refs - tables)
    if not missing:
        return (
            "-- [KEEP] %s `%s`: references only surviving tables; recreated verbatim.\n"
            % (kind, name)
        )
    cites = "; ".join(
        "%s (%s)" % (t, LEGACY_CITE.get(t, "no mapping cite")) for t in missing
    )
    return (
        "-- [REVIEW] %s `%s`: references legacy table(s) %s, renamed/retired in the\n"
        "-- 3NF build (%s). Emitted verbatim for the record; re-author against the\n"
        "-- target schema before use.\n"
    ) % (kind, name, ", ".join("`%s`" % t for t in missing), cites)


def main():
    objects = pickle.load(open(OBJ, "rb"))
    dlv = open(DLV, encoding="utf-8", errors="replace").read()
    tables = set(re.findall(r"^CREATE TABLE(?: IF NOT EXISTS)? `([^`]+)`", dlv, re.M))

    # --- remove the 84 view-projection table blocks ---------------------
    projection = set(objects["view"].keys())
    lines = dlv.splitlines(keepends=True)
    out = []
    i = 0
    removed = 0
    n = len(lines)
    while i < n:
        line = lines[i]
        m = re.match(r"^-- DDL: (`[^`]+`|[\w]+) \(extra/view\)", line)
        if m:
            name = m.group(1).strip("`")
            if name in projection:
                # consume comment trio + CREATE TABLE block
                while i < n and "-- DDL:" in lines[i]:
                    i += 1
                # now at CREATE TABLE IF NOT EXISTS `name` ( ... );
                while i < n:
                    if re.match(
                        r"^CREATE TABLE IF NOT EXISTS `%s` \(" % re.escape(name),
                        lines[i],
                    ):
                        while i < n and not lines[i].rstrip().endswith(");"):
                            i += 1
                        i += 1  # the `);` line
                        break
                    i += 1
                removed += 1
                continue
        out.append(line)
        i += 1
    body = "".join(out)
    print("projection blocks removed:", removed)

    # --- update header notes ---------------------------------------------
    old_note = (
        "--   * vw_*/v_* entries are emitted verbatim as projection tables\n"
        "--     (0 rows) so view-backed controllers keep their schema.\n"
    )
    new_note = (
        "--   * All database objects are covered in Section 4: 84 views, 150\n"
        "--     procedures, 21 functions, 58 triggers, 10 events (extracted from\n"
        "--     the source dump; see docs/database_audit/02_objects_inventory.md).\n"
    )
    assert old_note in body, "header note not found"
    body = body.replace(old_note, new_note)

    # --- strip old trailing comment ---------------------------------------
    old_tail = (
        "-- ------------------------------------------------------------\n"
        "-- Views\n"
        "-- ------------------------------------------------------------\n"
        "-- view projections emitted as tables in Section 1/3 above\n"
    )
    body = body.rstrip()
    if body.endswith(old_tail.rstrip()):
        body = body[: -len(old_tail.rstrip())].rstrip()

    # --- build Section 4 ---------------------------------------------------
    sec = []
    sec.append("\n-- ------------------------------------------------------------\n")
    sec.append("-- Section 4: Database objects (views, procedures, functions,\n")
    sec.append("-- triggers, events) per docs/database_audit/02_objects_inventory.md\n")
    sec.append("-- ------------------------------------------------------------\n")

    counts = {"view": 0, "procedure": 0, "function": 0, "trigger": 0, "event": 0}
    for kind in ("view", "procedure", "function", "trigger", "event"):
        sec.append(
            "\n-- %s\n-- ------------------------------------------------------------\n"
            % kind.capitalize()
        )
        for name in sorted(objects[kind]):
            sql = objects[kind][name]
            refs = referenced_tables(sql, kind)
            sec.append(flag_comment(kind, name, refs, tables))
            if kind == "view":
                sec.append(sql.rstrip() + ";\n\n")
            else:
                sec.append(
                    "DROP %s IF EXISTS `%s`;\nDELIMITER $$\n" % (kind.upper(), name)
                )
                sec.append(sql.rstrip() + "$$\nDELIMITER ;\n\n")
            counts[kind] += 1

    final = body + "\n" + "".join(sec)
    print("object counts:", counts)

    shutil.copy2(DLV, DLV + ".bak")
    with open(DLV, "w", encoding="utf-8") as fh:
        fh.write(final)
    print("wrote", DLV, "(%d bytes)" % len(final))


if __name__ == "__main__":
    main()
