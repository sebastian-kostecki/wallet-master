#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

COMPOSE="docker compose -f docker-compose.prod.yml"

check_inertia_ssr() {
    echo "==> Checking Inertia SSR..."

    for attempt in {1..5}; do
        if $COMPOSE exec -T app php artisan inertia:check-ssr; then
            echo "==> Inertia SSR is healthy."
            return 0
        fi

        if [[ "$attempt" -lt 5 ]]; then
            sleep 2
        fi
    done

    echo "WARNING: Inertia SSR health check failed; the app will continue with CSR fallback." >&2
    return 0
}

echo "==> Pulling latest..."
git pull

echo "==> Building app image..."
$COMPOSE build app

BACKUP_HOST_DIR="${BACKUP_HOST_DIR:-/storage/wallet-master-backups}"

echo "==> Ensuring backup directory exists..."
sudo mkdir -p "$BACKUP_HOST_DIR"
sudo chown -R "$(id -u)":"$(id -g)" "$BACKUP_HOST_DIR"

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

check_inertia_ssr

echo "==> Done. Check https://budget.kostecki.dev/up"
