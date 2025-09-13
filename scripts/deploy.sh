#!/usr/bin/env bash

# Deployment helper script
# Usage: deploy.sh [-n|--dry-run] <build-id> <target-dir>
# Stops services, downloads artifacts for the given build ID,
# extracts them into the target directory, runs database migrations,
# and restarts services. When run with --dry-run, commands are logged
# but not executed.

set -euo pipefail

usage() {
    echo "Usage: $0 [-n|--dry-run] <build-id> <target-dir>" >&2
}

DRY_RUN=false

while [[ "${1-}" == -* ]]; do
    case "$1" in
        -n|--dry-run)
            DRY_RUN=true
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage
            exit 1
            ;;
    esac
done

if [[ $# -lt 2 ]]; then
    usage
    exit 1
fi

BUILD_ID="$1"
TARGET_DIR="$2"
ARTIFACT_DIR="/tmp/deploy-artifacts-$BUILD_ID"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

run() {
    log "$*"
    if ! $DRY_RUN; then
        "$@"
    fi
}

run sudo systemctl stop app

run rm -rf "$ARTIFACT_DIR"
run mkdir -p "$ARTIFACT_DIR"
run gh run download "$BUILD_ID" --dir "$ARTIFACT_DIR"

ARCHIVE=$(find "$ARTIFACT_DIR" -maxdepth 1 -type f | head -n1 || true)
if [[ -n "$ARCHIVE" ]]; then
    run unzip "$ARCHIVE" -d "$TARGET_DIR"
else
    log "No artifact archive found in $ARTIFACT_DIR" >&2
fi

run php bin/console doctrine:migrations:migrate --no-interaction

run sudo systemctl start app
