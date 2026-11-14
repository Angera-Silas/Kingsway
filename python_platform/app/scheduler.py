"""Schedule-driven job dispatcher for the Kingsway read models.

Why this exists
---------------
The previous design put the schedule in the crontab: one curl line per
projection. That made MySQL connections proportional to the number of cron
lines, because every PHP request opens its own connection and a wave of
simultaneous lines needs one connection each. It exhausted
``max_user_connections`` (30 on the shared host) and could not scale.

This module inverts it. The schedule is a manifest file and there is no cron
at all: the ticker that Passenger auto-starts (scripts/ticker.py, launched by
app/ticker_launcher.py) ticks every TICK_INTERVAL_SECONDS (default 2 s), and
one long-lived process owns the connection and executes whatever is due:

    ticker (every 2 s) -> reload schedules.yaml if it changed
                       -> execute the due jobs
                       -> 1 cached MySQL connection

POST /internal/tick remains for manual, on-demand runs only.

Adding a job is adding a line to a YAML file; the running ticker picks it up
on its next tick (reload_if_changed). The connection count never changes:
94 jobs or 94,000 jobs both cost exactly one connection.

Durability is deliberately NOT via ``jobs_queue``: every projection refresh is
already idempotent, so a batch that dies mid-flight is simply re-run on the
next tick. That keeps the whole path on a single connection - one INSERT per
job into ``jobs_queue`` would need a second write path for no benefit.
"""

from __future__ import annotations

import threading
import time
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Iterable

try:  # PyYAML ships with the platform requirements
    import yaml
except ImportError as error:  # pragma: no cover - dependency is declared
    raise RuntimeError("PyYAML is required by the scheduler") from error

DEFAULT_MANIFEST = Path(__file__).resolve().parent.parent / "schedules.yaml"

# Mirrors the Python worker's CloudLinux LVE bounds.
DEFAULT_MAX_JOBS = 25
DEFAULT_MAX_SECONDS = 45.0


@dataclass
class ScheduledJob:
    name: str
    kind: str
    interval: int
    endpoint: str = ""
    payload: dict[str, Any] = field(default_factory=dict)
    # Env var names for this job's X-Kingsway-Worker-Secret, first set wins.
    # Mirrors the PHP endpoint's own fallback chain; empty = default secret.
    secret_env: tuple[str, ...] = ()


