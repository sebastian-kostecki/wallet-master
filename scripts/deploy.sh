#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE="docker compose -f docker-compose.prod.yml"

echo "==> Pulling latest..."
git pull

echo "==> Building app image..."
$COMPOSE build app

echo "==> Starting stack..."
$COMPOSE up -d

echo "==> Running migrations..."
$COMPOSE exec -T app php artisan migrate --force

echo "==> Caching config/routes/views..."
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache

echo "==> Done. Check https://budget.kostecki.dev/up"
