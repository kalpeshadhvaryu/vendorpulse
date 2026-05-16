#!/usr/bin/env bash
set -Eeuo pipefail
trap 'echo "❌ Deployment failed at line $LINENO"' ERR

echo "🚀 Starting Rapid Deployment..."

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
FRONTEND_DIR="$ROOT_DIR/web_dashboard"

# 1. Pull the fresh code without blocking on uncommitted changes
echo "⬇️ Syncing Repository..."
cd "$ROOT_DIR"
git pull origin "${BACKEND_BRANCH:-kalpesh}" --rebase

cd "$FRONTEND_DIR"
git pull origin "${FRONTEND_BRANCH:-kalpesh}" --rebase

# 2. Update Backend Containers & Fix Permissions immediately
echo "🗃️ Refreshing Backend State..."
cd "$ROOT_DIR"

COMPOSE_CMD=(docker compose -f docker-compose.yml -f docker-compose.bind.yml)
"${COMPOSE_CMD[@]}" up -d app horizon scheduler

# Force fix storage path folders and permission limits in one shot
"${COMPOSE_CMD[@]}" exec -T app sh -c '
    mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
    chmod -R 775 storage bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
'

# Fast cache refresh
"${COMPOSE_CMD[@]}" exec -T app php artisan optimize:clear
"${COMPOSE_CMD[@]}" exec -T app php artisan migrate --force

# 3. Hot-Reload or Fast-Build Frontend
echo "🧱 Incrementing Frontend Build..."
cd "$FRONTEND_DIR"

# Only run install if node_modules is completely missing
if [ ! -d "node_modules" ]; then
    echo "🧰 Missing node_modules, running clean install..."
    npm install
fi

npm run build

echo "🔁 Hot-restarting PM2 Instance..."
pm2 restart vendorpulse-frontend || pm2 start npm --name vendorpulse-frontend --cwd "$FRONTEND_DIR" -- start

echo "✅ Quick deployment complete!"