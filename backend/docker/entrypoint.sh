#!/bin/sh
set -eu

APP_DIR="/var/www/backend"
cd "$APP_DIR"

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
    php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction
fi

exec "$@"
