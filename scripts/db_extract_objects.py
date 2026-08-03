#!/usr/bin/env python3
"""Single-pass extractor for non-table DB objects from a mysqldump.

Extracts CREATE statements for views, procedures, functions, events and
triggers (including DEFINER=... variants), skipping the companion DROP
statements, and dumps them to objects.pkl keyed by object class.

Output shape:
    {"views": {"<name>": "<CREATE sql>"}, "procedures": {...}, ...}

Literal-aware: terminator matching (; or $$ per DELIMITER) skips content
inside single quotes, double quotes, backticks, line comments and block
comments so embedded ; / $$ in strings never split a statement.
"""

import pickle
import re
import sys

SRC = "/home/prof_angera/Projects/php_pages/Kingsway/database/KingsWayDatabase_2026_08_01_1409hrs.sql"
OUT = "/tmp/opencode/etl/objects.pkl"

OBJECT_KINDS = ("view", "procedure", "function", "event", "trigger")


def parse(src_text):
    objects = {k: {} for k in OBJECT_KINDS}
    i = 0
    n = len(src_text)
    delim = ";"
    buf = []

    def kind_of(stmt):
        s = stmt.lstrip()
        m = re.match(
            r"CREATE\s+(?:OR\s+REPLACE\s+)?"
            r"(?:DEFINER\s*=\s*[^\s]+\s+)?"
            r"(?P<kind>VIEW|PROCEDURE|FUNCTION|EVENT|TRIGGER)\b",
            s,
            re.I,
        )
        return m.group("kind").lower() if m else None

    def name_of(stmt, kind):
        if kind == "view":
            m = re.search(r"CREATE\s+OR\s+REPLACE\s+VIEW\s+`([^`]+)`", stmt, re.I)
        elif kind == "trigger":
            m = re.search(
                r"CREATE\s+(?:DEFINER\s*=\s*`[^`]+`@`[^`]+`\s+)?TRIGGER\s+`([^`]+)`",
                stmt,
                re.I,
            )
        else:
            m = re.search(
                r"CREATE\s+DEFINER\s*=\s*`[^`]+`@`[^`]+`\s+%s\s+`([^`]+)`" % kind,
                stmt,
                re.I,
            )
        return m.group(1) if m else None

    dlen = len(delim)
    while i < n:
        c = src_text[i]
        if buf:
            pass
        # line comment
        if src_text.startswith("--", i) or c == "#":
            j = src_text.find("\n", i)
            i = n if j == -1 else j + 1
            continue
        # block comment
        if src_text.startswith("/*", i):
            j = src_text.find("*/", i + 2)
            i = n if j == -1 else j + 2
            continue
        # delimiter directive
        if src_text.startswith("DELIMITER", i) and (
            i == 0 or src_text[i - 1] in "\n\r"
        ):
            j = src_text.find("\n", i)
            line = src_text[i : j if j != -1 else n].strip()
            parts = line.split()
            if len(parts) >= 2:
                delim = parts[1]
                dlen = len(delim)
            i = n if j == -1 else j + 1
            continue
        # string literals (keep content in buf; only terminator matching must skip)
        if c in ("'", '"', "`"):
            q = c
            buf.append(c)
            i += 1
            while i < n:
                if src_text[i] == q:
                    if i + 1 < n and src_text[i + 1] == q:
                        buf.append(q)
                        buf.append(q)
                        i += 2
                        continue
                    buf.append(q)
                    i += 1
                    break
                if q != "`" and src_text[i] == "\\" and i + 1 < n:
                    buf.append(src_text[i])
                    buf.append(src_text[i + 1])
                    i += 2
                    continue
                buf.append(src_text[i])
                i += 1
            continue
        # terminator
        if dlen == 1:
            if c == delim:
                stmt = "".join(buf).strip()
                buf = []
                if stmt:
                    k = kind_of(stmt)
                    if k:
                        nm = name_of(stmt, k)
                        if nm:
                            objects[k][nm] = stmt
                i += 1
                continue
        else:
            if src_text.startswith(delim, i):
                stmt = "".join(buf).strip()
                buf = []
                if stmt:
                    k = kind_of(stmt)
                    if k:
                        nm = name_of(stmt, k)
                        if nm:
                            objects[k][nm] = stmt
                i += dlen
                continue
        buf.append(c)
        i += 1

    return objects


def main():
    with open(SRC, "r", encoding="utf-8", errors="replace") as fh:
        text = fh.read()
    objects = parse(text)
    import os

    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    with open(OUT, "wb") as fh:
        pickle.dump(objects, fh, protocol=4)
    for k in OBJECT_KINDS:
        print("%-11s %d" % (k + "s", len(objects[k])))


if __name__ == "__main__":
    sys.exit(main())
