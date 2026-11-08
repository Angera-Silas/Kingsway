"""Database trigger definitions for change-driven refresh.

These functions generate the SQL needed to install triggers on every base
table that is joined by at least one projection. Triggers are created
automatically by ``refresh_engine.ensure_triggers()`` in small batches.

Running this as a one-off setup is useful for new deployments.

    php scripts/setup_mv_triggers.php
"""

from __future__ import annotations

import sys
from pathlib import Path
from typing import Any

# Add parent to path for direct execution
sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from app.config import Config
from app.refresh_engine import RefreshEngine


def setup_triggers(config: Config | None = None, batch: int = 50) -> dict[str, Any]:
    """Install change-capture triggers across all projection source tables."""
    c = config or Config()
    engine = RefreshEngine(c)

    # Ensure the support tables exist first.
    engine.ensure_schema()

    result = {"created": 0, "errors": [], "disabled": False}

    if not c.read_change_triggers:
        result["disabled"] = True
        return result

    report = engine.ensure_triggers(budget=batch * 3)  # 3 events per table
    result["created"] = report.get("created", 0)
    result["status"] = report.get("status", "unknown")

    return result


if __name__ == "__main__":
    setup_triggers()
    print("Trigger setup complete.")
