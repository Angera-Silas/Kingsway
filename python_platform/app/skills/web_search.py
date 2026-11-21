"""Web search + school identity grounding skills.

This is the capability the school asked for: the assistant must be able to
search the web very fast and give teachers ideas, student ideas, activities,
assessments and lesson/scheme drafts — never limited to what happens to be
stored in the school database.

Design rules:
  * Five keyless engines are available: DuckDuckGo, Bing, SearXNG, Google News
    and Marginalia. A CAPTCHA or empty SERP on one engine must never become
    "the web has nothing": fall forward to the next engine.
  * Every outbound request goes through one bounded egress layer with a shared
    SSRF guard, per-redirect revalidation and health-based egress/proxy choice.
  * Educational/reputable domains are preferred and ranked first.
  * Every returned result carries a source URL so answers cite provenance.
  * Results are bounded (count + characters) so prompts stay within budget.
  * Failures degrade gracefully: an unreachable engine yields an empty list,
    never an exception that breaks the caller.
  * No school credentials, learner identity or secrets are ever searched.
"""

from __future__ import annotations

import base64
import html as html_lib
import ipaddress
import json
import os
import random
import re
import socket
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
import urllib.robotparser
from concurrent.futures import ThreadPoolExecutor, as_completed
from typing import Any

MAX_RESULTS = 8
MAX_SNIPPET_CHARS = 600

# Bounded page read for the fetch skill. 1.5MB is enough for any article while
# refusing to pull a whole PDF or video into memory.
_MAX_FETCH_BYTES = 1_500_000

# A crawl is a bounded reading session, not a site mirror: at most six pages,
# two link levels deep, and a total character budget so one request cannot
# become an unbounded scrape.
MAX_CRAWL_PAGES = 6
MAX_CRAWL_DEPTH = 2
MAX_CRAWL_CHARS = 6000

# Search orchestration (same shape as the Angisoft Crawling & Extraction
# Engine): over-fetch because local relevance filtering discards results, cap
# the over-fetch so one query cannot run away, and renumber positions after
# every filter.
MAX_REDIRECT_HOPS = 5
MAX_OVERFETCH = 100
OVERFETCH_FACTOR = 2
# A digest can issue dozens of queries. Without a wall-clock budget, one
# unlucky query can walk every public SearXNG instance and hold the worker.
MAX_SEARXNG_INSTANCES = 3
DEFAULT_SEARCH_DEADLINE_SECONDS = 15
# One engine must never consume the whole search budget: a single hung DNS/TLS
# connection used to starve every fallback. Each strict-chain call is capped at
# this slice so the chain always reaches the next engine.
MAX_ENGINE_SLICE_SECONDS = 4

# The five search engines. Order is pragmatic for this host: DuckDuckGo and
# Bing answer most school-research queries, Google News covers current/MoE
# items, and Marginalia is an independent index. Public SearXNG instances are
# frequently rate-limited and can consume the whole wall-clock budget on a
# three-instance walk, so metasearch is deliberately LAST in the strict chain:
# a working early engine must never be starved by a slow fallback. Callers can
# pin any subset, and fan-out still queries all five in parallel.
SEARCH_PROVIDERS: tuple[str, ...] = (
    "duckduckgo",
    "bing",
    "google_news",
    "marginalia",
    "searxng",
)

# Ranked educational domains. Rank is a tie-breaker, never a hard filter, so a
# genuinely useful .com teaching resource is still usable.
PREFERRED_DOMAINS: tuple[tuple[str, int], ...] = (
    ("kicd.go.ke", 100),
    ("kicd.ac.ke", 100),
    ("education.go.ke", 90),
    ("moe.go.ke", 85),
    ("knec.ac.ke", 80),
    ("curriculum.go.ke", 70),
    ("unesco.org", 60),
    ("worldbank.org", 55),
    ("britishcouncil.org", 50),
    ("edu", 40),
    ("gov", 30),
    ("org", 20),
)

# Browser-class User-Agent for page fetches. A self-identifying bot agent gets
# a stripped, result-free SERP from several engines, which silently turns every
# world-knowledge question into a model-memory answer.
_BROWSER_UA = (
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
)

# Search engines answer Firefox/Safari far more often than a Chrome-heavy
# scraper pool. The index rotates on retry so a blocked UA is not repeated.
_SEARCH_USER_AGENTS: tuple[str, ...] = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:133.0) Gecko/20100101 Firefox/133.0",
    "Mozilla/5.0 (X11; Linux x86_64; rv:133.0) Gecko/20100101 Firefox/133.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 "
    "(KHTML, like Gecko) Version/17.3 Safari/605.1.15",
)

# DuckDuckGo SERP endpoints tried in order, each with its result anchor. Kept
# as a list rather than one hard-coded regex so a markup change degrades to the
# next endpoint instead of returning nothing at all.
_SERP_SOURCES: tuple[tuple[str, str], ...] = (
    (
        "https://html.duckduckgo.com/html/?q=",
        r'result__a"[^>]*href="([^"]+)"[^>]*>(.*?)</a>',
    ),
    (
        "https://lite.duckduckgo.com/lite/?q=",
        r'result-link"[^>]*href="([^"]+)"[^>]*>(.*?)</a>',
    ),
)

_DEFAULT_SEARXNG_ENDPOINTS: tuple[str, ...] = (
    "https://searx.be",
    "https://search.inetol.net",
    "https://opnxng.com",
    "https://searx.tiekoetter.com",
    "https://priv.au",
    "https://search.sapti.me",
    "https://paulgo.io",
    "https://searxng.site",
)

_FREE_PROXY_SOURCES: tuple[str, ...] = (
    "https://raw.githubusercontent.com/TheSpeedX/PROXY-List/master/http.txt",
    "https://raw.githubusercontent.com/monosans/proxy-list/main/proxies/http.txt",
    "https://raw.githubusercontent.com/proxifly/free-proxy-list/main/proxies/protocols/http/data.txt",
)


class SearchBlockedError(RuntimeError):
    """An engine served a CAPTCHA/anti-bot page rather than results."""


def _redact_url(value: str) -> str:
    """Strip userinfo so a proxy credential never reaches a journal or answer."""
    parsed = urllib.parse.urlparse(str(value or ""))
    if not (parsed.username or parsed.password):
        return str(value or "")
    netloc = parsed.hostname or ""
    if parsed.port:
        netloc = f"{netloc}:{parsed.port}"
    return urllib.parse.urlunparse(parsed._replace(netloc=netloc))


