"""Direct, read-only dataset access for the governed Polars tool.

The platform owner approved expanding the Python engine to query the database
directly with its own configured connection credentials. This module is the
whole boundary: the model may only choose a NAMED dataset from a fixed
allowlist. It can never supply SQL, a table name, rows or a filter.

Safety properties, mirroring PHP's ``PolarsDatasetService``:

  * every query is a fixed aggregate SELECT compiled from this allowlist;
  * the operator's effective permissions gate the dataset, exactly as they do
    at the PHP edge;
  * reads target the KingsWayReads projection when it is published, otherwise
    the master source view - never an arbitrary table;
  * only aggregate grains leave: scalar values, strings bounded, row cap;
  * a journal entry records every access for audit.

No learner, guardian or staff row can cross this boundary: the allowlisted
queries are GROUP BY aggregates, so an individual child's record never reaches
the model.
"""

from __future__ import annotations

import re
from decimal import Decimal
from typing import Any, Callable

# Named datasets. The keys are the only values a model may pass to data.query.
# ``permission``/``all_permission`` mirror the PHP service so the two layers
# make identical authorization decisions.
DATASETS: dict[str, dict[str, Any]] = {
    "attendance_by_day": {
        "permission": "attendance_view",
        "all_permission": "attendance_all_permissions",
        "projection": "student_attendance_summary",
        "view": "vw_student_attendance_summary",
        "target": "mmv_student_attendance_summary",
        "sql": (
            "SELECT date, status, COUNT(*) AS count FROM {ref} "
            "GROUP BY date, status ORDER BY date DESC LIMIT 200"
        ),
        "description": "Daily attendance status counts (present, absent, late) for the current scope.",
    },
    "attendance_by_class": {
        "permission": "attendance_view",
        "all_permission": "attendance_all_permissions",
        "projection": "student_attendance_summary",
        "view": "vw_student_attendance_summary",
        "target": "mmv_student_attendance_summary",
        "sql": (
            "SELECT class_name, status, COUNT(*) AS count FROM {ref} "
            "GROUP BY class_name, status ORDER BY class_name LIMIT 200"
        ),
        "description": "Attendance status counts per class for the current scope.",
    },
    "fee_balance_by_status": {
        "permission": "finance_fees_view",
        "all_permission": "finance_all_permissions",
        "projection": "student_fee_balances",
        "view": "vw_student_fee_balances",
        "target": "mmv_student_fee_balances",
        "sql": (
            "SELECT payment_status, COUNT(*) AS learners, SUM(balance) AS total_balance, "
            "AVG(days_overdue) AS avg_days_overdue FROM {ref} "
            "GROUP BY payment_status ORDER BY payment_status LIMIT 50"
        ),
        "description": "Fee balance totals and overdue days by payment status (aggregates only).",
    },
    "fleet_by_status": {
        "permission": "transport_view",
        "all_permission": "transport_all_permissions",
        "projection": "vehicles",
        "view": "vw_vehicles",
        "target": "mmv_vehicles",
        "sql": (
            "SELECT status, COUNT(*) AS vehicles, SUM(capacity) AS total_capacity, "
            "SUM(CASE WHEN service_due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_service, "
            "SUM(CASE WHEN insurance_expiry < CURDATE() THEN 1 ELSE 0 END) AS expired_insurance "
            "FROM {ref} GROUP BY status ORDER BY status LIMIT 50"
        ),
        "description": "Transport fleet counts, capacity and maintenance signals by status.",
    },
    "classes_by_year": {
        "permission": "academic_view",
        "all_permission": "academics_all_permissions",
        "projection": "academic_year_classes",
        "view": "vw_academic_year_classes",
        "target": "mmv_academic_year_classes",
        "sql": (
            "SELECT academic_year_id, COUNT(*) AS classes FROM {ref} "
            "GROUP BY academic_year_id ORDER BY academic_year_id DESC LIMIT 50"
        ),
        "description": "Number of classes per academic year.",
    },
}

MAX_RESULT_ROWS = 200
MAX_STRING_CHARS = 120
_IDENT = re.compile(r"^[A-Za-z0-9_]+$")


def _ident(value: str) -> str:
    """Quote one identifier from trusted config/allowlist, rejecting tricks."""
    text = str(value or "")
    if not _IDENT.match(text):
        raise ValueError("unsafe SQL identifier")
    return f"`{text}`"


