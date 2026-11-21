"""Advisory public-content coworker for a Kenyan CBC school.

The school asked the assistant to "suggest what to post, how, news headlines,
events, assist in drafting posts". This skill produces that guidance
deterministically and permission-scopedly:

* ADVISORY ONLY - it never publishes, schedules or writes anything. The
  operator still uses the governed communications/website workflows to publish,
  with their own approvals. This avoids an AI silently posting to a public site.
* PERMISSION-GATED - the calling tool checks the operator holds a real
  communications/website permission before this skill is reached.
* BOUNDED - fixed templates, capped lists and lengths; free text (topic) is
  clipped. No school data is an input, so nothing sensitive can leak.
* SOURCED - optional current headlines come from the curated education.news
  digest, each carrying a URL to verify before publishing.
"""

from __future__ import annotations

from typing import Any, Callable

ALLOWED_PURPOSES = (
    "announcement",
    "news",
    "event",
    "reminder",
    "admissions",
    "general",
)
ALLOWED_CHANNELS = ("website", "whatsapp", "sms", "email", "newsletter", "noticeboard")
MAX_ITEMS = 5
MAX_TOPIC_CHARS = 160

# Fixed, school-relevant idea banks. Kept short so a whole advisory fits in a
# prompt field and the model can elaborate without inventing facts.
_IDEAS: dict[str, tuple[str, ...]] = {
    "announcement": (
        "Share the-on this week's highlights",
        "Announce the next term's opening date and key dates",
        "Introduce a new staff member or club",
        "Celebrate a learner achievement (no names without consent)",
        "Remind families of the uniform and book list",
    ),
    "news": (
        "Summarise a recent CBC/CBE update from the Ministry",
        "Report on a school event with one photo and a short caption",
        "Share a KICD curriculum resource for parents",
        "Highlight a community partnership or outreach",
        "Publish a short 'term in review' note",
    ),
    "event": (
        "Preview the next school event with date, venue and purpose",
        "Invite parents to a class showcase",
        "Announce a sports day or inter-house competition",
        "Share the weekly assemblies and worship schedule",
        "Promote the careers or vocational day (farm, bakery, sewing)",
    ),
    "reminder": (
        "Remind families of the weekend reporting time for boarders",
        "Remind day learners of the term closing date",
        "Remind parents to update emergency contacts",
        "Remind staff of the weekly planning meeting",
        "Remind families about fee payment deadlines",
    ),
    "admissions": (
        "Restate the eight admissions stages plainly",
        "Explain which grades require an interview",
        "Share what to bring on the first day",
        "Publish the admissions contact and visiting hours",
        "Explain the day versus boarding options",
    ),
    "general": (
        "Share the school's motto and what it means this term",
        "Introduce the chaplaincy and spiritual-life programme",
        "Highlight the farm, greenhouse and vocational training",
        "Publish a short safety or wellbeing tip",
        "Thank the community for its support",
    ),
}

_HEADLINES: dict[str, tuple[str, ...]] = {
    "announcement": (
        "Key dates for the coming term",
        "What families need to know this week",
    ),
    "news": ("From the Ministry: what changed for CBC", "School news in brief"),
    "event": ("Join us for {topic}", "What's coming up at Kingsway"),
    "reminder": ("A short reminder for families", "Before the next school day"),
    "admissions": (
        "Our admissions process, step by step",
        "Joining Kingsway: what to expect",
    ),
    "general": ("Life at Kingsway this term", "A note from our school community"),
}

_CHANNEL_GUIDANCE: dict[str, tuple[str, ...]] = {
    "website": (
        "Keep the headline under 70 characters and the first sentence self-contained.",
        "Add one clear call to action and a contact point.",
    ),
    "whatsapp": (
        "Write for a phone screen: 3 short lines and one link.",
        "Send once during school hours; avoid late-night broadcasts.",
    ),
    "sms": (
        "Cap at 160 characters; put the date and place first.",
        "Avoid links and special characters that break handsets.",
    ),
    "email": (
        "Use a specific subject line and put the key date in the first line.",
        "Sign off with a named contact, not a generic inbox.",
    ),
    "newsletter": (
        "Lead with one story, then a short list of dates.",
        "Check names and photos against consent records before publishing.",
    ),
    "noticeboard": (
        "Use a title plus three bullet points; large print for a corridor board.",
        "Date the notice and remove it after the event.",
    ),
}


class PublicContentSkill:
    def __init__(
        self,
        journal=None,
        news: Callable[[str, int], dict[str, Any]] | None = None,
    ) -> None:
        self.journal = journal
        self._news = news

    def advise(
        self,
        purpose: str = "general",
        audience: str = "parents and guardians",
        channel: str = "website",
        topic: str = "",
        include_news: bool = False,
        limit: int = MAX_ITEMS,
    ) -> dict[str, Any]:
        purpose = (purpose or "general").strip().lower()
        if purpose not in ALLOWED_PURPOSES:
            purpose = "general"
        channel = (channel or "website").strip().lower()
        if channel not in ALLOWED_CHANNELS:
            channel = "website"
        audience = (
            str(audience or "parents and guardians").strip()[:60]
            or "parents and guardians"
        )
        topic = str(topic or "").strip()[:MAX_TOPIC_CHARS]
        limit = max(1, min(int(limit or 0), MAX_ITEMS))

        ideas = [
            idea.format(topic=topic or "our school community")
            for idea in _IDEAS[purpose][:limit]
        ]
        headline_bank = _HEADLINES[purpose]
        headlines = [
            h.format(topic=topic or "a Kingsway event") for h in headline_bank
        ][: min(limit, len(headline_bank))]

        event_ideas: list[str] = []
        if purpose in ("event", "general", "news"):
            event_ideas = list(_IDEAS["event"][: min(limit, 3)])

        subject = topic or headline_bank[0].format(topic="our school community")
        draft = (
            f"{subject}\n\n"
            f"Dear {audience},\n\n"
            f"A short note about {subject}. We will share the key details, "
            f"including the date, venue and what you need to bring.\n\n"
            f"Thank you,\nKingsway Preparatory School"
        )[:600]

        how_to = [
            f"Channel: {channel}.",
            f"Write for {audience}.",
            *_CHANNEL_GUIDANCE[channel],
            "Draft only: review and use the school's publishing workflow before it goes live.",
        ]

        result: dict[str, Any] = {
            "status": "advisory",
            "purpose": purpose,
            "audience": audience,
            "channel": channel,
            "post_ideas": ideas,
            "headlines": headlines,
            "event_ideas": event_ideas,
            "draft": draft,
            "how_to": how_to,
            "note": "Advisory only. Check facts and consent, then publish through the approved school workflow.",
        }

        if include_news and self._news is not None:
            try:
                digest = self._news("all", limit)
                result["news_headlines"] = digest.get("headlines", [])[:limit]
                result["news_note"] = digest.get("note", "")
            except Exception:
                result["news_headlines"] = []

        if self.journal is not None:
            self.journal.write(
                "ai_generation",
                {
                    "type": "public_content_advisory",
                    "purpose": purpose,
                    "channel": channel,
                    "include_news": bool(include_news),
                },
            )
        return result
