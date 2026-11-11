"""Slash-command router for the staff coworker.

Two kinds of slash command exist, deliberately split so one registry owns each
concern and they cannot drift:

  * *style* commands (/recap, /table, /concise ...) narrow how an answer is
    written and live in ``guard`` because they never change what the assistant
    is allowed to do.
  * *action* commands (/web, /crawl, /draft, /report ...) select a governed
    tool and are defined here. Executing one is a tool call, so it still passes
    every permission gate in PHP; a command can never widen scope.

A command only ever preselects a tool the chosen agent already owns. If the
agent lacks it, the command degrades to an instruction and the normal governed
loop decides what to run.
"""

from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any

# Agents that own the full staff toolbox. Commands route here explicitly so
# /web or /crawl reaches an agent that is allowed to run them, regardless of
# which workspace the staff member happens to be on.
REPORTS_AGENT = "reports.agent"
FINANCE_AGENT = "finance.agent"
ACADEMICS_AGENT = "academics.agent"
# /draft must reach an agent that actually owns drafts.create, or the command
# silently degrades into an ordinary question and the staff member gets a
# report answer instead of the draft they asked for.
DRAFTING_AGENT = "communications.agent"

MAX_COMMAND_QUESTION = 500


@dataclass(frozen=True)
class ActionCommand:
    name: str
    description: str
    example: str
    tool: str = ""
    agent: str = ""
    question_hint: str = ""
    instruction: str = ""
    expects_url: bool = False
    expects_template: bool = False
    fixed_input: dict[str, Any] = field(default_factory=dict)
    # A governed tool the orchestrator runs itself once the command's tool has
    # answered. "/chart" asks for a picture, so the figure is drawn whether or
    # not the model remembers to ask for it: a command that depends on the
    # model's cooperation is a command that works half the time.
    follow_up: str = ""
    # Input that belongs to the follow-up rather than to the command's own tool:
    # "/pdf" passes no format to the report, it tells the producer which format
    # to render. Keeping it here means a command declares its whole action in
    # one place.
    follow_up_input: dict[str, Any] = field(default_factory=dict)


@dataclass(frozen=True)
class Invocation:
    name: str
    question: str
    tool: str
    tool_input: dict[str, Any]
    agent: str
    instruction: str
    help_requested: bool = False


# Canonical action commands. Aliases resolve to one of these names so the help
# catalogue and the parser can never disagree.
COMMANDS: dict[str, ActionCommand] = {
    "web": ActionCommand(
        name="web",
        description="Search the public web and answer from current sources",
        example="/web KICD 2026 CBC assessment guidance",
        tool="web.search",
        agent=REPORTS_AGENT,
        question_hint="the search query",
        instruction=(
            "Answer from the public web results supplied. Cite each source URL "
            "you rely on and say explicitly when the web does not settle it."
        ),
    ),
    "crawl": ActionCommand(
        name="crawl",
        description="Read several linked pages from one public site",
        example="/crawl https://kicd.ac.ke what changed in the curriculum",
        tool="web.crawl",
        agent=REPORTS_AGENT,
        question_hint="a URL followed by what to look for",
        instruction=(
            "Answer from the crawled pages supplied. Cite the page URLs you "
            "used and separate what the sources say from your own advice."
        ),
        expects_url=True,
    ),
    "report": ActionCommand(
        name="report",
        description="Answer from an authorized governed school report",
        example="/report fee arrears by class this term",
        tool="reports.nlq",
        agent=REPORTS_AGENT,
        question_hint="the data question",
    ),
    "data": ActionCommand(
        name="data",
        description="Compute a governed aggregation over authorized data",
        example="/data average attendance rate by class",
        tool="reports.aggregate",
        agent=REPORTS_AGENT,
        question_hint="the data question",
        fixed_input={"agg_type": "avg"},
    ),
    "brief": ActionCommand(
        name="brief",
        description="Fetch the deterministic daily/weekly/term intelligence briefing",
        example="/brief weekly",
        tool="reports.insight_brief",
        agent=REPORTS_AGENT,
        question_hint="daily, weekly or term",
    ),
    "chart": ActionCommand(
        name="chart",
        description="Build a chart from authorized data and draw it as an image",
        example="/chart attendance by class this week",
        tool="charts.generate",
        agent=REPORTS_AGENT,
        question_hint="what to chart",
        instruction=(
            "The figure below was drawn from the authorized rows. Explain the "
            "pattern it shows, name the highest and lowest values, and give one "
            "practical next step. Never ask for the figure again."
        ),
        follow_up="analytics.chart",
    ),
    "forecast": ActionCommand(
        name="forecast",
        description="Project the trend of one authorized series",
        example="/forecast enrolment for the next three terms",
        tool="reports.nlq",
        agent=REPORTS_AGENT,
        question_hint="the series to project",
        instruction=(
            "The trend projection below was computed from the authorized rows. "
            "State the direction and the projected values, and say plainly that "
            "this is a trend indication rather than a promise."
        ),
        follow_up="analytics.forecast",
    ),
    "draft": ActionCommand(
        name="draft",
        description="Draft a message, notice or announcement for staff review",
        example="/draft notice half-term closing arrangements",
        tool="drafts.create",
        agent=DRAFTING_AGENT,
        question_hint="the draft kind followed by the content",
        instruction=(
            "Prepare the draft for human review. Show the full text, then list "
            "what the staff member may want to adjust before saving."
        ),
        expects_template=True,
    ),
    "pdf": ActionCommand(
        name="pdf",
        description="Render the data you are viewing as a downloadable PDF report",
        example="/pdf fee arrears by class",
        tool="reports.nlq",
        agent=REPORTS_AGENT,
        question_hint="the report to turn into a document",
        follow_up="documents.produce",
        follow_up_input={"format": "pdf"},
        instruction=(
            "The PDF above was produced from the authorized rows and is ready "
            "to download or print. Say what it contains in one sentence, then "
            "stop."
        ),
    ),
    "docx": ActionCommand(
        name="docx",
        description="Produce the data you are viewing as a Word document",
        example="/docx attendance by class this week",
        tool="reports.nlq",
        agent=REPORTS_AGENT,
        question_hint="the report to turn into a document",
        follow_up="documents.produce",
        follow_up_input={"format": "docx"},
        instruction=(
            "The Word document above was produced from the authorized rows. "
            "Say what it contains in one sentence, then stop."
        ),
    ),
    "video": ActionCommand(
        name="video",
        description="Produce a short video summary of the data you are viewing",
        example="/video attendance by class this week",
        tool="reports.nlq",
        agent=REPORTS_AGENT,
        question_hint="the report to summarise visually",
        follow_up="documents.produce",
        follow_up_input={"format": "video"},
        instruction=(
            "The short video above shows the figures from the authorized "
            "report. Describe the motion in one sentence, then stop."
        ),
    ),
    "help": ActionCommand(
        name="help",
        description="List the commands and what each one does",
        example="/help",
    ),
}

