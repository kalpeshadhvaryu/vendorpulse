#!/usr/bin/env bash
set -Eeuo pipefail
trap 'echo "❌ Deployment failed at line $LINENO"' ERR

echo "🚀 Starting Rapid Deployment..."

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
FRONTEND_DIR="$ROOT_DIR/web_dashboard"

# 1. Sync fresh code from GitHub
echo "⬇️ Syncing Repository..."
cd "$ROOT_DIR"
git fetch origin "${BACKEND_BRANCH:-kalpesh}"
git reset --hard "origin/${BACKEND_BRANCH:-kalpesh}"

cd "$FRONTEND_DIR"
git fetch origin "${FRONTEND_BRANCH:-kalpesh}"
git reset --hard "origin/${FRONTEND_BRANCH:-kalpesh}"

# 2. Update Backend Containers
echo "🗃️ Refreshing Backend State..."
cd "$ROOT_DIR"

COMPOSE_CMD=(docker compose -f docker-compose.yml -f docker-compose.bind.yml)
"${COMPOSE_CMD[@]}" up -d app horizon scheduler

# 🚀 CRITICAL FIX: Wait 3 seconds for containers to completely initialize before running internal actions
echo "⏳ Waiting for container processes to warm up..."
sleep 3

# 🔒 Force fix storage paths using BOTH host fallback and internal ROOT user
echo "🔒 Restoring folder permissions..."
# Fix host-side bind directories first (prevents Docker from generating them as root on boot)
mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

# Fix internally within the running workspace container
"${COMPOSE_CMD[@]}" exec -T --user root app sh -c '
    mkdir -p /var/www/html/storage/logs /var/www/html/storage/framework/cache /var/www/html/storage/framework/sessions /var/www/html/storage/framework/views /var/www/html/bootstrap/cache
    chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
'

# Fast cache refresh
echo "🧩 Running Laravel optimization states..."
"${COMPOSE_CMD[@]}" exec -T app php artisan optimize:clear --no-interaction
"${COMPOSE_CMD[@]}" exec -T app php artisan migrate --force --no-interaction
"${COMPOSE_CMD[@]}" exec -T app php artisan optimize --no-interaction

# 3. Build Frontend
echo "🧱 Incrementing Frontend Build..."
cd "$FRONTEND_DIR"

if [ ! -d "node_modules" ]; then
    echo "🧰 Missing node_modules, running clean install..."
    npm install
fi

npm run build

echo "🔁 Hot-restarting PM2 Instance..."
pm2 restart vendorpulse-frontend || pm2 start npm --name vendorpulse-frontend --cwd "$FRONTEND_DIR" -- start

echo "✅ Quick deployment complete!"