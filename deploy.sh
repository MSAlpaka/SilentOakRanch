#!/bin/bash
set -e
set -euo pipefail

APP_DIR="/srv/stallapp"
cd "$APP_DIR"

echo "🐴 Silent Oak Ranch – Starting deployment..."

echo "🔄 Pulling latest changes from GitHub..."
git fetch origin main
git reset --hard origin/main

echo "🧱 Ensuring shared directories exist..."
mkdir -p shared/backend/var shared/jwt/backend shared/agreements/signing

if [ -d "var/cache" ]; then
  echo "🧹 Clearing cache..."
  rm -rf var/cache/*
fi

echo "🔨 Building Docker images..."
docker compose build --pull backend frontend

echo "🚢 Deploying updated services..."
docker compose up -d --remove-orphans

echo "⏳ Waiting for backend service to become healthy..."
BACKEND_CONTAINER=$(docker compose ps -q backend)
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
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction

echo "✅ Deployment complete – Silent Oak Ranch is up to date."
