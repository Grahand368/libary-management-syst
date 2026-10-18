#!/usr/bin/env bash
# ============================================================================
# migrate.sh — apply Aurora Library's database migrations in order.
#
# Usage:
#   DB_USER=root DB_NAME=lms ./database/migrate.sh
#   DB_USER=root DB_NAME=lms ./database/migrate.sh --dry-run
#
# Set DB_PASS via env var (optional). The script will fall back to `mysql`
# reading them from .my.cnf if you prefer.
# ============================================================================

set -euo pipefail

DB_USER="${DB_USER:-root}"
DB_NAME="${DB_NAME:-lms}"
DB_HOST="${DB_HOST:-localhost}"
DRY_RUN=0
[[ "${1:-}" == "--dry-run" ]] && DRY_RUN=1

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

run() {
    local file="$1"
    local label
    label="$(basename "$file")"
    echo "==> Applying $label"

    if [[ $DRY_RUN -eq 1 ]]; then
        echo "    (dry-run) mysql --host=$DB_HOST --user=$DB_USER $DB_NAME < $file"
        return 0
    fi

    if [[ -n "${DB_PASS:-}" ]]; then
        mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" "$DB_NAME" < "$file"
    else
        mysql --host="$DB_HOST" --user="$DB_USER" "$DB_NAME" < "$file"
    fi
}

echo "Aurora Library DB migrations"
echo "  target: $DB_USER@$DB_HOST/$DB_NAME"
echo

# Step 0 is diagnostic — always run (or print) first.
run "$SCRIPT_DIR/000_preflight_duplicates.sql"

# Optional prompt to continue after preflight in interactive shells.
if [[ -t 1 && $DRY_RUN -eq 0 ]]; then
    echo
    read -rp "Pre-flight complete. Inspect the output above. Apply the rest? [y/N] " yn
    [[ "$yn" =~ ^[Yy]$ ]] || { echo "Aborted."; exit 1; }
fi

for f in 001_security_migration.sql \
         002_fine_system.sql \
         003_book_requests.sql \
         004_purchase_requests.sql \
         005_database_constraints.sql; do
    run "$SCRIPT_DIR/$f"
done

echo
echo "All migrations applied."