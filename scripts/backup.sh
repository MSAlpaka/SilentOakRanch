#!/usr/bin/env bash
set -euo pipefail

function log() {
  printf '[%s] %s\n' "$(date --iso-8601=seconds)" "$*"
}

function usage() {
  cat <<USAGE
Usage: $0 [--verify]

--verify   Validate latest remote backup without creating a new one.
USAGE
}

VERIFY_ONLY=0
while [[ $# -gt 0 ]]; do
  case "$1" in
    --verify)
      VERIFY_ONLY=1
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      usage >&2
      exit 1
      ;;
  esac
done

REMOTE="${STORAGE_BOX_REMOTE:-}"
if [[ -z "$REMOTE" ]]; then
  echo "STORAGE_BOX_REMOTE is not defined" >&2
  exit 2
fi

WORKDIR="${BACKUP_WORKDIR:-/tmp/backup}"
mkdir -p "$WORKDIR"

if [[ $VERIFY_ONLY -eq 1 ]]; then
  log "Running remote backup verification"
  latest_dir=$(rclone lsf "$REMOTE/daily" --dirs-only 2>/dev/null | sort | tail -n1 | tr -d '/')
  if [[ -z "$latest_dir" ]]; then
    echo "No backups found in $REMOTE/daily" >&2
    exit 3
  fi
  tmpdir=$(mktemp -d)
  trap 'rm -rf "$tmpdir"' EXIT
  log "Fetching checksum manifest for $latest_dir"
  rclone copyto "$REMOTE/daily/$latest_dir/SHA256SUMS" "$tmpdir/SHA256SUMS"
  if [[ ! -s "$tmpdir/SHA256SUMS" ]]; then
    echo "Checksum manifest empty" >&2
    exit 4
  fi
  log "Checksum manifest contents:" 
  cat "$tmpdir/SHA256SUMS"
  log "Verification completed"
  exit 0
fi

TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="$WORKDIR/$TIMESTAMP"
mkdir -p "$BACKUP_DIR"

log "Starting Silent Oak Ranch backup at $TIMESTAMP"

POSTGRES_USER="${POSTGRES_USER:-postgres}"
POSTGRES_DB="${POSTGRES_DB:-postgres}"
POSTGRES_PASSWORD="${POSTGRES_PASSWORD:-}"
PGHOST="${POSTGRES_HOST:-db}"
PGPORT="${POSTGRES_PORT:-5432}"

export PGPASSWORD="$POSTGRES_PASSWORD"

PG_FILE="$BACKUP_DIR/postgres.sql.gz"
log "Dumping Postgres database $POSTGRES_DB"
pg_dump --host "$PGHOST" --port "$PGPORT" --username "$POSTGRES_USER" --format=plain "$POSTGRES_DB" | gzip -9 > "$PG_FILE"

MARIADB_USER="${MARIADB_USER:-root}"
MARIADB_PASSWORD="${MARIADB_PASSWORD:-${MARIADB_ROOT_PASSWORD:-}}"
MYSQL_PWD="$MARIADB_PASSWORD"
export MYSQL_PWD
MYSQL_FILE="$BACKUP_DIR/mariadb.sql.gz"
log "Dumping MariaDB database"
mysqldump --host "${MARIADB_HOST:-wp-db}" --port "${MARIADB_PORT:-3306}" --user "$MARIADB_USER" --all-databases --single-transaction | gzip -9 > "$MYSQL_FILE"
unset MYSQL_PWD

log "Archiving shared volumes"
AGREEMENTS_SRC="${AGREEMENTS_PATH:-/data/shared/agreements}"
AUDIT_SRC="${AUDIT_PATH:-/data/shared/audit}"
BACKEND_VAR_SRC="${BACKEND_VAR_PATH:-/data/backend-var}"
mkdir -p "$BACKUP_DIR/files"
tar -C "$AGREEMENTS_SRC" -czf "$BACKUP_DIR/files/agreements.tar.gz" .
tar -C "$AUDIT_SRC" -czf "$BACKUP_DIR/files/audit.tar.gz" .
tar -C "$BACKEND_VAR_SRC" -czf "$BACKUP_DIR/files/backend-var.tar.gz" .

MANIFEST="$BACKUP_DIR/SHA256SUMS"
log "Writing checksum manifest"
(
  cd "$BACKUP_DIR"
  find . -type f ! -name "SHA256SUMS" -print0 | sort -z | xargs -0 sha256sum > "$MANIFEST"
)

REMOTE_DAILY="$REMOTE/daily/$TIMESTAMP"
log "Uploading backup to $REMOTE_DAILY"
rclone copy "$BACKUP_DIR" "$REMOTE_DAILY" --create-empty-src-dirs

log "Pruning remote backups"
rclone delete "$REMOTE/daily" --min-age 7d --rmdirs
if [[ $(date +%u) -eq 1 ]]; then
  log "Updating weekly retention"
  rclone copy "$BACKUP_DIR" "$REMOTE/weekly/$TIMESTAMP" --create-empty-src-dirs
  rclone delete "$REMOTE/weekly" --min-age 28d --rmdirs
fi
if [[ $(date +%d) -eq 01 ]]; then
  log "Updating monthly retention"
  rclone copy "$BACKUP_DIR" "$REMOTE/monthly/$TIMESTAMP" --create-empty-src-dirs
  rclone delete "$REMOTE/monthly" --min-age 365d --rmdirs
fi

log "Backup complete"
rm -rf "$BACKUP_DIR"
