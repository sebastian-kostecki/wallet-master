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

echo "==> Fixing storage permissions..."
$COMPOSE exec -T -u root app chown -R sail:sail storage bootstrap/cache

echo "==> Running migrations..."
$COMPOSE exec -T app php artisan migrate --force

echo "==> Discovering packages and caching..."
$COMPOSE exec -T app php artisan package:discover --ansi
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache

echo "==> Done. Check https://budget.kostecki.dev/up"
