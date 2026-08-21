#!/usr/bin/env python3
"""
Phase 3 per-page controller review (docs/database_audit/16_FRONTEND_REVAMP_PLAN.md).

For every page under pages/ that wires a js/pages/<controller>.js controller, emit a
machine-derived review document filling the plan's template with what is mechanically
verifiable (heuristics flagged as such):

    page -> controller -> API.<module>.<method> -> endpoint -> backend handler
         -> backend expected params ($data / $_POST / $_GET keys, incl. service passthrough)
         -> payload keys sent by the page (object-literal keys, serializeArray, FormData)
         -> response keys consumed (post-await .prop access, unwrapped .data)
         -> data sources in the handler body (vw_* views, sp_* procs, FROM tables)
    plus: AuthContext.ready / permission guard presence, raw fetch usage, innerHTML vs
          escapeHtml (XSS), Bootstrap modal usage.

Emits:
    docs/frontend_matrix/pages/<route>.md        per-page review (template from the plan)
    docs/frontend_matrix/page_review_status.csv  one row per page + fix-task ids
    docs/frontend_matrix/page_review_index.md    headline counts + flag distribution + fix list

Usage: python3 scripts/frontend_matrix/generate_page_review.py
"""

import os
import re
import csv
import glob
from pathlib import Path
from collections import Counter, OrderedDict, defaultdict

import generate_role_matrix as g

ROOT = g.ROOT
OUT = g.OUT
PAGES_OUT = OUT / "pages"

HANDLER_STATUSES = ("ok",)
OK = "OK"
WARN = "warn"
NA = "na"

# ------------------------------------------------------------------- class index


def build_class_index():
    """{class name: repo-relative path} across api/**/*.php."""
    index = {}
    for f in sorted(glob.glob(str(ROOT / "api" / "**" / "*.php"), recursive=True)):
        rel = os.path.relpath(f, ROOT).replace("\\", "/")
        src = Path(f).read_text(encoding="utf-8", errors="replace")
        for m in re.finditer(r"class\s+(\w+)", src):
            index.setdefault(m.group(1), rel)
    return index


# ------------------------------------------------------------------ method bodies


def method_body(src, method):
    """Return the brace-balanced body of `function <method>(` (no leading `{`), or None."""
    m = re.search(r"function\s+" + re.escape(method) + r"\s*\(", src)
    if not m:
        return None
    i = src.find("{", m.start())
    if i < 0:
        return None
    depth = 1
    j = i + 1
    while j < len(src) and depth:
        c = src[j]
        if c == "{":
            depth += 1
        elif c == "}":
            depth -= 1
        j += 1
    return src[i + 1 : j - 1] if depth == 0 else None


_KEY_RE = re.compile(
    r"\$data\s*\[\s*[\"'](\w+)[\"']\s*\]"
    r"|\$data\s*->\s*(\w+)"
    r"|\$_POST\s*\[\s*[\"'](\w+)[\"']\s*\]"
    r"|\$_GET\s*\[\s*[\"'](\w+)[\"']\s*\]"
)


def _extract_keys(body):
    keys = []
    for m in _KEY_RE.finditer(body):
        key = next((x for x in m.groups() if x), None)
        if key:
            keys.append(key)
    return keys


_OPTIONAL_KEY_RE = re.compile(
    r"\$[A-Za-z_]\w*\[\s*['\"]([A-Za-z_]\w*)['\"]\s*\]\s*(\?\?|\|\|)"
)


def _optional_keys(body):
    """Keys read with a null-coalescing/fallback guard (e.g. `$data['month'] ?? null`),
    i.e. the backend tolerates their absence."""
    return [m.group(1) for m in _OPTIONAL_KEY_RE.finditer(body)]


def _data_sources(body):
    views = sorted(set(re.findall(r"\bvw_[A-Za-z0-9_]+", body)))
    procs = sorted(set(re.findall(r"\bsp_[A-Za-z0-9_]+", body)))
    funcs = sorted(set(re.findall(r"\bfn_[A-Za-z0-9_]+", body)))
    tables = sorted(
        set(
            re.findall(
                r"\b(?:FROM|JOIN|UPDATE|INTO)\s+(`?[A-Za-z_][A-Za-z0-9_]*`?)",
                body,
                re.I,
            )
        )
    )
    tables = [t.strip("`") for t in tables]
    return views, procs, funcs, tables


