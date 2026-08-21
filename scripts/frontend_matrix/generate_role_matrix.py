#!/usr/bin/env python3
"""
Generate the Phase 2 frontend role matrix (docs/database_audit/16_FRONTEND_REVAMP_PLAN.md).

Builds the full chain for every role:
    role -> sidebar_menu_items (role_sidebar_menus) -> url -> pages/<url>.php
         -> js/pages/<controller>.js -> API.<module>.<method> -> api.js path+verb
         -> api/controllers/<X>Controller::method  (replicates ControllerRouter)
Plus dashboards (role_dashboards -> dashboards -> components/dashboards/<key>.php
-> js/dashboards/<key>.js) and route-level authorization (role_routes -> routes_registry).

Outputs (created fresh in docs/frontend_matrix/):
    index.md              — methodology + headline counts + shared-endpoint analysis + findings
    roles/<role-slug>.md  — per-role deep matrix
    summary.csv           — one row per (role, sidebar item)
    endpoints.csv         — one row per (role, endpoint) with handler resolution

Usage: python3 scripts/frontend_matrix/generate_role_matrix.py
"""

import os
import re
import csv
import glob
import json
import subprocess
from pathlib import Path
from collections import Counter, OrderedDict, defaultdict

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / "docs" / "frontend_matrix"
ROLES_OUT = OUT / "roles"

VERBS = ("GET", "POST", "PUT", "DELETE", "PATCH")

# --------------------------------------------------------------------------- DB


def db_config():
    cfg = {}
    with open(ROOT / "config" / ".env", encoding="utf-8") as fh:
        for line in fh:
            line = line.strip()
            if line and "=" in line and not line.startswith("#"):
                k, _, v = line.partition("=")
                cfg[k.strip()] = v.strip()
    return cfg


def mysql(sql):
    cfg = db_config()
    cmd = [
        "mysql",
        "-h",
        cfg.get("DB_HOST", "127.0.0.1"),
        "-P",
        str(cfg.get("DB_PORT", "3306")),
        "-u",
        cfg.get("DB_USER", "root"),
        "-p" + cfg.get("DB_PASS", ""),
        "-N",
        "--batch",
        cfg.get("DB_NAME", "KingsWayAcademy"),
        "-e",
        sql,
    ]
    proc = subprocess.run(cmd, capture_output=True, text=True)
    if proc.returncode != 0:
        raise RuntimeError(f"mysql failed: {proc.stderr[-1500:]}")
    return [row.split("\t") for row in proc.stdout.splitlines() if row.strip()]


def load_db():
    db = {}
    roles = {}
    for rid, name, scope, is_system, is_active, user_count in mysql(
        "SELECT id, name, scope, is_system, is_active, user_count FROM roles ORDER BY id"
    ):
        roles[int(rid)] = {
            "id": int(rid),
            "name": name,
            "scope": scope,
            "is_system": int(is_system),
            "is_active": int(is_active),
            "user_count": int(user_count or 0),
        }
    db["roles"] = roles

    menus = {}
    for (
        mid,
        name,
        label,
        url,
        route_id,
        parent_id,
        menu_type,
        display_order,
        domain,
        is_active,
    ) in mysql(
        "SELECT id, name, label, IFNULL(url,''), IFNULL(route_id,0), IFNULL(parent_id,0), "
        "menu_type, display_order, IFNULL(domain,''), is_active FROM sidebar_menu_items ORDER BY display_order, id"
    ):
        menus[int(mid)] = {
            "id": int(mid),
            "name": name,
            "label": label,
            "url": url,
            "route_id": int(route_id),
            "parent_id": int(parent_id),
            "menu_type": menu_type,
            "display_order": int(display_order),
            "domain": domain,
            "is_active": int(is_active),
        }
    db["menus"] = menus

    role_menus = defaultdict(list)
    for rid, mid in mysql(
        "SELECT role_id, menu_item_id FROM role_sidebar_menus ORDER BY custom_order, id"
    ):
        role_menus[int(rid)].append(int(mid))
    db["role_menus"] = dict(role_menus)

    routes = {}
    for rid, name, url, domain, module, controller, action, is_active in mysql(
        "SELECT id, name, IFNULL(url,''), IFNULL(domain,''), IFNULL(module,''), "
        "IFNULL(controller,''), IFNULL(action,''), is_active FROM routes_registry ORDER BY id"
    ):
        routes[int(rid)] = {
            "id": int(rid),
            "name": name,
            "url": url,
            "domain": domain,
            "module": module,
            "controller": controller,
            "action": action,
            "is_active": int(is_active),
        }
    db["routes"] = routes

    role_routes = defaultdict(list)
    for rid, rid2 in mysql(
        "SELECT role_id, route_id FROM role_routes WHERE is_allowed = 1"
    ):
        role_routes[int(rid)].append(int(rid2))
    db["role_routes"] = dict(role_routes)

    dashboards = {}
    for did, name, display_name, domain, route_id, is_active in mysql(
        "SELECT id, name, IFNULL(display_name,''), IFNULL(domain,''), IFNULL(route_id,0), "
        "is_active FROM dashboards ORDER BY id"
    ):
        dashboards[int(did)] = {
            "id": int(did),
            "name": name,
            "display_name": display_name,
            "domain": domain,
            "route_id": int(route_id),
            "is_active": int(is_active),
        }
    db["dashboards"] = dashboards

    role_dash = defaultdict(list)
    for rid, did, is_primary, disp_order in mysql(
        "SELECT role_id, dashboard_id, is_primary, IFNULL(display_order,0) "
        "FROM role_dashboards ORDER BY is_primary DESC, display_order, dashboard_id"
    ):
        role_dash[int(rid)].append(int(did))
    db["role_dashboards"] = dict(role_dash)
    return db