ALIASES: dict[str, str] = {
    "search": "web",
    "browse": "web",
    "fetch": "web",
    "scrape": "crawl",
    "insight": "brief",
    "briefing": "brief",
    "graph": "chart",
    "plot": "chart",
    "predict": "forecast",
    "compose": "draft",
    "commands": "help",
    "?": "help",
}

# Draft templates accepted after /draft. Kept small and explicit: an unknown
# token is treated as part of the content, never passed through as a template.
DRAFT_TEMPLATES = ("notice", "announcement", "message", "email", "sms", "memo")


def resolve_name(token: str) -> str | None:
    name = str(token or "").strip().lstrip("/").lower()
    if name in COMMANDS:
        return name
    return ALIASES.get(name)


def parse(text: str) -> Invocation | None:
    """Return the invocation for an action command, or None.

    Style commands are left untouched so ``guard.extract_slash`` keeps owning
    them. Only the first token is considered, and the remainder is bounded.
    """
    if not isinstance(text, str):
        return None
    stripped = text.strip()
    if not stripped.startswith("/"):
        return None
    parts = stripped[1:].split(None, 1)
    if not parts:
        return None
    name = resolve_name(parts[0])
    if name is None:
        return None
    command = COMMANDS[name]
    remainder = (parts[1] if len(parts) > 1 else "").strip()
    remainder = remainder[:MAX_COMMAND_QUESTION]
    if remainder.startswith(":"):
        remainder = remainder[1:].strip()

    if name == "help":
        return Invocation(
            name=name,
            question="",
            tool="",
            tool_input={},
            agent="",
            instruction="",
            help_requested=True,
        )

    question = remainder
    tool_input: dict[str, Any] = dict(command.fixed_input)

    if command.expects_url:
        url, _, rest = remainder.partition(" ")
        tool_input["url"] = url[:500]
        tool_input.setdefault("depth", 1)
        tool_input.setdefault("max_pages", 4)
        question = rest.strip() or url
    elif command.expects_template:
        token, _, rest = remainder.partition(" ")
        template = token.lower().strip()
        if template in DRAFT_TEMPLATES:
            tool_input["template"] = template
            question = rest.strip()
        else:
            tool_input["template"] = "notice"
            question = remainder
        tool_input["context"] = question[:2000]
    elif name == "brief":
        cadence = (remainder.split() or ["daily"])[0].lower()
        tool_input["cadence"] = (
            cadence if cadence in ("daily", "weekly", "term") else "daily"
        )
        question = remainder or "the intelligence briefing"
    elif command.tool:
        if question:
            key = "query" if name == "web" else "question"
            tool_input[key] = question

    return Invocation(
        name=name,
        question=question or command.question_hint,
        tool=command.tool,
        tool_input=tool_input,
        agent=command.agent,
        instruction=command.instruction,
    )


def catalogue() -> list[dict[str, str]]:
    """Machine-readable command list for the help answer and the UI."""
    return [
        {
            "command": f"/{command.name}",
            "description": command.description,
            "example": command.example,
        }
        for command in COMMANDS.values()
    ]


def help_text(style_commands: list[dict[str, str]] | None = None) -> str:
    lines = ["**Commands**", ""]
    for command in COMMANDS.values():
        lines.append(f"- `{command.example}` — {command.description}")
    if style_commands:
        lines += ["", "**Answer styles** (combine with any question)", ""]
        for item in style_commands:
            lines.append(f"- `{item['command']}` — {item['description']}")
    return "\n".join(lines)
