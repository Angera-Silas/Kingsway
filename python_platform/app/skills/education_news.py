"""Curated education-news surfacing for a Kenyan CBC/CBE school.

The school asked to be told "what's new in the Ministry of Education Kenya,
CBC, CBE" and "trends in education around the world". This skill turns that
into a deterministic, bounded, deduplicated digest from the public web:

* a small set of FIXED, high-signal queries per topic (the model cannot inject
  arbitrary queries here - it only picks a topic);
* full web search via the existing WebSearchSkill (no source allowlist);
* deduplication by canonical URL, so the same story from two mirrors - or two
  topics - appears once;
* ranking by the existing educational-domain preference;
* pre-flattened, source-bearing strings so the answer guard can scrub them and
  the model can cite provenance.

No school data is an input. The only inputs are a topic label and a bounded
limit. This is an outbound public read like web.* and is journalled as such.
"""

from __future__ import annotations

from concurrent.futures import ThreadPoolExecutor, as_completed
from typing import Any, Callable
from urllib.parse import urlparse, urlunparse

from .web_search import WebSearchSkill, _domain_rank

# Fixed, curated queries. A caller chooses a topic label, never free-form text,
# so a prompt cannot turn "news" into a probe of arbitrary/private content.
# Kingsway is a Kenyan CBC primary + junior-secondary school for learners under
# 15 (Grade 9 down to Playgroup). The assessment queries deliberately name
# junior school / KJSEA, not senior-school examinations, so the digest does not
# surface content the school does not deliver.
TOPICS: dict[str, tuple[str, ...]] = {
    "moe": (
        "Kenya Ministry of Education CBC CBE basic education news 2026",
        "Kenya Ministry of Education circulars primary junior school 2026",
    ),
    "curriculum": (
        "KICD Kenya CBC curriculum design primary junior school news",
        "Kenya CBC competency based curriculum KICD basic education 2026",
    ),
    "assessment": (
        "Kenya junior school assessment KNEC CBC 2026",
        "KJSEA Kenya junior school assessment news 2026",
    ),
    "global": (
        "global primary education trends 2026 UNESCO World Bank",
        "education technology trends 2026 OECD primary teaching learning",
    ),
}
ALL_TOPICS = ("moe", "curriculum", "assessment", "global")
MAX_ITEMS = 10
MAX_ITEM_CHARS = 240

# A search hit must actually be about school education before it is surfaced.
# The queries are already curated, but the public engines (especially the
# open SearXNG/marginalia hops) occasionally return generic noise - e.g. a
# Wikipedia "Kenya" country page for a "... Kenya ... education" query. These
# strong terms are the minimum proof of relevance; generic words such as
# "school" alone are deliberately absent because a country article mentions
# schools too.
_RELEVANCE_TERMS = (
    "education",
    "cbc",
    "cbe",
    "curriculum",
    "kicd",
    "knec",
    "kjsea",
    "competenc",  # competency / competency-based
    "basic education",
    "junior school",
    "primary",
    "ministry of education",
    "syllabus",
    "teacher",
    "learner",
    "classroom",
    "unesco",
    "oecd",
    "world bank",
    "assessment",
    "exam",
)

# Kingsway ends at Grade 9 (junior secondary). Senior-school / KCSE / A-level
# content describes a phase the school does not deliver and is unsuitable for
# learners under 15, so it is dropped rather than presented as relevant news.
_OFF_PROFILE_TERMS = (
    "senior school",
    "senior secondary",
    "kcse",
    "kce ",
    "a-level",
    "a level",
    "form 1",
    "form 2",
    "form 3",
    "form 4",
    "form one",
    "form two",
    "form three",
    "form four",
    "high school",
    "sixth form",
)

# Encyclopedia/country reference pages are not "what's new" and crowd out the
# actual circulars and education stories. Keep them only as a last resort by
# de-ranking rather than hard-dropping (a genuinely education-specific article
# on such a site can still be useful when nothing better was returned).
_GENERIC_SOURCE_HOSTS = ("wikipedia.org", "britannica.com")


