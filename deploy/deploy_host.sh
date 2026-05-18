#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
FRONTEND_DIR="$ROOT_DIR/web_dashboard"

BACKEND_BRANCH="${BACKEND_BRANCH:-kalpesh}"
FRONTEND_BRANCH="${FRONTEND_BRANCH:-kalpesh}"
PM2_APP_NAME="${PM2_APP_NAME:-vendorpulse-frontend}"
FORCE_RESET="${FORCE_RESET:-0}"
MAINTENANCE_MODE="${MAINTENANCE_MODE:-1}"
START_HOST_WORKERS="${START_HOST_WORKERS:-1}"

COMPOSE=(docker compose -f "$ROOT_DIR/docker-compose.yml" -f "$ROOT_DIR/docker-compose.bind.yml")

down_active=0

cleanup() {
    local rc=$?

    if [[ $down_active -eq 1 ]]; then
        cd "$ROOT_DIR"
        php artisan up || true
    fi

    if [[ $rc -ne 0 ]]; then
        echo "❌ Deployment failed (exit $rc)"
    fi

    exit $rc
}

trap cleanup EXIT

require_cmd() {
    local cmd="$1"
    if ! command -v "$cmd" >/dev/null 2>&1; then
        echo "❌ Missing required command: $cmd"
        exit 1
    fi
}

sync_repo() {
    local repo_dir="$1"
    local branch="$2"
    local label="$3"

    echo "\n== Syncing $label ($branch) =="
    cd "$repo_dir"

    if [[ "$FORCE_RESET" != "1" ]] && [[ -n "$(git status --porcelain)" ]]; then
        echo "❌ $label has local changes. Commit/stash first, or run with FORCE_RESET=1"
        exit 1
    fi

    git fetch origin "$branch"

    if [[ "$FORCE_RESET" == "1" ]]; then
        git checkout -B "$branch" "origin/$branch"
        git reset --hard "origin/$branch"
    else
        git checkout "$branch"
        git pull --ff-only origin "$branch"
    fi
}

wait_for_compose_service() {
    local service="$1"
    local timeout="${2:-60}"
    local container_id

    container_id="$(${COMPOSE[@]} ps -q "$service" || true)"
    if [[ -z "$container_id" ]]; then
        echo "⚠️ Could not determine container id for service: $service"
        return 0
    fi

    local start_ts
    start_ts="$(date +%s)"

    while true; do
        local status health
        status="$(docker inspect -f '{{.State.Status}}' "$container_id" 2>/dev/null || echo unknown)"
        health="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$container_id" 2>/dev/null || echo none)"

        if [[ "$status" == "running" ]] && [[ "$health" == "healthy" || "$health" == "none" ]]; then
            echo "✅ $service is $status ($health)"
            return 0
        fi

        if (( $(date +%s) - start_ts >= timeout )); then
            echo "❌ Timeout waiting for $service (status=$status health=$health)"
            docker logs --tail 50 "$container_id" || true
            exit 1
        fi

        sleep 2
    done
}

echo "🚀 Starting host-based deployment"

require_cmd git
require_cmd php
require_cmd npm
require_cmd pm2
require_cmd docker

sync_repo "$ROOT_DIR" "$BACKEND_BRANCH" "backend"
sync_repo "$FRONTEND_DIR" "$FRONTEND_BRANCH" "frontend"

echo "\n== Ensuring infra containers (postgres/redis) =="
cd "$ROOT_DIR"
"${COMPOSE[@]}" up -d postgres redis
wait_for_compose_service postgres 90
wait_for_compose_service redis 60

echo "\n== Deploying Laravel (host runtime) =="
if [[ "$MAINTENANCE_MODE" == "1" ]]; then
    php artisan down --retry=60 || true
    down_active=1
fi

mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

php artisan optimize:clear
php artisan migrate --force
php artisan optimize

if [[ "$START_HOST_WORKERS" == "1" ]]; then
    if pgrep -f "artisan horizon" >/dev/null 2>&1; then
        php artisan horizon:terminate || true
    else
        nohup php artisan horizon > "$ROOT_DIR/storage/logs/horizon-host.log" 2>&1 &
    fi

    if ! pgrep -f "artisan schedule:work" >/dev/null 2>&1; then
        nohup php artisan schedule:work > "$ROOT_DIR/storage/logs/scheduler-host.log" 2>&1 &
    fi
fi

if [[ $down_active -eq 1 ]]; then
    php artisan up || true
    down_active=0
fi

echo "\n== Deploying Next.js (host runtime) =="
cd "$FRONTEND_DIR"
if [[ -f package-lock.json ]]; then
    npm ci --no-audit --no-fund
else
    npm install --no-audit --no-fund
fi

npm run build
pm2 restart "$PM2_APP_NAME" || pm2 start npm --name "$PM2_APP_NAME" --cwd "$FRONTEND_DIR" -- start
pm2 save || true

echo "\n== Quick verification =="
cd "$ROOT_DIR"
php artisan horizon:status || true
php artisan queue:failed || true
php artisan tinker --execute="dump(Illuminate\\Support\\Facades\\Schema::hasColumns('organizations', ['phone_country_code','phone_number']));"

echo "\n✅ Deployment completed successfully"