def backend_params(controller, method, ctrls, class_index, depth=0):
    """Resolve the backend method's expected input keys.

    Looks at the controller method body, then follows $this->prop->svc() passthroughs
    into the service/API class bodies (depth-bounded).
    Returns dict: keys, id_used, sources, passthrough.
    """
    entry = ctrls.get(controller) or {}
    if not entry:
        return None
    f = ROOT / "api" / "controllers" / entry["file"]
    src = Path(f).read_text(encoding="utf-8", errors="replace")
    body = method_body(src, method)
    if body is None:
        return None
    services = dict(re.findall(r"\$this->(\w+)\s*=\s*new\s+(\w+)\s*\(", src))
    keys = _extract_keys(body)
    optional = _optional_keys(body)
    passthrough = re.findall(r"\$this->(\w+)\s*->\s*(\w+)\s*\(", body)
    chain = []
    views, procs, funcs, tables = _data_sources(body)
    id_used = "$id" in body

    if depth < 2:
        for prop, svc_meth in passthrough:
            cls = services.get(prop)
            if not cls or cls not in class_index:
                continue
            svc_path = class_index[cls]
            ssrc = Path(ROOT / svc_path).read_text(encoding="utf-8", errors="replace")
            sbody = method_body(ssrc, svc_meth)
            if sbody is None:
                continue
            keys += _extract_keys(sbody)
            optional += _optional_keys(sbody)
            sv, sp, sf, st = _data_sources(sbody)
            views += sv
            procs += sp
            funcs += sf
            tables += st
            chain.append(f"{cls}::{svc_meth}")

    return {
        "keys": list(dict.fromkeys(keys)),
        "optional": list(dict.fromkeys(optional)),
        "id_used": id_used,
        "views": list(dict.fromkeys(views)),
        "procs": list(dict.fromkeys(procs)),
        "funcs": list(dict.fromkeys(funcs)),
        "tables": list(dict.fromkeys(tables)),
        "passthrough": list(dict.fromkeys(chain)),
    }


# ------------------------------------------------------------------- JS analysis


def capture_args(text, start):
    """Balanced-paren inner text after the '(' at `start`. Returns (inner, end_index)."""
    depth = 0
    i = start
    while i < len(text):
        c = text[i]
        if c == "(":
            depth += 1
        elif c == ")":
            depth -= 1
            if depth == 0:
                return text[start + 1 : i], i
        i += 1
    return text[start + 1 :], len(text)


def obj_keys(inner):
    """Top-level keys of the first object literal in `inner`."""
    m = re.search(r"\{\s*", inner)
    if not m:
        return []
    i = m.start() + 1
    depth = 1
    j = i
    while j < len(inner) and depth:
        c = inner[j]
        if c == "{":
            depth += 1
        elif c == "}":
            depth -= 1
        j += 1
    body = inner[i : j - 1]
    parts, d, cur, q = [], 0, [], None
    for ch in body:
        if q is not None:
            if ch == q:
                q = None
            cur.append(ch)
            continue
        if ch in "'\"`":
            q = ch
            cur.append(ch)
        elif ch == "{":
            d += 1
            cur.append(ch)
        elif ch == "}":
            d -= 1
            cur.append(ch)
        elif ch == "," and d == 0:
            parts.append("".join(cur))
            cur = []
        else:
            cur.append(ch)
    parts.append("".join(cur))
    keys = []
    for p in parts:
        m2 = re.match(r"\s*([A-Za-z_$][\w$]*)\s*:", p)
        if m2:
            keys.append(m2.group(1))
    return keys


