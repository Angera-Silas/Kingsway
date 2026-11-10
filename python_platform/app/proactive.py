"""Proactive, permission-aware suggestions for the page a staff member is on.

The assistant already knows the route, the module, the entity a staff member
has open, the filters they have set and the metrics they can see. This module
turns that context into a small number of concrete next questions.

Three rules shape it:

  * **Deterministic, no provider call.** A nudge that costs a model call every
    time a page sits idle would be an expensive way to guess. These are
    authored hints keyed to context, so they are predictable, auditable and
    free. The model still writes the answer once the staff member accepts.
  * **Permission-gated before it is ever offered.** A suggestion that leads to
    a refusal is worse than no suggestion. Each hint names the permissions it
    needs and is dropped unless the operator holds one of them.
  * **It never states a fact.** A nudge is an offer to look, not an assertion
    about the school: "Show fee arrears by class", never "Grade 7 arrears are
    up 12%". Nothing here can leak a figure the operator is not allowed to see.
"""

from __future__ import annotations

import hashlib
from typing import Any

# A nudge is offered at most once per page signature per browser session, and
# the client enforces that. The server still caps how many it returns.
MAX_SUGGESTIONS = 3
MAX_LABEL = 80
MAX_REASON = 140

# Modules whose permission is not simply "<module>_view".
PERMISSION_ALIASES = {
    "academic": ("academic_view", "analytics_catalogue_view"),
    "academics": ("academic_view", "analytics_catalogue_view"),
    "students": ("students_view", "analytics_catalogue_view"),
    "admissions": ("admission_view", "analytics_catalogue_view"),
    "boarding": ("attendance_boarding_view", "attendance_view"),
    "reports": ("analytics_catalogue_view",),
    "analytics": ("analytics_catalogue_view",),
    "dashboard": (),
    "communications": ("communications_view",),
    "finance": ("finance_view",),
    "staff": ("staff_view",),
    "inventory": ("inventory_view",),
    "transport": ("transport_view",),
    "health": ("health_view",),
    "catering": ("catering_view", "inventory_view"),
    "maintenance": ("maintenance_view",),
    "activities": ("activities_view",),
    "counseling": ("counseling_view", "students_view"),
}

