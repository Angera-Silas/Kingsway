#!/usr/bin/env python3
"""Add missing secondary indexes to the 3NF/4NF deliverable inline.

Two passes:
  1. FK/join columns  - every `*_id` column (except `id`) across all tables that
                        has no existing index starting with it.
  2. Zero-index tables - the 21 tables whose only index is the PRIMARY key also
                        get indexes on common filter columns (status, dates,
                        created_at/updated_at, name, code, email, phone).

Index names are `idx_<table>_<col>` (truncated to 64 chars with a short hash
suffix when needed). TEXT/LONGTEXT/BLOB/JSON columns are skipped (they need a
prefix length to index). Backs up the deliverable before writing.
"""

import json
import re
import shutil

DLV = "/home/prof_angera/Projects/php_pages/Kingsway/database/KingWayDatabase_3nf_4nf_implemented.sql"
SCHEMA = "/tmp/opencode/etl/schema_new.json"

skip_prefix = (
    "PRIMARY KEY",
    "UNIQUE KEY",
    "KEY ",
    "FULLTEXT KEY",
    "CONSTRAINT",
    "FOREIGN KEY",
    "INDEX",
)
non_indexable = (
    "text",
    "longtext",
    "tinytext",
    "mediumtext",
    "blob",
    "longblob",
    "json",
)

filter_cols = ("status", "created_at", "updated_at")


def col_type(body_lines):
    """col_name -> normalized type string for the CREATE TABLE body."""
    out = {}
    for line in body_lines:
        m = re.match(r"\s*`([^`]+)`\s+([a-z]+)", line)
        if m:
            out[m.group(1)] = m.group(2).lower()
    return out


def existing_first_cols(body_lines):
    first = set()
    for line in body_lines:
        m = re.match(
            r"\s*(?:PRIMARY KEY|UNIQUE KEY|KEY|FULLTEXT KEY)\s+(?:`[^`]+`\s*)?\(`([^`]+)`",
            line,
        )
        if m:
            first.add(m.group(1))
    return first


def idx_name(table, col, used):
    base = "idx_%s_%s" % (table, col)
    name = base[:64]
    if len(base) > 64:
        name = base[:57] + "%04x" % (hash(col) & 0xFFFF)
    while name in used:
        name = name[:-1] + "x"
    used.add(name)
    return name


def main():
    text = open(DLV, encoding="utf-8", errors="replace").read()
    schema = json.load(open(SCHEMA))
    zero = {
        t
        for t, v in schema.items()
        if not any(i["type"] != "PRIMARY" for i in v["indexes"])
    }

    pattern = re.compile(
        r"(CREATE TABLE IF NOT EXISTS `([^`]+)` \()(.*?)(\)\s+ENGINE=)", re.S
    )
    new_total = 0
    skipped_text = []
    output = []
    pos = 0
    for m in pattern.finditer(text):
        prefix, table, body, suffix = m.group(1), m.group(2), m.group(3), m.group(4)
        output.append(text[pos : m.start()])
        body_lines = body.splitlines(keepends=True)
        types = col_type(body_lines)
        first = existing_first_cols(body_lines)
        used = {
            m.group(1)
            for m in re.finditer(
                r"\s*(?:UNIQUE KEY|KEY|FULLTEXT KEY)\s+`([^`]+)`", body
            )
        }

        cand = []
        if table in zero:
            for c in schema[table]["columns"]:
                if c == "id" or c in first:
                    continue
                if (
                    c.endswith("_id")
                    or c in filter_cols
                    or c.endswith("_date")
                    or c in ("name", "code", "email", "phone")
                ):
                    cand.append(c)
        else:
            for c in schema[table]["columns"]:
                if c != "id" and c.endswith("_id") and c not in first:
                    cand.append(c)

        keys = []
        for c in cand:
            t = types.get(c, "")
            if any(t.startswith(p) for p in non_indexable):
                skipped_text.append("%s.%s (%s)" % (table, c, t))
                continue
            keys.append("  KEY `%s` (`%s`)" % (idx_name(table, c, used), c))

        if keys:
            # last existing line must carry a trailing comma
            if body_lines and not body_lines[-1].rstrip().endswith(","):
                body_lines[-1] = body_lines[-1].rstrip() + ",\n"
            body_lines.extend(l + "\n" for l in keys)
            body = "".join(body_lines)
            new_total += len(keys)
        output.append(prefix + body + suffix)
        pos = m.end()
    output.append(text[pos:])

    if new_total == 0:
        print("no new indexes; nothing written")
        return

    shutil.copy2(DLV, DLV + ".bak4")
    open(DLV, "w", encoding="utf-8").write("".join(output))
    print("new indexes added:", new_total)
    print("skipped text/blob/json columns:", len(skipped_text))
    for s in skipped_text:
        print("   -", s)


if __name__ == "__main__":
    main()