API_CALL_RE = re.compile(
    r"(?:window\.)?API\??\.([A-Za-z_]\w*)\??\.([A-Za-z_]\w*)(?:\?\.)?\s*\("
)
BARE_CALLAPI_RE = re.compile(
    r"(?<![\w.?])(?:callAPI|apiCall)\(\s*([\"'`])(.*?)\1(?:\s*,\s*([\"'])(GET|POST|PUT|DELETE|PATCH)\3)?",
    re.DOTALL,
)
ASSN_AWAIT_RE = re.compile(
    r"(?:const|let|var)\s+(\w+)\s*=\s*await\s+(?:window\.)?API\.(\w+)\.(\w+)\s*\(",
    re.DOTALL,
)
EXCLUDE_RESP_PROPS = {"data", "then", "catch", "finally"}


def analyze_js(relpath):
    """Return a dict of machine checks for a js file under js/."""
    f = ROOT / relpath
    rec = {
        "exists": f.is_file(),
        "api_calls": [],
        "direct_calls": [],
        "payload": OrderedDict(),  # (module, method) -> {keys, calls, flags}
        "response_keys": [],
        "auth_ready": False,
        "permission_guard": False,
        "raw_fetch": 0,
        "inner_html": 0,
        "inner_html_interp": 0,
        "escape_html": 0,
        "modals": 0,
    }
    if not rec["exists"]:
        return rec
    src = f.read_text(encoding="utf-8", errors="replace")
    lines = [ln for ln in src.split("\n") if not re.match(r"^\s*(//|\*)", ln)]
    code = "\n".join(lines)

    rec["auth_ready"] = bool(re.search(r"AuthContext\s*\.\s*ready\s*\(", code))
    rec["permission_guard"] = bool(
        re.search(r"hasPermission\s*\(|requirePermission\s*\(|can\s*\.\s*\w+", code)
    )
    rec["raw_fetch"] = len(
        re.findall(r"\bfetch\s*\(|\bXMLHttpRequest\b|\baxios\b", code)
    )
    rec["inner_html"] = len(
        re.findall(r"innerHTML\s*=|insertAdjacentHTML\s*\(|outerHTML\s*=", code)
    )
    rec["inner_html_interp"] = len(
        re.findall(r"(?:innerHTML|outerHTML)\s*=\s*[^\n]*\$\{", code)
    )
    rec["escape_html"] = len(re.findall(r"\b(?:escapeHtml|_?escH?)\s*\(", code))
    rec["modals"] = len(
        re.findall(r"bootstrap\s*\.\s*Modal|data-bs-toggle\s*=\s*[\"']modal", code)
    )

    seen = set()
    for m in API_CALL_RE.finditer(code):
        key = (m.group(1), m.group(2))
        rec["api_calls"].append(key)
        if key in seen:
            continue
        seen.add(key)
        inner, _ = capture_args(code, m.end() - 1)
        keys = obj_keys(inner)
        flags = []
        if "serializeArray" in inner:
            flags.append("serializeArray")
        if "new FormData" in inner:
            flags.append("FormData")
        if "JSON.stringify" in inner:
            flags.append("JSON.stringify")
        if re.match(r"\s*\+", inner):
            flags.append("dynamic-arg")
        rec["payload"][key] = {
            "keys": list(dict.fromkeys(keys)),
            "flags": flags,
            "calls": 1 + sum(1 for x in rec["api_calls"] if x == key),
        }
    rec["api_calls"] = list(dict.fromkeys(rec["api_calls"]))

    # bare callAPI('/path', 'VERB') direct endpoints
    seen_direct = set()
    for m in BARE_CALLAPI_RE.finditer(code):
        key = (m.group(2), m.group(4) or "GET")
        if key in seen_direct:
            continue
        seen_direct.add(key)
        inner, _ = capture_args(code, m.end() - 1)
        dkeys = obj_keys(inner)
        dflags = []
        if "serializeArray" in inner:
            dflags.append("serializeArray")
        if "new FormData" in inner:
            dflags.append("FormData")
        rec["direct_calls"].append(
            {"path": key[0], "verb": key[1], "keys": dkeys, "flags": dflags}
        )

    # response keys consumed after each await assignment
    resp_vars = set()
    windows = []
    for m in ASSN_AWAIT_RE.finditer(code):
        var = m.group(1)
        nxt = ASSN_AWAIT_RE.search(code, m.end())
        end = nxt.start() if nxt else min(m.end() + 800, len(code))
        windows.append((m.group(2), m.group(3), var, code[m.end() : end]))
    for m in re.finditer(
        r"(?:const|let|var)\s+(\w+)\s*=\s*await\s+(?:callAPI|apiCall)\(", code
    ):
        var = m.group(1)
        nxt = ASSN_AWAIT_RE.search(code, m.end())
        end = nxt.start() if nxt else min(m.end() + 800, len(code))
        windows.append(("direct", "callAPI", var, code[m.end() : end]))
    for mod, meth, var, win in windows:
        keys = []
        for pm in re.finditer(r"\b%s\.\s*(\w+)" % re.escape(var), win):
            if pm.group(1) not in EXCLUDE_RESP_PROPS and pm.group(1) not in keys:
                keys.append(pm.group(1))
        # unwrap pattern: const payload = <var>?.data || <var> || {}
        for pm in re.finditer(
            r"(?:const|let|var)\s+(\w+)\s*=\s*"
            r"(?:%s\??\s*\.\s*data\s*\|\|\s*%s\s*\|\|\s*\{\}|%s\s*\|\|\s*\{\}|%s)"
            % (re.escape(var), re.escape(var), re.escape(var), re.escape(var)),
            win,
        ):
            pvar = pm.group(1)
            for km in re.finditer(r"\b%s\.\s*(\w+)" % re.escape(pvar), win):
                if km.group(1) not in EXCLUDE_RESP_PROPS and km.group(1) not in keys:
                    keys.append(km.group(1))
        for k in keys:
            if (mod, meth, k) not in resp_vars:
                resp_vars.add((mod, meth, k))
                rec["response_keys"].append(f"{mod}.{meth} -> {k}")
    return rec