class Scheduler:
    """Evaluates a manifest against last-run state and runs what is due."""

    def __init__(
        self,
        config: Any,
        manifest_path: str | Path | None = None,
        state_path: str | Path | None = None,
        clock: Any = time.time,
    ) -> None:
        self.config = config
        self.manifest_path = Path(manifest_path or DEFAULT_MANIFEST)
        # Last-run state is a tiny JSON file next to the manifest. It is kept
        # out of MySQL on purpose: reading it must not cost a connection.
        self.state_path = Path(
            state_path or self.manifest_path.with_suffix(".state.json")
        )
        self._clock = clock
        self._jobs: list[ScheduledJob] = []
        self._last_run: dict[str, float] = {}
        self._lock = threading.Lock()
        self._load()

    # ---------------------------------------------------------------- loading

    def _manifest_signature(self) -> tuple[int, int] | None:
        try:
            stat = self.manifest_path.stat()
        except OSError:
            return None
        return (stat.st_mtime_ns, stat.st_size)

    def _load(self) -> None:
        self._manifest_sig = self._manifest_signature()
        self._jobs = self._parse_manifest()
        self._last_run = self._read_state()

    def reload_if_changed(self) -> dict[str, Any] | None:
        """Hot-reload schedules.yaml when it changed on disk.

        The long-lived ticker calls this every tick, so adding or retuning a
        job takes effect within one tick with no restart. Last-run state is
        kept, so unchanged jobs do not all fire at once. An invalid manifest
        never empties the schedule: the previous jobs stay in force and the
        error is returned for the caller to journal.

        Returns None when nothing changed, otherwise a summary dict.
        """
        signature = self._manifest_signature()
        if signature is None or signature == self._manifest_sig:
            return None
        self._manifest_sig = signature
        try:
            jobs = self._parse_manifest()
        except Exception as error:  # noqa: BLE001 - keep running on a bad edit
            return {"reloaded": False, "error": f"{type(error).__name__}: {error}"}
        before = {job.name for job in self._jobs}
        after = {job.name for job in jobs}
        self._jobs = jobs
        return {
            "reloaded": True,
            "jobs": len(jobs),
            "added": sorted(after - before),
            "removed": sorted(before - after),
        }

    def _parse_manifest(self) -> list[ScheduledJob]:
        if not self.manifest_path.is_file():
            raise RuntimeError(f"Schedule manifest not found: {self.manifest_path}")

        raw = yaml.safe_load(self.manifest_path.read_text(encoding="utf-8")) or {}
        entries = raw.get("jobs") or []
        if not isinstance(entries, list):
            raise RuntimeError("schedules.yaml: 'jobs' must be a list")

        jobs: list[ScheduledJob] = []
        seen: set[str] = set()
        for entry in entries:
            if not isinstance(entry, dict):
                continue
            name = str(entry.get("name") or "").strip()
            if name == "" or name in seen:
                continue
            interval = int(entry.get("interval") or 0)
            if interval <= 0:
                continue
            raw_secret = entry.get("secret_env") or ()
            if isinstance(raw_secret, str):
                raw_secret = (raw_secret,)
            secret_env = tuple(str(name).strip() for name in raw_secret if str(name).strip())
            jobs.append(
                ScheduledJob(
                    name=name,
                    kind=str(entry.get("kind") or "projection"),
                    interval=interval,
                    endpoint=str(entry.get("endpoint") or ""),
                    payload=dict(entry.get("payload") or {}),
                    secret_env=secret_env,
                )
            )
            seen.add(name)

        if not jobs:
            raise RuntimeError("schedules.yaml: no runnable jobs defined")

        return jobs

    def _read_state(self) -> dict[str, float]:
        try:
            import json

            data = json.loads(self.state_path.read_text(encoding="utf-8"))
            return {str(k): float(v) for k, v in (data or {}).items()}
        except Exception:  # noqa: BLE001 - missing/corrupt state means "never run"
            return {}

    def _write_state(self) -> None:
        try:
            import json

            self.state_path.write_text(json.dumps(self._last_run), encoding="utf-8")
        except Exception:  # noqa: BLE001 - state loss only costs one extra run
            pass

    # ------------------------------------------------------------- evaluation

    def jobs(self) -> list[ScheduledJob]:
        """Immutable snapshot of the manifest for the refresh engine."""
        return list(self._jobs)

    def interval_for(self, name: str) -> int:
        """Configured cadence in seconds, or 0 when the job is not in the manifest."""
        for job in self._jobs:
            if job.name == name:
                return int(job.interval)
        return 0

    def due_jobs(self, now: float | None = None) -> list[ScheduledJob]:
        """Jobs whose interval has elapsed since their last successful run.

        Overdue work accumulates: a job that is 10 intervals late runs once and
        is then rescheduled, so a long outage does not produce a burst.
        """
        now = now if now is not None else self._clock()
        due = [
            job
            for job in self._jobs
            if now - self._last_run.get(job.name, 0.0) >= job.interval
        ]
        due.sort(key=lambda job: self._last_run.get(job.name, 0.0))
        return due

    def mark_run(self, names: Iterable[str], now: float | None = None) -> None:
        now = now if now is not None else self._clock()
        with self._lock:
            for name in names:
                self._last_run[name] = now
            self._write_state()

    def summary(self) -> dict[str, Any]:
        return {
            "manifest": str(self.manifest_path),
            "jobs_total": len(self._jobs),
            "jobs_never_run": sum(
                1 for job in self._jobs if job.name not in self._last_run
            ),
            "jobs_due_now": len(self.due_jobs()),
        }
