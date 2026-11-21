"""Direct, read-only database access for the Python platform.

The platform owner approved expanding the Python engine to read the school
databases directly with its own configured credentials. This module is that
access layer. Read-only is the default and is enforced in three places:

  1. the MySQL session is opened with ``SET SESSION TRANSACTION READ ONLY``;
  2. only read statements (SELECT / SHOW / EXPLAIN / DESCRIBE / WITH ... SELECT)
     are compiled, anything else is refused before reaching MySQL;
  3. results are bounded by a row cap and scalar-only sanitisation.

Writes are impossible through this layer: ``readonly=False`` is not accepted.
The governed, permission-scoped named datasets (``db_datasets.py``) and the
user-id permission resolver both sit on top of this connection.
"""

from __future__ import annotations

import re
from decimal import Decimal
from typing import Any, Callable

MAX_QUERY_ROWS = 500
MAX_STRING_CHARS = 120

# A read statement must start with one of these verbs. WITH is allowed only when
# the statement ultimately contains a SELECT (checked below).
_READ_VERBS = ("SELECT", "SHOW", "EXPLAIN", "DESCRIBE", "DESC", "WITH")
_WRITE_TOKENS = re.compile(
    r"\b(INSERT|UPDATE|DELETE|REPLACE|DROP|ALTER|CREATE|TRUNCATE|GRANT|REVOKE|"
    r"LOAD|CALL|EXECUTE|SET\s+@|LOCK\s+TABLES|FLUSH)\b",
    re.IGNORECASE,
)


class ReadOnlyViolation(ValueError):
    """Raised when a statement would mutate data or is otherwise unsafe."""


def assert_read_only(sql: str) -> str:
    """Return the statement if it is a bounded, single read; else refuse."""
    text = str(sql or "").strip().rstrip(";").strip()
    if not text:
        raise ReadOnlyViolation("empty statement")
    first = text.split(None, 1)[0].upper()
    if first not in _READ_VERBS:
        raise ReadOnlyViolation("only read statements are permitted")
    if _WRITE_TOKENS.search(text):
        raise ReadOnlyViolation("statement contains a mutating keyword")
    if text.count(";") > 0:
        raise ReadOnlyViolation("multiple statements are not permitted")
    if first == "WITH" and not re.search(r"\bSELECT\b", text, re.IGNORECASE):
        raise ReadOnlyViolation("WITH statement must contain a SELECT")
    return text


def _ident(value: str) -> str:
    text = str(value or "")
    if not re.match(r"^[A-Za-z0-9_]+$", text):
        raise ValueError("unsafe schema identifier")
    return f"`{text}`"


class ReadOnlyDatabase:
    """A connection factory restricted to read-only SQL against school schemas.

    ``connect`` is injectable for hermetic tests. Production uses the platform's
    configured credentials. ``readonly`` is accepted only as True - passing
    False raises, so no caller can silently obtain a writable connection.
    """

    def __init__(
        self, config, connect: Callable | None = None, readonly: bool = True
    ) -> None:
        if readonly is not True:
            raise ReadOnlyViolation("the database access layer is read-only only")
        self.readonly = True
        self.config = config
        self._connect = connect

    def _open(self, schema: str | None = None):
        if self._connect is not None:
            connection = self._connect()
            self._enforce(connection)
            return connection
        import pymysql

        connection = pymysql.connect(
            host=self.config.db_host,
            port=self.config.db_port,
            user=self.config.db_user,
            password=self.config.db_password,
            database=schema,
            charset="utf8mb4",
            connect_timeout=self.config.db_connect_timeout,
            read_timeout=self.config.db_read_timeout,
            autocommit=True,
            ssl_ca=self.config.db_ssl_ca or None,
            ssl_verify_cert=bool(self.config.db_ssl_ca),
            ssl_verify_identity=bool(self.config.db_ssl_ca),
        )
        self._enforce(connection)
        return connection

    @staticmethod
    def _enforce(connection) -> None:
        try:
            with connection.cursor() as cursor:
                cursor.execute("SET SESSION TRANSACTION READ ONLY")
        except Exception:  # noqa: BLE001 - statement guard is the hard boundary
            pass

    def query(
        self, sql: str, params: tuple | None = None, schema: str | None = None
    ) -> dict[str, Any]:
        """Run one read statement and return bounded, scalar-only rows."""
        statement = assert_read_only(sql)
        connection = None
        try:
            connection = self._open(schema)
            with connection.cursor() as cursor:
                cursor.execute(statement, params or ())
                columns = [description[0] for description in (cursor.description or [])]
                fetched = cursor.fetchall()[:MAX_QUERY_ROWS]
            rows: list[dict[str, Any]] = []
            for raw in fetched:
                row: dict[str, Any] = {}
                for column, value in zip(columns, raw):
                    if isinstance(value, Decimal):
                        value = (
                            int(value)
                            if value == value.to_integral_value()
                            else float(value)
                        )
                    if value is not None and not isinstance(
                        value, (int, float, str, bool)
                    ):
                        continue
                    row[str(column)] = (
                        value[:MAX_STRING_CHARS] if isinstance(value, str) else value
                    )
                if row:
                    rows.append(row)
            return {
                "status": "ok",
                "columns": columns,
                "rows": rows,
                "row_count": len(rows),
                "readonly": True,
            }
        except ReadOnlyViolation:
            raise
        except Exception:  # noqa: BLE001 - a failed read must not fail a turn
            return {"status": "unavailable", "readonly": True}
        finally:
            if connection is not None and self._connect is not None:
                try:
                    connection.close()
                except Exception:  # noqa: BLE001
                    pass

    def dataframe(
        self, sql: str, params: tuple | None = None, schema: str | None = None
    ):
        """Return a Polars DataFrame from one read statement.

        Polars reads the already-authorized rows returned by ``query`` - it
        never opens a database connection of its own.
        """
        import polars as pl

        result = self.query(sql, params, schema)
        if result.get("status") != "ok" or not result.get("rows"):
            return pl.DataFrame()
        return pl.DataFrame(result["rows"])