# ---------------------------------------------------------------- role/page maps


def build_page_role_map(db):
    """route -> list of (role_id, role_name, item_id, item_label) from role_sidebar_menus."""
    roles = db["roles"]
    menus = db["menus"]
    out = defaultdict(list)
    for rid, mids in db["role_menus"].items():
        rname = roles[rid]["name"]
        for mid in mids:
            item = menus.get(mid)
            if not item:
                continue
            raw = (item["url"] or "").strip().lstrip("/")
            lookup = raw.split("?", 1)[0]
            if raw.startswith("home.php"):
                mq = re.search(r"[?&]route=([\w\-]+)", raw)
                lookup = mq.group(1) if mq else ""
            if lookup.endswith(".php"):
                lookup = lookup[:-4]
            if not lookup:
                continue
            out[lookup].append((rid, rname, mid, item["label"]))
    return out


FIX_FLAGS = ("NO_CONTROLLER_FILE", "RAW_FETCH", "UNRESOLVED_ENDPOINT", "XSS_NO_ESCAPE")


def status_flags(rec, endpoint_stats, xss_warn, page_file="", fragments=(), stubs=()):
    """Returns (fix_flags, info_flags). Only fix_flags produce a fix task."""
    fix = []
    info = []
    is_fragment = page_file in fragments
    is_stub = page_file in stubs
    if not rec["exists"] and not is_fragment and not is_stub:
        fix.append("NO_CONTROLLER_FILE")
    if is_fragment:
        info.append("TEMPLATE_FRAGMENT")
    if is_stub:
        info.append("DELEGATION_STUB")
    if rec["raw_fetch"]:
        fix.append("RAW_FETCH")
    if endpoint_stats["unresolved"]:
        fix.append("UNRESOLVED_ENDPOINT")
    if xss_warn:
        fix.append("XSS_NO_ESCAPE")
    if not rec["auth_ready"] and not rec["permission_guard"]:
        info.append("NO_AUTH_GUARD")
    if rec["inner_html"] == 0 and rec["raw_fetch"] == 0:
        info.append("RENDER_ONLY")
    if rec["inner_html"] and not rec["inner_html_interp"]:
        info.append("ESCAPED_LITERAL_HTML")
    return fix, info


