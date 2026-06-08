#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
FRONTEND_DIR="$ROOT_DIR/web_dashboard"

BACKEND_BRANCH="${BACKEND_BRANCH:-kalpesh}"
FRONTEND_BRANCH="${FRONTEND_BRANCH:-kalpesh}"
FORCE_RESET="${FORCE_RESET:-0}"

# Force Docker Compose configuration using your manifest files
COMPOSE=(docker compose -f "$ROOT_DIR/docker-compose.yml" -f "$ROOT_DIR/docker-compose.bind.yml")

cleanup() {
    local rc=$?
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

    echo -e "\n== Syncing $label ($branch) =="
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

echo "🚀 Starting Container-Native Deployment Process"

require_cmd git
require_cmd docker

# Sync repository tracks natively
sync_repo "$ROOT_DIR" "$BACKEND_BRANCH" "backend"
sync_repo "$FRONTEND_DIR" "$FRONTEND_BRANCH" "frontend"

echo -e "\n== Step 1: Stopping current runtime stack layers =="
"${COMPOSE[@]}" down

echo -e "\n== Step 2: Triggering compilation and bringing up the entire environment stack =="
"${COMPOSE[@]}" up -d --build --force-recreate

echo -e "\n== Step 3: Ensuring Core Infrastructure services are completely initialized =="
wait_for_compose_service postgres 90
wait_for_compose_service redis 60
wait_for_compose_service app 60

echo -e "\n== Step 4: Syncing permissions and clearing framework caches inside the container =="
docker exec -t vendorpulse-app-1 mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
docker exec -t vendorpulse-app-1 chmod -R ug+rwX storage bootstrap/cache

# Clear internal caches inside the container so it doesn't leak to the host machine
docker exec -t vendorpulse-app-1 rm -f bootstrap/cache/config.php
docker exec -t vendorpulse-app-1 php artisan optimize:clear

echo -e "\n== Step 5: Running database schema migrations inside the Docker isolated network =="
docker exec -t vendorpulse-app-1 php artisan migrate --force

echo -e "\n== Step 6: Forcing runtime configuration and optimization caching =="
docker exec -t vendorpulse-app-1 php artisan optimize

echo -e "\n== Step 7: Injecting and synchronizing your global administrator profile user record =="
docker exec -t vendorpulse-app-1 php artisan tinker --execute="\$user = \App\Models\User::updateOrCreate(['email' => 'admin@vendorpulse.com'], ['name' => 'Global Admin', 'password' => bcrypt('Admin@123456')]);"

echo -e "\n✅ Deployment completed successfully inside Docker!"