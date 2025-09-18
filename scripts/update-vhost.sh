#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)
VHOST_DIR="$ROOT_DIR/proxy/vhost.d"
TEMPLATE="$VHOST_DIR/vhost.conf.template"
ENV_FILE="${1:-$ROOT_DIR/.env}"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

if [[ ! -f "$TEMPLATE" ]]; then
    log "VHost template $TEMPLATE not found; nothing to update."
    exit 0
fi

if [[ ! -f "$ENV_FILE" ]]; then
    log "Environment file $ENV_FILE not found; skipping VHost update."
    exit 0
fi

# Extract DOMAIN from the environment file; ignore comments and whitespace.
domain=$(grep -E '^DOMAIN=' "$ENV_FILE" | tail -n 1 | cut -d '=' -f2- | tr -d '"' | tr -d "'")
domain=${domain%%#*}
domain=$(echo "$domain" | xargs)

if [[ -z "$domain" ]]; then
    log "DOMAIN is not set in $ENV_FILE; skipping VHost update."
    exit 0
fi

DEST="$VHOST_DIR/$domain"

# Remove stale domain-specific configs so only the current domain remains.
shopt -s nullglob
for file in "$VHOST_DIR"/*; do
    if [[ "$file" != "$TEMPLATE" && "$file" != "$DEST" && -f "$file" ]]; then
        log "Removing outdated VHost override $(basename "$file")"
        rm -f "$file"
    fi
done
shopt -u nullglob

log "Rendering VHost override for domain $domain"
cp "$TEMPLATE" "$DEST"
chmod 0644 "$DEST"