def main():
    db = g.load_db()
    ctrls = g.load_controllers()
    api_modules = g.parse_api_modules()
    pages = g.load_pages()
    registry_routes = set()
    for _rid, r in db.get("routes", {}).items():
        for part in re.split(r"[?&]", r.get("url", "")):
            if part.startswith("route="):
                registry_routes.add(part[len("route=") :])
        if not r.get("url", "").startswith("home.php?route="):
            base = r.get("url", "").rsplit("/", 1)[-1]
            if base.endswith(".php"):
                registry_routes.add(base[:-4])
    stubs, fragments = g.load_embedded_pages(registry_routes)
    class_index = build_class_index()
    page_roles = build_page_role_map(db)

    PAGES_OUT.mkdir(parents=True, exist_ok=True)
    reviews = []  # rows for csv
    fix_counter = [0]

    for route in sorted(pages):
        page = pages[route]
        rec = {
            "route": route,
            "file": page["file"],
            "controllers": page["controllers"],
            "roles": [],
            "items": [],
            "checks": {},
            "endpoints": [],
            "payload": {},
            "backend": {},
            "params": NA,
            "flags": [],
            "fix_task": "",
        }
        for rid, rname, mid, label in page_roles.get(route, []):
            rec["roles"].append((rid, rname))
            rec["items"].append((mid, label))
        rec["roles"] = list(dict.fromkeys(rec["roles"]))
        rec["items"] = list(dict.fromkeys(rec["items"]))

        if page["controllers"]:
            rec["checks"] = analyze_js(
                os.path.join("js", "pages", page["controllers"][0])
            )
        else:
            rec["checks"] = analyze_js("js/pages/__none__")
            rec["checks"]["exists"] = False

        chk = rec["checks"]
        refs, direct, legacy = (
            g.controller_api_refs(os.path.join("js", "pages", page["controllers"][0]))
            if page["controllers"]
            else (Counter(), [], [])
        )
        endpoints = g.collect_calls(refs, direct, legacy, api_modules, ctrls)
        rec["endpoints"] = endpoints
        unresolved = [e for e in endpoints if g.is_unresolved(e)]

        for mod, meth in chk["payload"]:
            pl = chk["payload"][(mod, meth)]
            handlers = []
            for e in g.endpoint_for_call(mod, meth, api_modules, ctrls):
                bp = (
                    backend_params(e["controller"], e["method"], ctrls, class_index)
                    if e.get("controller") and e.get("method")
                    else None
                )
                rec["backend"][(mod, meth)] = bp
                handlers.append(
                    {
                        "path": f"{e['verb']} {e['path']}",
                        "status": e["status"],
                        "handler": f"{e['class']}::{e['method']}"
                        if e.get("method")
                        else None,
                        "params": bp,
                    }
                )
            rec["payload"][(mod, meth)] = {
                "keys": pl["keys"],
                "flags": pl["flags"],
                "handlers": handlers,
            }

        # direct callAPI endpoints -> backend handlers
        rec["direct_handlers"] = []
        for d in chk["direct_calls"]:
            res = g.resolve_endpoint(d["path"], d["verb"], ctrls)
            bp = (
                backend_params(res["controller"], res["method"], ctrls, class_index)
                if res.get("controller") and res.get("method")
                else None
            )
            rec["direct_handlers"].append(
                {
                    "path": f"{d['verb']} {d['path']}",
                    "status": res["status"],
                    "handler": f"{res['class']}::{res['method']}"
                    if res.get("method")
                    else None,
                    "params": bp,
                }
            )

        # params match: sent keys vs backend expected keys (per module.method)
        for (mod, meth), pinfo in rec["payload"].items():
            sent = set(pinfo["keys"])
            if not sent and not pinfo["flags"]:
                # No object literal at the call site: the api.js method may
                # inject the keys itself via a query string or positional id
                # (e.g. `/transport/students-by-route?route_id=${id}`). Count
                # those as sent so the backend-only check does not false-warn.
                for item in api_modules.get(mod, {}).get(meth, []):
                    path = item[0]
                    q = path.split("?", 1)
                    if len(q) > 1:
                        for kv in re.split(r"[&]", q[1]):
                            k = kv.split("=", 1)[0]
                            if k and not k.startswith("$"):
                                sent.add(k)
                    if q[0].endswith("/${") and pinfo.get("calls", 0) and not sent:
                        sent.add("id")
            expected = set()
            optional = set()
            for h in pinfo["handlers"]:
                if h["params"]:
                    expected |= set(h["params"]["keys"])
                    optional |= set(h["params"].get("optional", []))
            if sent or expected:
                missing = (expected - sent) - optional
                if missing and rec["params"] != WARN:
                    rec["params"] = WARN
                elif not missing and rec["params"] != WARN:
                    rec["params"] = OK

        xss_warn = chk["inner_html_interp"] > 0 and chk["escape_html"] == 0
        ep_stats = {"total": len(endpoints), "unresolved": len(unresolved)}
        fix_flags, info_flags = status_flags(
            chk, ep_stats, xss_warn, rec["file"], fragments, stubs
        )
        rec["flags"] = fix_flags
        rec["info_flags"] = info_flags
        if fix_flags:
            fix_counter[0] += 1
            rec["fix_task"] = f"FIX-3-{fix_counter[0]:04d}"

        reviews.append(rec)
        write_page_doc(rec)

    write_status_csv(reviews)
    write_index(reviews)
    print(f"page reviews: {len(reviews)} | fix tasks: {fix_counter[0]}")


