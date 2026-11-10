"""Python-owned, change-driven read-projection refresh engine.

Why this module exists
----------------------
PHP and Python both used to be able to rebuild the ``mmv_*`` materialized
tables. Two engines meant two schedules, two advisory-lock contestants and a
guaranteed drift whenever one of them was down. This module makes Python the
only writer of the reads namespace:

* the crontab pokes ``POST /internal/tick`` once a minute (or a supervised
  ``worker_process.py`` runs it continuously);
* every tick reads ``python_platform/schedules.yaml`` for cadence and
  ``KingsWayReads.reads_meta`` for what actually happened last;
* database triggers record which base tables changed, so projections are
  rebuilt within seconds of a write instead of waiting for a full sweep;
* when triggers are unavailable, ``information_schema.TABLES.UPDATE_TIME``
  provides a lower-fidelity fallback without changing application writes.

The request path never refreshes anything and never rejects a read because a
snapshot is old. It serves the materialized table when it exists and the
source view when Python has not published it yet, so users always see data.
"""

from __future__ import annotations

import hashlib
import logging
import re
import time
from datetime import datetime
from typing import Any, Iterable

from .journal import Journal
from .read_models import (
    PROJECTIONS,
    PolarsReadModelRefresher,
    ReadModelError,
    ReadModelRefresher,
    _open_mysql_connection,
)

LOGGER = logging.getLogger("kingsway.python_worker")

_IDENTIFIER = re.compile(r"[A-Za-z0-9_]+")
# MariaDB stores canonical view definitions with fully-qualified identifiers.
_VIEW_REFERENCE = re.compile(r"`([^`]+)`\.`([^`]+)`")

CHANGES_TABLE = "reads_source_changes"
DIRTY_TABLE = "reads_projection_dirty"
STATE_TABLE = "reads_change_state"
WATCH_TABLE = "reads_table_watch"

# Refreshers whose rebuild streams a large source view through Polars. They
# need a second connection because MySQL cannot stream an unbounded result and
# write on the same session at the same time.
_POLARS_PROJECTIONS = {
    "student_term_placement",
    "fee_status_summary",
    "person_directory",
}

_DEFAULT_POLL_SECONDS = 2.0
_TRIGGER_BATCH = 30
_DEPENDENCY_TTL_SECONDS = 300.0
_UPDATE_TIME_SCAN_SECONDS = 60.0


def _qid(value: str) -> str:
    if not _IDENTIFIER.fullmatch(value):
        raise ReadModelError("Unsafe database identifier")
    return f"`{value}`"


def _qualified(schema: str, table: str) -> str:
    return f"{_qid(schema)}.{_qid(table)}"


def _sql_literal(value: str) -> str:
    """Quote a value for the tiny, code-owned trigger definitions only."""
    return "'" + value.replace("\\", "\\\\").replace("'", "''") + "'"