# ------------------------------------------------------------------- backend


def normalize_controller_key(name):
    return re.sub(r"[^a-z0-9]", "", name.lower())


def camel_words(resource):
    parts = [p for p in re.split(r"[-_\s]", resource) if p]
    if not parts:
        return ""
    return "".join(p.capitalize() for p in parts)


def load_controllers():
    ctrls = {}
    for f in sorted(glob.glob(str(ROOT / "api" / "controllers" / "*Controller.php"))):
        class_name = os.path.basename(f)[: -len("Controller.php")]
        key = normalize_controller_key(class_name)
        src = Path(f).read_text(encoding="utf-8", errors="replace")
        methods = set(re.findall(r"public\s+function\s+(\w+)\s*\(", src))
        entry = {"file": os.path.basename(f), "class": class_name, "methods": methods}
        ctrls[key] = entry
        stripped = re.sub(r"\d+", "", key)
        if stripped and stripped != key:
            ctrls.setdefault(stripped, entry)
        if key.endswith("s"):
            ctrls.setdefault(key[:-1], entry)
    return ctrls


def resolve_endpoint(path, verb, ctrls):
    """Replicate api/router/ControllerRouter.php naming for a path+verb.

    Returns dict {controller, method, class, status} or None.
    """
    p = (path or "").strip().strip("`").strip('"').strip("'").lstrip("/")
    if p.startswith("api/"):
        p = p[len("api/") :]
    p = p.split("?")[0]
    segs = [s for s in p.split("/") if s]
    if not segs:
        return {
            "controller": None,
            "class": None,
            "method": None,
            "status": "root",
            "resource": "",
        }
    ctrl_key = normalize_controller_key(segs[0])
    rest = list(segs[1:])
    if rest and (rest[-1].isdigit() or "${" in rest[-1]):
        rest.pop()
    resource = "-".join(rest) if rest else ""
    ctrl = ctrls.get(ctrl_key)
    if ctrl is None and ctrl_key.endswith("s"):
        ctrl = ctrls.get(ctrl_key[:-1])  # loadController singular fallback
    if ctrl is None:
        return {
            "controller": segs[0],
            "class": None,
            "method": None,
            "status": "no_controller",
            "resource": resource,
        }
    methods = ctrl["methods"]
    if resource == "index" and "index" in methods:
        return {
            "controller": ctrl_key,
            "class": ctrl["class"],
            "method": "index",
            "status": "ok",
            "resource": resource,
        }
    cands = []
    if resource:
        cands.append(verb.lower() + camel_words(resource))
    else:
        cands.append(verb.lower())
    cands.append(verb.lower() + ctrl["class"])
    if ctrl["class"].endswith("s"):
        cands.append(verb.lower() + ctrl["class"][:-1])
    for cand in cands:
        if cand in methods:
            return {
                "controller": ctrl_key,
                "class": ctrl["class"],
                "method": cand,
                "status": "ok",
                "resource": resource,
            }
    if len(rest) > 1:
        res2 = "-".join(rest[:-1])
        m2 = verb.lower() + camel_words(res2)
        if m2 in methods:
            return {
                "controller": ctrl_key,
                "class": ctrl["class"],
                "method": m2,
                "status": "ok",
                "resource": res2,
            }
    return {
        "controller": ctrl_key,
        "class": ctrl["class"],
        "method": None,
        "status": "404",
        "resource": resource,
    }


# ----------------------------------------------------------------- frontend


def parse_api_modules():
    """Return {module: {method: [(path, verb), ...]}} from js/api.js."""
    src = (ROOT / "js" / "api.js").read_text(encoding="utf-8", errors="replace")
    lines = src.split("\n")
    hdr = re.compile(r"^  ([A-Za-z_][A-Za-z0-9_]*): \{$")
    cls = re.compile(r"^  \},?$")
    modules = OrderedDict()
    for i, line in enumerate(lines):
        m = hdr.match(line)
        if not m:
            continue
        name = m.group(1)
        end = len(lines)
        for j in range(i + 1, len(lines)):
            if cls.match(lines[j]):
                end = j
                break
        block = "\n".join(lines[i : end + 1])
        modules[name] = _parse_module_block(block)
    return modules


def _parse_module_block(block):
    """Parse one module's text into {method: [(path, verb), ...]}."""
    lines = block.split("\n")
    meth = re.compile(r"^    ([A-Za-z_][A-Za-z0-9_]*):")
    cur = None
    blocks = []
    for ln in lines:
        m = meth.match(ln)
        if m:
            if cur is not None:
                blocks.append(cur)
            cur = {"name": m.group(1), "text": [ln]}
        elif cur is not None:
            cur["text"].append(ln)
    if cur is not None:
        blocks.append(cur)
    out = {}
    for b in blocks:
        calls = _find_api_calls("\n".join(b["text"]))
        if calls:
            out[b["name"]] = calls
    return out


_CALL_RE = re.compile(
    r"apiCall\(\s*([\"'`])(.*?)\1\s*,\s*([\"'])(GET|POST|PUT|DELETE|PATCH)\3",
    re.DOTALL,
)
_TERNARY_CALL_RE = re.compile(
    r"apiCall\(\s*[A-Za-z_]\w*\s*\?\s*([\"'`])(.*?)\1\s*:\s*([\"'`])(.*?)\3\s*,\s*([\"'])(GET|POST|PUT|DELETE|PATCH)\5",
    re.DOTALL,
)
_URL_RE = re.compile(r"url\s*=\s*([\"'`])(.*?)\1")