def write_page_doc(rec):
    route = rec["route"]
    page = rec["file"]
    chk = rec["checks"]
    cname = rec["controllers"][0] if rec["controllers"] else "(none)"

    L = []
    L.append(f"# {route}.php")
    L.append("")
    L.append(f"- **File**: `pages/{page}`")
    L.append(f"- **Controller**: `{cname}`")
    roles = ", ".join(f"`{n}`" for _, n in rec["roles"]) or "—"
    items = ", ".join(f"`{i}`" for i, _ in rec["items"]) or "—"
    L.append(f"- **Roles**: {roles}")
    L.append(f"- **Sidebar item(s)**: {items}")
    L.append("")
    L.append("## Init / auth")
    L.append("")
    L.append(f"- `AuthContext.ready()` awaited: `{'Y' if chk['auth_ready'] else 'N'}`")
    L.append(f"- Permission/RBAC guard: `{'Y' if chk['permission_guard'] else 'N'}`")
    L.append(f"- Raw `fetch`/`XMLHttpRequest`/`axios`: `{chk['raw_fetch']}`")
    L.append("")
    L.append("## API methods used")
    L.append("")
    if chk["payload"]:
        L.append(
            g.md_table(
                [
                    "API method",
                    "Payload keys (sent)",
                    "Flags",
                    "Endpoint",
                    "Handler",
                    "Status",
                ],
                [
                    [
                        f"`{m}.{me}`",
                        ", ".join(f"`{k}`" for k in p["keys"]) or "—",
                        ", ".join(p["flags"]) or "—",
                        "<br>".join(h["path"] for h in p["handlers"]) or "—",
                        "<br>".join(h["handler"] or "—" for h in p["handlers"]),
                        "<br>".join(h["status"] for h in p["handlers"]),
                    ]
                    for (m, me), p in rec["payload"].items()
                ],
            )
        )
    else:
        L.append("_(no `API.<module>.<method>` calls detected)_")
    L.append("")
    if chk["direct_calls"]:
        L.append("## Direct `callAPI` endpoints")
        L.append("")
        L.append(
            g.md_table(
                ["Endpoint", "Payload keys (sent)", "Flags"],
                [
                    [
                        f"`{d['verb']} {d['path']}`",
                        ", ".join(f"`{k}`" for k in d["keys"]) or "—",
                        ", ".join(d["flags"]) or "—",
                    ]
                    for d in chk["direct_calls"]
                ],
            )
        )
        L.append("")
    L.append("## Backend params (expected)")
    L.append("")
    seen_bp = set()
    rows = []
    all_handlers = [
        (h["handler"], h["params"])
        for (mod, meth), pinfo in rec["payload"].items()
        for h in pinfo["handlers"]
    ] + [(h["handler"], h["params"]) for h in rec.get("direct_handlers", [])]
    for handler, bp in all_handlers:
        if not bp:
            continue
        key = (handler, tuple(bp["keys"]))
        if key in seen_bp:
            continue
        seen_bp.add(key)
        rows.append(
            [
                handler or "—",
                ", ".join(f"`{k}`" for k in bp["keys"]) or "—",
                f"`id`" if bp["id_used"] else "—",
                ", ".join(bp["views"] + bp["procs"] + bp["funcs"] + bp["tables"])
                or "—",
            ]
        )
    if rows:
        L.append(
            g.md_table(
                [
                    "Handler",
                    "Input keys ($data/$_POST/$_GET)",
                    "Path id",
                    "Data sources (views/procs/tables)",
                ],
                rows,
            )
        )
    else:
        L.append("_(no backend handler resolved for param extraction)_")
    L.append("")
    L.append("## Response shape (data keys consumed)")
    L.append("")
    if chk["response_keys"]:
        L.append("\n".join(f"- `{k}`" for k in chk["response_keys"]))
    else:
        L.append("_(no post-await `.prop` consumption detected)_")
    L.append("")
    L.append("## UI / security")
    L.append("")
    L.append(
        f"- `innerHTML`/`insertAdjacentHTML` assignments: `{chk['inner_html']}` (with interpolation: `{chk['inner_html_interp']}`)"
    )
    L.append(
        f"- `escapeHtml()` calls: `{chk['escape_html']}` — XSS check: `{'FAIL' if chk['inner_html_interp'] > 0 and chk['escape_html'] == 0 else 'PASS'}`"
    )
    L.append(f"- Bootstrap modal usage: `{chk['modals']}`")
    L.append(f"- Payload/backend param match: `{rec['params'].upper()}`")
    L.append(f"- Fix flags: {', '.join('`' + f + '`' for f in rec['flags']) or 'none'}")
    L.append(
        f"- Info flags: {', '.join('`' + f + '`' for f in rec.get('info_flags', [])) or 'none'}"
    )
    L.append(f"- Fix task: `{rec['fix_task'] or '—'}`")
    L.append("")
    L.append("## End-to-end trace")
    L.append("")
    L.append(
        "> collect → store → analyse → display: page payload keys → API endpoint → controller "
        "method → service passthrough → SQL views/procs/tables (rows above). This is "
        "machine-derived; the interactive flow (form submit → render) needs human sign-off."
    )
    L.append("")
    L.append("## Review checklist")
    L.append("")
    L.append("| Check | Status | Notes |")
    L.append("|---|---|---|")
    L.append(
        "| Init: `AuthContext.ready()` | "
        + ("✅" if chk["auth_ready"] else "❌")
        + " | machine |"
    )
    L.append(
        "| Data load: `window.API.*`, no raw fetch | "
        + ("✅" if chk["raw_fetch"] == 0 else "❌")
        + " | machine |"
    )
    L.append(
        "| Params: sent ≈ backend `$data` | "
        + ({"ok": "✅", "warn": "❌"}.get(rec["params"], "⚠️"))
        + " | heuristic |"
    )
    L.append(
        "| Response: unwrapped `data` handled | "
        + ("✅" if chk["response_keys"] else "⚠️")
        + " | heuristic |"
    )
    L.append(
        "| UI: Bootstrap + `escapeHtml` | "
        + ("✅" if chk["inner_html_interp"] == 0 or chk["escape_html"] > 0 else "❌")
        + " | machine |"
    )
    L.append("| Responsive @375/768/1200px | ⚠️ | **manual sign-off required** |")
    L.append("")

    out = PAGES_OUT / (route + ".md")
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text("\n".join(L), encoding="utf-8")


