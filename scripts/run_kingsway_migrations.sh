#!/usr/bin/env bash
# Validate and optionally apply the committed Kingsway SQL migrations.
#
# Safe default: print the ordered migration plan without changing the database.
# Apply only after review:
#   MYSQL_PWD='...' APPLY=1 ./scripts/run_kingsway_migrations.sh
#
# Optional variables: MYSQL, DB, DBU, MIGRATIONS_DIR, PHP_VERIFY.
set -euo pipefail

MYSQL="${MYSQL:-/opt/lampp/bin/mysql}"
DB="${DB:-KingsWayAcademy}"
DBU="${DBU:-root}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MIGRATIONS_DIR="${MIGRATIONS_DIR:-$ROOT/database/migrations}"
APPLY="${APPLY:-0}"

if [[ ! -x "$MYSQL" ]]; then
    echo "ERROR: mysql client is not executable: $MYSQL" >&2
    exit 1
fi

if [[ ! -d "$MIGRATIONS_DIR" ]]; then
    echo "ERROR: migration directory does not exist: $MIGRATIONS_DIR" >&2
    exit 1
fi

mapfile -t FILES < <(
    find "$MIGRATIONS_DIR" -maxdepth 1 -type f -name '*.sql' -printf '%f\n' | LC_ALL=C sort
)

if [[ "${#FILES[@]}" -eq 0 ]]; then
    echo "ERROR: no committed SQL migrations found in $MIGRATIONS_DIR" >&2
    echo "Create an approved migration set before applying database changes." >&2
    exit 1
fi

if [[ -z "${MYSQL_PWD:-}" ]]; then
    echo "ERROR: MYSQL_PWD is required; do not place credentials in this script." >&2
    exit 1
fi

echo "=== Kingsway migration plan (DB=$DB) ==="
printf ' - %s\n' "${FILES[@]}"

if [[ "$APPLY" != "1" ]]; then
    echo "Validation only. Set APPLY=1 to execute this reviewed plan."
    exit 0
fi

for file in "${FILES[@]}"; do
    echo ">>> Applying $file"
    "$MYSQL" --batch --force=false -u "$DBU" -p"$MYSQL_PWD" "$DB" < "$MIGRATIONS_DIR/$file"
    echo "    OK"
done

echo "=== Migration execution complete ==="

PHP_VERIFY="${PHP_VERIFY:-/opt/lampp/bin/php}"
if [[ -x "$PHP_VERIFY" && -f "$ROOT/scripts/verify_rbac_ui_alignment.php" ]]; then
    "$PHP_VERIFY" "$ROOT/scripts/verify_rbac_ui_alignment.php"
fi
