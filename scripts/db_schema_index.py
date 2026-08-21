#!/usr/bin/env python3
"""Parse the deliverable's CREATE TABLE blocks into a machine-readable schema.

Output: /tmp/opencode/etl/schema_new.json
  { "<table>": {"columns": ["id", ...], "indexes": [{"type","name","cols"}]} }
"""

import json
import re

DLV = "/home/prof_angera/Projects/php_pages/Kingsway/database/KingWayDatabase_3nf_4nf_implemented.sql"
OUT = "/tmp/opencode/etl/schema_new.json"

col_re = re.compile(r"^\s*`([^`]+)`\s+\S")
key_re = re.compile(
    r"^\s*(PRIMARY KEY|UNIQUE KEY|KEY|FULLTEXT KEY)\s+(?:`([^`]+)`\s*)?\(([^)]*)\)"
)
skip_prefix = (
    "PRIMARY KEY",
    "UNIQUE KEY",
    "KEY ",
    "FULLTEXT KEY",
    "CONSTRAINT",
    "FOREIGN KEY",
    "INDEX",
)


def main():
    text = open(DLV, encoding="utf-8", errors="replace").read()
    blocks = re.findall(
        r"CREATE TABLE IF NOT EXISTS `([^`]+)` \((.*?)\)\s+ENGINE=", text, re.S
    )
    schema = {}
    for name, body in blocks:
        columns = []
        indexes = []
        for line in body.splitlines():
            if any(line.lstrip().startswith(p) for p in skip_prefix):
                km = key_re.match(line)
                if km:
                    ktype, kname, kcols = km.groups()
                    indexes.append(
                        {
                            "type": "PRIMARY"
                            if ktype == "PRIMARY KEY"
                            else "UNIQUE"
                            if ktype == "UNIQUE KEY"
                            else ktype,
                            "name": kname or "PRIMARY",
                            "cols": [c.strip().strip("`") for c in kcols.split(",")],
                        }
                    )
                continue
            m = col_re.match(line)
            if m:
                columns.append(m.group(1))
        schema[name] = {"columns": columns, "indexes": indexes}
    json.dump(schema, open(OUT, "w"), indent=1)
    print("tables parsed:", len(schema))
    print("sample:", list(schema)[:15])
    ncols = sum(len(v["columns"]) for v in schema.values())
    nidx = sum(len(v["indexes"]) for v in schema.values())
    print("total columns:", ncols, "total index entries:", nidx)
    # tables with NO non-primary index
    noidx = [
        t
        for t, v in schema.items()
        if not any(i["type"] != "PRIMARY" for i in v["indexes"])
    ]
    print("tables with no secondary index:", len(noidx))
    print(sorted(noidx)[:40])


if __name__ == "__main__":
    main()