def write_status_csv(reviews):
    with open(OUT / "page_review_status.csv", "w", newline="") as fh:
        w = csv.writer(fh)
        w.writerow(
            [
                "page",
                "file",
                "controller",
                "roles",
                "sidebar_items",
                "n_api_calls",
                "n_endpoints",
                "n_unresolved",
                "auth_ready",
                "permission_guard",
                "raw_fetch",
                "inner_html",
                "inner_html_interp",
                "escape_html",
                "xss_warn",
                "bootstrap_modals",
                "params_match",
                "fix_task",
                "flags",
                "info_flags",
            ]
        )
        for rec in reviews:
            chk = rec["checks"]
            xss = chk["inner_html_interp"] > 0 and chk["escape_html"] == 0
            w.writerow(
                [
                    rec["route"],
                    rec["file"],
                    rec["controllers"][0] if rec["controllers"] else "",
                    ";".join(n for _, n in rec["roles"]),
                    ";".join(str(i) for i, _ in rec["items"]),
                    len(chk["api_calls"]),
                    len(rec["endpoints"]),
                    sum(1 for e in rec["endpoints"] if e["status"] != "ok"),
                    1 if chk["auth_ready"] else 0,
                    1 if chk["permission_guard"] else 0,
                    chk["raw_fetch"],
                    chk["inner_html"],
                    chk["inner_html_interp"],
                    chk["escape_html"],
                    1 if xss else 0,
                    chk["modals"],
                    rec["params"],
                    rec["fix_task"],
                    ";".join(rec["flags"]),
                    ";".join(rec.get("info_flags", [])),
                ]
            )


