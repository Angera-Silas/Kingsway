"""Web search + school identity grounding skills.

This is the capability the school asked for: the assistant must be able to
search the web very fast and give teachers ideas, student ideas, activities,
assessments and lesson/scheme drafts — never limited to what happens to be
stored in the school database.

Design rules:
  * Educational/reputable domains are preferred and ranked first.
  * Every returned result carries a source URL so answers cite provenance.
  * Results are bounded (count + characters) so prompts stay within budget.
  * Failures degrade gracefully: an unreachable search engine yields an
    empty list, never an exception that breaks the caller.
  * No school credentials, learner identity or secrets are ever searched.
"""

from __future__ import annotations

import json
import os
import re
import time
import urllib.error
import urllib.parse
import urllib.request
import urllib.robotparser
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

# Ranked educational domains. Rank is a tie-breaker, never a hard filter, so a
# genuinely useful .com teaching resource is still usable.
PREFERRED_DOMAINS: tuple[tuple[str, int], ...] = (
    ("kicd.go.ke", 100),
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

# DuckDuckGo serves a real SERP to a browser User-Agent and a stripped,
# result-free page to a self-identifying bot agent. A client that calls itself
# "KingswayAssistant/1.0 (school research)" therefore gets ZERO results and the
# assistant silently answers from model knowledge instead — the failure looks
# like "no results found" rather than an error, so it is invisible. This is a
# browser-class agent.
_BROWSER_UA = (
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
)

# DuckDuckGo endpoints tried in order, each with its result anchor. Kept as a
# list rather than one hard-coded regex so a markup change degrades to the next
# endpoint instead of returning nothing at all.
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


def _domain_rank(url: str) -> int:
    host = urllib.parse.urlparse(url).netloc.lower()
    for suffix, rank in PREFERRED_DOMAINS:
        if suffix in host:
            return rank
    return 0


def public_url_error(url: str) -> str | None:
    """Return a refusal message for a non-public/invalid URL, else None.

    One implementation shared by fetch and crawl. The assistant must never be
    usable as an SSRF probe against the school's own loopback/private network,
    and a crawl must not be able to fan out to metadata services.
    """
    parsed = urllib.parse.urlparse(str(url or "").strip())
    if parsed.scheme not in ("http", "https") or not parsed.netloc:
        return "only http and https urls can be fetched"
    host = (parsed.hostname or "").lower()
    if host in ("localhost", "127.0.0.1", "::1", "0.0.0.0"):
        return "that host cannot be fetched"
    if host.startswith(("127.", "192.168.", "10.", "169.254.")):
        return "that host cannot be fetched"
    if host.startswith("172."):
        octet = host.split(".")[1]
        if octet.isdigit() and 16 <= int(octet) <= 31:
            return "that host cannot be fetched"
    return None


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
        # robots.txt decisions are cached per host for the life of the process
        # worker so a crawl checks each host once, not once per page.
        self._robots: dict[str, Any] = {}

    # ------------------------------------------------------------------ search
    def search(self, query: str, limit: int = MAX_RESULTS) -> list[dict]:
        query = str(query or "").strip()
        if not query:
            return []
        limit = max(1, min(limit, MAX_RESULTS))
        try:
            if self._engine == "bing":
                results = self._bing(query, limit)
            else:
                results = self._duckduckgo(query, limit)
        except (urllib.error.URLError, OSError, ValueError) as error:
            self.journal.write(
                "ai_generation",
                {
                    "type": "web_search_failed",
                    "engine": self._engine,
                    "error_class": type(error).__name__,
                },
            )
            return []
        results.sort(key=lambda item: item.get("rank", 0), reverse=True)
        final = results[:limit]
        if self.journal is not None:
            if not final:
                # Zero used to be indistinguishable from "no such page exists".
                # Journalled so a markup/UA change is diagnosable instead of
                # silently degrading every world-knowledge answer to model guesses.
                self.journal.write(
                    "ai_generation",
                    {
                        "type": "web_search_no_results",
                        "engine": self._engine,
                        "query_len": len(query),
                    },
                )
            self.journal.write(
                "ai_generation",
                {
                    "type": "web_search",
                    "engine": self._engine,
                    "result_count": len(final),
                },
            )
        return final

    def academic_context(self, subject: str, grade: str, topic: str) -> dict:
        """Bundle web ideas with KICD-style curriculum language for planning.

        Returns everything a planning skill needs: teaching ideas, activity
        ideas, assessment ideas and suggested resources, each with sources.
        """
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

    def fetch(self, url: str, max_chars: int = 6000) -> dict:
        """Read one public page and return its readable text.

        This is the "browse freely" half of web access: a search result names a
        page, and this brings back its contents so the agent answers from the
        current source instead of from model memory. Any public URL is allowed
        — PREFERRED_DOMAINS only RANKS search results, it never gates them.

        Bounded and guarded: http/https only, private/loopback hosts refused so
        the assistant cannot be used as an SSRF probe against the school's own
        infrastructure, and the body is capped in bytes and characters.
        """
        url = str(url or "").strip()
        if not url:
            return {"status": "unavailable", "message": "a url is required"}
        refusal = public_url_error(url)
        if refusal:
            return {"status": "unavailable", "message": refusal}

        started = time.monotonic()
        try:
            content_type, page = self._read_html(url)
        except (urllib.error.URLError, OSError, ValueError) as error:
            if self.journal is not None:
                self.journal.write(
                    "ai_generation",
                    {
                        "type": "web_fetch_failed",
                        "error_class": type(error).__name__,
                        "duration_ms": int((time.monotonic() - started) * 1000),
                    },
                )
            return {
                "status": "unavailable",
                "message": "that page could not be fetched",
            }

        if "text" not in content_type and "html" not in content_type:
            return {
                "status": "unavailable",
                "message": f"unsupported content type {content_type or 'unknown'}",
            }

        text = _html_to_text(page)[:max_chars]
        if self.journal is not None:
            self.journal.write(
                "ai_generation",
                {
                    "type": "web_fetch",
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

    def _read_html(self, url: str) -> tuple[str, str]:
        """GET one public page, returning (content_type, decoded_body)."""
        request = urllib.request.Request(
            url, headers={"User-Agent": _BROWSER_UA, "Accept-Language": "en"}
        )
        with urllib.request.urlopen(request, timeout=self._timeout) as response:
            content_type = (response.headers.get("Content-Type") or "").lower()
            raw = response.read(_MAX_FETCH_BYTES)
        return content_type, raw.decode("utf-8", "replace")

    def _robots_allows(self, url: str) -> bool:
        """Honour robots.txt for a crawl, failing open on an unreachable file.

        A site with no robots.txt (or an unreachable one) is crawlable; an
        explicit Disallow is respected. Cached per scheme+host.
        """
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
        """Read a bounded set of linked pages from one public site.

        This is the multi-page half of free browsing: ``fetch`` answers from one
        URL, ``crawl`` follows a small number of on-site links so the agent can
        compare several pages (a syllabus index and its sub-pages, for
        instance) in a single turn. It is deliberately not a scraper fleet:
        robots.txt is honoured, the page count, depth and total characters are
        capped, and off-site links are skipped unless explicitly requested.
        """
        seed = str(seed or "").strip()
        if not seed:
            return {"status": "unavailable", "message": "a seed url is required"}
        refusal = public_url_error(seed)
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

        if self.journal is not None:
            self.journal.write(
                "ai_generation",
                {
                    "type": "web_crawl",
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

        A browser User-Agent is mandatory: DuckDuckGo returns a stripped page
        with no results to a self-identifying bot agent, which would silently
        turn every world-knowledge question into a model-knowledge answer.
        """
        last_error: Exception | None = None
        for endpoint, anchor in _SERP_SOURCES:
            try:
                request = urllib.request.Request(
                    endpoint + urllib.parse.quote_plus(query),
                    headers={"User-Agent": _BROWSER_UA, "Accept-Language": "en"},
                )
                with urllib.request.urlopen(request, timeout=self._timeout) as response:
                    html = response.read().decode("utf-8", "replace")
            except (urllib.error.URLError, OSError, ValueError) as error:
                last_error = error
                continue

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
                }
            )
            if len(results) >= limit:
                break
        return results

    def _bing(self, query: str, limit: int) -> list[dict]:
        key = os.environ.get("AI_WEB_SEARCH_KEY")
        if not key:
            return self._duckduckgo(query, limit)
        endpoint = (
            os.environ.get("AI_WEB_SEARCH_ENDPOINT") or "https://api.bing.microsoft.com"
        )
        request = urllib.request.Request(
            f"{endpoint}/v7.0/search?q={urllib.parse.quote_plus(query)}&count={limit}",
            headers={"Ocp-Apim-Subscription-Key": key},
        )
        with urllib.request.urlopen(request, timeout=self._timeout) as response:
            payload = json.loads(response.read().decode("utf-8"))
        results: list[dict] = []
        for item in (payload.get("webPages") or {}).get("value") or []:
            url = str(item.get("url") or "")
            if not url:
                continue
            results.append(
                {
                    "title": str(item.get("name") or "")[:200],
                    "url": url[:500],
                    "snippet": str(item.get("snippet") or "")[:MAX_SNIPPET_CHARS],
                    "rank": _domain_rank(url),
                }
            )
        return results

    @staticmethod
    def _unwrap_ddg(href: str) -> str:
        href = href.replace("&amp;", "&")
        if "uddg=" not in href:
            return href
        parsed = urllib.parse.parse_qs(urllib.parse.urlparse(href).query)
        return (parsed.get("uddg") or [href])[0]