class RefreshEngine:
    """Refresh the reads namespace from the primary schema, Python only."""

    def __init__(
        self,
        config: Any,
        scheduler: Any,
        journal: Journal | None = None,
        clock: Any = time.time,
        monotonic: Any = time.monotonic,
        sleep: Any = time.sleep,
    ) -> None:
        self.config = config
        self.scheduler = scheduler
        self.journal = journal or Journal(config.log_dir)
        self._clock = clock
        self._monotonic = monotonic
        self._sleep = sleep

        self._refresher = ReadModelRefresher(config)
        self._polars: PolarsReadModelRefresher | None = None

        self._dependency_map: dict[str, set[str]] | None = None
        self._table_to_projections: dict[str, set[str]] = {}
        self._dependency_loaded_at = 0.0
        self._schema_ready = False
        self._triggers_supported = True
        self._triggers_complete = False
        self._trigger_cursor = 0
        self._last_update_scan = 0.0
        self._last_ingest_error: str | None = None

    # ------------------------------------------------------------------ basics

    @property
    def master_schema(self) -> str:
        return str(self.config.db_master_schema)

    @property
    def reads_schema(self) -> str:
        return str(self.config.db_reads_schema)

    def _connect(self):
        return _open_mysql_connection(self.config, self.master_schema)

    def close(self) -> None:
        try:
            from .read_models import close_all_connections

            close_all_connections()
        except Exception:  # noqa: BLE001 - best effort
            pass

    def _journal(self, event: dict[str, Any], level: str = "info") -> None:
        try:
            self.journal.write("reads", event, level)
        except Exception:  # noqa: BLE001 - logging never breaks a refresh
            pass

    # -------------------------------------------------------- schema + triggers

    def ensure_schema(self) -> bool:
        """Create the change-feed tables. Idempotent and safe to re-run."""
        if self._schema_ready:
            return True
        statement = (
            f"CREATE TABLE IF NOT EXISTS {_qualified(self.reads_schema, CHANGES_TABLE)} ("
            "`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,"
            "`table_name` VARCHAR(64) NOT NULL,"
            "`changed_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),"
            "PRIMARY KEY (`id`),"
            "KEY `idx_changed_at` (`changed_at`),"
            "KEY `idx_table_changed` (`table_name`,`changed_at`)"
            ") ENGINE=InnoDB"
        )
        dirty = (
            f"CREATE TABLE IF NOT EXISTS {_qualified(self.reads_schema, DIRTY_TABLE)} ("
            "`projection` VARCHAR(64) NOT NULL,"
            "`change_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,"
            "`marked_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),"
            "PRIMARY KEY (`projection`),"
            "KEY `idx_marked_at` (`marked_at`)"
            ") ENGINE=InnoDB"
        )
        state = (
            f"CREATE TABLE IF NOT EXISTS {_qualified(self.reads_schema, STATE_TABLE)} ("
            "`id` TINYINT UNSIGNED NOT NULL DEFAULT 1,"
            "`last_change_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,"
            "`updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) "
            "ON UPDATE CURRENT_TIMESTAMP(6),"
            "PRIMARY KEY (`id`)"
            ") ENGINE=InnoDB"
        )
        watch = (
            f"CREATE TABLE IF NOT EXISTS {_qualified(self.reads_schema, WATCH_TABLE)} ("
            "`table_name` VARCHAR(64) NOT NULL,"
            "`update_time` DATETIME(6) NULL,"
            "PRIMARY KEY (`table_name`)"
            ") ENGINE=InnoDB"
        )
        try:
            connection = self._connect()
            with connection.cursor() as cursor:
                for sql in (statement, dirty, state, watch):
                    cursor.execute(sql)
                cursor.execute(
                    f"INSERT IGNORE INTO {_qualified(self.reads_schema, STATE_TABLE)} "
                    "(`id`,`last_change_id`) VALUES (1,0)"
                )
            self._schema_ready = True
            return True
        except Exception as error:  # noqa: BLE001 - the due sweep still works
            self._journal(
                {
                    "type": "read_refresh_schema_failed",
                    "error_class": type(error).__name__,
                },
                "warning",
            )
            return False

    def _existing_triggers(self, connection: Any) -> set[str]:
        with connection.cursor() as cursor:
            cursor.execute(
                "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS "
                "WHERE TRIGGER_SCHEMA=%s",
                (self.master_schema,),
            )
            return {str(row[0]) for row in cursor.fetchall()}

    @staticmethod
    def _trigger_name(table: str, event: str) -> str:
        digest = hashlib.sha1(table.encode("utf-8")).hexdigest()[:20]
        return f"trg_rf_{digest}_{event}"

    def ensure_triggers(self, budget: int = _TRIGGER_BATCH) -> dict[str, Any]:
        """Install change-capture triggers in small, idempotent batches.

        A deployment may run without TRIGGER privilege. In that case the
        engine falls back to ``information_schema.TABLES.UPDATE_TIME`` and the
        application write path is never affected.
        """

        if not bool(getattr(self.config, "read_change_triggers", True)):
            return {"status": "disabled", "created": 0}
        if not self._triggers_supported or self._triggers_complete:
            return {
                "status": "unsupported" if not self._triggers_supported else "complete",
                "created": 0,
            }
        if not self.ensure_schema():
            return {"status": "no_schema", "created": 0}

        tables = sorted(self.dependency_tables())
        if not tables:
            return {"status": "no_tables", "created": 0}

        try:
            connection = self._connect()
            existing = self._existing_triggers(connection)
        except Exception as error:  # noqa: BLE001
            self._journal(
                {
                    "type": "read_refresh_trigger_probe_failed",
                    "error_class": type(error).__name__,
                },
                "warning",
            )
            return {"status": "probe_failed", "created": 0}

        created = 0
        events = (("ai", "INSERT"), ("au", "UPDATE"), ("ad", "DELETE"))
        while self._trigger_cursor < len(tables) and created < budget:
            table = tables[self._trigger_cursor]
            self._trigger_cursor += 1
            for suffix, event in events:
                name = self._trigger_name(table, suffix)
                if name in existing:
                    continue
                sql = (
                    f"CREATE TRIGGER {_qid(name)} AFTER {event} ON "
                    f"{_qualified(self.master_schema, table)} FOR EACH ROW "
                    f"INSERT INTO {_qualified(self.reads_schema, CHANGES_TABLE)} "
                    "(`table_name`,`changed_at`) VALUES ("
                    f"{_sql_literal(table)}, NOW(6))"
                )
                try:
                    with connection.cursor() as cursor:
                        cursor.execute(sql)
                    existing.add(name)
                    created += 1
                except Exception as error:  # noqa: BLE001
                    message = str(error).lower()
                    if "already exists" in message or "duplicate" in message:
                        existing.add(name)
                        continue
                    # Missing TRIGGER privilege or binary-log restrictions are
                    # permanent for this process. Record once and use the
                    # UPDATE_TIME fallback instead of retrying every tick.
                    self._triggers_supported = False
                    self._journal(
                        {
                            "type": "read_refresh_triggers_unavailable",
                            "error_class": type(error).__name__,
                        },
                        "warning",
                    )
                    return {"status": "unsupported", "created": created}
            if self._trigger_cursor >= len(tables):
                self._triggers_complete = True
                break

        if created:
            self._journal(
                {"type": "read_refresh_triggers_installed", "created": created}
            )
        return {
            "status": "complete" if self._triggers_complete else "installing",
            "created": created,
        }

    # ----------------------------------------------------------- dependency map

    def dependency_tables(self) -> set[str]:
        """Every base table any registered projection ultimately reads."""
        self._load_dependencies()
        return set(self._table_to_projections)

    def _load_dependencies(self) -> None:
        now = self._monotonic()
        if (
            self._dependency_map is not None
            and now - self._dependency_loaded_at < _DEPENDENCY_TTL_SECONDS
        ):
            return
        try:
            connection = self._connect()
            with connection.cursor() as cursor:
                cursor.execute(
                    "SELECT TABLE_NAME, VIEW_DEFINITION FROM information_schema.VIEWS "
                    "WHERE TABLE_SCHEMA=%s",
                    (self.master_schema,),
                )
                definitions = {
                    str(row[0]): str(row[1] or "") for row in cursor.fetchall()
                }
        except Exception as error:  # noqa: BLE001 - keep the last known map
            LOGGER.warning("read projection dependency introspection failed: %s", error)
            if self._dependency_map is None:
                self._dependency_map = {}
                self._table_to_projections = {}
            return

        resolved: dict[str, set[str]] = {}

        def resolve(name: str, stack: tuple[str, ...] = ()) -> set[str]:
            if name in resolved:
                return resolved[name]
            definition = definitions.get(name)
            if definition is None:
                # Already a base table.
                return {name}
            if name in stack:
                return set()
            bases: set[str] = set()
            for schema, referenced in _VIEW_REFERENCE.findall(definition):
                if schema != self.master_schema:
                    continue
                if referenced in definitions:
                    bases |= resolve(referenced, stack + (name,))
                else:
                    bases.add(referenced)
            resolved[name] = bases
            return bases

        mapping: dict[str, set[str]] = {}
        for projection, definition in PROJECTIONS.items():
            source = str(definition.get("source") or "")
            if not source:
                continue
            bases = resolve(source)
            for base in bases:
                if not _IDENTIFIER.fullmatch(base):
                    continue
                mapping.setdefault(base, set()).add(projection)

        self._dependency_map = {
            projection: {base for base, rows in mapping.items() if projection in rows}
            for projection in PROJECTIONS
        }
        self._table_to_projections = mapping
        self._dependency_loaded_at = now

    def projections_for_tables(self, tables: Iterable[str]) -> set[str]:
        if self._dependency_map is None:
            self._load_dependencies()
        dirty: set[str] = set()
        for table in tables:
            dirty |= self._table_to_projections.get(str(table), set())
        return dirty

    # -------------------------------------------------------------- change feed

    def _last_change_id(self, connection: Any) -> int:
        with connection.cursor() as cursor:
            cursor.execute(
                f"SELECT last_change_id FROM {_qualified(self.reads_schema, STATE_TABLE)} "
                "WHERE id=1"
            )
            row = cursor.fetchone()
            return int(row[0]) if row else 0

    def _advance_change_id(self, connection: Any, value: int) -> None:
        with connection.cursor() as cursor:
            cursor.execute(
                f"INSERT INTO {_qualified(self.reads_schema, STATE_TABLE)} "
                "(`id`,`last_change_id`) VALUES (1,%s) "
                "ON DUPLICATE KEY UPDATE `last_change_id`=GREATEST(`last_change_id`,VALUES(`last_change_id`))",
                (value,),
            )

    def _mark_dirty(
        self, connection: Any, projections: Iterable[str], change_id: int
    ) -> None:
        rows = [(projection, change_id) for projection in sorted(set(projections))]
        if not rows:
            return
        with connection.cursor() as cursor:
            cursor.executemany(
                f"INSERT INTO {_qualified(self.reads_schema, DIRTY_TABLE)} "
                "(`projection`,`change_id`,`marked_at`) VALUES (%s,%s,NOW(6)) "
                "ON DUPLICATE KEY UPDATE `marked_at`=VALUES(`marked_at`),"
                "`change_id`=GREATEST(`change_id`,VALUES(`change_id`))",
                rows,
            )

    def ingest_changes(self, limit: int = 5000) -> dict[str, Any]:
        """Move base-table changes into the durable dirty-projection set."""
        if not self.ensure_schema():
            return {"ingested": 0, "dirty": 0}
        try:
            connection = self._connect()
        except Exception as error:  # noqa: BLE001
            self._journal(
                {
                    "type": "read_refresh_ingest_connect_failed",
                    "error_class": type(error).__name__,
                },
                "warning",
            )
            return {"ingested": 0, "dirty": 0}

        ingested = 0
        dirty: set[str] = set()
        try:
            last_id = self._last_change_id(connection)
            with connection.cursor() as cursor:
                cursor.execute(
                    f"SELECT `id`,`table_name` FROM "
                    f"{_qualified(self.reads_schema, CHANGES_TABLE)} "
                    "WHERE `id` > %s ORDER BY `id` ASC LIMIT %s",
                    (last_id, max(1, min(20000, int(limit)))),
                )
                rows = cursor.fetchall()
            if rows:
                max_id = max(int(row[0]) for row in rows)
                dirty = self.projections_for_tables(str(row[1]) for row in rows)
                self._mark_dirty(connection, dirty, max_id)
                self._advance_change_id(connection, max_id)
                # The log has been durably converted into the dirty set.
                with connection.cursor() as cursor:
                    cursor.execute(
                        f"DELETE FROM {_qualified(self.reads_schema, CHANGES_TABLE)} "
                        "WHERE `id` <= %s",
                        (max_id,),
                    )
                ingested = len(rows)
            self._last_ingest_error = None
        except Exception as error:  # noqa: BLE001 - due sweep remains the safety net
            self._last_ingest_error = type(error).__name__
            self._journal(
                {
                    "type": "read_refresh_ingest_failed",
                    "error_class": type(error).__name__,
                },
                "warning",
            )

        # When triggers are not installed, discover writes through the storage
        # engine's table update timestamps. It is coarser than a trigger but
        # needs no schema privileges and no application changes.
        if not self._triggers_complete:
            dirty |= self._scan_update_times(connection)

        return {"ingested": ingested, "dirty": len(dirty)}

    def _scan_update_times(self, connection: Any) -> set[str]:
        now = self._monotonic()
        if now - self._last_update_scan < _UPDATE_TIME_SCAN_SECONDS:
            return set()
        self._last_update_scan = now
        tables = self.dependency_tables()
        if not tables:
            return set()
        try:
            with connection.cursor() as cursor:
                placeholders = ",".join(["%s"] * len(tables))
                cursor.execute(
                    "SELECT `TABLE_NAME`,`UPDATE_TIME` FROM information_schema.TABLES "
                    f"WHERE `TABLE_SCHEMA`=%s AND `TABLE_TYPE`='BASE TABLE' "
                    f"AND `TABLE_NAME` IN ({placeholders})",
                    (self.master_schema, *sorted(tables)),
                )
                observed = {str(row[0]): row[1] for row in cursor.fetchall() if row[0]}
                cursor.execute(
                    f"SELECT `table_name`,`update_time` FROM "
                    f"{_qualified(self.reads_schema, WATCH_TABLE)}"
                )
                baseline = {str(row[0]): row[1] for row in cursor.fetchall() if row[0]}
        except Exception:  # noqa: BLE001 - trigger feed may still be working
            return set()

        if not baseline:
            # First run: record the current state without enqueueing a storm of
            # refreshes. Missing targets are handled by the due sweep.
            rows = [(table, observed.get(table)) for table in sorted(observed)]
            try:
                with connection.cursor() as cursor:
                    cursor.executemany(
                        f"INSERT INTO {_qualified(self.reads_schema, WATCH_TABLE)} "
                        "(`table_name`,`update_time`) VALUES (%s,%s) "
                        "ON DUPLICATE KEY UPDATE `update_time`=VALUES(`update_time`)",
                        rows,
                    )
            except Exception:  # noqa: BLE001
                pass
            return set()

        changed: list[str] = []
        updates: list[tuple[Any, str]] = []
        for table, current in observed.items():
            previous = baseline.get(table)
            if current is None:
                continue
            if previous is None or self._as_timestamp(current) > self._as_timestamp(
                previous
            ):
                changed.append(table)
                updates.append((current, table))
        if changed:
            try:
                with connection.cursor() as cursor:
                    cursor.executemany(
                        f"INSERT INTO {_qualified(self.reads_schema, CHANGES_TABLE)} "
                        "(`table_name`,`changed_at`) VALUES (%s,NOW(6))",
                        [(table,) for table in changed],
                    )
                    cursor.executemany(
                        f"UPDATE {_qualified(self.reads_schema, WATCH_TABLE)} "
                        "SET `update_time`=%s WHERE `table_name`=%s",
                        updates,
                    )
            except Exception:  # noqa: BLE001
                return set()
        return self.projections_for_tables(changed)

    @staticmethod
    def _as_timestamp(value: Any) -> float:
        if isinstance(value, datetime):
            return value.timestamp()
        try:
            return float(value)
        except (TypeError, ValueError):
            return 0.0

    # ------------------------------------------------------------------ schedule

    def _interval_for(self, projection: str) -> int:
        try:
            interval = int(self.scheduler.interval_for(projection) or 0)
        except AttributeError:
            interval = 0
        if interval > 0:
            return interval
        definition = PROJECTIONS.get(projection) or {}
        return max(30, int(definition.get("max_age_seconds") or 300))

    def due_projections(self) -> list[str]:
        """Projections whose target is missing or older than its YAML interval."""
        try:
            connection = self._connect()
        except Exception:  # noqa: BLE001
            return []

        try:
            with connection.cursor() as cursor:
                cursor.execute(
                    "SELECT TABLE_NAME FROM information_schema.TABLES "
                    "WHERE TABLE_SCHEMA=%s AND TABLE_TYPE='BASE TABLE'",
                    (self.reads_schema,),
                )
                targets = {str(row[0]) for row in cursor.fetchall()}
                cursor.execute(
                    f"SELECT projection, refreshed_at FROM "
                    f"{_qualified(self.reads_schema, 'reads_meta')}"
                )
                meta = {str(row[0]): row[1] for row in cursor.fetchall() if row[0]}
        except Exception:  # noqa: BLE001 - missing reads_meta means "all due"
            meta = {}
            targets = set()

        now = self._clock()
        due: list[tuple[float, str]] = []
        for projection, definition in PROJECTIONS.items():
            target = str(definition.get("target") or f"mmv_{projection}")
            if target not in targets:
                due.append((0.0, projection))
                continue
            refreshed = meta.get(projection)
            if refreshed is None:
                due.append((0.0, projection))
                continue
            age = now - self._as_timestamp(refreshed)
            if age >= self._interval_for(projection):
                due.append((age, projection))
        due.sort(key=lambda item: (-item[0], item[1]))
        return [projection for _age, projection in due]

    # ------------------------------------------------------------------- writes

    def _dirty_rows(self, connection: Any) -> list[tuple[str, int]]:
        try:
            with connection.cursor() as cursor:
                cursor.execute(
                    f"SELECT projection, change_id FROM "
                    f"{_qualified(self.reads_schema, DIRTY_TABLE)} "
                    "ORDER BY marked_at ASC"
                )
                return [(str(row[0]), int(row[1] or 0)) for row in cursor.fetchall()]
        except Exception:  # noqa: BLE001
            return []

    def pending(self, limit: int = 25) -> list[tuple[str, int]]:
        """Dirty projections first, then missing/overdue safety sweeps."""
        connection = self._connect()
        dirty = self._dirty_rows(connection)
        ordered: list[tuple[str, int]] = []
        seen: set[str] = set()

        def priority(name: str) -> tuple[int, str]:
            definition = PROJECTIONS.get(name) or {}
            return (int(definition.get("max_age_seconds") or 600), name)

        for name, change_id in sorted(dirty, key=lambda row: priority(row[0])):
            if name in PROJECTIONS and name not in seen:
                ordered.append((name, change_id))
                seen.add(name)

        for name in self.due_projections():
            if name not in seen:
                ordered.append((name, 0))
                seen.add(name)

        return ordered[: max(1, int(limit))]

    def clear_dirty(self, projection: str, change_id: int) -> None:
        """Clear a dirty marker only when no newer change arrived meanwhile."""
        try:
            connection = self._connect()
            with connection.cursor() as cursor:
                cursor.execute(
                    f"DELETE FROM {_qualified(self.reads_schema, DIRTY_TABLE)} "
                    "WHERE projection=%s AND change_id=%s",
                    (projection, int(change_id)),
                )
        except Exception:  # noqa: BLE001 - a stale marker only costs one refresh
            pass

    def refresh(self, projection: str) -> dict[str, Any]:
        if projection in _POLARS_PROJECTIONS:
            if self._polars is None:
                self._polars = PolarsReadModelRefresher(self.config)
            return self._polars.refresh(projection)
        return self._refresher.refresh(projection)

    # --------------------------------------------------------------------- tick

    def tick(
        self,
        max_jobs: int = 50,
        max_seconds: float = 40.0,
        poll_seconds: float = _DEFAULT_POLL_SECONDS,
    ) -> dict[str, Any]:
        """Run a bounded, change-aware refresh batch.

        The loop keeps polling for new changes until the time budget expires,
        so a single one-minute cron tick provides near-continuous coverage:
        a write is typically reflected in its views within a few seconds.
        """
        started = self._monotonic()
        deadline = started + max(1.0, min(300.0, float(max_seconds)))
        poll_seconds = max(0.5, min(15.0, float(poll_seconds)))
        budget_jobs = max(1, min(1000, int(max_jobs)))

        processed = succeeded = failed = 0
        failures: list[dict[str, str]] = []
        ran: list[str] = []
        seen: set[str] = set()
        trigger_report: dict[str, Any] = {"status": "pending", "created": 0}
        ingested = 0

        while processed < budget_jobs and self._monotonic() < deadline:
            trigger_report = self.ensure_triggers()
            if ingested == 0:
                report = self.ingest_changes()
                ingested += int(report.get("ingested") or 0)

            try:
                batch = self.pending(limit=min(10, budget_jobs - processed))
            except Exception as error:  # noqa: BLE001
                failures.append(
                    {"job": "read-models", "error_class": type(error).__name__}
                )
                break

            batch = [row for row in batch if row[0] not in seen]
            if not batch:
                remaining = deadline - self._monotonic()
                if remaining <= poll_seconds:
                    break
                self._sleep(min(poll_seconds, remaining))
                report = self.ingest_changes()
                ingested += int(report.get("ingested") or 0)
                continue

            for projection, change_id in batch:
                if processed >= budget_jobs or self._monotonic() >= deadline:
                    break
                seen.add(projection)
                processed += 1
                job_started = self._monotonic()
                try:
                    result = self.refresh(projection)
                    if change_id > 0:
                        self.clear_dirty(projection, change_id)
                    succeeded += 1
                    ran.append(projection)
                    self._journal(
                        {
                            "type": "read_projection_refreshed",
                            "projection": projection,
                            "rows_count": int(result.get("rows_count") or 0),
                            "duration_ms": int(
                                (self._monotonic() - job_started) * 1000
                            ),
                            "trigger": "change" if change_id > 0 else "schedule",
                        }
                    )
                except Exception as error:  # noqa: BLE001 - one failure must not stop the batch
                    failed += 1
                    failures.append(
                        {
                            "job": projection,
                            "error_class": type(error).__name__,
                            # The message is what makes a failure fixable. Recording
                            # only the class name turned every projection failure
                            # into an undiagnosable "ReadModelError".
                            "error": str(error)[:300],
                        }
                    )
                    self._journal(
                        {
                            "type": "read_projection_refresh_failed",
                            "projection": projection,
                            "error_class": type(error).__name__,
                            "error": str(error)[:300],
                        },
                        "warning",
                    )

        outcome = {
            "due": processed + max(0, len(self.pending(limit=1))),
            "processed": processed,
            "succeeded": succeeded,
            "failed": failed,
            "skipped": 0,
            "failures": failures[:10],
            "refreshed": ran[:50],
            "change_ingested": ingested,
            "triggers": trigger_report,
            "duration_ms": int((self._monotonic() - started) * 1000),
        }
        self._journal({"type": "scheduler_tick_finished", **outcome})
        return outcome

    # ------------------------------------------------------------------- status

    def status(self) -> dict[str, Any]:
        """Small operational snapshot; never returns row contents."""
        connection = self._connect()
        dirty = 0
        changes = 0
        published = 0
        try:
            with connection.cursor() as cursor:
                cursor.execute(
                    f"SELECT COUNT(*) FROM {_qualified(self.reads_schema, DIRTY_TABLE)}"
                )
                dirty = int(cursor.fetchone()[0])
                cursor.execute(
                    f"SELECT COUNT(*) FROM {_qualified(self.reads_schema, CHANGES_TABLE)}"
                )
                changes = int(cursor.fetchone()[0])
                cursor.execute(
                    f"SELECT COUNT(*) FROM {_qualified(self.reads_schema, 'reads_meta')} "
                    "WHERE refreshed_at IS NOT NULL"
                )
                published = int(cursor.fetchone()[0])
        except Exception:  # noqa: BLE001
            pass
        return {
            "projections": len(PROJECTIONS),
            "published": published,
            "dirty": dirty,
            "pending_changes": changes,
            "triggers": "complete" if self._triggers_complete else "installing",
            "last_ingest_error": self._last_ingest_error,
        }