def sanitise(rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    """Scalars only, strings bounded - a final guard across the boundary."""
    clean: list[dict[str, Any]] = []
    for row in rows:
        kept: dict[str, Any] = {}
        for column, value in row.items():
            if not isinstance(column, str):
                continue
            # MySQL SUM()/AVG() aggregates return Decimal, a scalar to the model
            # but not a builtin. Coerce so aggregate figures are not dropped.
            if isinstance(value, Decimal):
                value = (
                    int(value) if value == value.to_integral_value() else float(value)
                )
            if value is not None and not isinstance(value, (int, float, str, bool)):
                continue
            kept[column] = value[:MAX_STRING_CHARS] if isinstance(value, str) else value
        if kept:
            clean.append(kept)
    return clean[:MAX_RESULT_ROWS]


class DirectDatasetService:
    """Release allowlisted aggregate rows straight from the school database.

    ``connect`` is injectable so tests stay hermetic; production uses the
    platform's own configured credentials.
    """

    def __init__(self, config, connect: Callable | None = None, journal=None) -> None:
        self.config = config
        self._connect = connect
        self.journal = journal

    def datasets(self, permissions: list[str]) -> list[dict[str, str]]:
        return [
            {"name": name, "description": definition["description"]}
            for name, definition in DATASETS.items()
            if self._allowed(permissions, definition)
        ]

    @staticmethod
    def _allowed(permissions: list[str], definition: dict[str, Any]) -> bool:  # noqa: D102
        held = {str(p) for p in (permissions or [])}
        return (
            "*" in held
            or definition["permission"] in held
            or definition["all_permission"] in held
        )

    def _open(self):
        if self._connect is not None:
            return self._connect()
        try:
            import pymysql
        except ImportError as error:  # fail closed when the driver is absent
            raise RuntimeError("database driver is not installed") from error
        return pymysql.connect(
            host=self.config.db_host,
            port=self.config.db_port,
            user=self.config.db_user,
            password=self.config.db_password,
            charset="utf8mb4",
            connect_timeout=self.config.db_connect_timeout,
            read_timeout=self.config.db_read_timeout,
            autocommit=True,
            ssl_ca=self.config.db_ssl_ca or None,
            ssl_verify_cert=bool(self.config.db_ssl_ca),
            ssl_verify_identity=bool(self.config.db_ssl_ca),
        )

    def _resolve_ref(self, connection, definition: dict[str, Any]) -> str:
        """Prefer the published read-replica projection, else the master view."""
        reads = getattr(self.config, "db_reads_schema", "")
        master = getattr(self.config, "db_master_schema", "")
        if reads and getattr(self.config, "read_models_enabled", True):
            try:
                with connection.cursor() as cursor:
                    cursor.execute(
                        f"SELECT status FROM {_ident(reads)}.`reads_meta` "
                        "WHERE projection = %s LIMIT 1",
                        (definition["projection"],),
                    )
                    row = cursor.fetchone()
                    if row and str(row[0]) == "live":
                        return f"{_ident(reads)}.{_ident(definition['target'])}"
            except Exception:  # noqa: BLE001 - fall back to the master view
                pass
        return f"{_ident(master)}.{_ident(definition['view'])}"

    def rows(
        self, dataset: str, permissions: list[str], user_id: int = 0
    ) -> dict[str, Any]:
        """Release bounded aggregate rows for one allowlisted dataset."""
        name = str(dataset or "").strip().lower()[:60]
        available = [item["name"] for item in self.datasets(permissions)]
        definition = DATASETS.get(name)
        if definition is None:
            return {
                "status": "unknown_dataset",
                "message": "choose a dataset from those authorized for you",
                "available": available,
            }
        if not self._allowed(permissions, definition):
            return {
                "status": "not_authorized",
                "message": "this workspace does not grant access to that dataset",
                "available": available,
            }

        connection = None
        try:
            connection = self._open()
            ref = self._resolve_ref(connection, definition)
            sql = definition["sql"].replace("{ref}", ref)
            # Only the fixed aggregate SELECT ever runs. This is defence in depth:
            # the template comes from this module, never from the model.
            stripped = sql.lstrip().upper()
            if not stripped.startswith("SELECT") or ";" in sql:
                raise ValueError("refused non-SELECT dataset query")
            with connection.cursor() as cursor:
                cursor.execute(sql)
                columns = [description[0] for description in (cursor.description or [])]
                fetched = cursor.fetchall()[:MAX_RESULT_ROWS]
            rows = [dict(zip(columns, row)) for row in fetched]
            result = {
                "status": "ok",
                "dataset": name,
                "columns": columns,
                "rows": sanitise(rows),
                "row_count": len(rows),
                "granularity": "aggregate",
                "note": "Read directly from the permission-scoped school data; aggregate rows only, no individual records.",
            }
            if self.journal is not None:
                self.journal.write(
                    "ai_generation",
                    {
                        "type": "direct_dataset_query",
                        "operator_id": int(user_id or 0),
                        "dataset": name,
                        "row_count": len(rows),
                    },
                )
            return result
        except Exception:  # noqa: BLE001 - one dataset must not fail a turn
            return {
                "status": "unavailable",
                "message": "that dataset could not be read right now",
            }
        finally:
            if connection is not None and self._connect is not None:
                try:
                    connection.close()
                except Exception:  # noqa: BLE001
                    pass


class UserAccessResolver:
    """Resolve a staff member's effective roles and permissions by user id.

    Mirrors RBACMiddleware::resolvePermissionsFromTables exactly: permissions
    inherited from roles plus direct grants/overrides that have not expired.
    Using the user id (never model-supplied permission strings) means an agent
    cannot widen its own scope by naming a permission in a prompt.
    """

    PERMISSIONS_SQL = (
        "SELECT DISTINCT p.code FROM user_roles ur "
        "JOIN role_permissions rp ON rp.role_id = ur.role_id "
        "JOIN permissions p ON p.id = rp.permission_id WHERE ur.user_id = %s "
        "UNION DISTINCT "
        "SELECT DISTINCT p.code FROM user_permissions up "
        "JOIN permissions p ON p.id = up.permission_id "
        "WHERE up.user_id = %s AND up.permission_type IN ('grant','override') "
        "AND (up.expires_at IS NULL OR up.expires_at > NOW())"
    )
    ROLES_SQL = (
        "SELECT r.id, r.name FROM user_roles ur "
        "JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = %s ORDER BY r.id"
    )

    def __init__(self, config, connect: Callable | None = None) -> None:
        self.config = config
        self._connect = connect

    def _open(self):
        if self._connect is not None:
            return self._connect()
        import pymysql

        return pymysql.connect(
            host=self.config.db_host,
            port=self.config.db_port,
            user=self.config.db_user,
            password=self.config.db_password,
            charset="utf8mb4",
            connect_timeout=self.config.db_connect_timeout,
            read_timeout=self.config.db_read_timeout,
            autocommit=True,
            ssl_ca=self.config.db_ssl_ca or None,
            ssl_verify_cert=bool(self.config.db_ssl_ca),
            ssl_verify_identity=bool(self.config.db_ssl_ca),
        )

    def resolve(self, user_id: int) -> dict[str, Any]:
        """Return the operator's authoritative permissions and roles."""
        uid = int(user_id or 0)
        if uid <= 0:
            return {"status": "invalid_user"}
        connection = None
        try:
            master = _ident(self.config.db_master_schema)
            connection = self._open()
            with connection.cursor() as cursor:
                cursor.execute(
                    f"SELECT 1 FROM {master}.`users` WHERE id = %s LIMIT 1", (uid,)
                )
                if not cursor.fetchone():
                    return {"status": "unknown_user"}
            # The RBAC queries are unqualified and need a default schema. Scope
            # them explicitly to the master schema so a wrong default cannot
            # resolve to a different database.
            permissions_sql = self.PERMISSIONS_SQL
            roles_sql = self.ROLES_SQL
            for table in (
                "user_roles",
                "role_permissions",
                "permissions",
                "user_permissions",
                "roles",
            ):
                permissions_sql = re.sub(
                    rf"\b{table}\b", f"{master}.{table}", permissions_sql
                )
                roles_sql = re.sub(rf"\b{table}\b", f"{master}.{table}", roles_sql)
            with connection.cursor() as cursor:
                cursor.execute(permissions_sql, (uid, uid))
                codes = [str(row[0]) for row in cursor.fetchall() if row and row[0]]
            # Keep dotted aliases too, as RBACMiddleware::expandPermissionAliases does.
            effective = list(codes)
            for code in codes:
                if "_" in code:
                    alias = code.replace("_", ".")
                    if alias not in effective:
                        effective.append(alias)
            with connection.cursor() as cursor:
                cursor.execute(roles_sql, (uid,))
                roles = [
                    {"id": int(row[0]), "name": str(row[1])}
                    for row in cursor.fetchall()
                    if row and row[0] is not None
                ]
            return {
                "status": "ok",
                "user_id": uid,
                "permissions": effective,
                "roles": roles,
                "role_ids": [role["id"] for role in roles],
            }
        except Exception:  # noqa: BLE001 - authorization failure fails closed
            return {"status": "unavailable"}
        finally:
            if connection is not None and self._connect is not None:
                try:
                    connection.close()
                except Exception:  # noqa: BLE001
                    pass