def _domain_rank(url: str) -> int:
    host = urllib.parse.urlparse(url).netloc.lower()
    for suffix, rank in PREFERRED_DOMAINS:
        if suffix in host:
            return rank
    return 0


def _canonical_url(url: str) -> str:
    """Dedupe key: same destination with a different fragment/tracking tag."""
    parsed = urllib.parse.urlparse(str(url or "").strip())
    query = [
        (key, value)
        for key, value in urllib.parse.parse_qsl(parsed.query, keep_blank_values=True)
        if not key.lower().startswith("utm_") and key.lower() != "fbclid"
    ]
    path = parsed.path.rstrip("/")
    return urllib.parse.urlunparse(
        (
            parsed.scheme.lower(),
            parsed.netloc.lower(),
            path,
            "",
            urllib.parse.urlencode(query),
            "",
        )
    )


def public_url_error(url: str, resolve: bool = False) -> str | None:
    """Return a refusal message for a non-public/invalid URL, else None.

    One implementation shared by search, fetch and crawl and by every redirect
    hop. The assistant must never be usable as an SSRF probe against the
    school's own loopback/private network, and a crawl must not be able to fan
    out to metadata services. DNS answers are all checked because a hostname
    can resolve to both a public and a private address.

    ``resolve=False`` is the default for cheap validation (and for unit tests
    using reserved .test domains); a DNS hiccup never becomes a permanent
    refusal because the actual request would fail with a normal network error.
    """
    parsed = urllib.parse.urlparse(str(url or "").strip())
    if parsed.scheme not in ("http", "https") or not parsed.netloc:
        return "only http and https urls can be fetched"
    if parsed.username or parsed.password:
        return "urls with credentials cannot be fetched"
    host = (parsed.hostname or "").lower().rstrip(".")
    if not host:
        return "that host cannot be fetched"
    if host.endswith((".localhost", ".local", ".internal")) or host in (
        "localhost",
        "metadata",
        "metadata.google.internal",
        "instance-data",
    ):
        return "that host cannot be fetched"
    candidates: list[str] = []
    try:
        ipaddress.ip_address(host)
        candidates.append(host)
    except ValueError:
        pass
    if resolve and not candidates:
        try:
            candidates = [answer[4][0] for answer in socket.getaddrinfo(host, None)]
        except (OSError, UnicodeError):
            candidates = []  # fail open; the request itself will fail
    for candidate in candidates:
        address = candidate.split("%", 1)[0]
        if address.lower().startswith("::ffff:"):
            address = address[7:]
        try:
            ip = ipaddress.ip_address(address)
        except ValueError:
            continue
        if (
            ip.is_private
            or ip.is_loopback
            or ip.is_link_local
            or ip.is_reserved
            or ip.is_multicast
            or ip.is_unspecified
        ):
            return "that host cannot be fetched"
    return None


class _NoRedirect(urllib.request.HTTPRedirectHandler):
    """Keep redirects manual so every hop can pass the SSRF guard."""

    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


_no_redirect_opener = urllib.request.build_opener(_NoRedirect)


_health_lock = threading.Lock()
_health: dict[str, dict[str, float]] = {}
_proxy_state: dict[str, Any] = {
    "pool": [],
    "refreshed_at": 0.0,
    "refreshing": False,
}


def _health_score(key: str) -> float:
    with _health_lock:
        record = _health.get(key)
    if not record or not (record["successes"] + record["failures"]):
        return 1.0
    return record["successes"] / (record["successes"] + record["failures"])


def _is_healthy(key: str) -> bool:
    with _health_lock:
        record = _health.get(key)
    return not record or time.monotonic() >= record["cooldown_until"]


def _record_success(key: str) -> None:
    with _health_lock:
        record = _health.setdefault(
            key, {"successes": 0, "failures": 0, "cooldown_until": 0.0}
        )
        record["successes"] += 1
        record["cooldown_until"] = 0.0


def _record_failure(key: str) -> None:
    with _health_lock:
        record = _health.setdefault(
            key, {"successes": 0, "failures": 0, "cooldown_until": 0.0}
        )
        record["failures"] += 1
        penalty = min(900.0, 30.0 * (2 ** min(int(record["failures"]), 5)))
        record["cooldown_until"] = time.monotonic() + penalty


def _egress_nodes() -> list[str]:
    raw = os.environ.get("AI_WEB_EGRESS_NODES", "") or ""
    return [value.strip().rstrip("/") for value in raw.split(",") if value.strip()]


def _searxng_endpoints() -> list[str]:
    raw = os.environ.get("AI_WEB_SEARXNG_ENDPOINTS") or os.environ.get(
        "AI_WEB_SEARXNG_ENDPOINT", ""
    )
    values = [value.strip().rstrip("/") for value in raw.split(",") if value.strip()]
    return values or list(_DEFAULT_SEARXNG_ENDPOINTS)


def _proxy_enabled() -> bool:
    return (
        os.environ.get("AI_WEB_FREE_PROXY_ENABLED", "false").strip().lower() == "true"
    )


def _validate_proxy(proxy_url: str) -> bool:
    opener = urllib.request.build_opener(
        urllib.request.ProxyHandler({"http": proxy_url, "https": proxy_url}),
        _NoRedirect,
    )
    try:
        request = urllib.request.Request(
            "https://api.ipify.org?format=json",
            headers={"User-Agent": _BROWSER_UA},
        )
        with opener.open(request, timeout=6) as response:
            payload = json.loads(response.read(4096).decode("utf-8", "replace"))
        return bool(payload.get("ip"))
    except Exception:  # noqa: BLE001 - a dead proxy is a normal result
        return False