def is_profile_relevant(item: dict[str, Any]) -> bool:
    """True when a search hit is on-profile for this school's news digest."""
    text = f"{item.get('title') or ''} {item.get('snippet') or ''}".strip().lower()
    if not any(term in text for term in _RELEVANCE_TERMS):
        return False
    if any(term in text for term in _OFF_PROFILE_TERMS):
        return False
    return True


def canonical_url(url: str) -> str:
    """A stable key for dedup: scheme+host+path without query/fragment."""
    try:
        parsed = urlparse(str(url or "").strip())
        return urlunparse(
            (
                parsed.scheme.lower(),
                parsed.netloc.lower(),
                parsed.path.rstrip("/"),
                "",
                "",
                "",
            )
        )
    except Exception:
        return str(url or "").strip()


class EducationNewsSkill:
    def __init__(
        self, journal=None, search: Callable[[str, int], list[dict]] | None = None
    ) -> None:
        self.journal = journal
        self._search = search or WebSearchSkill(journal).search

    def digest(self, topic: str = "all", limit: int = MAX_ITEMS) -> dict[str, Any]:
        topic = (topic or "all").strip().lower()
        keys = (
            ALL_TOPICS
            if topic in ("all", "", "latest")
            else ((topic,) if topic in TOPICS else ())
        )
        if not keys:
            return {
                "status": "invalid_topic",
                "message": "choose a topic: moe, curriculum, assessment, global or all",
                "topics": list(ALL_TOPICS),
            }
        limit = max(1, min(int(limit or 0), MAX_ITEMS))

        # Run the curated queries concurrently. Serially, a single slow engine
        # could make the eight-query digest exceed two minutes; bounded fan-out
        # keeps the whole digest within one query's wall-clock budget.
        jobs = [(key, query) for key in keys for query in TOPICS[key]]
        fetched: list[list[dict]] = [[] for _ in jobs]
        with ThreadPoolExecutor(max_workers=min(4, len(jobs))) as pool:
            futures = {
                pool.submit(self._search, query, limit): index
                for index, (_key, query) in enumerate(jobs)
            }
            for future in as_completed(futures):
                index = futures[future]
                try:
                    fetched[index] = future.result() or []
                except Exception:
                    fetched[index] = []

        seen: set[str] = set()
        collected: list[dict[str, Any]] = []
        for (key, _query), results in zip(jobs, fetched):
            for item in results:
                # Drop engine noise and senior-school content before dedupe so
                # an off-profile hit cannot consume a slot or shadow a real
                # basic-education result from another topic.
                if not is_profile_relevant(item):
                    continue
                url = str(item.get("url") or "").strip()
                if not url:
                    continue
                canon = canonical_url(url)
                if canon in seen:
                    continue
                seen.add(canon)
                title = str(item.get("title") or "").strip()
                snippet = str(item.get("snippet") or "").strip()
                label = title or canon
                if snippet:
                    label = f"{label} - {snippet}"
                rank = int(item.get("rank", 0)) + _domain_rank(url)
                host = urlparse(url).netloc.lower()
                if any(ref in host for ref in _GENERIC_SOURCE_HOSTS):
                    rank -= 25
                collected.append(
                    {
                        "topic": key,
                        "title": title[:160],
                        "url": url[:300],
                        "label": label[:MAX_ITEM_CHARS],
                        "rank": rank,
                    }
                )

        # Best educational sources first, then bounded.
        collected.sort(key=lambda item: item["rank"], reverse=True)
        items = collected[:limit]
        if self.journal is not None:
            self.journal.write(
                "ai_generation",
                {
                    "type": "education_news",
                    "topics": list(keys),
                    "unique_count": len(items),
                },
            )
        return {
            "status": "answered" if items else "unavailable",
            "topics": list(keys),
            "item_count": len(items),
            "items": [
                {
                    "topic": i["topic"],
                    "title": i["title"],
                    "label": i["label"],
                    "url": i["url"],
                }
                for i in items
            ],
            # Pre-flattened strings: the orchestrator keeps only scalar list
            # items, and the URL is embedded so the model cites provenance.
            "headlines": [f"[{i['topic']}] {i['label']} ({i['url']})" for i in items],
            "note": (
                "Public web results for a Kenyan primary and junior-secondary "
                "school (learners under 15). Prefer junior-school and basic-"
                "education sources; verify against the official source before acting."
            ),
        }