def _find_api_calls(text):
    """Return [(path, verb, dynamic)] for one api.js module method.

    `dynamic` is True when the path is built from a template `${...}` or the
    `x ? 'interpolated' : 'plain'` ternary; such parameterized routes resolve
    at runtime even when the analyzer cannot fix their concrete shape.
    """
    calls = []
    for m in _CALL_RE.finditer(text):
        calls.append((_clean_path(m.group(2)), m.group(4), "${" in m.group(2)))
    for m in _TERNARY_CALL_RE.finditer(text):
        branches = [(m.group(2), "${" in m.group(2)), (m.group(4), "${" in m.group(4))]
        branches.sort(key=lambda b: b[1])
        calls.append((_clean_path(branches[0][0]), m.group(6), branches[0][1]))
    if not calls:
        m = _URL_RE.search(text)
        if m:
            verb = "GET" if "GET" in text else ("POST" if "POST" in text else "")
            if verb:
                calls.append((_clean_path(m.group(2)), verb, False))
    return calls


def _clean_path(path):
    return re.sub(r"\$\{[^}]*\}", "", path).strip()


def load_pages():
    """Return {route: {'file', 'controllers': [js names]}} for pages/*.php."""
    pages = {}
    for f in sorted(glob.glob(str(ROOT / "pages" / "**" / "*.php"), recursive=True)):
        rel = os.path.relpath(f, ROOT / "pages").replace("\\", "/")
        route = rel[:-4] if rel.endswith(".php") else rel
        src = Path(f).read_text(encoding="utf-8", errors="replace")
        ctrls = re.findall(r"js/pages/([\w\-\/]+\.js)", src)
        pages[route] = {
            "file": rel,
            "controllers": list(dict.fromkeys(ctrls)),
        }
    return pages


def load_role_template_fragments():
    """Return a set of page file rel-paths declared as `PageShell.loadRoleTemplate`
    fragments (e.g. 'transport/admin_transport.php'). Such pages are partial views
    injected into a shell by name, so a missing controller is not a defect."""
    fragments = set()
    block_re = re.compile(r"loadRoleTemplate\s*\((\{.*?\})\)", re.DOTALL)
    template_dir_re = re.compile(r"templateDir\s*:\s*['\"]([^'\"]*)['\"]")
    file_re = re.compile(r"file\s*:\s*['\"]([^'\"]+\.php)['\"]")
    for f in glob.glob(str(ROOT / "pages" / "**" / "*.php"), recursive=True):
        src = Path(f).read_text(encoding="utf-8", errors="replace")
        for block in block_re.findall(src):
            dir_match = template_dir_re.search(block)
            if not dir_match:
                continue
            template_dir = dir_match.group(1)
            for fm in file_re.findall(block):
                if template_dir.startswith("/pages/"):
                    rel = template_dir[len("/pages/") :] + fm
                else:
                    rel = fm
                fragments.add(rel.replace("\\", "/"))
    return fragments


PAGE_INCLUDE_RE = re.compile(
    r"\b(?:require|include)(?:_once)?\s+__DIR__\s*\.\s*['\"]([^'\"]+\.php)['\"]"
)
PHP_BLOCK_RE = re.compile(r"<\?php(.*?)(?:\?>|\Z)", re.DOTALL)


def load_embedded_pages(registry_routes=()):
    """Return (delegation_stubs, template_fragments): sets of page file rel-paths.

    - delegation_stubs: thin routing aliases whose PHP body (after comments) is only
      `$var = ...` assignments plus a single `include/require __DIR__ . '/other.php'`
      of another page (e.g. admission_status -> manage_students_admissions). They carry
      no markup or logic of their own, so a missing controller is not a defect.
    - template_fragments: pages embedded by another page via `include/require __DIR__`
      (e.g. admissions/admissions_base.php), or declared in a `loadRoleTemplate` call.
      A page that is itself a registered route is a canonical destination, never a
      fragment, even when a delegation stub aliases it.
    """
    page_files = {
        os.path.relpath(f, ROOT / "pages").replace("\\", "/")
        for f in glob.glob(str(ROOT / "pages" / "**" / "*.php"), recursive=True)
    }
    registry = set(registry_routes)
    stubs = set()
    included = set()
    for f in glob.glob(str(ROOT / "pages" / "**" / "*.php"), recursive=True):
        rel = os.path.relpath(f, ROOT / "pages").replace("\\", "/")
        src = Path(f).read_text(encoding="utf-8", errors="replace")
        resolved = []
        for tgt in PAGE_INCLUDE_RE.findall(src):
            tgt_rel = os.path.normpath(
                os.path.join(os.path.dirname(rel), tgt.lstrip("/"))
            ).replace("\\", "/")
            if tgt_rel in page_files:
                resolved.append(tgt_rel)
        if not resolved:
            continue
        included |= set(resolved)
        # A stub is an include-only alias: strip comments and blank lines from the
        # PHP blocks; remaining lines must be `$ident = ...;` assignments and one
        # include/require, with no markup outside PHP tags.
        code_lines = []
        for blk in PHP_BLOCK_RE.findall(src):
            no_comments = re.sub(r"(?://[^\n]*|/\*.*?\*/)", "", blk, flags=re.DOTALL)
            code_lines += [
                ln.strip()
                for ln in no_comments.splitlines()
                if ln.strip() and not ln.strip().startswith(("*", "//"))
            ]
        non_stub = [
            ln
            for ln in code_lines
            if not re.match(r"^(?:\$[A-Za-z_]\w*\s*=|\?>\s*$)", ln)
            and not PAGE_INCLUDE_RE.search(ln)
        ]
        if not non_stub:
            stubs.add(rel)
    fragments = ((included - stubs) - registry) | set(load_role_template_fragments())
    return stubs, fragments


