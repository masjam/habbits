#!/usr/bin/env bash
# Cadangkan database MySQL/MariaDB sebelum migrasi.
# Pemakaian: scripts/backup-db.sh [path/.env] [folder-backup]
# Gagal (exit != 0) => deploy berhenti sebelum migrasi dijalankan.
set -euo pipefail

ENV_FILE="${1:-/var/www/habit/.env}"
BACKUP_DIR="${2:-$HOME/habit-backups}"
KEEP_DAYS="${KEEP_DAYS:-14}"

[ -f "$ENV_FILE" ] || { echo "Backup: file .env tidak ditemukan: $ENV_FILE" >&2; exit 1; }

get() {
  grep -E "^$1=" "$ENV_FILE" | head -n1 | cut -d= -f2- \
    | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'\$//"
}

CONN="$(get DB_CONNECTION)"
if [ "$CONN" != "mysql" ] && [ "$CONN" != "mariadb" ]; then
  echo "Backup: DB_CONNECTION=$CONN, dilewati (hanya mysql/mariadb)."
  exit 0
fi

DB_HOST="$(get DB_HOST)"; DB_PORT="$(get DB_PORT)"
DB_NAME="$(get DB_DATABASE)"; DB_USER="$(get DB_USERNAME)"
export MYSQL_PWD="$(get DB_PASSWORD)"

umask 077
mkdir -p "$BACKUP_DIR"
OUT="$BACKUP_DIR/${DB_NAME}_$(date +%Y%m%d_%H%M%S).sql.gz"

mysqldump --single-transaction --quick --routines --events \
  -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USER" "$DB_NAME" \
  | gzip > "$OUT"

# Pastikan hasilnya valid dan tidak kosong
gzip -t "$OUT"
[ -s "$OUT" ] || { echo "Backup: hasil kosong" >&2; rm -f "$OUT"; exit 1; }

find "$BACKUP_DIR" -name "${DB_NAME}_*.sql.gz" -mtime +"$KEEP_DAYS" -delete
echo "Backup OK: $OUT ($(du -h "$OUT" | cut -f1))"
