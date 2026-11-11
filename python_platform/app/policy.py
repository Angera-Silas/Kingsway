"""Prompt field policies - the same allowlist/minimize contract as the
PHP AiPromptPolicy, enforced on every envelope before it reaches a model.

Context limits: 10,000 bytes per envelope; arrays capped at 20 items of
<=500 scalar characters; strings capped at 500 characters per field.
"""

from __future__ import annotations

import json
from typing import Any

FIELDS: dict[str, list[str]] = {
    "system.agent_triage": ["question", "audience", "agents", "route", "module"],
    # Field ORDER is deliberate: OpenAI-compatible providers cache by exact
    # token prefix, so everything stable for a given agent and route comes
    # first and the per-request question comes last. With the question first,
    # every request had a different prefix and could never reuse a cached
    # prompt; with this order the audience/agent/route/tools/hints head is
    # byte-identical across turns for the same operator context.
    "system.agent_chat": [
        "audience",
        "agent_id",
        "route",
        "module",
        "tools",
        "behavior_hints",
        # Constant guidance: answer from the governed results already supplied.
        "grounding_note",
        "tool_results",
        # Conversational continuity: bounded prior turns and the resolved
        # quick-command instruction. Both are capped by the caller and carry
        # no learner identity.
        "conversation",
        # Session-level continuity: stable topic labels, the operator's stated
        # preferences, and where they are in the app. All are short labels with
        # no learner identity.
        "session_topics",
        "preferences",
        "navigation",
        # What the operator is looking at right now: the record open in a modal,
        # the rows they selected, visible KPI labels and active filters. Short
        # labels only, so the assistant can offer page-relevant help without
        # receiving a second copy of the page's data.
        "page_context",
        "instruction",
        "question",
    ],
    "system.workspace_briefing": [
        "route",
        "module",
        "audience",
        "scan",
        "behavior_hints",
    ],
    # Academic planning (lesson plans, schemes of work, assessments, notes,
    # objectives). This entry was missing, so skills/academic_planning.py raised
    # PolicyError("workflow academics.planning has no approved prompt policy")
    # on every call and routes.py, which does not catch PolicyError, turned
    # that into a 500.
    #
    # school_context and web_ideas are JSON blobs rather than short labels, so
    # they need their own caps (see FIELD_CAPS below): the default 500-character
    # cap would truncate the KICD outcomes and the web results into invalid
    # JSON and hand the model a broken grounding payload - the same class of
    # silent evidence loss described in the MAX_CONTEXT_BYTES note above.
    "academics.planning": [
        "kind",
        "question",
        "audience",
        "school_context",
        "web_ideas",
        "instruction",
    ],
}

# Per-field character caps, for fields whose whole content is one longer blob.
# A field listed here is capped at its own value; everything else keeps
# MAX_SCALAR_CHARS. Truncating a JSON blob at an arbitrary character count
# produces invalid JSON, so these are bounded here AND compacted by the caller,
# and the cap is a backstop rather than the only limit.
FIELD_CAPS: dict[str, int] = {
    "school_context": 8000,
    "web_ideas": 8000,
    "tool_results": 8000,
    "grounding_note": 2000,
    "behavior_hints": 2000,
}

# Byte budget for a prompt and for one envelope payload.
#
# This number used to be 10,000, which is barely larger than the guardrail
# block plus the agent contract that every staff prompt must carry. The
# consequence was deterministic: the prompt-size trimmer in the orchestrator
# removed the governed tool_results on EVERY turn, so the model answered fee
# arrears, attendance and enrolment questions with no evidence in front of it -
# and a staff member was answered with a refusal for rows the server had
# already released to them. The budget has to leave room for the evidence,
# which is the only part of the prompt that is actually about this question.
#
# 24,000 bytes is still a hard bound (roughly 6,000 tokens of prompt), and it
# is small next to the models the provider chain serves, which carry contexts
# two orders of magnitude larger.
MAX_CONTEXT_BYTES = 24_000
# How many distinct fields a default (unlisted) workflow may carry. Explicit
# allowlists are unaffected; this only caps a fallback payload.
MAX_DEFAULT_FIELDS = 40

# Workflows that fell back to the bounded default policy, for journaling.
DEFAULT_POLICY_USE: list[str] = []
MAX_ARRAY_ITEMS = 20
MAX_SCALAR_CHARS = 500


