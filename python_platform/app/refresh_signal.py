"""Tell open staff pages that fresh data is ready, invisibly.

When the ticker refreshes a read-model projection, pages showing that data
should reload it, without the user ever learning that a background process,
a replica or a projection exists.

So this module sends browsers exactly what an ordinary save already sends:
PHP's RealtimeMutationPublisher event (type ``DATA_CHANGED``, scope ``all``,
which reaches staff channels only; parents never inherit it), carrying a
public API *domain* and its page targets. No projection, schema, ticker or job
name ever leaves the server. In the browser, js/core/realtime_dispatch.js
handles it like any save: it invalidates the cached keys and APIRealtime
reloads only pages that use that domain, deferring while the tab is hidden or
the user is typing. No popup, toast or indicator is involved.

This also fixes the save race: PHP's own DATA_CHANGED fires when the write
commits, before the replica has caught up, so a page could reload old replica
data. This signal fires after the projection is actually fresh.

Delivery is fire-and-forget on a background thread with a bounded queue: a
slow or absent Node gateway never delays a projection refresh.
"""

from __future__ import annotations

import queue
import threading
from typing import Any, Callable, Iterable

# Mirror of api/services/RealtimeMutationPublisher.php DOMAIN_TARGETS, so the
# browser receives the same targets a real save in that domain produces.
DOMAIN_TARGETS: dict[str, list[str]] = {
    "academic": ["academic", "assessments", "dashboard_headteacher", "dashboard_academic"],
    "admission": ["admissions", "students", "dashboard_school_admin"],
    "attendance": ["attendance", "dashboard_headteacher", "dashboard_academic"],
    "boarding": ["boarding", "attendance", "dashboard_boarding"],
    "catering": ["catering", "inventory", "dashboard_catering"],
    "communications": ["communications", "notifications"],
    "finance": ["finance", "payments", "dashboard_finance"],
    "inventory": ["inventory", "dashboard_inventory"],
    "reports": ["reports"],
    "staff": ["staff", "dashboard_staff"],
    "students": ["students", "attendance", "dashboard_school_admin"],
    "system": ["system", "dashboard_system_admin"],
    "transport": ["transport", "dashboard_transport"],
    "activities": ["activities"],
    "library": ["library"],
    "chaplaincy": ["chaplaincy"],
    "counseling": ["counseling"],
}

# Projection first word -> public API domain. Unmapped projections send no
# signal; their pages still recover through the normal cache expiry.
_PREFIX_DOMAIN: dict[str, str] = {}
for _domain, _prefixes in {
    "students": "student students learner guardian parent parents person persons "
    "enrollment promotion portfolio leadership",
    "staff": "staff leave deputy duty job departments performance",
    "academic": "academic lesson assessment assessments scheme schemes exam strands "
    "strand sub timetable learning core competency curriculum grade grading class "
    "classes streams term calendar time national past school",
    "admission": "admission",
    "attendance": "attendance",
    "finance": "fee payment payments mpesa bank budget budgets cash collection expense "
    "expenses extra financial payroll payslip payslips chart",
    "inventory": "inventory uniform catalog equipment fixed purchase requisition "
    "requisitions suppliers",
    "transport": "transport routes vehicles",
    "boarding": "boarding dormitory dormitories rooms",
    "catering": "food meal",
    "communications": "communication internal announcements",
    "activities": "activity",
    "library": "library",
    "chaplaincy": "chapel chaplaincy",
    "counseling": "counseling",
    "reports": "analytics",
    "system": "blocked dashboard dashboards sidebar workflow",
}.items():
    for _prefix in _prefixes.split():
        _PREFIX_DOMAIN[_prefix] = _domain

# Projections that feed pages in more than one domain.
_EXTRA_DOMAINS: dict[str, tuple[str, ...]] = {
    "student_transport_summary": ("transport",),
    "fee_status_summary": ("students",),
}


def domains_for(projection: str) -> list[str]:
    """Public API domains whose pages show data from ``projection``."""
    name = str(projection or "").strip().lower()
    first = name.split("_", 1)[0]
    domains: list[str] = []
    if first in _PREFIX_DOMAIN:
        domains.append(_PREFIX_DOMAIN[first])
    for extra in _EXTRA_DOMAINS.get(name, ()):
        if extra not in domains:
            domains.append(extra)
    return domains


def events_for(projections: Iterable[str]) -> list[dict[str, Any]]:
    """One save-shaped DATA_CHANGED payload per affected domain."""
    domains: list[str] = []
    for projection in projections:
        for domain in domains_for(projection):
            if domain not in domains:
                domains.append(domain)
    return [
        {
            "domain": domain,
            "action": "updated",
            "targets": list(dict.fromkeys([domain, *DOMAIN_TARGETS.get(domain, [])])),
        }
        for domain in domains
    ]


class RefreshSignal:
    """Coalesce refreshed projections and publish save-shaped events.

    ``add()`` is called on the refresh path and only records the name. A
    daemon thread flushes every ``window_seconds`` so a burst of refreshes
    becomes one event per domain.
    """

    EVENT_TYPE = "DATA_CHANGED"
    SCOPE = "all"  # staff channels; parents never inherit (see module docstring)

    def __init__(
        self,
        publish: Callable[[str, str, dict], bool] | None,
        window_seconds: float = 0.25,
        max_pending: int = 2048,
    ) -> None:
        self._publish = publish
        self._window = max(0.05, float(window_seconds))
        self._pending: set[str] = set()
        self._lock = threading.Lock()
        self._outbox: "queue.Queue[dict[str, Any]]" = queue.Queue(maxsize=256)
        self._max_pending = max_pending
        self._stop = threading.Event()
        self.sent = 0
        self.dropped = 0
        self._thread: threading.Thread | None = None
        if publish is not None:
            self._thread = threading.Thread(target=self._run, name="refresh-signal", daemon=True)
            self._thread.start()

    @property
    def enabled(self) -> bool:
        return self._publish is not None

    def add(self, projection: str) -> None:
        if self._publish is None:
            return
        with self._lock:
            if len(self._pending) < self._max_pending:
                self._pending.add(str(projection))

    def flush(self) -> int:
        """Queue events for everything pending; returns events queued."""
        with self._lock:
            pending, self._pending = self._pending, set()
        queued = 0
        for event in events_for(sorted(pending)):
            try:
                self._outbox.put_nowait(event)
                queued += 1
            except queue.Full:
                self.dropped += 1
        return queued

    def _run(self) -> None:
        while not self._stop.is_set():
            self._stop.wait(self._window)
            self.flush()
            while True:
                try:
                    event = self._outbox.get_nowait()
                except queue.Empty:
                    break
                try:
                    if self._publish(self.EVENT_TYPE, self.SCOPE, event):
                        self.sent += 1
                    else:
                        self.dropped += 1
                except Exception:  # noqa: BLE001 - realtime never breaks refresh
                    self.dropped += 1

    def stop(self, timeout: float = 2.0) -> None:
        self._stop.set()
        if self._thread is not None:
            self._thread.join(timeout=timeout)


def build(config: Any) -> RefreshSignal:
    """Signal wired to the Node gateway, or a no-op when it isn't configured."""
    try:
        from .realtime import RealtimePublisher

        publisher = RealtimePublisher(config)
        if not publisher.configured():
            return RefreshSignal(None)
        return RefreshSignal(publisher.publish)
    except Exception:  # noqa: BLE001
        return RefreshSignal(None)

