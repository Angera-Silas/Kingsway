"""Academic planning skill: lesson plans, schemes of work, assessments, notes.

This is the school's primary requirement: the assistant must be able to search
the web very fast and give teachers ideas, activities, assessments, notes and
objectives — grounded in the school's own KICD curriculum and resources, and
always returned as an editable draft rather than a finished document.
"""

from __future__ import annotations

import json
from typing import Any

from .. import output_guard, policy
from ..agents import PROMPT_TEMPLATES
from ..journal import Journal, hash_text
from . import web_search

MAX_OUTPUT_CHARS = 6000

TEMPLATE_MAP = {
    "lesson_plan": "academics.planning",
    "scheme_of_work": "academics.planning",
    "assessment": "academics.planning",
    "notes": "academics.planning",
    "objectives": "academics.planning",
}


def _bounded_text(value: Any, limit: int) -> str:
    return str(value or "").strip()[:limit]


class AcademicPlanningSkill:
    """Draft academic artefacts from school context + live web search."""

    def __init__(self, provider, journal: Journal) -> None:
        self.provider = provider
        self.journal = journal
        self.search = web_search.WebSearchSkill(journal)

    def plan(self, payload: dict) -> dict:
        kind = str(payload.get("kind") or "lesson_plan").strip()
        if kind not in TEMPLATE_MAP:
            kind = "lesson_plan"
        question = _bounded_text(payload.get("question"), 1000)
        context = (
            payload.get("context") if isinstance(payload.get("context"), dict) else {}
        )

        web = self.search.academic_context(
            _bounded_text(context.get("learning_area") or context.get("subject"), 120),
            _bounded_text(context.get("grade") or context.get("grade_level"), 40),
            question or _bounded_text(context.get("topic"), 200),
        )

        envelope = policy.minimize(
            TEMPLATE_MAP[kind],
            {
                "kind": kind,
                "question": question,
                "audience": "school_staff",
                "school_context": json.dumps(context, ensure_ascii=False),
                "web_ideas": json.dumps(web, ensure_ascii=False),
                "instruction": _bounded_text(payload.get("instruction"), 400),
            },
        )
        raw = self.provider.complete(
            [
                {"role": "system", "content": PROMPT_TEMPLATES[TEMPLATE_MAP[kind]]},
                {"role": "user", "content": json.dumps(envelope, ensure_ascii=False)},
            ],
            {"response_format": "json_object", "temperature": 0.4, "max_tokens": 2000},
        )
        result = output_guard.scrub(raw)
        if not isinstance(result, dict) or not isinstance(result.get("body"), str):
            self.journal.write(
                "ai_generation",
                {
                    "type": "academic_planning_failed",
                    "kind": kind,
                    "question_hash": hash_text(question),
                },
            )
            return {
                "status": "unavailable",
                "reason": "provider_did_not_return_a_valid_draft",
                "kind": kind,
            }

        self.journal.write(
            "ai_generation",
            {
                "type": "academic_planning_drafted",
                "kind": kind,
                "question_hash": hash_text(question),
                "web_source_count": web["source_count"],
                "provider_calls": 1,
            },
        )
        return {
            "status": "answered",
            "kind": kind,
            "title": _bounded_text(
                result.get("title") or f"Draft {kind.replace('_', ' ')}", 200
            ),
            "body": result["body"].strip()[:MAX_OUTPUT_CHARS],
            "objectives": [str(i) for i in (result.get("objectives") or [])][:10],
            "activities": [str(i) for i in (result.get("activities") or [])][:10],
            "assessment_ideas": [
                str(i) for i in (result.get("assessment_ideas") or [])
            ][:10],
            "resources": [str(i) for i in (result.get("resources") or [])][:10],
            "sources": self._citations(web),
            "advisory": True,
            "draft_not_final": True,
        }

    @staticmethod
    def _citations(web: dict) -> list[dict]:
        sources: list[dict] = []
        for bucket in ("teaching_ideas", "activity_ideas", "assessment_ideas"):
            for item in web.get(bucket) or []:
                url = item.get("url")
                if url and url not in {s.get("url") for s in sources}:
                    sources.append(
                        {
                            "title": item.get("title", ""),
                            "url": url,
                            "kind": bucket.replace("_ideas", ""),
                        }
                    )
        return sources[:12]
