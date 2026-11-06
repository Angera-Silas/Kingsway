"""Execute the due jobs from a Scheduler tick.

Everything runs inside ONE process against the process-wide cached MySQL
connection established by ``read_models._open_mysql_connection``. Projection
jobs refresh directly on that connection; there is no enqueue step, so a tick
needs exactly one connection no matter how many jobs it drains.
"""

from __future__ import annotations

import time
from typing import Any

from .read_models import (
    ReadModelError,
    ReadModelRefresher,
    PolarsReadModelRefresher,
    close_all_connections,
)
from .scheduler import DEFAULT_MAX_JOBS, DEFAULT_MAX_SECONDS, Scheduler


class SchedulerRunner:
    def __init__(self, config: Any, scheduler: Scheduler) -> None:
        self.config = config
        self.scheduler = scheduler
        self._refresher = ReadModelRefresher(config)
        self._polars = PolarsReadModelRefresher(config)

    # -------------------------------------------------------------- projection

    def _refresh(self, projection: str) -> dict[str, Any]:
        if not self.config.read_models_enabled:
            raise ReadModelError("Python read-model refresh is disabled")
        try:
            return self._refresher.refresh(projection)
        except Exception:  # noqa: BLE001 - fall back to the polars path
            return self._polars.refresh(projection)

    # -------------------------------------------------------------------- run

    def _missing_projections(self) -> list[str]:
        """Projections whose target is absent or was never published.

        The scheduler's last-run state lives in a JSON file that survives a
        schema wipe. After the reads namespace is rebuilt every entry still
        claims "ran recently", so an interval-based check alone leaves those
        projections unpublished for their full interval (or forever, when the
        interval is long). The target is therefore verified before the state
        file is trusted.

        An EMPTY target is deliberately NOT treated as missing. Many
        projections are legitimately empty in a given environment (no payroll
        runs, no bank sync, no AI insights yet); requeueing them every tick
        just burned the whole batch on busywork.

        Runs on the same cached connection the batch already opened.
        """
        try:
            connection = self._refresher._connect()
        except Exception:  # noqa: BLE001 - no connection, nothing to verify
            return []

        reads = self.config.db_reads_schema
        try:
            with connection.cursor() as cursor:
                cursor.execute(
                    "SELECT table_name FROM information_schema.TABLES "
                    "WHERE table_schema=%s AND table_name LIKE 'mmv_%%'",
                    (reads,),
                )
                present = {str(row[0]) for row in cursor.fetchall()}
                cursor.execute(
                    "SELECT projection FROM " + reads + ".reads_meta "
                    "WHERE refreshed_at IS NULL"
                )
                unpublished = {str(row[0]) for row in cursor.fetchall()}
                # A projection is also due when it is past its freshness budget:
                # that is exactly what ReadReplicaService's gate enforces, so a
                # stale row here means the read path is refusing it. Catching it
                # keeps the scheduler aligned with what readers actually need.
                cursor.execute(
                    "SELECT projection FROM " + reads + ".reads_meta "
                    "WHERE refreshed_at IS NOT NULL "
                    "AND TIMESTAMPDIFF(SECOND, refreshed_at, NOW()) > max_age_seconds"
                )
                stale = {str(row[0]) for row in cursor.fetchall()}
        except Exception:
            # A silent return here is how this check failed invisibly for two
            # whole deploys (a `LIKE 'mmv_%'` that pymysql read as a parameter
            # marker). Log it so a broken recovery is never invisible again.
            import logging

            logging.getLogger("kingsway.python_worker").exception(
                "scheduler recovery check failed"
            )
            return []

        return [
            job.name
            for job in self.scheduler._jobs
            if job.kind == "projection"
            and (
                f"mmv_{job.name}" not in present
                or job.name in unpublished
                or job.name in stale
            )
        ]

    def run_bounded(
        self,
        max_jobs: int = DEFAULT_MAX_JOBS,
        max_seconds: float = DEFAULT_MAX_SECONDS,
    ) -> dict[str, Any]:
        """Run at most ``max_jobs`` due jobs, or until ``max_seconds`` elapses.

        Bounded for CloudLinux LVE survival: the durable state file keeps
        un-run jobs queued for the next tick, so exiting early is safe.
        """
        max_jobs = max(1, min(500, int(max_jobs)))
        max_seconds = max(5.0, min(300.0, float(max_seconds)))
        deadline = time.monotonic() + max_seconds

        due = self.scheduler.due_jobs()

        # A rebuilt reads schema leaves every interval claiming "recently run".
        # Verify the targets directly and queue whatever is actually missing.
        due_by_name = {job.name: job for job in due}
        for name in self._missing_projections():
            if name not in due_by_name:
                job = next((j for j in self.scheduler._jobs if j.name == name), None)
                if job is not None:
                    due.append(job)
                    due_by_name[name] = job
        due.sort(key=lambda job: self.scheduler._last_run.get(job.name, 0.0))

        attempted = len(due)
        processed = succeeded = failed = 0
        failures: list[dict[str, str]] = []
        ran: list[str] = []

        for job in due:
            if processed >= max_jobs or time.monotonic() >= deadline:
                break

            started = time.monotonic()
            try:
                if job.kind == "projection":
                    self._refresh(job.name)
                else:
                    # Non-projection jobs are dispatched over HTTP by the
                    # platform client; they cost this process no DB connection.
                    self._dispatch(job)
                ran.append(job.name)
                succeeded += 1
            except Exception as error:  # noqa: BLE001 - one failure must not stop the batch
                failed += 1
                failures.append({"job": job.name, "error_class": type(error).__name__})
            finally:
                processed += 1

        # Reschedule everything attempted, including failures, so a broken job
        # cannot spin at the head of every tick.
        self.scheduler.mark_run(ran or [])

        result = {
            "due": attempted,
            "processed": processed,
            "succeeded": succeeded,
            "failed": failed,
            "skipped": max(0, attempted - processed),
            "failures": failures[:10],
            "duration_ms": int((time.monotonic() - (deadline - max_seconds)) * 1000),
        }

        # Release the cached connection: Passenger ends the process after the
        # request, and holding it open would strand one connection per tick
        # against max_user_connections.
        try:
            close_all_connections()
        except Exception:  # noqa: BLE001
            pass

        return result

    def _dispatch(self, job: Any) -> None:
        """HTTP-dispatch an operational job.

        Uses urllib from the standard library rather than `requests`: the
        scheduler must not fail a job because an optional HTTP dependency was
        not installed on the host.
        """
        import json
        import os
        import urllib.error
        import urllib.request

        base = os.environ.get("KINGSWAY_PHP_BASE_URL", "").rstrip("/")
        endpoint = job.endpoint
        if endpoint == "" or base == "":
            return

        headers = {"Content-Type": "application/json"}
        secret = os.environ.get("KINGSWAY_PHP_WORKER_SECRET")
        if secret:
            headers["X-Kingsway-Worker-Secret"] = secret

        timeout = max(1, min(60, int(os.environ.get("KINGSWAY_HTTP_TIMEOUT", "12"))))
        payload = json.dumps(job.payload or {}).encode("utf-8")
        request = urllib.request.Request(
            base + endpoint, data=payload, headers=headers, method="POST"
        )
        try:
            with urllib.request.urlopen(request, timeout=timeout) as response:  # noqa: S310
                response.read()
        except urllib.error.HTTPError as error:  # noqa: BLE001
            if error.code >= 500:
                raise
        except Exception:  # noqa: BLE001 - transport faults must not kill the tick
            pass
