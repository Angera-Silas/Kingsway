#!/usr/bin/env python3
"""Analyze objects.pkl against the deliverable's table set.

For each extracted object (views/procedures/functions/triggers/events),
find referenced table names and classify the object as OK (all referenced
tables exist in the deliverable) or BROKEN (references missing tables).

Also reports `KingsWayAcademy.` schema qualifier usage.
"""

import pickle
import re

OBJ = "/tmp/opencode/etl/objects.pkl"
DLV = "/home/prof_angera/Projects/php_pages/Kingsway/database/KingWayDatabase_3nf_4nf_implemented.sql"

from_clause = re.compile(
    r"\b(?:from|join|update|into|table|delete\s+from)\s+"
    r"(?:`KingsWayAcademy`\.)?`([^`]+)`",
    re.I,
)
trigger_on = re.compile(r"\bON\s+(?:`KingsWayAcademy`\.)?`([^`]+)`", re.I)
call_proc = re.compile(r"\bCALL\s+`?([a-zA-Z0-9_]+)`?", re.I)


def referenced_tables(sql, kind):
    refs = set()
    for m in from_clause.finditer(sql):
        refs.add(m.group(1))
    if kind == "trigger":
        for m in trigger_on.finditer(sql):
            refs.add(m.group(1))
    return refs


def main():
    objects = pickle.load(open(OBJ, "rb"))
    dlv = open(DLV, encoding="utf-8", errors="replace").read()
    tables = set(re.findall(r"^CREATE TABLE(?: IF NOT EXISTS)? `([^`]+)`", dlv, re.M))
    print("deliverable tables:", len(tables))

    for kind, objs in objects.items():
        present = broken = 0
        broken_list = []
        qualifier = 0
        for name, sql in objs.items():
            if "KingsWayAcademy`." in sql:
                qualifier += 1
            refs = referenced_tables(sql, kind)
            missing = refs - tables
            if missing:
                broken += 1
                broken_list.append((name, sorted(missing)))
            else:
                present += 1
        print(
            "\n== %ss ==  OK:%d  broken:%d  (schema-qualified:%d)"
            % (kind, present, broken, qualifier)
        )
        for nm, miss in broken_list:
            print("   %-45s missing: %s" % (nm, ", ".join(miss[:6])))


if __name__ == "__main__":
    main()
