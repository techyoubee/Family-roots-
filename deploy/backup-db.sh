#!/usr/bin/env bash
#
# Family Roots - database backup.
#
# The application has no export feature, so backups are taken here with
# mysqldump. The dump is consistent (--single-transaction) and compressed.
#
# Usage:
#   ./deploy/backup-db.sh
#   APP_DIR=/var/www/family-roots BACKUP_DIR=/var/backups/family-roots KEEP_DAYS=14 ./deploy/backup-db.sh
#
# Typical cron entry (nightly 02:15):
#   15 2 * * * APP_DIR=/var/www/family-roots /var/www/family-roots/deploy/backup-db.sh
#
# Windows: run the same mysqldump command from a scheduled task, or use
#   mysqldump.exe --single-transaction ... > backup.sql
# there is no .bat wrapper shipped.
#
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/family-roots}"
KEEP_DAYS="${KEEP_DAYS:-14}"
ENV_FILE="$APP_DIR/.env"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "backup-db: $ENV_FILE not found (set APP_DIR)" >&2
    exit 1
fi

# Read a single key from .env without sourcing it (values may contain spaces).
env_get() {
    local key="$1"
    local line
    line="$(grep -E "^[[:space:]]*${key}=" "$ENV_FILE" | tail -n 1 || true)"
    [[ -z "$line" ]] && return 0
    local value="${line#*=}"
    value="${value%\"}"; value="${value#\"}"
    value="${value%\'}"; value="${value#\'}"
    printf '%s' "$value"
}

DB_HOST="$(env_get DB_HOST)"; DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="$(env_get DB_PORT)"; DB_PORT="${DB_PORT:-3306}"
DB_NAME="$(env_get DB_NAME)"; DB_NAME="${DB_NAME:-family_roots}"
DB_USER="$(env_get DB_USER)"
DB_PASS="$(env_get DB_PASS)"

if [[ -z "$DB_USER" || -z "$DB_PASS" ]]; then
    echo "backup-db: DB_USER/DB_PASS missing from $ENV_FILE" >&2
    exit 1
fi

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR" 2>/dev/null || true

# Credentials go in a private temporary defaults file, never on the command
# line (visible in ps to every local user).
CRED_FILE="$(mktemp)"
chmod 600 "$CRED_FILE"
cleanup() { rm -f "$CRED_FILE"; }
trap cleanup EXIT

cat > "$CRED_FILE" <<EOF
[client]
user=$DB_USER
password=$DB_PASS
host=$DB_HOST
port=$DB_PORT
EOF

STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="$BACKUP_DIR/${DB_NAME}-${STAMP}.sql.gz"
TMP_OUT="${OUT}.part"

set +e
mysqldump \
    --defaults-extra-file="$CRED_FILE" \
    --single-transaction \
    --quick \
    --routines \
    --events \
    --no-tablespaces \
    --default-character-set=utf8mb4 \
    "$DB_NAME" | gzip -c > "$TMP_OUT"
STATUS=${PIPESTATUS[0]}
set -e

if [[ "$STATUS" -ne 0 || ! -s "$TMP_OUT" ]]; then
    rm -f "$TMP_OUT"
    echo "backup-db: mysqldump failed (exit $STATUS)" >&2
    exit 1
fi

mv "$TMP_OUT" "$OUT"
chmod 600 "$OUT"

# Keep the newest KEEP_DAYS worth of dumps.
find "$BACKUP_DIR" -maxdepth 1 -type f -name "${DB_NAME}-*.sql.gz" \
    -mtime +"$KEEP_DAYS" -delete

echo "backup-db: wrote $OUT ($(du -h "$OUT" | cut -f1)); retention ${KEEP_DAYS}d"