REF_RE = re.compile(
    r"(?:window\.)?API\??\.([A-Za-z_][A-Za-z0-9_]*)\??\.([A-Za-z_][A-Za-z0-9_]*)(?:\?\.)?\s*\(?"
)
DIRECT_RE = re.compile(
    r"(?:window\.)?API\??\.(?:apiCall|callAPI)\??\(\s*([\"'`])(.*?)\1\s*,\s*([\"'])(GET|POST|PUT|DELETE|PATCH)\3",
    re.DOTALL,
)
BARE_CALLAPI_RE = re.compile(
    r"(?<![\w.?])(?:callAPI|apiCall)\(\s*([\"'`])(.*?)\1(?:\s*,\s*([\"'])(GET|POST|PUT|DELETE|PATCH)\3)?",
    re.DOTALL,
)
LEGACY_RE = re.compile(
    r"\.API\(\s*([\"'])(GET|POST|PUT|DELETE|PATCH)\1\s*,\s*([\"'`])(.*?)\3",
    re.DOTALL,
)


def controller_api_refs(relpath):
    """Return (refs: Counter[(module, method)], direct: [(path, verb)], legacy: [(path, verb)]).

    `direct` = API.apiCall/API.callAPI with a literal path.
    `legacy` = this.API('VERB', 'path') calls (legacy window.callAPI pattern).
    """
    f = ROOT / relpath
    refs = Counter()
    direct = []
    legacy = []
    if not f.is_file():
        return refs, direct, legacy
    src = f.read_text(encoding="utf-8", errors="replace")
    src = "\n".join(ln for ln in src.split("\n") if not re.match(r"^\s*(//|\*)", ln))
    for m in REF_RE.finditer(src):
        refs[(m.group(1), m.group(2))] += 1
    for m in DIRECT_RE.finditer(src):
        dynamic = "${" in m.group(2) or bool(re.match(r"\s*\+", src[m.end() :]))
        direct.append((m.group(2), m.group(4), dynamic))
    for m in BARE_CALLAPI_RE.finditer(src):
        dynamic = "${" in m.group(2) or bool(re.match(r"\s*\+", src[m.end() :]))
        direct.append((m.group(2), m.group(4) or "GET", dynamic))
    for m in LEGACY_RE.finditer(src):
        dynamic = bool(re.match(r"\s*\+", src[m.end() :]))
        legacy.append((_clean_path(m.group(4)), m.group(2), dynamic))
    return refs, direct, legacy


def parse_dashboard_router():
    """Return (ROLE_DASHBOARDS dict, ROUTE_ROLES dict) from config/DashboardRouter.php."""
    src = (ROOT / "config" / "DashboardRouter.php").read_text(
        encoding="utf-8", errors="replace"
    )
    role_dash = {}
    m = re.search(r"ROLE_DASHBOARDS\s*=\s*\[(.*?)\];", src, re.S)
    if m:
        for k, v in re.findall(r"(\d+)\s*=>\s*[\"']([A-Za-z0-9_]+)[\"']", m.group(1)):
            role_dash[int(k)] = v
    route_roles = defaultdict(list)
    m = re.search(r"ROUTE_ROLES\s*=\s*\[(.*?)\];", src, re.S)
    if m:
        for k, val in re.findall(r"[\"']([\w\-]+)[\"']\s*=>\s*\[(.*?)\]", m.group(1)):
            for rid in re.findall(r"\d+", val):
                route_roles[k].append(int(rid))
    return role_dash, dict(route_roles)


# --------------------------------------------------------------- aggregation


def endpoint_for_call(module, method, api_modules, ctrls):
    """Resolve one API.<module>.<method> reference to a list of endpoint dicts."""
    mod = api_modules.get(module, {})
    calls = mod.get(method)
    if not calls:
        return [
            {
                "module": module,
                "method": method,
                "path": None,
                "verb": None,
                "status": "no_api_method",
                "controller": None,
                "class": None,
            }
        ]
    out = []
    for item in calls:
        path, verb = item[0], item[1]
        dynamic = item[2] if len(item) > 2 else False
        res = resolve_endpoint(path, verb, ctrls)
        status = (
            "dynamic"
            if dynamic and (not res or res["status"] != "ok")
            else (res["status"] if res else "no_route")
        )
        out.append(
            {
                "module": module,
                "method": method,
                "path": path,
                "verb": verb,
                "status": status,
                "controller": res["controller"] if res else None,
                "class": res["class"] if res else None,
                "dynamic": dynamic,
            }
        )
    return out


def collect_calls(refs, direct, legacy, api_modules, ctrls):
    """Collect endpoints used by a page/dashboard's controllers."""
    endpoints = []
    seen = set()
    for (module, method), count in refs.items():
        for e in endpoint_for_call(module, method, api_modules, ctrls):
            key = (e["verb"], e["path"])
            if key not in seen:
                seen.add(key)
                e["refs"] = count
                endpoints.append(e)
    for item in direct:
        path, verb = item[0], item[1]
        dynamic = item[2] if len(item) > 2 else False
        key = (verb, path)
        if key not in seen:
            seen.add(key)
            res = resolve_endpoint(path, verb, ctrls)
            status = (
                "dynamic"
                if dynamic and (not res or res["status"] != "ok")
                else (res["status"] if res else "no_route")
            )
            endpoints.append(
                {
                    "module": "direct",
                    "method": "apiCall",
                    "path": path,
                    "verb": verb,
                    "status": status,
                    "controller": res["controller"] if res else None,
                    "class": res["class"] if res else None,
                    "dynamic": dynamic,
                    "refs": 1,
                }
            )
    for path, verb, dynamic in legacy:
        key = (verb, path)
        if key not in seen:
            seen.add(key)
            res = resolve_endpoint(path, verb, ctrls) if not dynamic else None
            endpoints.append(
                {
                    "module": "legacy",
                    "method": "this.API",
                    "path": path,
                    "verb": verb,
                    "status": "dynamic"
                    if dynamic
                    else (res["status"] if res else "no_route"),
                    "controller": res["controller"] if res else None,
                    "class": res["class"] if res else None,
                    "dynamic": dynamic,
                    "refs": 1,
                }
            )
    return endpoints


