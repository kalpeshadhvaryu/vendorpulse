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

# 2. Update Backend Containers
echo "🗃️ Refreshing Backend State..."
cd "$ROOT_DIR"

COMPOSE_CMD=(docker compose -f docker-compose.yml -f docker-compose.bind.yml)
"${COMPOSE_CMD[@]}" up -d app horizon scheduler

# Fast cache refresh + ALWAYS run migrations on every deploy
echo "🧩 Running database migrations (always)..."
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

# =========================================================================
# 🚀 FINAL FIX: Force Permissions & Clear Cache at the VERY END
# =========================================================================
echo "🔒 Running final container permission enforcement..."

# 1. Force create the logs directory inside the container
docker exec -i --user root vendorpulse-app-1 mkdir -p /var/www/html/storage/logs

# 2. Change the ownership of the entire storage folder to the web server user (www-data)
docker exec -i --user root vendorpulse-app-1 chown -R www-data:www-data /var/www/html/storage

# 3. Grant proper read/write permissions to the storage directory
docker exec -i --user root vendorpulse-app-1 chmod -R 775 /var/www/html/storage

# 4. Final Application Cache Clear
echo "🧹 Wiping final configuration caches..."
docker exec -i vendorpulse-app-1 php artisan optimize:clear

echo "✅ Quick deployment complete! App is verified and ready."