def _refresh_proxy_pool() -> None:
    """Opt-in, self-built proxy pool: fetch lists, validate, keep 12 healthy.

    Public proxies are slow and mostly dead, so this mirrors the reference
    engine's default: OFF unless the operator opts in. A list being down never
    breaks ingestion, and every proxy is validated against a real HTTPS
    endpoint before it is used.
    """
    if not _proxy_enabled() or _proxy_state["refreshing"]:
        return
    _proxy_state["refreshing"] = True
    _proxy_state["refreshed_at"] = time.monotonic()
    try:
        candidates: list[str] = []
        seen: set[str] = set()
        for list_url in _FREE_PROXY_SOURCES:
            try:
                request = urllib.request.Request(
                    list_url, headers={"User-Agent": _BROWSER_UA}
                )
                with _no_redirect_opener.open(request, timeout=12) as response:
                    text = response.read(200_000).decode("utf-8", "replace")
            except Exception:  # noqa: BLE001 - one dead list is not fatal
                continue
            for line in text.splitlines():
                value = line.strip()
                if re.fullmatch(r"\d{1,3}(?:\.\d{1,3}){3}:\d{2,5}", value):
                    proxy = f"http://{value}"
                    if proxy not in seen:
                        seen.add(proxy)
                        candidates.append(proxy)
                if len(candidates) >= 60:
                    break
            if len(candidates) >= 60:
                break

        validated: list[str] = []
        with ThreadPoolExecutor(max_workers=8) as pool:
            futures = {
                pool.submit(_validate_proxy, proxy): proxy for proxy in candidates[:24]
            }
            for future in as_completed(futures):
                if future.result():
                    validated.append(futures[future])
        if validated:
            _proxy_state["pool"] = validated[:12]
    finally:
        _proxy_state["refreshing"] = False


def _pick_proxy() -> str | None:
    if not _proxy_enabled():
        return None
    if time.monotonic() - float(_proxy_state["refreshed_at"]) > 1800:
        _refresh_proxy_pool()
    healthy = [url for url in _proxy_state["pool"] if _is_healthy(f"proxy:{url}")]
    if not healthy:
        return None
    healthy.sort(key=lambda url: _health_score(f"proxy:{url}"), reverse=True)
    return random.choice(healthy[: min(3, len(healthy))])


def _perform_direct(
    url: str,
    data: bytes | None,
    headers: dict[str, str],
    timeout: float,
    proxy_url: str | None = None,
    guard: bool = True,
) -> tuple[int, dict[str, str], bytes, str]:
    """One HTTP hop, never following redirects. Returns status/headers/body/url."""
    if guard:
        refusal = public_url_error(url, resolve=True)
        if refusal:
            raise ValueError(refusal)
    if proxy_url:
        opener = urllib.request.build_opener(
            urllib.request.ProxyHandler({"http": proxy_url, "https": proxy_url}),
            _NoRedirect,
        )
    else:
        opener = _no_redirect_opener
    request = urllib.request.Request(
        url, data=data, headers=headers, method="POST" if data else "GET"
    )
    try:
        with opener.open(request, timeout=timeout) as response:
            return (
                response.status,
                {key.lower(): value for key, value in response.headers.items()},
                response.read(_MAX_FETCH_BYTES),
                response.geturl(),
            )
    except urllib.error.HTTPError as error:
        return (
            error.code,
            {key.lower(): value for key, value in error.headers.items()},
            error.read(_MAX_FETCH_BYTES),
            error.geturl(),
        )


def _perform_node(
    node: str,
    url: str,
    data: bytes | None,
    headers: dict[str, str],
    timeout: float,
) -> tuple[int, dict[str, str], bytes, str, str] | None:
    """Send one request through an operator-configured egress node."""
    token = os.environ.get("AI_WEB_EGRESS_TOKEN", "") or ""
    if not token:
        return None
    payload = json.dumps(
        {
            "url": url,
            "method": "POST" if data else "GET",
            "headers": headers,
            "body": data.decode("utf-8", "replace") if data else None,
        }
    ).encode("utf-8")
    status, _, raw, _ = _perform_direct(
        f"{node}/internal/egress",
        payload,
        {"Content-Type": "application/json", "x-egress-token": token},
        timeout,
        guard=False,  # operator config, not a user-supplied URL
    )
    if status < 200 or status >= 300:
        return None
    parsed = json.loads(raw.decode("utf-8", "replace"))
    if parsed.get("bodyBase64"):
        body = base64.b64decode(parsed["bodyBase64"])
    else:
        body = str(parsed.get("body") or "").encode("utf-8")
    return (
        int(parsed.get("status", status)),
        parsed.get("headers") or {},
        body,
        str(parsed.get("finalUrl") or url),
        f"node:{_redact_url(node)}",
    )


def _egress_fetch(
    url: str,
    data: bytes | None = None,
    headers: dict[str, str] | None = None,
    timeout: float = 8,
    prefer_proxy: bool = False,
) -> tuple[dict[str, str], bytes, str, str]:
    """Fetch a public URL through health-ranked egress, checking every hop.

    Selection mirrors the reference engine: an operator egress node first, an
    opted-in validated proxy for sites that throttle datacentre IPs, then
    direct egress. Any tier failing falls through to the next; a dead pool can
    never stop search or a crawl.
    """
    request_headers = dict(headers or {})
    current = str(url or "").strip()
    retried_proxy = False

    for hop in range(MAX_REDIRECT_HOPS + 1):
        refusal = public_url_error(current, resolve=True)
        if refusal:
            raise ValueError(refusal)

        response: tuple[int, dict[str, str], bytes, str, str] | None = None
        for node in _egress_nodes():
            if not _is_healthy(f"node:{node}"):
                continue
            key = f"node:{node}"
            try:
                response = _perform_node(node, current, data, request_headers, timeout)
            except Exception:  # noqa: BLE001 - fall through to the next tier
                response = None
            if response:
                _record_success(key)
                break
            _record_failure(key)

        if response is None and prefer_proxy:
            proxy = os.environ.get("AI_WEB_PROXY", "") or _pick_proxy()
            if proxy:
                key = f"proxy:{_redact_url(proxy)}"
                try:
                    status, resp_headers, body, final = _perform_direct(
                        current, data, request_headers, timeout, proxy_url=proxy
                    )
                    response = (status, resp_headers, body, final, key)
                except Exception:  # noqa: BLE001 - a dead proxy falls through
                    response = None
                if response:
                    _record_success(key)
                else:
                    _record_failure(key)

        if response is None:
            status, resp_headers, body, final = _perform_direct(
                current, data, request_headers, timeout
            )
            via = "direct"
            # A throttled datacentre IP gets exactly one proxy retry.
            if (
                status in (403, 429)
                and not prefer_proxy
                and not retried_proxy
                and (os.environ.get("AI_WEB_PROXY", "") or _proxy_enabled())
            ):
                retried_proxy = True
                prefer_proxy = True
                continue
        else:
            status, resp_headers, body, final, via = response

        location = str(resp_headers.get("location") or "")
        if 300 <= status < 400 and location:
            if hop == MAX_REDIRECT_HOPS:
                raise ValueError("too many redirects")
            current = urllib.parse.urljoin(current, location)
            continue
        if status < 200 or status >= 300:
            raise urllib.error.HTTPError(
                current, status, f"HTTP {status} ({via})", resp_headers, None
            )
        return resp_headers, body, final, via

    raise ValueError("too many redirects")


