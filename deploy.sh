#!/bin/bash
set -euo pipefail

APP_DIR="/srv/stallapp"
COMPOSE_FILES=("docker-compose.yml")
ENVIRONMENT="prod"
VERIFY=0

usage() {
  cat <<USAGE
Usage: $0 [--env=<stage|prod>] [--verify]

--env      Target environment (default: prod)
--verify   Run post-deployment smoke checks
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --env=*)
      ENVIRONMENT="${1#*=}"
      ;;
    --verify)
      VERIFY=1
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
  shift
done

if [[ "$ENVIRONMENT" != "prod" && "$ENVIRONMENT" != "stage" ]]; then
  echo "Unsupported environment: $ENVIRONMENT" >&2
  exit 2
fi

if [[ "$ENVIRONMENT" == "stage" && -f "docker-compose.stage.yml" ]]; then
  COMPOSE_FILES=("docker-compose.stage.yml" "docker-compose.yml")
fi

cd "$APP_DIR"

echo "🐴 Silent Oak Ranch – Starting deployment..."

echo "🔄 Pulling latest changes from GitHub (branch: main)..."
git fetch origin main
git reset --hard origin/main

echo "🧱 Ensuring shared directories exist..."
mkdir -p shared/backend/var shared/jwt/backend shared/agreements/signing

if [ -d "var/cache" ]; then
  echo "🧹 Clearing cache..."
  rm -rf var/cache/*
fi

COMPOSE_ARGS=()
for file in "${COMPOSE_FILES[@]}"; do
  COMPOSE_ARGS+=("-f" "$file")
done

echo "🌍 Target environment: $ENVIRONMENT"

echo "🔨 Building Docker images..."
docker compose "${COMPOSE_ARGS[@]}" build --pull backend frontend wordpress

echo "🚢 Deploying updated services..."
docker compose "${COMPOSE_ARGS[@]}" up -d --remove-orphans traefik db redis wp-db backend frontend wordpress

echo "⏳ Waiting for backend service to become healthy..."
BACKEND_CONTAINER=$(docker compose "${COMPOSE_ARGS[@]}" ps -q backend)
if [ -n "$BACKEND_CONTAINER" ]; then
  for attempt in {1..30}; do
    STATUS=$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$BACKEND_CONTAINER" 2>/dev/null || echo "unknown")
    if [ "$STATUS" = "healthy" ] || [ "$STATUS" = "running" ]; then
      break
    fi
    echo "  • backend status: $STATUS (attempt $attempt/30)"
    sleep 5
  done
fi

echo "🗄️ Running database migrations..."
docker compose "${COMPOSE_ARGS[@]}" exec backend php bin/console doctrine:migrations:migrate --no-interaction

if [[ $VERIFY -eq 1 ]]; then
  echo "🧪 Running post-deployment verification..."
  HEALTH_JSON=$(docker compose "${COMPOSE_ARGS[@]}" exec backend curl -fsS http://127.0.0.1:8080/health)
  echo "$HEALTH_JSON"
  if ! grep -q '"ok":true' <<<"$HEALTH_JSON"; then
    echo "Health check did not return ok=true" >&2
    exit 3
  fi
  docker compose "${COMPOSE_ARGS[@]}" exec backend test -w /var/www/backend/shared/audit
  echo "✅ Verification succeeded."
fi

echo "✅ Deployment complete – Silent Oak Ranch is up to date."