def is_unresolved(e):
    """True when an endpoint has no resolvable handler.

    `dynamic` (parameterized `${...}`/`+ id`/`${route}` routes) is a legitimate
    runtime pattern, not a defect.
    """
    return e["status"] not in ("ok", "dynamic")


def dashboard_js_key(key):
    return os.path.join("js", "dashboards", key + ".js")


def build_role_data(rid, db, api_modules, ctrls, pages, dash_js, dash_router_roles):
    role = db["roles"][rid]
    data = {
        "role": role,
        "dashboards": [],
        "items": [],
        "endpoints": [],
        "route_ids": db["role_routes"].get(rid, []),
    }
    seen_ep = set()

    # dashboards
    dids = db["role_dashboards"].get(rid, [])
    if not dids and rid in dash_router_roles:
        dids = [
            next(
                (
                    k
                    for k, v in db["dashboards"].items()
                    if v["name"] == dash_router_roles[rid]
                ),
                0,
            )
        ]
    for did in dids:
        dash = db["dashboards"].get(did)
        if not dash:
            continue
        d = {
            "id": dash["id"],
            "key": dash["name"],
            "name": dash["display_name"],
            "domain": dash["domain"],
            "component": f"components/dashboards/{dash['name']}.php",
            "endpoints": [],
        }
        refs, direct, legacy = controller_api_refs(dashboard_js_key(dash["name"]))
        for e in collect_calls(refs, direct, legacy, api_modules, ctrls):
            d["endpoints"].append(e)
            ep = (e["verb"], e["path"])
            if ep not in seen_ep:
                seen_ep.add(ep)
                data["endpoints"].append(dict(e, source=f"dashboard:{dash['name']}"))
        data["dashboards"].append(d)

    # sidebar items
    for mid in db["role_menus"].get(rid, []):
        item = db["menus"].get(mid)
        if not item:
            data["items"].append({"id": mid, "missing": True})
            continue
        row = dict(item)
        parent = db["menus"].get(item["parent_id"])
        row["parent"] = parent["label"] if parent else ""
        raw_url = (item["url"] or "").strip().lstrip("/")
        row["url"] = raw_url
        lookup = raw_url.split("?", 1)[0]
        if raw_url.startswith("home.php"):
            mq = re.search(r"[?&]route=([\w\-]+)", raw_url)
            lookup = mq.group(1) if mq else ""
        if lookup.endswith(".php"):
            lookup = lookup[:-4]
        row["lookup"] = lookup
        route = db["routes"].get(item["route_id"])
        row["route"] = route
        page = None
        if lookup and lookup in pages:
            page = pages[lookup]
        elif lookup and lookup in dash_js:
            page = {
                "file": f"components/dashboards/{lookup}.php",
                "controllers": [lookup + ".js"],
                "is_dashboard": True,
            }
        row["page"] = page
        if page is None and not lookup:
            row["group_item"] = True
        elif page is None:
            row["page_missing"] = True
        endpoints = []
        row["legacy_api"] = False
        if page:
            ctrl_names = [os.path.basename(c) for c in page["controllers"]]
            for cname in page["controllers"]:
                refs, direct, legacy = controller_api_refs(
                    os.path.join("js", "pages", cname)
                )
                if legacy:
                    row["legacy_api"] = True
                for e in collect_calls(refs, direct, legacy, api_modules, ctrls):
                    endpoints.append(e)
                    ep = (e["verb"], e["path"])
                    if ep not in seen_ep:
                        seen_ep.add(ep)
                        data["endpoints"].append(dict(e, source=f"item:{item['id']}"))
        row["controllers"] = (
            [os.path.basename(c) for c in page["controllers"]] if page else []
        )
        row["endpoints"] = endpoints
        data["items"].append(row)
    return data


def slug(name):
    return re.sub(r"[^a-z0-9]+", "_", name.lower()).strip("_")


# ------------------------------------------------------------------- outputs


def md_table(headers, rows):
    out = ["| " + " | ".join(headers) + " |", "|" + "---|" * len(headers)]
    for r in rows:
        out.append("| " + " | ".join(str(c).replace("|", "\\|") for c in r) + " |")
    return "\n".join(out)


def status_mark(s):
    return (
        "OK"
        if s == "ok"
        else ("404" if s == "404" else ("NO_CTRL" if s == "no_controller" else s))
    )


