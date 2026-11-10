"""Report-intent parsing for the governed "talk to your data" path.

Division of labour, and the reason this lives here rather than in PHP:

  * PHP owns the catalogue, the permissions, the row scope and the execution.
  * The engine owns the reasoning: turning a staff sentence into a *constrained*
    intent — one report code from the catalogue it was handed, plus a small
    filter map.

The engine never sees school data here, never calls a governed tool and never
reads the database, so the PHP -> engine -> PHP hop is acyclic: PHP calls this
endpoint only to classify a question, then executes the report itself.

Everything the model returns is treated as a suggestion. The report code is
dropped unless it is in the supplied catalogue, and filters are reduced to
scalars with known keys. PHP re-validates both anyway.
"""

from __future__ import annotations

import json
import re
from typing import Any

MAX_REPORTS = 60
MAX_FILTERS = 10
MAX_EXPLANATION = 400
_CODE = re.compile(r"^[A-Z][A-Z0-9_]{2,99}$")

INTENT_INSTRUCTIONS = (
    "You choose the ONE governed report that answers a staff question.\n"
    "You may only use a report code from the supplied catalogue, and only "
    "filters listed in that report's allowed_filters.\n"
    "Reply with nothing except JSON:\n"
    '{"report_code":"<code or empty>","filters":{},"confidence":0.0,'
    '"explanation":"<one short sentence>","cannot_answer":false}\n'
    "Use an empty report_code and cannot_answer=true when no catalogue report "
    "answers the question. Never invent a code, a filter key or a value."
)


def _clean_reports(reports: Any) -> list[dict[str, Any]]:
    """Keep only the catalogue fields the classifier is allowed to see."""
    clean: list[dict[str, Any]] = []
    if not isinstance(reports, list):
        return clean
    for report in reports[:MAX_REPORTS]:
        if not isinstance(report, dict):
            continue
        code = str(report.get("code") or "").strip().upper()
        if not _CODE.match(code):
            continue
        entry: dict[str, Any] = {"code": code}
        for key, limit in (
            ("title", 160),
            ("description", 200),
            ("domain", 60),
            ("grain", 60),
        ):
            value = str(report.get(key) or "").strip()
            if value:
                entry[key] = value[:limit]
        for key in ("allowed_filters", "required_filters"):
            values = report.get(key)
            if isinstance(values, list):
                names = [
                    str(value).strip()[:60]
                    for value in values[:40]
                    if isinstance(value, (str, int, float))
                ]
                if names:
                    entry[key] = names
        defaults = report.get("default_filters")
        if isinstance(defaults, dict):
            entry["default_filters"] = {
                str(key)[:60]: value
                for key, value in list(defaults.items())[:MAX_FILTERS]
                if isinstance(value, (str, int, float, bool))
            }
        clean.append(entry)
    return clean


def _clean_filters(raw: Any, allowed: list[str]) -> dict[str, Any]:
    """Keep scalar filters whose key the report allows."""
    if not isinstance(raw, dict):
        return {}
    allowed_set = {str(name) for name in allowed}
    filters: dict[str, Any] = {}
    for key, value in list(raw.items())[:MAX_FILTERS]:
        name = str(key).strip()
        if not name or (allowed_set and name not in allowed_set):
            continue
        if isinstance(value, bool) or isinstance(value, (int, float)):
            filters[name] = value
        elif isinstance(value, str):
            filters[name] = value[:120]
    return filters


def parse_intent(
    provider: Any,
    journal: Any,
    question: str,
    audience: str,
    reports: Any,
) -> dict[str, Any]:
    """Classify a staff question into a bounded report intent."""
    catalogue = _clean_reports(reports)
    question = str(question or "").strip()[:500]
    if not question or not catalogue:
        return {
            "cannot_answer": True,
            "report_code": "",
            "filters": {},
            "confidence": 0.0,
            "explanation": "no governed report is available for this question",
        }

    by_code = {report["code"]: report for report in catalogue}
    payload = json.dumps(
        {
            "question": question,
            "audience": str(audience or "staff")[:40],
            "reports": catalogue,
        },
        ensure_ascii=False,
    )
    try:
        response = provider.complete(
            [
                {"role": "system", "content": INTENT_INSTRUCTIONS},
                {"role": "user", "content": payload},
            ],
            {"response_format": "json_object", "task": "intent"},
        )
    except Exception as error:  # noqa: BLE001 - a classifier failure is not fatal
        if journal is not None:
            journal.write(
                "ai_generation",
                {
                    "type": "nlq_intent_failed",
                    "error_class": type(error).__name__,
                },
            )
        return {
            "cannot_answer": True,
            "report_code": "",
            "filters": {},
            "confidence": 0.0,
            "explanation": "the question could not be matched to a governed report",
        }

    if not isinstance(response, dict):
        response = {}
    code = str(response.get("report_code") or "").strip().upper()
    report = by_code.get(code)
    if report is None:
        # The model named a report that is not in the catalogue: discard it.
        return {
            "cannot_answer": True,
            "report_code": "",
            "filters": {},
            "confidence": 0.0,
            "explanation": str(response.get("explanation") or "")[:MAX_EXPLANATION],
        }
    filters = _clean_filters(
        response.get("filters"), report.get("allowed_filters") or []
    )
    try:
        confidence = max(0.0, min(1.0, float(response.get("confidence") or 0.0)))
    except (TypeError, ValueError):
        confidence = 0.0
    return {
        "cannot_answer": False,
        "report_code": code,
        "filters": filters,
        "confidence": confidence,
        "explanation": str(response.get("explanation") or "")[:MAX_EXPLANATION],
    }