class PolicyError(ValueError):
    pass


def _bounded_json(value: str, cap: int) -> str | None:
    """Fit a JSON-string field under cap without ever emitting invalid JSON.

    Slicing a JSON document at a character limit produces broken JSON, and a
    model handed a truncated blob is worse off than one handed a smaller one.
    Keys are dropped largest-first until the document fits. Returns None when
    the value is not a JSON object/array (an ordinary long string), so the
    caller falls back to the plain character slice.
    """
    try:
        parsed = json.loads(value)
    except (ValueError, TypeError):
        return None
    if not isinstance(parsed, (dict, list)):
        return None

    if isinstance(parsed, list):
        while parsed and len(json.dumps(parsed, ensure_ascii=False, separators=(",", ":"))) > cap:
            parsed = parsed[:-1]
        return json.dumps(parsed, ensure_ascii=False, separators=(",", ":")) or None

    for key in sorted(
        parsed, key=lambda k: len(json.dumps(parsed[k], ensure_ascii=False)), reverse=True
    ):
        if len(json.dumps(parsed, ensure_ascii=False, separators=(",", ":"))) <= cap:
            break
        del parsed[key]
    return json.dumps(parsed, ensure_ascii=False, separators=(",", ":")) or None


def _clean_scalar(
    value: Any, depth: int = 0, limit: int | None = None
) -> str | int | float | bool | None:
    if value is None:
        return None
    if isinstance(value, bool):
        return value
    if isinstance(value, (int, float)):
        return value
    if isinstance(value, str):
        cap = MAX_SCALAR_CHARS if limit is None else limit
        # A cap larger than the default only ever applies to fields
        # declared in FIELD_CAPS, and for those the slice must never
        # yield invalid JSON.
        if limit is not None and cap > MAX_SCALAR_CHARS and len(value) > cap:
            bounded = _bounded_json(value, cap)
            if bounded is not None:
                return bounded
        return value.strip()[:cap]
    if depth == 0 and isinstance(value, (list, tuple)):
        raise PolicyError("nested arrays are not allowed in prompt fields")
    raise PolicyError("unsupported value in prompt field")


def default_allowlist(payload: dict[str, Any]) -> list[str]:
    """A bounded allowlist for a workflow that has no explicit entry.

    Every key the caller supplied is permitted, because the payload for these
    workflows is built server-side from aggregates that PHP has already
    re-authorized, and narrowing it here without knowing the workflow would
    silently strip the evidence the model needs. What still applies is the
    whole bound: field count, per-field type, per-field cap and the envelope
    byte budget.

    This is deliberately a FALLBACK, not a policy of record. Every use is
    journaled (see DEFAULT_POLICY_USE) so an explicit FIELDS entry can be
    written for the workflow and this path retired.
    """
    return sorted(str(key) for key in payload.keys())[:MAX_DEFAULT_FIELDS]


def minimize(
    workflow: str,
    payload: dict[str, Any],
    allow_default: bool = False,
) -> dict[str, Any]:
    allowlist = FIELDS.get(workflow)
    if allowlist is None:
        if not allow_default:
            raise PolicyError(f"workflow {workflow} has no approved prompt policy")
        # Bounded fallback: keep the workflow running, keep every bound, and
        # leave a trace so the gap is visible rather than silent.
        allowlist = default_allowlist(payload)
        DEFAULT_POLICY_USE.append(workflow)
    clean: dict[str, Any] = {}
    for field in allowlist:
        if field not in payload:
            continue
        value = payload[field]
        cap = FIELD_CAPS.get(field)
        if isinstance(value, (list, tuple)):
            items: list[Any] = []
            for item in list(value)[:MAX_ARRAY_ITEMS]:
                if isinstance(item, (list, tuple, dict)):
                    raise PolicyError(f"field {field} contains unsupported nested data")
                items.append(_clean_scalar(item, depth=0, limit=cap))
            if items:
                clean[field] = items
        elif value is not None:
            clean[field] = _clean_scalar(value, limit=cap)
    if len(json.dumps(clean, ensure_ascii=False).encode("utf-8")) > MAX_CONTEXT_BYTES:
        raise PolicyError("prompt context exceeds the approved context limit")
    return clean