# The authored catalogue. `modules` is matched against the route/module and
# `needs` is an ANY-of permission list; an empty `needs` means the hint is
# available to every signed-in staff member (it only offers a question, and the
# governed tool behind it re-authorizes anyway).
NUDGES: tuple[dict[str, Any], ...] = (
    {
        "id": "finance.chart_arrears",
        "modules": ("finance", "fees", "dashboard"),
        "needs": ("finance_view", "analytics_catalogue_view"),
        "title": "Chart fee arrears by class",
        "prompt": "Chart fee arrears by class",
        "reason": "Turns the arrears figures on this page into a comparison you can share.",
    },
    {
        "id": "finance.explain_movement",
        "modules": ("finance", "fees"),
        "needs": ("finance_view", "analytics_catalogue_view"),
        "title": "Explain this term's collection trend",
        "prompt": "How has fee collection moved this term, and which classes are behind?",
        "reason": "A short read of the trend behind the balances you are looking at.",
    },
    {
        "id": "finance.forecast",
        "modules": ("finance", "fees"),
        "needs": ("finance_view", "analytics_catalogue_view"),
        "title": "Project collection for next term",
        "prompt": "Forecast fee collection for the next term",
        "reason": "A bounded trend indication, not a promise, drawn from authorized rows.",
    },
    {
        "id": "attendance.lowest_classes",
        "modules": ("attendance", "dashboard", "academic", "academics"),
        "needs": ("attendance_view", "academic_view", "analytics_catalogue_view"),
        "title": "Which classes have the lowest attendance?",
        "prompt": "Which classes have the lowest attendance this term?",
        "reason": "Attendance is the metric you are viewing; this ranks it by class.",
    },
    {
        "id": "attendance.weekend_boarders",
        "modules": ("attendance", "boarding"),
        "needs": ("attendance_boarding_view", "attendance_view"),
        "title": "Weekend attendance for boarders",
        "prompt": "How many boarders missed weekend attendance this term?",
        "reason": "Weekend sessions only count for boarders, so this is a separate question.",
    },
    {
        "id": "academic.learning_gap",
        "modules": ("academic", "academics", "assessment", "exams"),
        "needs": ("academic_view", "analytics_catalogue_view"),
        "title": "Draft a learning-gap note for this class",
        "prompt": "Draft a learning-gap note for this class based on the latest assessment results",
        "reason": "You have a class open; the draft stays for your review before it is used.",
    },
    {
        "id": "academic.coverage",
        "modules": ("academic", "academics"),
        "needs": ("academic_view",),
        "title": "Summarise curriculum coverage",
        "prompt": "Summarise curriculum coverage for this term and flag the strands behind schedule",
        "reason": "Uses the coverage records already authorized for you.",
    },
    {
        "id": "admissions.funnel",
        "modules": ("admissions", "admission", "students"),
        "needs": ("admission_view", "students_view", "analytics_catalogue_view"),
        "title": "Where is the admissions funnel slowing?",
        "prompt": "Show the admissions funnel for this term and where applicants are waiting longest",
        "reason": "The eight-stage funnel, summarised as counts rather than individual applicants.",
    },
    {
        "id": "communications.draft_notice",
        "modules": ("communications", "dashboard"),
        "needs": ("communications_view",),
        "title": "Draft a notice for review",
        "prompt": "/draft notice to parents about the coming half-term arrangements",
        "reason": "Creates a draft in your inbox; nothing is sent until you approve it.",
    },
    {
        "id": "staff.attendance",
        "modules": ("staff", "hr"),
        "needs": ("staff_view", "analytics_catalogue_view"),
        "title": "Teacher attendance this term",
        "prompt": "Show teaching staff attendance this term",
        "reason": "Aggregate rates per role, never an individual's record in chat.",
    },
    {
        "id": "inventory.reorder",
        "modules": ("inventory", "uniform", "stores"),
        "needs": ("inventory_view",),
        "title": "What needs reordering?",
        "prompt": "Which uniform and store items are below reorder level?",
        "reason": "Turns the stock page into a short reorder list.",
    },
    {
        "id": "transport.routes",
        "modules": ("transport", "routes"),
        "needs": ("transport_view",),
        "title": "Summarise route utilisation",
        "prompt": "Summarise transport route utilisation and pickup punctuality this term",
        "reason": "Aggregate per route, so no learner's movements are exposed.",
    },
    {
        "id": "health.visits",
        "modules": ("health", "clinic"),
        "needs": ("health_view",),
        "title": "Clinic visits this week",
        "prompt": "Summarise clinic visits this week by category",
        "reason": "Counts by category only; the assistant never returns a learner's health record.",
    },
    {
        "id": "catering.consumption",
        "modules": ("catering", "kitchen", "meals"),
        "needs": ("catering_view", "inventory_view"),
        "title": "Meal consumption this week",
        "prompt": "Summarise meal consumption this week and flag any shortfall against the menu plan",
        "reason": "Compares what was served with what was planned.",
    },
    {
        "id": "maintenance.open",
        "modules": ("maintenance", "facilities"),
        "needs": ("maintenance_view",),
        "title": "Open maintenance by priority",
        "prompt": "List open maintenance requests by priority and age",
        "reason": "Shows what is waiting, ordered by how long it has waited.",
    },
    {
        "id": "activities.library",
        "modules": ("activities", "library"),
        "needs": ("activities_view",),
        "title": "Library borrowing this term",
        "prompt": "Summarise library borrowing this term by class",
        "reason": "Borrowing counts per class, not per learner.",
    },
    {
        "id": "counseling.referrals",
        "modules": ("counseling", "welfare"),
        "needs": ("counseling_view", "students_view"),
        "title": "Welfare referrals this term",
        "prompt": "Summarise welfare referrals this term by category",
        "reason": "Case counts by category; individual case notes stay in the counselling workspace.",
    },
    {
        "id": "reports.kpi",
        "modules": ("reports", "analytics", "dashboard"),
        "needs": ("analytics_catalogue_view",),
        "title": "What moved in the school's KPIs?",
        "prompt": "Explain what moved in the school's key figures this term and why it matters",
        "reason": "Uses the governed KPI reports you can already open.",
    },
    {
        "id": "reports.brief",
        "modules": ("reports", "analytics", "dashboard"),
        "needs": ("analytics_catalogue_view",),
        "title": "Give me the weekly briefing",
        "prompt": "/brief weekly",
        "reason": "The deterministic weekly briefing, in one turn.",
    },
)


def _permission_set(permissions: Any) -> set[str]:
    return {
        str(item).strip().lower() for item in (permissions or ()) if str(item).strip()
    }


def _module_tokens(context: dict[str, Any]) -> list[str]:
    """The route and module, normalised and split, most specific first."""
    tokens: list[str] = []
    for key in ("route", "module"):
        value = str(context.get(key) or "").strip().lower()
        if not value:
            continue
        for part in value.replace("/", " ").replace("-", " ").split():
            if part and part not in tokens:
                tokens.append(part[:40])
    return tokens


