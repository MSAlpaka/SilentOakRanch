#!/bin/sh
set -eu

APP_DIR="/var/www/backend"
APP_USER="${APP_USER:-appuser}"
APP_GROUP="${APP_GROUP:-$APP_USER}"
RUNTIME_DIRS="/var/www/backend/var /var/www/backend/config/jwt /var/www/backend/config/agreements /var/www/backend/shared/audit"

cd "$APP_DIR"

run_as_app_user() {
    if [ "$(id -u)" -eq 0 ]; then
        exec_cmd="su-exec"
        if ! command -v "$exec_cmd" >/dev/null 2>&1; then
            echo "Error: $exec_cmd is required to drop privileges" >&2
            exit 1
        fi
        "$exec_cmd" "$APP_USER:$APP_GROUP" "$@"
    else
        "$@"
    fi
}

prepare_runtime_dirs() {
    if [ "$(id -u)" -ne 0 ]; then
        return
    fi

    for dir in $RUNTIME_DIRS; do
        mkdir -p "$dir"
        chown -R "$APP_USER:$APP_GROUP" "$dir"
    done
}

prepare_runtime_dirs

resolve_path() {
    value="$1"
    prefix='%kernel.project_dir%'
    case "$value" in
        $prefix*)
            suffix=${value#"$prefix"}
            value="$APP_DIR$suffix"
            ;;
    esac
    printf '%s' "$value"
}

SECRET_KEY_PATH=$(resolve_path "${JWT_SECRET_KEY:-$APP_DIR/config/jwt/private.pem}")
PUBLIC_KEY_PATH=$(resolve_path "${JWT_PUBLIC_KEY:-$APP_DIR/config/jwt/public.pem}")

if [ ! -f "$SECRET_KEY_PATH" ] || [ ! -f "$PUBLIC_KEY_PATH" ]; then
    echo "Generating JWT keypair via lexik/jwt-authentication-bundle..."
    mkdir -p "$(dirname "$SECRET_KEY_PATH")"
    if [ "$(id -u)" -eq 0 ]; then
        chown "$APP_USER:$APP_GROUP" "$(dirname "$SECRET_KEY_PATH")"
    fi
    run_as_app_user php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction
fi

if [ "$(id -u)" -eq 0 ]; then
    exec su-exec "$APP_USER:$APP_GROUP" "$@"
else
    exec "$@"
fi
