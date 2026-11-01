#!/usr/bin/env bash
set -euo pipefail

FILE="${1:-}"
TARGET="${2:-}"
FORCE="${3:-}"

if [[ -z "$FILE" || -z "$TARGET" ]]; then
  echo "Usage: restore.sh <backup.sql.gz> <target_db> [--force]" >&2
  exit 1
fi

PROTECTED=("$DB_DATABASE" "dukaan_os")
for p in "${PROTECTED[@]}"; do
  if [[ "$TARGET" == "$p" && "$FORCE" != "--force" ]]; then
    echo "Refusing to restore into protected database '$TARGET'. Use --force and typed confirmation." >&2
    exit 1
  fi
done

if [[ "$FORCE" == "--force" ]]; then
  read -r -p "Type RESTORE to confirm overwrite of $TARGET: " CONFIRM
  if [[ "$CONFIRM" != "RESTORE" ]]; then
    echo "Confirmation failed" >&2
    exit 1
  fi
fi

gunzip -c "$FILE" | mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "${DB_USERNAME}" "$TARGET"

TC=$(mysql -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "${DB_USERNAME}" -N -e "SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='$TARGET';")
echo "Triggers after restore: $TC"