def _entity_nudge(
    page_context: dict[str, Any], permissions: set[str]
) -> dict[str, Any] | None:
    """One hint that leans on the specific thing the staff member has open."""
    entity = page_context.get("page_entity")
    if not isinstance(entity, dict):
        return None
    label = str(entity.get("label") or "").strip()[:MAX_LABEL]
    if not label:
        return None
    kind = str(entity.get("kind") or "").strip().lower()
    status = str(entity.get("status") or "").strip()[:40]

    if kind in {"class", "stream", "learning_area", "subject"}:
        if not (
            permissions
            & {"academic_view", "attendance_view", "analytics_catalogue_view"}
        ):
            return None
        return {
            "id": "entity.class_compare",
            "title": f"Compare {label} with the school average",
            "prompt": f"How does {label} compare with the school average this term?",
            "reason": f"You have {label} open; this compares it on the metrics you can see.",
        }
    if kind in {"invoice", "payment", "fee", "student_fee"}:
        if not (permissions & {"finance_view", "analytics_catalogue_view"}):
            return None
        return {
            "id": "entity.fee_context",
            "title": "Explain this balance",
            "prompt": (
                f"Explain the fee position for {label} using the class and term figures"
            ),
            "reason": "Uses aggregate figures around this item, not the learner's account.",
        }
    if kind in {"staff", "employee", "teacher"}:
        if not (permissions & {"staff_view", "academic_view"}):
            return None
        return {
            "id": "entity.staff_context",
            "title": f"Draft a note about {label}",
            "prompt": f"Draft a note about {label} for my review",
            "reason": "A draft for you to edit; it is never sent on its own.",
        }
    if status:
        return {
            "id": "entity.status_context",
            "title": f"What does '{status}' mean here?",
            "prompt": f"Explain what a status of '{status}' means for {label} and what usually happens next",
            "reason": f"You have {label} open with status '{status}'.",
        }
    return None


def build_suggestions(
    context: dict[str, Any], page_context: dict[str, Any] | None = None
) -> list[dict[str, Any]]:
    """Up to MAX_SUGGESTIONS page-aware questions for this operator.

    The caller's permissions decide what may be offered; the governed tools
    behind each suggestion re-authorize independently, so a hint is never a
    way around a check.
    """
    permissions = _permission_set(context.get("permissions"))
    page_context = page_context if isinstance(page_context, dict) else {}
    tokens = _module_tokens(context)
    token_set = set(tokens)

    suggestions: list[dict[str, Any]] = []
    seen: set[str] = set()

    entity_hint = _entity_nudge(page_context, permissions)
    if entity_hint is not None:
        suggestions.append(entity_hint)
        seen.add(entity_hint["id"])

    for nudge in NUDGES:
        if len(suggestions) >= MAX_SUGGESTIONS:
            break
        if nudge["id"] in seen:
            continue
        if not token_set & set(nudge["modules"]):
            continue
        needed = set(nudge["needs"])
        if needed and not (permissions & needed):
            continue
        suggestions.append(
            {
                "id": nudge["id"],
                "title": nudge["title"][:MAX_LABEL],
                "prompt": nudge["prompt"],
                "reason": nudge["reason"][:MAX_REASON],
            }
        )
        seen.add(nudge["id"])

    # A hint that exists because of an explicit filter is more useful than a
    # generic one, so it displaces the last entry rather than being dropped.
    filters = page_context.get("filters")
    if isinstance(filters, dict) and filters:
        label = next(
            (str(value) for value in filters.values() if str(value).strip()), ""
        )
        if label and permissions & {
            "analytics_catalogue_view",
            "academic_view",
            "attendance_view",
        }:
            hint = {
                "id": "filter.compare",
                "title": f"Break this down beyond {label[:40]}",
                "prompt": (
                    f"Break down the data I am viewing (filtered to {label[:40]}) by class and term"
                ),
                "reason": "You have a filter applied; this widens it one step for comparison.",
            }
            suggestions = [hint] + suggestions
            suggestions = suggestions[:MAX_SUGGESTIONS]

    return suggestions


def signature(
    context: dict[str, Any], page_context: dict[str, Any] | None = None
) -> str:
    """A stable id for "this page, as this operator is looking at it".

    The client uses it to offer a nudge at most once per page state, so a
    staff member is never nagged by the same hint on every scroll.
    """
    parts = [
        str(context.get("route") or ""),
        str(context.get("module") or ""),
    ]
    page_context = page_context if isinstance(page_context, dict) else {}
    entity = page_context.get("page_entity")
    if isinstance(entity, dict):
        parts.extend([str(entity.get("kind") or ""), str(entity.get("label") or "")])
    filters = page_context.get("filters")
    if isinstance(filters, dict):
        parts.extend(f"{key}={filters[key]}" for key in sorted(filters)[:10])
    return hashlib.sha256("|".join(parts).encode("utf-8")).hexdigest()[:16]
