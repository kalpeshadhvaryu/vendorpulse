#!/usr/bin/env bash
set -Eeuo pipefail
trap 'echo "❌ Deployment failed at line $LINENO"' ERR

echo "🚀 Starting Rapid Deployment..."

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
FRONTEND_DIR="$ROOT_DIR/web_dashboard"

# 1. Sync fresh code from GitHub without blocking on untracked local changes
echo "⬇️ Syncing Repository..."
cd "$ROOT_DIR"
git fetch origin "${BACKEND_BRANCH:-kalpesh}"
git reset --hard "origin/${BACKEND_BRANCH:-kalpesh}"

cd "$FRONTEND_DIR"
git fetch origin "${FRONTEND_BRANCH:-kalpesh}"
git reset --hard "origin/${FRONTEND_BRANCH:-kalpesh}"

# 2. Update Backend Containers & Fix Permissions immediately
echo "🗃️ Refreshing Backend State..."
cd "$ROOT_DIR"

COMPOSE_CMD=(docker compose -f docker-compose.yml -f docker-compose.bind.yml)
"${COMPOSE_CMD[@]}" up -d app horizon scheduler

# Force fix storage paths using the ROOT user inside the container
echo "🔒 Restoring folder permissions inside container..."
"${COMPOSE_CMD[@]}" exec -T --user root app sh -c '
    mkdir -p /var/www/html/storage/logs /var/www/html/storage/framework/cache /var/www/html/storage/framework/sessions /var/www/html/storage/framework/views /var/www/html/bootstrap/cache
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
'

# Fast cache refresh + ALWAYS run migrations on every deploy
echo "🧩 Running database migrations (always)..."
# APP_KEY_VALUE="$("${COMPOSE_CMD[@]}" exec -T app php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class); $kernel->bootstrap(); echo (string) config("app.key");')"
if [ -z "$APP_KEY_VALUE" ]; then
    echo "❌ APP_KEY is missing inside app container. Set APP_KEY before deploy."
    exit 1
fi

if [ -t 0 ]; then
    docker exec -it vendorpulse-app-1 php artisan optimize:clear
else
    docker exec vendorpulse-app-1 php artisan optimize:clear
fi
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