def write_index(reviews):
    n = len(reviews)
    withc = [r for r in reviews if r["checks"]["exists"]]
    noctrl = [r for r in reviews if not r["checks"]["exists"]]
    L = []
    L.append("# Phase 3 — Per-Page Controller Review")
    L.append("")
    L.append(
        "Generated by `scripts/frontend_matrix/generate_page_review.py` against the live "
        "`KingsWayAcademy` database and this repo. One machine-derived review per page under "
        "`pages/`; human-judgement fields (responsive behaviour, interactive flow) are marked "
        "for manual sign-off."
    )
    L.append("")
    L.append("## Headline counts")
    L.append("")
    L.append(
        g.md_table(
            ["Metric", "Value"],
            [
                ["Pages reviewed", n],
                ["With `js/pages` controller", len(withc)],
                ["Without controller (legacy/inline/stub)", len(noctrl)],
            ],
        )
    )
    L.append("")
    L.append("## Flag distribution")
    L.append("")
    fix_dist = Counter()
    info_dist = Counter()
    for r in reviews:
        for f in r["flags"]:
            fix_dist[f] += 1
        for f in r.get("info_flags", []):
            info_dist[f] += 1
    L.append("### Fix-triggering")
    L.append("")
    L.append(g.md_table(["Flag", "Pages"], [[f, c] for f, c in fix_dist.most_common()]))
    L.append("")
    L.append("### Informational")
    L.append("")
    L.append(
        g.md_table(["Flag", "Pages"], [[f, c] for f, c in info_dist.most_common()])
    )
    L.append("")
    L.append("## Fix tasks")
    L.append("")
    L.append(
        g.md_table(
            ["Task", "Page", "Controller", "Flags"],
            [
                [
                    r["fix_task"],
                    r["route"],
                    r["controllers"][0] if r["controllers"] else "",
                    ";".join(r["flags"]),
                ]
                for r in reviews
                if r["fix_task"]
            ],
        )
    )
    L.append("")
    L.append("## Manual sign-off queue (unflagged pages)")
    L.append("")
    L.append(
        g.md_table(
            ["Page", "Controller"],
            [
                [r["route"], r["controllers"][0] if r["controllers"] else ""]
                for r in reviews
                if not r["flags"]
            ],
        )
    )
    L.append("")
    (OUT / "page_review_index.md").write_text("\n".join(L), encoding="utf-8")


if __name__ == "__main__":
    main()
