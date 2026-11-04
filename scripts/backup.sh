#!/usr/bin/env bash
set -euo pipefail

DB="${DB_DATABASE:-}"
USER="${DB_USERNAME:-}"
PASS="${DB_PASSWORD:-}"
HOST="${DB_HOST:-127.0.0.1}"
PORT="${DB_PORT:-3306}"
OUTDIR="${BACKUP_DIR:-$(dirname "$0")/../storage/backups}"
KEEP="${BACKUP_KEEP:-7}"

if [[ -z "$DB" || -z "$USER" ]]; then
  echo "DB_DATABASE and DB_USERNAME are required" >&2
  exit 1
fi

mkdir -p "$OUTDIR"
TS=$(date +%Y%m%d_%H%M%S)
FILE="$OUTDIR/${DB}_${TS}.sql.gz"

MYSQL_PWD="$PASS" mysqldump --no-tablespaces --single-transaction --routines --triggers -h "$HOST" -P "$PORT" -u "$USER" "$DB" | gzip > "$FILE"

# retention
ls -1t "$OUTDIR"/${DB}_*.sql.gz 2>/dev/null | tail -n +$((KEEP+1)) | xargs -r rm -f

echo "Backup written: $FILE"
