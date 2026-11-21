"""Stable per-user assistant personality profile.

Advisory only. These are self-selected productivity preferences that shape how
an assistant writes for one operator - tone, verbosity, role focus and goals.
They NEVER grant data access, override the system/security rules, or change
which tools a user may call: authorization stays server-side and permission
scoped. The profile is stored as data inside the operator's ``assistant_prefs``
row (per-user isolated, DPA-erasable) and injected into the prompt envelope as
short, bounded lines alongside the existing behavior hints.

This complements :mod:`behavior`, which learns passively from interactions.
Here the user states explicitly how they want their coworker to work.
"""

from __future__ import annotations

from typing import Any

# Controlled vocabulary for the two enumerations, free text (bounded) for the
# rest. An out-of-vocabulary value is ignored rather than passed to a model.
ALLOWED_TONES = (
    "professional",
    "friendly",
    "concise",
    "encouraging",
    "formal",
    "plain",
)
ALLOWED_VERBOSITY = ("brief", "balanced", "detailed")
LIMITS: dict[str, int] = {
    "tone": 24,
    "verbosity": 16,
    "focus": 80,
    "goals": 160,
}


def clean_personality(value: Any) -> dict[str, Any]:
    """Validate and bound a profile dict. Only known keys survive.

    Stored as a nested ``personality`` object inside the user's prefs so it is
    erasable with the rest of their assistant data and cannot be confused with
    the existing flat preference keys.
    """
    if not isinstance(value, dict):
        return {}
    clean: dict[str, Any] = {}
    tone = str(value.get("tone") or "").strip().lower()
    if tone in ALLOWED_TONES:
        clean["tone"] = tone
    verbosity = str(value.get("verbosity") or "").strip().lower()
    if verbosity in ALLOWED_VERBOSITY:
        clean["verbosity"] = verbosity
    for key in ("focus", "goals"):
        text = str(value.get(key) or "").strip()[: LIMITS[key]]
        if text:
            clean[key] = text
    if "proactive" in value:
        clean["proactive"] = bool(value.get("proactive"))
    return clean


def personality_lines(profile: Any) -> list[str]:
    """Turn a stored profile into short, neutral lines for the prompt envelope.

    The lines are phrased as preferences, not instructions, and are bounded so
    a profile can never bloat the context or smuggle an override. They are still
    lower-trust content: the guard's non-negotiable rules outrank them.
    """
    clean = clean_personality(profile)
    lines: list[str] = []
    if clean.get("tone"):
        lines.append(f"Preferred tone: {clean['tone']}")
    if clean.get("verbosity"):
        lines.append(f"Preferred detail: {clean['verbosity']}")
    if clean.get("focus"):
        lines.append(f"Role focus: {clean['focus'][:80]}")
    if clean.get("goals"):
        lines.append(f"Working goals: {clean['goals'][:160]}")
    if clean.get("proactive") is False:
        lines.append("Proactive suggestions: only when asked")
    return lines