_LINK_HREF = re.compile(r'href=["\']([^"\']+)["\']', re.I)
_SKIP_LINK_EXTENSIONS = (
    ".pdf",
    ".zip",
    ".rar",
    ".7z",
    ".png",
    ".jpg",
    ".jpeg",
    ".gif",
    ".webp",
    ".svg",
    ".mp4",
    ".mp3",
    ".avi",
    ".mov",
    ".wav",
    ".xls",
    ".xlsx",
    ".doc",
    ".docx",
    ".ppt",
    ".pptx",
    ".css",
    ".js",
    ".ico",
)


def _extract_links(html: str, base_url: str) -> list[str]:
    """Normalize in-page links to absolute, fetchable URLs, deduplicated."""
    links: list[str] = []
    seen: set[str] = set()
    for href in _LINK_HREF.findall(html):
        href = href.strip()
        if not href or href.startswith(("#", "mailto:", "tel:", "javascript:")):
            continue
        absolute = urllib.parse.urljoin(base_url, href)
        parsed = urllib.parse.urlparse(absolute)
        if parsed.scheme not in ("http", "https") or not parsed.netloc:
            continue
        if parsed.path.lower().endswith(_SKIP_LINK_EXTENSIONS):
            continue
        clean = urllib.parse.urlunparse(parsed._replace(fragment=""))
        if clean not in seen:
            seen.add(clean)
            links.append(clean)
    return links


_TAG_STRIP = re.compile(r"<[^>]+>")
_SCRIPT_BLOCK = re.compile(r"<(script|style|noscript)[^>]*>.*?</\1>", re.S | re.I)
_WHITESPACE = re.compile(r"[ \t]+")


def _extract_title(html: str) -> str:
    match = re.search(r"<title[^>]*>(.*?)</title>", html, re.S | re.I)
    if not match:
        return ""
    return _TAG_STRIP.sub("", match.group(1)).strip()


def _html_to_text(html: str) -> str:
    """Reduce a page to readable prose without a parser dependency.

    Script/style blocks are dropped first so JavaScript and CSS never reach the
    model, and block-level ends become newlines so the text keeps its shape.
    """
    text = _SCRIPT_BLOCK.sub(" ", html)
    text = re.sub(r"(?i)</(p|div|li|h[1-6]|tr|section|article)>", "\n", text)
    text = re.sub(r"(?i)<br\s*/?>", "\n", text)
    text = _TAG_STRIP.sub(" ", text)
    text = _WHITESPACE.sub(" ", text)
    lines = [line.strip() for line in text.splitlines()]
    return "\n".join(line for line in lines if line)


