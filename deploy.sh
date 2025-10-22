#!/bin/bash
set -e
APP_DIR="/srv/stallapp"
cd $APP_DIR

echo "🐴 Silent Oak Ranch – Starting deployment..."

# Fetch latest code
echo "🔄 Pulling latest changes from GitHub..."
git fetch origin main
git reset --hard origin/main

# Optional: Clear cache directories (if any)
if [ -d "var/cache" ]; then
  echo "🧹 Clearing cache..."
  rm -rf var/cache/*
fi

echo "✅ Deployment complete – Silent Oak Ranch is up to date."
