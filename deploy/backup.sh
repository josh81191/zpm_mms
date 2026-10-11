#!/usr/bin/env bash
# Nightly backup. Cron example (with ~/.pgpass configured):
#   0 2 * * * /var/www/zpm/deploy/backup.sh >> /var/log/zpm-backup.log 2>&1
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/zpm}"
KEEP_DAYS="${KEEP_DAYS:-30}"
DB_NAME="${DB_NAME:-zpm_membership}"
DB_USER="${DB_USER:-zpm_app}"
DB_HOST="${DB_HOST:-127.0.0.1}"

mkdir -p "$BACKUP_DIR"
umask 077
FILE="$BACKUP_DIR/${DB_NAME}_$(date +%Y%m%d_%H%M%S).dump"

pg_dump -h "$DB_HOST" -U "$DB_USER" -Fc -f "$FILE" "$DB_NAME"
find "$BACKUP_DIR" -name "${DB_NAME}_*.dump" -mtime +"$KEEP_DAYS" -delete
echo "Backup written: $FILE"

# Restore test (into an empty scratch database, as a superuser):
#   createdb zpm_restore_test && psql -d zpm_restore_test -c 'CREATE EXTENSION postgis'
#   pg_restore -d zpm_restore_test --no-owner "$FILE"