class WebSearchSkill:
    """Search the public web for academic content and school facts."""

    def __init__(self, journal) -> None:
        self.journal = journal
        self._engine = (os.environ.get("AI_WEB_SEARCH_ENGINE") or "duckduckgo").lower()
        self._timeout = max(
            3, min(20, int(os.environ.get("AI_WEB_SEARCH_TIMEOUT") or 8))
        )
        self._deadline_seconds = max(
            3,
            min(
                60,
                int(
                    os.environ.get("AI_WEB_SEARCH_DEADLINE")
                    or DEFAULT_SEARCH_DEADLINE_SECONDS
                ),
            ),
        )
        self._deadline_at = 0.0
        # Per-engine slice: a hung engine (e.g. DuckDuckGo retrying three SERP
        # endpoints at 8s each) must never consume the whole query budget and
        # starve reliable fallbacks like Bing.
        self._engine_deadline_at = 0.0
        configured = os.environ.get("AI_WEB_SEARCH_PROVIDERS", "") or ""
        pinned = [
            value.strip().lower() for value in configured.split(",") if value.strip()
        ]
        known = [value for value in pinned if value in SEARCH_PROVIDERS]
        order: list[str] = list(known or SEARCH_PROVIDERS)
        if self._engine in SEARCH_PROVIDERS and self._engine not in order:
            order.insert(0, self._engine)
        self._providers = tuple(order)
        # robots.txt decisions are cached per host for the life of the process
        # worker so a crawl checks each host once, not once per page.
        self._robots: dict[str, Any] = {}

    def _journal(self, event_type: str, payload: dict[str, Any]) -> None:
        if self.journal is None:
            return
        self.journal.write("ai_generation", {"type": event_type, **payload})

    def _search_headers(self, index: int = 0) -> dict[str, str]:
        agent = _SEARCH_USER_AGENTS[
            (index + random.randrange(len(_SEARCH_USER_AGENTS)))
            % len(_SEARCH_USER_AGENTS)
        ]
        return {
            "User-Agent": agent,
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language": "en-US,en;q=0.9",
            "Cache-Control": "no-cache",
        }

    def _get(self, url: str, index: int = 0, data: bytes | None = None) -> str:
        now = time.monotonic()
        if self._deadline_at and now >= self._deadline_at:
            raise SearchBlockedError("search deadline exceeded")
        if self._engine_deadline_at and now >= self._engine_deadline_at:
            raise SearchBlockedError("engine time slice exceeded")
        # Bound the actual socket call by whichever deadline is nearest. A hung
        # engine can then fail in its slice instead of eating the whole query.
        timeout = self._timeout
        for deadline in (self._deadline_at, self._engine_deadline_at):
            if deadline:
                timeout = min(timeout, max(0.5, deadline - now))
        headers = self._search_headers(index)
        if data:
            headers["Content-Type"] = "application/x-www-form-urlencoded"
        _, body, _, _ = _egress_fetch(url, data=data, headers=headers, timeout=timeout)
        return body.decode("utf-8", "replace")

    # -------------------------------------------------------------- relevance
    _STOPWORDS = frozenset(
        {
            "the",
            "and",
            "for",
            "with",
            "from",
            "this",
            "that",
            "what",
            "when",
            "latest",
            "current",
            "about",
            "into",
            "your",
            "our",
            "how",
            "does",
            "week",
            "news",
            "update",
            "updates",
            "new",
        }
    )

    @classmethod
    def _query_terms(cls, query: str) -> list[str]:
        return [
            word
            for word in re.findall(r"[a-z0-9.-]+", str(query or "").lower())
            if len(word) >= 3 and word not in cls._STOPWORDS
        ]

    @classmethod
    def _relevant(cls, results: list[dict], terms: list[str]) -> list[dict]:
        """Drop results with no meaningful echo of the query.

        Search engines occasionally return an entirely unrelated SERP (a
        personalisation or anti-bot artifact). A single generic match
        ("curriculum" on a CV page) is not evidence: two distinct query terms
        must appear before a result can support an answer.
        """
        if not terms:
            return results
        needed = 2 if len(terms) >= 2 else 1
        kept: list[dict] = []
        for result in results:
            haystack = " ".join(
                str(result.get(key) or "") for key in ("title", "url", "snippet")
            ).lower()
            matches = sum(1 for term in terms if term in haystack)
            if matches >= needed:
                kept.append(result)
        return kept

    @staticmethod
    def _normalise_results(results: list[dict], engine: str) -> list[dict]:
        normalised: list[dict] = []
        for item in results or []:
            url = str(item.get("url") or "").strip()
            if not url.startswith(("http://", "https://")):
                continue
            if public_url_error(url):
                continue
            normalised.append(
                {
                    "title": str(item.get("title") or "")[:200],
                    "url": url[:500],
                    "snippet": str(item.get("snippet") or "")[:MAX_SNIPPET_CHARS],
                    "rank": int(item.get("rank") or 0),
                    "engine": str(item.get("engine") or engine),
                }
            )
        return normalised

    # ------------------------------------------------------------------ search
    def search(
        self,
        query: str,
        limit: int = MAX_RESULTS,
        providers: list[str] | tuple[str, ...] | None = None,
        fanout: bool = False,
    ) -> list[dict]:
        """Find public results, falling forward across five keyless engines.

        ``fanout=True`` queries every selected engine and merges round-robin
        (recall first); the default strict chain stops at the first engine that
        returns relevant results (cheap and polite for routine questions).
        """
        query = str(query or "").strip()
        if not query:
            return []
        limit = max(1, min(limit, MAX_RESULTS))
        selected = [p for p in (providers or self._providers) if p in SEARCH_PROVIDERS]
        if not selected:
            return []
        terms = self._query_terms(query)
        overfetch = min(MAX_OVERFETCH, max(limit * OVERFETCH_FACTOR, 8))
        results: list[dict] = []
        used: list[str] = []
        self._deadline_at = time.monotonic() + self._deadline_seconds
        self._engine_deadline_at = 0.0  # fan-out shares one global deadline

        if fanout and len(selected) > 1:
            pool = ThreadPoolExecutor(max_workers=min(5, len(selected)))
            futures = {
                pool.submit(self._engine_search, name, query, overfetch): name
                for name in selected
            }
            lists: dict[str, list[dict]] = {}
            try:
                for future in as_completed(
                    futures, timeout=max(0.1, self._deadline_at - time.monotonic())
                ):
                    name = futures[future]
                    try:
                        lists[name] = future.result()
                    except Exception as error:  # noqa: BLE001 - engine isolated
                        self._journal(
                            "web_search_failed",
                            {"engine": name, "error_class": type(error).__name__},
                        )
                        lists[name] = []
            except TimeoutError:
                self._journal("web_search_timeout", {"engines": len(selected)})
            finally:
                pool.shutdown(wait=False, cancel_futures=True)
            for index in range(overfetch):
                exhausted = True
                for name in selected:
                    bucket = lists.get(name, [])
                    if index < len(bucket):
                        exhausted = False
                        results.append(bucket[index])
                if exhausted:
                    break
            used = [name for name in selected if lists.get(name)]
        else:
            for name in selected:
                now = time.monotonic()
                if now >= self._deadline_at:
                    self._journal("web_search_timeout", {"engines_tried": len(used)})
                    break
                # Each engine gets a bounded slice. A hung engine fails inside
                # its slice and the chain moves on instead of starving Bing.
                self._engine_deadline_at = now + min(
                    MAX_ENGINE_SLICE_SECONDS, max(1, self._deadline_at - now)
                )
                used.append(name)
                try:
                    found = self._engine_search(name, query, overfetch)
                except (
                    urllib.error.URLError,
                    OSError,
                    ValueError,
                    SearchBlockedError,
                ) as error:
                    self._journal(
                        "web_search_failed",
                        {"engine": name, "error_class": type(error).__name__},
                    )
                    continue
                found = self._relevant(found, terms)
                if found:
                    results = found
                    break

        results = self._relevant(results, terms)
        deduped: list[dict] = []
        seen: set[str] = set()
        priority = {name: index for index, name in enumerate(selected)}
        for item in results:
            key = _canonical_url(item["url"])
            if not key or key in seen:
                continue
            seen.add(key)
            # Provider order is a small tie-breaker after domain reputation.
            item["rank"] = int(item.get("rank") or 0) + max(
                0, len(priority) - priority.get(item.get("engine", ""), 0)
            )
            deduped.append(item)
        deduped.sort(key=lambda item: item["rank"], reverse=True)
        final = deduped[:limit]
        for position, item in enumerate(final, start=1):
            item["position"] = position

        if final and used and used[0] != final[0].get("engine"):
            self._journal("web_search_fallback", {"engine": final[0].get("engine")})
        if not final:
            # Zero used to be indistinguishable from "no such page exists".
            # Journalled so a markup/UA change is diagnosable instead of
            # silently degrading every world-knowledge answer to model guesses.
            self._journal("web_search_no_results", {"query_len": len(query)})
        self._journal(
            "web_search",
            {
                "engine": final[0].get("engine") if final else "none",
                "result_count": len(final),
                "engines_tried": len(used),
            },
        )
        # A later fetch/crawl must not inherit a stale search slice.
        self._engine_deadline_at = 0.0
        return final

    def search_all(self, query: str, limit: int = MAX_RESULTS) -> list[dict]:
        """Recall-first variant: ask all five engines and merge."""
        return self.search(query, limit, fanout=True)

    def _engine_search(self, name: str, query: str, limit: int) -> list[dict]:
        adapters = {
            "duckduckgo": self._duckduckgo,
            "bing": self._bing,
            "searxng": self._searxng,
            "google_news": self._google_news,
            "marginalia": self._marginalia,
        }
        adapter = adapters[name]
        return self._normalise_results(adapter(query, limit), name)

    def egress_status(self) -> dict[str, Any]:
        return {
            "nodes": len(_egress_nodes()),
            "proxies": len(_proxy_state["pool"]),
            "proxy_enabled": _proxy_enabled(),
            "engines": list(self._providers),
        }

    def academic_context(self, subject: str, grade: str, topic: str) -> dict:
        """Bundle web ideas with KICD-style curriculum language for planning."""
        parts = [p for p in (subject, grade, topic) if str(p).strip()]
        base = " ".join(str(p) for p in parts) or "lesson ideas"
        teaching = self.search(f"{base} teaching ideas lesson plan", 5)
        activities = self.search(f"{base} classroom activities hands on", 5)
        assessment = self.search(f"{base} assessment questions rubric", 5)
        return {
            "query": base,
            "teaching_ideas": teaching,
            "activity_ideas": activities,
            "assessment_ideas": assessment,
            "source_count": len(teaching) + len(activities) + len(assessment),
        }

    # ------------------------------------------------------------------- fetch
    def fetch(self, url: str, max_chars: int = 6000) -> dict:
        """Read one public page and return its readable text.

        Any public URL is allowed — PREFERRED_DOMAINS only RANKS search results,
        it never gates them. Bounded and guarded: http/https only,
        private/loopback hosts refused so the assistant cannot be used as an
        SSRF probe, and the body is capped in bytes and characters.
        """
        url = str(url or "").strip()
        if not url:
            return {"status": "unavailable", "message": "a url is required"}
        refusal = public_url_error(url, resolve=True)
        if refusal:
            return {"status": "unavailable", "message": refusal}

        started = time.monotonic()
        try:
            content_type, page = self._read_html(url)
        except urllib.error.HTTPError as error:
            if error.code in (403, 429):
                try:
                    content_type, page = self._read_html(url, prefer_proxy=True)
                except (urllib.error.URLError, OSError, ValueError) as retry_error:
                    return self._fetch_failure(retry_error, started)
            else:
                return self._fetch_failure(error, started)
        except (urllib.error.URLError, OSError, ValueError) as error:
            return self._fetch_failure(error, started)

        if "text" not in content_type and "html" not in content_type:
            return {
                "status": "unavailable",
                "message": f"unsupported content type {content_type or 'unknown'}",
            }

        text = _html_to_text(page)[:max_chars]
        self._journal(
            "web_fetch",
            {
                "char_count": len(text),
                "duration_ms": int((time.monotonic() - started) * 1000),
            },
        )
        return {
            "status": "answered",
            "url": url[:500],
            "title": _extract_title(page)[:200],
            "content": text,
            "char_count": len(text),
            "truncated": len(text) >= max_chars,
        }

    def _fetch_failure(self, error: Exception, started: float) -> dict:
        self._journal(
            "web_fetch_failed",
            {
                "error_class": type(error).__name__,
                "duration_ms": int((time.monotonic() - started) * 1000),
            },
        )
        return {
            "status": "unavailable",
            "message": "that page could not be fetched",
        }

    def _read_html(self, url: str, prefer_proxy: bool = False) -> tuple[str, str]:
        """GET one public page through the egress layer as (content_type, body)."""
        headers, body, _, _ = _egress_fetch(
            url,
            headers={"User-Agent": _BROWSER_UA, "Accept-Language": "en"},
            timeout=self._timeout,
            prefer_proxy=prefer_proxy,
        )
        return (headers.get("content-type") or "").lower(), body.decode(
            "utf-8", "replace"
        )

    def _robots_allows(self, url: str) -> bool:
        """Honour robots.txt for a crawl, failing open on an unreachable file."""
        parsed = urllib.parse.urlparse(url)
        origin = f"{parsed.scheme}://{parsed.netloc}"
        parser = self._robots.get(origin)
        if parser is None:
            parser = urllib.robotparser.RobotFileParser(origin + "/robots.txt")
            try:
                parser.read()
            except (urllib.error.URLError, OSError, ValueError):
                parser = None
            self._robots[origin] = parser
        if parser is None:
            return True
        try:
            return bool(parser.can_fetch(_BROWSER_UA, url))
        except Exception:  # noqa: BLE001 - a malformed robots.txt must not block
            return True

    def crawl(
        self,
        seed: str,
        depth: int = 1,
        max_pages: int = 4,
        max_chars: int = 2000,
        same_site: bool = True,
    ) -> dict:
        """Read a bounded set of linked pages from one public site."""
        seed = str(seed or "").strip()
        if not seed:
            return {"status": "unavailable", "message": "a seed url is required"}
        refusal = public_url_error(seed, resolve=True)
        if refusal:
            return {"status": "unavailable", "message": refusal}

        depth = max(0, min(int(depth or 0), MAX_CRAWL_DEPTH))
        max_pages = max(1, min(int(max_pages or 1), MAX_CRAWL_PAGES))
        max_chars = max(400, min(int(max_chars or 0), 4000))
        seed_host = (urllib.parse.urlparse(seed).hostname or "").lower()

        started = time.monotonic()
        seen: set[str] = {seed}
        queue: list[tuple[str, int]] = [(seed, 0)]
        pages: list[dict[str, Any]] = []
        skipped: list[str] = []
        total_chars = 0

        while queue and len(pages) < max_pages and total_chars < MAX_CRAWL_CHARS:
            url, level = queue.pop(0)
            if not self._robots_allows(url):
                skipped.append(url)
                continue
            try:
                content_type, html = self._read_html(url)
            except (urllib.error.URLError, OSError, ValueError):
                skipped.append(url)
                continue
            if "text" not in content_type and "html" not in content_type:
                skipped.append(url)
                continue

            budget = min(max_chars, MAX_CRAWL_CHARS - total_chars)
            text = _html_to_text(html)[:budget]
            pages.append(
                {
                    "url": url[:500],
                    "title": _extract_title(html)[:200],
                    "content": text,
                }
            )
            total_chars += len(text)

            if level < depth:
                for link in _extract_links(html, url):
                    if len(seen) >= max_pages * 8:
                        break
                    link_host = (urllib.parse.urlparse(link).hostname or "").lower()
                    if same_site and link_host != seed_host:
                        continue
                    if link not in seen:
                        seen.add(link)
                        queue.append((link, level + 1))

        self._journal(
            "web_crawl",
            {
                "page_count": len(pages),
                "sources_skipped": len(skipped),
                "char_count": total_chars,
                "duration_ms": int((time.monotonic() - started) * 1000),
            },
        )

        if not pages:
            return {
                "status": "unavailable",
                "message": "no pages could be read from that site",
                "url": seed[:500],
            }

        combined: list[str] = []
        for page in pages:
            heading = page["title"] or page["url"]
            combined.append(f"{heading} ({page['url']})\n{page['content']}")
        content = "\n\n".join(combined)[:MAX_CRAWL_CHARS]

        return {
            "status": "answered",
            "seed": seed[:500],
            "page_count": len(pages),
            "pages": [
                f"{page['title'] or page['url']} — {page['url']}"[:300]
                for page in pages
            ],
            "content": content,
            "sources": [page["url"] for page in pages],
            "source_count": len(pages),
            "char_count": len(content),
            "truncated": total_chars >= MAX_CRAWL_CHARS,
            "summary": (
                f"read {len(pages)} page(s) from {seed_host}; "
                f"{len(skipped)} link(s) skipped (robots.txt or unreadable)"
            ),
        }

    def school_identity(self) -> dict:
        """Grounded school identity used for greetings and public framing."""
        return {
            "school_name": os.environ.get("SCHOOL_NAME")
            or "Kingsway Preparatory School",
            "motto": os.environ.get("SCHOOL_MOTTO") or "",
            "current_term": os.environ.get("SCHOOL_CURRENT_TERM") or "",
            "curriculum": os.environ.get("SCHOOL_CURRICULUM") or "Kenyan CBC",
        }

    # ----------------------------------------------------------------- engines
    def _duckduckgo(self, query: str, limit: int) -> list[dict]:
        """Query DuckDuckGo, falling forward across its SERP endpoints.

        A browser-class User-Agent is mandatory and a CAPTCHA body must raise,
        not silently parse as zero results: that was the invisible failure that
        made every world-knowledge answer fall back to model memory.
        """
        last_error: Exception | None = None
        for index, (endpoint, anchor) in enumerate(_SERP_SOURCES):
            try:
                html = self._get(endpoint + urllib.parse.quote_plus(query), index)
            except (urllib.error.URLError, OSError, ValueError) as error:
                last_error = error
                continue
            lowered = html.lower()
            if (
                "anomaly-modal" in lowered
                or "unfortunately, bots use duckduckgo" in lowered
                or ("select all squares" in lowered and "captcha" in lowered)
            ):
                raise SearchBlockedError("DuckDuckGo anti-bot challenge")
            results = self._parse_serp(html, anchor, limit)
            if results:
                return results
        if last_error is not None:
            raise last_error
        return []

    @staticmethod
    def _parse_serp(html: str, anchor: str, limit: int) -> list[dict]:
        results: list[dict] = []
        for match in re.finditer(anchor, html, re.S):
            url = WebSearchSkill._unwrap_ddg(match.group(1))
            title = re.sub(r"<[^>]+>", "", match.group(2)).strip()
            if not url or url.startswith("#"):
                continue
            results.append(
                {
                    "title": title[:200],
                    "url": url[:500],
                    "snippet": "",
                    "rank": _domain_rank(url),
                    "engine": "duckduckgo",
                }
            )
            if len(results) >= limit:
                break
        return results

    @staticmethod
    def _unwrap_ddg(href: str) -> str:
        href = href.replace("&amp;", "&")
        if "uddg=" not in href:
            return href
        parsed = urllib.parse.parse_qs(urllib.parse.urlparse(href).query)
        return (parsed.get("uddg") or [href])[0]

    # -------------------------------------------------------------------- bing
    def _bing(self, query: str, limit: int) -> list[dict]:
        """Bing: keyed API when configured, else RSS first, HTML last resort."""
        key = os.environ.get("AI_WEB_SEARCH_KEY")
        if key:
            endpoint = (
                os.environ.get("AI_WEB_SEARCH_ENDPOINT")
                or "https://api.bing.microsoft.com"
            )
            payload = json.loads(
                self._get(
                    f"{endpoint}/v7.0/search?q={urllib.parse.quote_plus(query)}&count={limit}"
                )
            )
            results: list[dict] = []
            for item in (payload.get("webPages") or {}).get("value") or []:
                url = str(item.get("url") or "")
                if url:
                    results.append(
                        {
                            "title": str(item.get("name") or "")[:200],
                            "url": url[:500],
                            "snippet": str(item.get("snippet") or "")[
                                :MAX_SNIPPET_CHARS
                            ],
                            "rank": _domain_rank(url),
                            "engine": "bing",
                        }
                    )
            return results
        results = self._bing_rss(query, limit)
        if results:
            return results
        return self._bing_html(query, limit)

    def _bing_rss(self, query: str, limit: int) -> list[dict]:
        """Keyless Bing RSS search — real URLs, no tracking hops."""
        endpoint = (
            "https://www.bing.com/search?format=rss&q=" + urllib.parse.quote_plus(query)
        )
        feed = self._get(endpoint)
        return self._parse_bing_rss(feed, limit)

    @staticmethod
    def _parse_bing_rss(feed: str, limit: int) -> list[dict]:
        return WebSearchSkill._parse_rss_items(feed, limit, "bing")

    @staticmethod
    def _parse_rss_items(feed: str, limit: int, engine: str) -> list[dict]:
        results: list[dict] = []
        for item in re.findall(r"<item>(.*?)</item>", feed, re.S):
            link = re.search(r"<link>(.*?)</link>", item, re.S)
            title = re.search(r"<title>(.*?)</title>", item, re.S)
            if not link:
                continue
            url = html_lib.unescape(link.group(1)).strip()
            if not url.startswith(("http://", "https://")):
                continue
            snippet = ""
            description = re.search(r"<description>(.*?)</description>", item, re.S)
            if description:
                # Unescape first: the feed encodes tags as &lt;p&gt;, so
                # stripping before unescaping would leave literal <p> behind.
                snippet = re.sub(
                    r"<[^>]+>",
                    "",
                    html_lib.unescape(description.group(1)),
                ).strip()[:MAX_SNIPPET_CHARS]
            results.append(
                {
                    "title": html_lib.unescape(title.group(1)).strip()[:200]
                    if title
                    else "",
                    "url": url[:500],
                    "snippet": snippet,
                    "rank": _domain_rank(url),
                    "engine": engine,
                }
            )
            if len(results) >= limit:
                break
        return results

    def _bing_html(self, query: str, limit: int) -> list[dict]:
        """Keyless Bing HTML search, used when RSS is empty."""
        endpoint = (
            "https://www.bing.com/search?q="
            + urllib.parse.quote_plus(query)
            + "&count="
            + str(limit)
        )
        markup = self._get(endpoint)
        return self._parse_bing_html(markup, limit)

    @staticmethod
    def _parse_bing_html(markup: str, limit: int) -> list[dict]:
        results: list[dict] = []
        for match in re.finditer(
            r"<h2[^>]*>\s*<a[^>]*href=\"([^\"]+)\"[^>]*>(.*?)</a>", markup, re.S
        ):
            url = html_lib.unescape(match.group(1))
            wrapped = re.search(r"[?&]u=a1([A-Za-z0-9_\-]+)", url)
            if wrapped:
                encoded = wrapped.group(1)
                encoded += "=" * (-len(encoded) % 4)
                try:
                    url = base64.urlsafe_b64decode(encoded).decode("utf-8", "replace")
                except (ValueError, UnicodeError):
                    continue
            if not url.startswith(("http://", "https://")):
                continue
            title = html_lib.unescape(re.sub(r"<[^>]+>", "", match.group(2)).strip())
            results.append(
                {
                    "title": title[:200],
                    "url": url[:500],
                    "snippet": "",
                    "rank": _domain_rank(url),
                    "engine": "bing",
                }
            )
            if len(results) >= limit:
                break
        return results

    # ----------------------------------------------------------------- searxng
    def _searxng(self, query: str, limit: int) -> list[dict]:
        """Metasearch across public SearXNG instances, JSON then HTML."""
        results: list[dict] = []
        seen: set[str] = set()
        for index, endpoint in enumerate(_searxng_endpoints()[:MAX_SEARXNG_INSTANCES]):
            if self._deadline_at and time.monotonic() >= self._deadline_at:
                break
            if not _is_healthy(f"engine:{endpoint}"):
                continue
            for use_json in (True, False):
                params: dict[str, str] = {
                    "q": query,
                    "language": "en",
                    "categories": "general",
                }
                if use_json:
                    params["format"] = "json"
                try:
                    markup = self._get(
                        f"{endpoint}/search",
                        index,
                        urllib.parse.urlencode(params).encode("utf-8"),
                    )
                    parsed = (
                        self._parse_searxng_json(markup, seen, limit)
                        if use_json
                        else self._parse_searxng_html(markup, seen, limit)
                    )
                except Exception as error:  # noqa: BLE001 - instance isolated
                    _record_failure(f"engine:{endpoint}")
                    self._journal(
                        "web_search_failed",
                        {"engine": "searxng", "error_class": type(error).__name__},
                    )
                    continue
                _record_success(f"engine:{endpoint}")
                if parsed:
                    results.extend(parsed)
                    break
            if len(results) >= limit:
                break
        return results[:limit]

    @staticmethod
    def _parse_searxng_json(markup: str, seen: set[str], limit: int) -> list[dict]:
        payload = json.loads(markup)
        results: list[dict] = []
        for item in payload.get("results") or []:
            url = str(item.get("url") or "")
            if not url.startswith(("http://", "https://")) or url in seen:
                continue
            seen.add(url)
            results.append(
                {
                    "title": str(item.get("title") or "")[:200],
                    "url": url[:500],
                    "snippet": str(item.get("content") or "")[:MAX_SNIPPET_CHARS],
                    "rank": _domain_rank(url),
                    "engine": "searxng",
                }
            )
            if len(results) >= limit:
                break
        return results

    @staticmethod
    def _parse_searxng_html(markup: str, seen: set[str], limit: int) -> list[dict]:
        results: list[dict] = []
        for block in re.findall(
            r"<article[^>]*class=\"[^\"]*result[^\"]*\"[^>]*>(.*?)</article>",
            markup,
            re.S,
        ):
            link = re.search(
                r"<h3[^>]*>\s*<a[^>]*href=\"([^\"]+)\"[^>]*>(.*?)</a>", block, re.S
            )
            if not link:
                continue
            url = html_lib.unescape(link.group(1))
            if not url.startswith(("http://", "https://")) or url in seen:
                continue
            snippet = ""
            content = re.search(
                r"<p[^>]*class=\"[^\"]*content[^\"]*\"[^>]*>(.*?)</p>", block, re.S
            )
            if content:
                snippet = re.sub(
                    r"<[^>]+>", "", html_lib.unescape(content.group(1))
                ).strip()[:MAX_SNIPPET_CHARS]
            seen.add(url)
            results.append(
                {
                    "title": re.sub(r"<[^>]+>", "", link.group(2)).strip()[:200],
                    "url": url[:500],
                    "snippet": snippet,
                    "rank": _domain_rank(url),
                    "engine": "searxng",
                }
            )
            if len(results) >= limit:
                break
        return results

    # -------------------------------------------------------------- google news
    def _google_news(self, query: str, limit: int) -> list[dict]:
        """Structured current-affairs headlines (MoE/CBC/global education)."""
        endpoint = "https://news.google.com/rss/search?" + urllib.parse.urlencode(
            {"q": query, "hl": "en", "gl": "KE", "ceid": "KE:en"}
        )
        feed = self._get(endpoint)
        return self._parse_rss_items(feed, limit, "google_news")

    # --------------------------------------------------------------- marginalia
    def _marginalia(self, query: str, limit: int) -> list[dict]:
        """Independent index, useful when the mainstream engines throttle us."""
        endpoint = "https://search.marginalia.nu/search?" + urllib.parse.urlencode(
            {"query": query}
        )
        markup = self._get(endpoint)
        return self._parse_marginalia(markup, limit)

    @staticmethod
    def _parse_marginalia(markup: str, limit: int) -> list[dict]:
        results: list[dict] = []
        for match in re.finditer(
            r"<h2[^>]*>\s*<a[^>]*href=\"([^\"]+)\"[^>]*>(.*?)</a>\s*</h2>(.{0,1200})",
            markup,
            re.S,
        ):
            url = html_lib.unescape(match.group(1))
            if not url.startswith(("http://", "https://")):
                continue
            snippet = re.sub(
                r"<[^>]+>",
                " ",
                html_lib.unescape(match.group(3)),
            )
            snippet = re.sub(r"\s+", " ", snippet).strip()[:MAX_SNIPPET_CHARS]
            results.append(
                {
                    "title": re.sub(r"<[^>]+>", "", match.group(2)).strip()[:200],
                    "url": url[:500],
                    "snippet": snippet,
                    "rank": _domain_rank(url),
                    "engine": "marginalia",
                }
            )
            if len(results) >= limit:
                break
        return results
