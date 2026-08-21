#!/usr/bin/env bash
# Apply ONLY the explicitly named Kingsway migrations.
#
# This runner never scans-and-applies the whole migrations directory. You must
# name the exact file(s) to execute, e.g.:
#
#   MYSQL_PWD='...' APPLY=1 ./scripts/run_kingsway_migrations.sh 042_relocate_functional_state_and_drop_log_tables.sql
#
# Files already recorded in the `migrations` ledger are skipped (unless
# FORCE=1). Successful applies are recorded in the ledger so later runs do not
# re-execute them. With no file arguments the script only prints the ledger
# status and exits (validation only).
#
# Optional variables: MYSQL, DB, DBU, MIGRATIONS_DIR, FORCE, PHP_VERIFY.
set -euo pipefail

MYSQL="${MYSQL:-/opt/lampp/bin/mysql}"
DB="${DB:-KingsWayAcademy}"
DBU="${DBU:-root}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MIGRATIONS_DIR="${MIGRATIONS_DIR:-$ROOT/database/migrations}"
APPLY="${APPLY:-0}"
FORCE="${FORCE:-0}"

if [[ ! -x "$MYSQL" ]]; then
    echo "ERROR: mysql client is not executable: $MYSQL" >&2
    exit 1
fi

if [[ ! -d "$MIGRATIONS_DIR" ]]; then
    echo "ERROR: migration directory does not exist: $MIGRATIONS_DIR" >&2
    exit 1
fi

if [[ -z "${MYSQL_PWD:-}" ]]; then
    echo "ERROR: MYSQL_PWD is required; do not place credentials in this script." >&2
    exit 1
fi

# Ensure the ledger table exists so pending/skip logic is accurate.
"$MYSQL" --batch --force=false -u "$DBU" -p"$MYSQL_PWD" "$DB" -e "
CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL UNIQUE,
    checksum VARCHAR(64) NOT NULL,
    applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    duration_ms INT DEFAULT 0
) ENGINE=InnoDB;" >/dev/null 2>&1

echo "=== Kingsway migration ledger status (DB=$DB) ==="
"$MYSQL" --batch -u "$DBU" -p"$MYSQL_PWD" "$DB" -e "
SELECT id, filename, LEFT(checksum,12) AS checksum, applied_at, duration_ms
FROM migrations ORDER BY id;"
echo

FILES=("$@")

if [[ "${#FILES[@]}" -eq 0 ]]; then
    echo "No migration files were named, so nothing was applied."
    echo "Apply an explicit file with:  MYSQL_PWD=... APPLY=1 $0 <file.sql ...>"
    echo "Never run this script without naming exact files."
    exit 0
fi

if [[ "$APPLY" != "1" ]]; then
    echo "Validation only (APPLY != 1). Requested files:"
    printf ' - %s\n' "${FILES[@]}"
    echo "Set APPLY=1 to execute exactly the files named above."
    exit 0
fi

for file in "${FILES[@]}"; do
    if [[ ! -f "$MIGRATIONS_DIR/$file" ]]; then
        echo "ERROR: not found in $MIGRATIONS_DIR: $file" >&2
        exit 1
    fi
done

for file in "${FILES[@]}"; do
    checksum=$(md5sum "$MIGRATIONS_DIR/$file" | cut -d' ' -f1)
    applied=$("$MYSQL" --batch --skip-column-names -u "$DBU" -p"$MYSQL_PWD" "$DB" -e \
        "SELECT COUNT(*) FROM migrations WHERE filename = '$file';")

    if [[ "$applied" == "1" && "$FORCE" != "1" ]]; then
        echo ">>> Skipping $file (already recorded in the migrations ledger; use FORCE=1 to re-run)"
        continue
    fi

    echo ">>> Applying $file"
    start_ms=$(date +%s%3N)
    if ! "$MYSQL" --batch --force=false -u "$DBU" -p"$MYSQL_PWD" "$DB" < "$MIGRATIONS_DIR/$file"; then
        echo "    FAILED: $file" >&2
        exit 1
    fi
    end_ms=$(date +%s%3N)
    duration_ms=$((end_ms - start_ms))

    "$MYSQL" --batch --force=false -u "$DBU" -p"$MYSQL_PWD" "$DB" -e "
        INSERT INTO migrations (filename, checksum, applied_at, duration_ms)
        VALUES ('$file', '$checksum', NOW(), $duration_ms)
        ON DUPLICATE KEY UPDATE checksum = '$checksum', applied_at = NOW(), duration_ms = $duration_ms;" >/dev/null 2>&1

    echo "    OK ($duration_ms ms)"
done

echo "=== Migration execution complete ==="

PHP_VERIFY="${PHP_VERIFY:-/opt/lampp/bin/php}"
if [[ -x "$PHP_VERIFY" && -f "$ROOT/scripts/verify_rbac_ui_alignment.php" ]]; then
    if "$PHP_VERIFY" -r 'exit(extension_loaded("pdo_mysql") ? 0 : 1);' 2>/dev/null; then
        "$PHP_VERIFY" "$ROOT/scripts/verify_rbac_ui_alignment.php" || echo "    (verify_rbac_ui_alignment reported issues; inspect manually)" >&2
    else
        echo "    (skipping verify_rbac_ui_alignment.php: PHP CLI has no pdo_mysql)"
    fi
fi
