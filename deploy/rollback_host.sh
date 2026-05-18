#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
FRONTEND_DIR="$ROOT_DIR/web_dashboard"

BACKEND_REF="${BACKEND_REF:-HEAD~1}"
FRONTEND_REF="${FRONTEND_REF:-HEAD~1}"
PM2_APP_NAME="${PM2_APP_NAME:-vendorpulse-frontend}"
FORCE_RESET="${FORCE_RESET:-0}"
MAINTENANCE_MODE="${MAINTENANCE_MODE:-1}"
START_HOST_WORKERS="${START_HOST_WORKERS:-1}"
RUN_MIGRATE="${RUN_MIGRATE:-0}"

COMPOSE=(docker compose -f "$ROOT_DIR/docker-compose.yml" -f "$ROOT_DIR/docker-compose.bind.yml")

down_active=0

cleanup() {
    local rc=$?

    if [[ $down_active -eq 1 ]]; then
        cd "$ROOT_DIR"
        php artisan up || true
    fi

    if [[ $rc -ne 0 ]]; then
        echo "❌ Rollback failed (exit $rc)"
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

ensure_clean_or_force() {
    local repo_dir="$1"
    local label="$2"

    cd "$repo_dir"
    if [[ "$FORCE_RESET" != "1" ]] && [[ -n "$(git status --porcelain)" ]]; then
        echo "❌ $label has local changes. Commit/stash first, or run with FORCE_RESET=1"
        exit 1
    fi
}

reset_repo_to_ref() {
    local repo_dir="$1"
    local ref="$2"
    local label="$3"

    echo "\n== Rolling back $label to $ref =="
    cd "$repo_dir"
    git fetch --all --tags --prune

    if ! git rev-parse --verify "$ref" >/dev/null 2>&1; then
        echo "❌ Ref not found in $label: $ref"
        exit 1
    fi

    git reset --hard "$ref"
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

echo "↩️ Starting host-based rollback"

require_cmd git
require_cmd php
require_cmd npm
require_cmd pm2
require_cmd docker

ensure_clean_or_force "$ROOT_DIR" "backend"
ensure_clean_or_force "$FRONTEND_DIR" "frontend"

echo "\n== Ensuring infra containers (postgres/redis) =="
cd "$ROOT_DIR"
"${COMPOSE[@]}" up -d postgres redis
wait_for_compose_service postgres 90
wait_for_compose_service redis 60

if [[ "$MAINTENANCE_MODE" == "1" ]]; then
    echo "\n== Enabling maintenance mode =="
    php artisan down --retry=60 || true
    down_active=1
fi

reset_repo_to_ref "$ROOT_DIR" "$BACKEND_REF" "backend"
reset_repo_to_ref "$FRONTEND_DIR" "$FRONTEND_REF" "frontend"

echo "\n== Applying Laravel runtime reset =="
cd "$ROOT_DIR"
mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

php artisan optimize:clear

if [[ "$RUN_MIGRATE" == "1" ]]; then
    php artisan migrate --force
fi

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

echo "\n== Rebuilding frontend and restarting PM2 =="
cd "$FRONTEND_DIR"
if [[ -f package-lock.json ]]; then
    npm ci --no-audit --no-fund
else
    npm install --no-audit --no-fund
fi

npm run build
pm2 restart "$PM2_APP_NAME" || pm2 start npm --name "$PM2_APP_NAME" --cwd "$FRONTEND_DIR" -- start
pm2 save || true

if [[ $down_active -eq 1 ]]; then
    echo "\n== Disabling maintenance mode =="
    cd "$ROOT_DIR"
    php artisan up || true
    down_active=0
fi

echo "\n== Quick verification =="
cd "$ROOT_DIR"
php artisan horizon:status || true
php artisan queue:failed || true

echo "\n✅ Rollback completed successfully"