def main():
    db = load_db()
    ctrls = load_controllers()
    api_modules = parse_api_modules()
    pages = load_pages()
    dash_router_roles, _ = parse_dashboard_router()
    dash_js = {
        os.path.basename(f)[:-3]: os.path.join("js", "dashboards", os.path.basename(f))
        for f in glob.glob(str(ROOT / "js" / "dashboards" / "*.js"))
    }

    roles_data = OrderedDict()
    for rid in sorted(db["roles"]):
        roles_data[rid] = build_role_data(
            rid, db, api_modules, ctrls, pages, dash_js, dash_router_roles
        )

    # ---- shared endpoint analysis across roles
    ep_roles = defaultdict(set)  # (verb,path) -> set(role_id)
    for rid, data in roles_data.items():
        for e in data["endpoints"]:
            if e["path"]:
                ep_roles[(e["verb"], e["path"])].add(rid)

    # ---- findings
    findings = {
        "role_routes_orphan_role": [],
        "role_routes_orphan_route": [],
        "sidebar_orphan_route": [],
        "unreferenced_routes": [],
    }
    known_role_ids = set(db["roles"])
    known_route_ids = set(db["routes"])
    for rid, rids in db["role_routes"].items():
        if rid not in known_role_ids:
            findings["role_routes_orphan_role"].append(rid)
        for rid2 in rids:
            if rid2 not in known_route_ids:
                findings["role_routes_orphan_route"].append((rid, rid2))
    for mid, item in db["menus"].items():
        if item["route_id"] and item["route_id"] not in known_route_ids:
            findings["sidebar_orphan_route"].append(
                (mid, item["label"], item["route_id"])
            )
    refd_route_ids = {
        item["route_id"] for item in db["menus"].values() if item["route_id"]
    }
    refd_route_ids |= {rid2 for rids in db["role_routes"].values() for rid2 in rids}
    for rid, route in db["routes"].items():
        if rid not in refd_route_ids:
            findings["unreferenced_routes"].append(route)

    legacy_files = []
    for base in ("js/pages", "js/dashboards"):
        for f in sorted(glob.glob(str(ROOT / base / "**" / "*.js"), recursive=True)):
            rel = os.path.relpath(f, ROOT).replace("\\", "/")
            src = Path(f).read_text(encoding="utf-8", errors="replace")
            if LEGACY_RE.search(src):
                legacy_files.append(rel)
    findings["legacy_pages"] = legacy_files

    ROLES_OUT.mkdir(parents=True, exist_ok=True)

    # ============================================================ summary.csv
    with open(OUT / "summary.csv", "w", newline="") as fh:
        w = csv.writer(fh)
        w.writerow(
            [
                "role_id",
                "role",
                "scope",
                "is_system",
                "user_count",
                "item_id",
                "label",
                "menu_type",
                "parent",
                "url",
                "route",
                "route_url",
                "page",
                "is_dashboard",
                "controllers",
                "n_endpoints",
                "n_unresolved",
            ]
        )
        for rid, data in roles_data.items():
            r = data["role"]
            for item in data["items"]:
                if item.get("missing"):
                    w.writerow(
                        [
                            r["id"],
                            r["name"],
                            r["scope"],
                            r["is_system"],
                            r["user_count"],
                            item["id"],
                            "(missing menu item)",
                            "",
                            "",
                            "",
                            "",
                            "",
                            "",
                            "",
                            "",
                            0,
                            0,
                        ]
                    )
                    continue
                route = item.get("route") or {}
                page = item.get("page") or {}
                un = sum(1 for e in item["endpoints"] if is_unresolved(e))
                w.writerow(
                    [
                        r["id"],
                        r["name"],
                        r["scope"],
                        r["is_system"],
                        r["user_count"],
                        item["id"],
                        item["label"],
                        item["menu_type"],
                        item["parent"],
                        item["url"],
                        route.get("name", ""),
                        route.get("url", ""),
                        page.get("file", ""),
                        "1" if page.get("is_dashboard") else "",
                        ";".join(item["controllers"]),
                        len(item["endpoints"]),
                        un,
                    ]
                )

    # ========================================================= endpoints.csv
    with open(OUT / "endpoints.csv", "w", newline="") as fh:
        w = csv.writer(fh)
        w.writerow(
            [
                "role_id",
                "role",
                "verb",
                "path",
                "module",
                "api_method",
                "handler",
                "status",
                "sources",
            ]
        )
        for rid, data in roles_data.items():
            r = data["role"]
            for e in data["endpoints"]:
                handler = (
                    f"{e['class']}::{e['method']}" if e["class"] and e["method"] else ""
                )
                w.writerow(
                    [
                        r["id"],
                        r["name"],
                        e["verb"],
                        e["path"],
                        e["module"],
                        e["method"],
                        handler,
                        e["status"],
                        e["source"],
                    ]
                )

    # ================================================================ index.md
    L = []
    L.append("# Frontend Role Matrix — Phase 2")
    L.append("")
    L.append(
        "Generated by `scripts/frontend_matrix/generate_role_matrix.py` against the live "
        "`KingsWayAcademy` database and this repo. Covers: role → sidebar items → page/controller "
        "→ `API.<module>.<method>` → endpoint path → backend handler (per `ControllerRouter` "
        "convention), plus dashboards and `role_routes` authorization."
    )
    L.append("")
    L.append("## Legend")
    L.append("")
    L.append(
        "- **endpoint** — `VERB /path` from `apiCall()` in `js/api.js` (or a direct `API.apiCall` in a controller)."
    )
    L.append(
        "- **handler** — resolved `ControllerClass::method` via `ControllerRouter` naming (plural→singular fallback, `index` special-case, numeric-id / string-id fallbacks)."
    )
    L.append(
        "- **status** — `OK` resolved; `404` no matching handler under the convention; `no_controller` controller file absent; `no_api_method` reference not backed by `js/api.js`."
    )
    L.append("")
    L.append("## Headline counts")
    L.append("")
    L.append(
        md_table(
            ["Metric", "Value"],
            [
                ["Roles (roles table)", len(db["roles"])],
                ["Sidebar menu items (sidebar_menu_items)", len(db["menus"])],
                [
                    "Sidebar items assigned to roles (role_sidebar_menus rows)",
                    sum(len(v) for v in db["role_menus"].values()),
                ],
                ["Roles with sidebar assignments", len(db["role_menus"])],
                [
                    "Roles with route-level authorization (role_routes)",
                    len(db["role_routes"]),
                ],
                [
                    "role_routes rows (allowed)",
                    sum(len(v) for v in db["role_routes"].values()),
                ],
                ["routes_registry entries", len(db["routes"])],
                ["Dashboards", len(db["dashboards"])],
                [
                    "role_dashboards rows",
                    sum(len(v) for v in db["role_dashboards"].values()),
                ],
                ["API modules (js/api.js)", len(api_modules)],
                ["Controllers (api/controllers)", len(ctrls)],
                ["Pages (pages/**/*.php)", len(pages)],
                ["Dashboard JS controllers (js/dashboards)", len(dash_js)],
            ],
        )
    )
    L.append("")
    L.append("## Role summary")
    L.append("")
    rows = []
    for rid, data in roles_data.items():
        r = data["role"]
        items = data["items"]
        uniq_endpoints = data["endpoints"]
        rows.append(
            [
                r["id"],
                r["name"],
                r["scope"],
                len([i for i in items if not i.get("missing")]),
                sum(1 for i in items if i.get("page_missing")),
                len(data["dashboards"]),
                len(data["route_ids"]),
                len(uniq_endpoints),
                sum(1 for e in uniq_endpoints if is_unresolved(e)),
            ]
        )
    L.append(
        md_table(
            [
                "role_id",
                "Role",
                "Scope",
                "Sidebar items",
                "Items w/o page",
                "Dashboards",
                "Auth routes",
                "Endpoints",
                "Unresolved",
            ],
            rows,
        )
    )
    L.append("")
    L.append("## Endpoints shared across roles")
    L.append("")
    L.append(
        "Endpoints used by more than one role. `#roles` = how many roles call it; "
        "`handlers` = distinct resolved handlers."
    )
    L.append("")
    shared = [(ep, sorted(rids)) for ep, rids in ep_roles.items() if len(rids) > 1]
    shared.sort(key=lambda kv: -len(kv[1]))
    srows = []
    for (verb, path), rids in shared:
        res = resolve_endpoint(path, verb, ctrls)
        handler = (
            f"{res['class']}::{res['method']}"
            if res and res["method"]
            else (res["status"] if res else "?")
        )
        names = ", ".join(db["roles"][r]["name"] for r in rids)
        srows.append([f"`{verb} /{path.lstrip('/')}`", len(rids), handler, names])
    L.append(md_table(["Endpoint", "#roles", "Handler", "Roles"], srows))
    L.append("")
    L.append(
        f"{len(ep_roles)} distinct endpoints referenced across all roles; "
        f"{len(shared)} are shared by ≥2 roles, {len(ep_roles) - len(shared)} are role-exclusive."
    )
    L.append("")
    L.append("## Unresolved endpoints (across all roles)")
    L.append("")
    unr = {}
    for rid, data in roles_data.items():
        for e in data["endpoints"]:
            if is_unresolved(e):
                key = (e["verb"], e["path"])
                if key not in unr:
                    unr[key] = {
                        "verb": e["verb"],
                        "path": e["path"],
                        "status": e["status"],
                        "roles": set(),
                        "module": e["module"],
                        "api_method": e["method"],
                    }
                unr[key]["roles"].add(rid)
    urows = []
    for (verb, path), u in sorted(unr.items()):
        names = ", ".join(db["roles"][r]["name"] for r in sorted(u["roles"]))
        urows.append(
            [
                f"`{u['verb']} /{u['path'].lstrip('/')}`",
                u["status"],
                f"{u['module']}.{u['api_method']}",
                names,
            ]
        )
    L.append(md_table(["Endpoint", "Status", "API ref", "Roles"], urows))
    L.append("")
    L.append(f"{len(unr)} distinct unresolved endpoints (of {len(ep_roles)}).")
    L.append("")
    L.append("## Findings")
    L.append("")
    L.append("### role_routes referencing unknown role ids")
    L.append("")
    if findings["role_routes_orphan_role"]:
        L.append(
            "Roles referenced in `role_routes` but absent from the `roles` table: "
            + ", ".join(
                str(x) for x in sorted(set(findings["role_routes_orphan_role"]))
            )
            + "."
        )
    else:
        L.append("None.")
    L.append("")
    L.append("### role_routes referencing unknown route ids")
    L.append("")
    if findings["role_routes_orphan_route"]:
        L.append(
            "`role_routes` rows whose route_id is absent from `routes_registry`: "
            + ", ".join(
                f"role {r}→route {rt}"
                for r, rt in findings["role_routes_orphan_route"][:20]
            )
            + (" …" if len(findings["role_routes_orphan_route"]) > 20 else "")
            + "."
        )
    else:
        L.append("None.")
    L.append("")
    L.append("### Sidebar items with orphan route_id")
    L.append("")
    if findings["sidebar_orphan_route"]:
        L.append(
            "Sidebar items whose `route_id` is absent from `routes_registry`: "
            + ", ".join(
                f"{lbl} (item {mid})→{rt}"
                for mid, lbl, rt in findings["sidebar_orphan_route"]
            )
            + "."
        )
    else:
        L.append("None.")
    L.append("")
    L.append("### routes_registry entries never referenced")
    L.append("")
    L.append(
        f"{len(findings['unreferenced_routes'])} of {len(db['routes'])} registry routes are "
        "referenced by neither a sidebar item nor `role_routes`."
    )
    L.append("")
    L.append("### Legacy `callAPI` pattern in controllers")
    L.append("")
    L.append(
        "Controllers using `this.API('VERB', 'path')` / legacy `window.callAPI` argument "
        "order (`js/api.js` exposes `window.callAPI = apiCall(endpoint, method)`, so these "
        "calls pass arguments in the wrong order): "
        + (
            ", ".join(f"`{f}`" for f in findings["legacy_pages"])
            if findings["legacy_pages"]
            else "none"
        )
        + "."
    )
    L.append("")
    L.append("## Per-role detail")
    L.append("")
    for rid, data in roles_data.items():
        L.append(
            f"- [`{slug(data['role']['name'])}.md`](roles/{slug(data['role']['name'])}.md) — "
            f"{data['role']['name']} (id {rid})"
        )
    L.append("")
    (OUT / "index.md").write_text("\n".join(L), encoding="utf-8")

    # ============================================================ per-role md
    for rid, data in roles_data.items():
        r = data["role"]
        L = []
        L.append(f"# {r['name']} — Role Matrix")
        L.append("")
        L.append(
            md_table(
                ["Field", "Value"],
                [
                    ["role_id", r["id"]],
                    ["scope", r["scope"]],
                    ["is_system", r["is_system"]],
                    ["is_active", r["is_active"]],
                    ["users", r["user_count"]],
                    ["sidebar items", len(data["items"])],
                    ["route-level auth (role_routes)", len(data["route_ids"])],
                    ["dashboards", len(data["dashboards"])],
                    ["distinct endpoints", len(data["endpoints"])],
                    [
                        "unresolved endpoints",
                        sum(1 for e in data["endpoints"] if is_unresolved(e)),
                    ],
                ],
            )
        )
        L.append("")
        L.append("## Dashboards")
        L.append("")
        if data["dashboards"]:
            drows = []
            for d in data["dashboards"]:
                un = sum(1 for e in d["endpoints"] if is_unresolved(e))
                drows.append(
                    [d["key"], d["name"], d["component"], len(d["endpoints"]), un]
                )
            L.append(
                md_table(
                    ["Key", "Display", "Component", "Endpoints", "Unresolved"], drows
                )
            )
        else:
            L.append("None.")
        L.append("")
        L.append("## Sidebar items")
        L.append("")
        irows = []
        for item in data["items"]:
            if item.get("missing"):
                irows.append(
                    [item["id"], "(missing menu item)", "", "", "", "", "", "", ""]
                )
                continue
            route = item.get("route") or {}
            page = item.get("page") or {}
            un = sum(1 for e in item["endpoints"] if is_unresolved(e))
            if item.get("group_item"):
                status = "group"
            elif item.get("page_missing"):
                status = "NO_PAGE"
            elif page.get("is_dashboard"):
                status = "dashboard"
            elif not page.get("controllers"):
                status = "NO_CONTROLLER"
            else:
                status = "ok"
            irows.append(
                [
                    item["id"],
                    item["label"],
                    item["menu_type"],
                    item["parent"],
                    item["url"],
                    route.get("name", ""),
                    page.get("file", ""),
                    ";".join(item["controllers"]),
                    f"{status}{' (legacy callAPI)' if item.get('legacy_api') else ''}"
                    f"{f' ({un} unresolved)' if un and status == 'ok' else ''}",
                ]
            )
        L.append(
            md_table(
                [
                    "id",
                    "Label",
                    "Type",
                    "Parent",
                    "URL",
                    "Route",
                    "Page",
                    "Controllers",
                    "Status",
                ],
                irows,
            )
        )
        L.append("")
        L.append("## Endpoint usage (dedup, all sources)")
        L.append("")
        erows = []
        for e in data["endpoints"]:
            handler = (
                f"`{e['class']}::{e['method']}`" if e["class"] and e["method"] else "—"
            )
            erows.append(
                [
                    f"`{e['verb']} /{e['path'].lstrip('/')}`" if e["path"] else "—",
                    e["module"],
                    e["method"],
                    handler,
                    status_mark(e["status"]),
                    e["source"],
                ]
            )
        L.append(
            md_table(
                ["Endpoint", "Module", "API method", "Handler", "Status", "Source"],
                erows,
            )
        )
        L.append("")
        L.append("## Authorized routes (role_routes)")
        L.append("")
        arows = []
        for rid2 in data["route_ids"]:
            rt = db["routes"].get(rid2)
            if rt is None:
                arows.append([rid2, "(orphan route_id)", "", "", ""])
            else:
                arows.append(
                    [rid2, rt["name"], rt["url"], rt["controller"], rt["action"]]
                )
        L.append(md_table(["route_id", "Name", "URL", "Controller", "Action"], arows))
        L.append("")
        (ROLES_OUT / f"{slug(r['name'])}.md").write_text("\n".join(L), encoding="utf-8")

    # ------------------------------------------------------------- console summary
    total_refs = sum(len(v) for mod in api_modules.values() for v in mod.values())
    print("=== ROLE MATRIX GENERATED ===")
    print(f"output: {OUT}")
    print(
        f"roles: {len(db['roles'])} | api modules: {len(api_modules)} | apiCall refs parsed: {total_refs}"
    )
    print(
        f"controllers: {len(ctrls)} | pages: {len(pages)} | dashboard js: {len(dash_js)}"
    )
    print(
        f"endpoints distinct across roles: {len(ep_roles)} | shared >=2 roles: {len(shared)}"
    )
    print(f"unresolved distinct: {len(unr)}")
    print(
        f"findings: role_routes orphan role: {len(findings['role_routes_orphan_role'])} "
        f"| orphan route: {len(findings['role_routes_orphan_route'])} "
        f"| sidebar orphan route: {len(findings['sidebar_orphan_route'])} "
        f"| unreferenced registry: {len(findings['unreferenced_routes'])} "
        f"| legacy callAPI controllers: {len(findings['legacy_pages'])}"
    )


if __name__ == "__main__":
    main()
