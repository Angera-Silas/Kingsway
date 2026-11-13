"""Run the non-projection jobs from schedules.yaml (replaces the old cron lines).

RefreshEngine.tick() only refreshes read-model projections. Everything else in
the manifest - communications outbox, attendance reminders, KCB
reconciliation, STK sweep, SMS contact sync, insight briefs, the Python queue
worker - is executed here, driven by the ticker on its own thread so a slow
PHP endpoint never delays the 2-second projection ticks.

Supported kinds
---------------
``http``          POST ``payload`` to the PHP endpoint with
                  X-Kingsway-Worker-Secret (via PhpQueueBridge: loopback base +
                  Host header first, public base as fallback; the response
                  must be ``success: true``). ``secret_env`` lists the env
                  var(s) holding that endpoint's own secret, first set wins,
                  mirroring the PHP constant's fallback chain.
``python_queue``  Drain a bounded batch of Python-owned queue jobs in-process
                  (what cron used to trigger via POST /internal/worker
                  {"mode": "queue"}). ``payload.limit`` caps the batch.

Every attempted job is rescheduled, success or failure, so a broken endpoint
cannot spin at the head of every tick. Failures are journalled.
"""

from __future__ import annotations

import time
from typing import Any, Callable

PROJECTION_KIND = "projection"


class OperationalJobRunner:
    def __init__(
        self,
        config: Any,
        scheduler: Any,
        journal: Any = None,
        bridge: Any = None,
        queue_worker_factory: Callable[[Any], Any] | None = None,
        clock: Callable[[], float] = time.monotonic,
        environ: Any = None,
    ) -> None:
        import os

        self._environ = os.environ if environ is None else environ
        self.config = config
        self.scheduler = scheduler
        self.journal = journal
        self._bridge = bridge
        self._queue_worker_factory = queue_worker_factory
        self._clock = clock

    # ----------------------------------------------------------- collaborators

    def _php(self) -> Any:
        if self._bridge is None:
            from .queue_worker import PhpQueueBridge

            self._bridge = PhpQueueBridge(self.config)
        return self._bridge

    def _queue_worker(self) -> Any:
        if self._queue_worker_factory is not None:
            return self._queue_worker_factory(self.config)
        from .queue_worker import PythonQueueWorker

        return PythonQueueWorker(self.config)

    def _log(self, entry: dict[str, Any]) -> None:
        if self.journal is None:
            return
        try:
            self.journal.write("ticker", entry)
        except Exception:  # noqa: BLE001 - logging must never stop jobs
            pass

    # --------------------------------------------------------------- execution

    def _secret_for(self, job: Any) -> str | None:
        """First set env var in the job's ``secret_env`` chain, else None
        (the bridge then uses the default worker secret)."""
        for name in getattr(job, "secret_env", ()) or ():
            value = self._environ.get(name, "").strip()
            if value:
                return value
        return None

    def _run(self, job: Any) -> dict[str, Any] | None:
        if job.kind == "http":
            if not job.endpoint:
                raise ValueError("http job has no endpoint")
            return self._php().post(
                job.endpoint, dict(job.payload or {}), secret=self._secret_for(job)
            )
        if job.kind == "python_queue":
            limit = int((job.payload or {}).get("limit") or 50)
            return self._queue_worker().run_batch(limit)
        raise ValueError(f"unknown job kind '{job.kind}'")

    def missing_configuration(self) -> list[dict[str, Any]]:
        """http jobs whose worker secret is not in this process's environment.

        On production, Python runs on its own subdomain and gets its env only
        from Passenger (SetEnv in that subdomain's .htaccess), never from the
        PHP config/.env. Every name listed here must be set there, with the
        same value as in PHP's config/.env, or the endpoint answers 403.
        """
        default = str(getattr(self.config, "php_worker_secret", "") or "").strip()
        missing: list[dict[str, Any]] = []
        for job in self.scheduler.jobs():
            if job.kind != "http":
                continue
            chain = tuple(getattr(job, "secret_env", ()) or ())
            if self._secret_for(job) is None and (chain or not default):
                missing.append(
                    {
                        "job": job.name,
                        "needs_env": list(chain)
                        or ["KINGSWAY_PHP_WORKER_SECRET or COMMUNICATION_WORKER_SECRET"],
                    }
                )
        return missing

    def run_due(self, max_seconds: float = 60.0) -> dict[str, Any]:
        """Run every due non-projection job once, within a time budget."""
        deadline = self._clock() + max(1.0, float(max_seconds))
        due = [job for job in self.scheduler.due_jobs() if job.kind != PROJECTION_KIND]
        succeeded: list[str] = []
        failed: list[dict[str, str]] = []
        for job in due:
            if self._clock() >= deadline:
                break
            started = self._clock()
            try:
                self._run(job)
                succeeded.append(job.name)
            except Exception as error:  # noqa: BLE001 - one job must not stop the rest
                message = f"{type(error).__name__}: {error}"[:300]
                cause = error.__cause__
                if cause is not None:
                    message = f"{message} ({type(cause).__name__}: {cause})"[:300]
                failed.append({"job": job.name, "error": message})
                self._log(
                    {
                        "event": "job_failed",
                        "job": job.name,
                        "kind": job.kind,
                        "error": message,
                        "duration_ms": int((self._clock() - started) * 1000),
                    }
                )
            finally:
                self.scheduler.mark_run([job.name])
        return {
            "due": len(due),
            "succeeded": len(succeeded),
            "failed": len(failed),
            "failures": failed[:10],
        }
