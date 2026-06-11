#!/usr/bin/env bash
# vpproduction.sh — VendorPulse production one-shot setup + deploy helper.
#
# FIRST TIME SETUP (run once on the production server):
#   bash vpproduction.sh setup
#
# NORMAL DEPLOY (every time you push code):
#   bash vpproduction.sh deploy
#
# VERIFY (check everything is healthy after deploy):
#   bash vpproduction.sh verify

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
COMPOSE=(docker compose -f "$ROOT_DIR/docker-compose.yml" -f "$ROOT_DIR/docker-compose.bind.yml")

# ─── colours ────────────────────────────────────────────────────────────────
GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; NC='\033[0m'
ok()   { echo -e "${GREEN}✅  $*${NC}"; }
warn() { echo -e "${YELLOW}⚠️   $*${NC}"; }
err()  { echo -e "${RED}❌  $*${NC}"; exit 1; }

ACTION="${1:-help}"

ensure_env_kv() {
    local file="$1"
    local key="$2"
    local value="$3"

    if [[ ! -f "$file" ]]; then
        return 0
    fi

    if grep -q "^${key}=" "$file"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$file"
    else
        printf '\n%s=%s\n' "$key" "$value" >> "$file"
    fi
}

docker_exec() {
    local service="$1"
    shift
    "${COMPOSE[@]}" exec -T "$service" "$@"
}

stop_host_workers() {
    echo "-- Stopping host-side workers (if any) --"

    local found=0
    if pgrep -af "php artisan horizon|php8\.[0-9]+ artisan horizon|artisan horizon:supervisor|artisan horizon:work" >/dev/null 2>&1; then
        found=1
    fi

    if pgrep -af "php artisan schedule:work|php8\.[0-9]+ artisan schedule:work" >/dev/null 2>&1; then
        found=1
    fi

    if [[ "$found" -eq 0 ]]; then
        ok "No host worker processes detected."

        return 0
    fi

    php artisan horizon:terminate >/dev/null 2>&1 || true
    pkill -f "php artisan schedule:work|php8\.[0-9]+ artisan schedule:work" >/dev/null 2>&1 || true

    local attempts=0
    while pgrep -af "php artisan horizon|php8\.[0-9]+ artisan horizon|artisan horizon:supervisor|artisan horizon:work|php artisan schedule:work|php8\.[0-9]+ artisan schedule:work" >/dev/null 2>&1; do
        attempts=$((attempts+1))
        if [[ $attempts -ge 10 ]]; then
            warn "Host worker processes are still present after stop attempt."
            pgrep -af "artisan horizon|artisan schedule:work" || true

            return 0
        fi
        sleep 1
    done

    ok "Host worker processes stopped."
}

# ─── SETUP ──────────────────────────────────────────────────────────────────
cmd_setup() {
    echo "== VendorPulse: First-time production setup =="

    # 1. Create .env.docker from .env with Docker-internal hostnames
    if [[ ! -f "$ROOT_DIR/.env" ]]; then
        err ".env not found at $ROOT_DIR/.env — copy deploy/liveproduction.env.template to .env and fill in secrets first."
    fi

    if [[ -f "$ROOT_DIR/.env.docker" ]]; then
        warn ".env.docker already exists — skipping creation. Delete it and re-run if you want to recreate."
    else
        cp "$ROOT_DIR/.env" "$ROOT_DIR/.env.docker"
        sed -i 's/^DB_HOST=.*/DB_HOST=postgres/'   "$ROOT_DIR/.env.docker"
        sed -i 's/^DB_PORT=.*/DB_PORT=5432/'        "$ROOT_DIR/.env.docker"
        sed -i 's/^REDIS_HOST=.*/REDIS_HOST=redis/' "$ROOT_DIR/.env.docker"
        sed -i 's/^REDIS_PORT=.*/REDIS_PORT=6379/'  "$ROOT_DIR/.env.docker"
        ok ".env.docker created with Docker-internal hostnames (postgres:5432, redis:6379)."
    fi

    # 1b. Enforce log-reduction settings in both host and docker env files
    ensure_env_kv "$ROOT_DIR/.env" "SITE_MONITORING_LOG_ONLY_CHANGES" "true"
    ensure_env_kv "$ROOT_DIR/.env" "SITE_MONITORING_LOG_HEARTBEAT_SECONDS" "3600"
    ensure_env_kv "$ROOT_DIR/.env" "EXPERIENCE_MONITORING_RUNNER_COMMAND" "node"
    ensure_env_kv "$ROOT_DIR/.env" "PLAYWRIGHT_BROWSERS_PATH" "$ROOT_DIR/.playwright-browsers"
    ensure_env_kv "$ROOT_DIR/.env.docker" "SITE_MONITORING_LOG_ONLY_CHANGES" "true"
    ensure_env_kv "$ROOT_DIR/.env.docker" "SITE_MONITORING_LOG_HEARTBEAT_SECONDS" "3600"
    ensure_env_kv "$ROOT_DIR/.env.docker" "EXPERIENCE_MONITORING_RUNNER_COMMAND" "node"
    ensure_env_kv "$ROOT_DIR/.env.docker" "PLAYWRIGHT_BROWSERS_PATH" "/var/www/html/.playwright-browsers"
    ok "Monitoring log retention flags enforced in .env and .env.docker."

    # 2. Verify .env.docker has correct values
    DB_HOST_DOCKER=$(grep "^DB_HOST=" "$ROOT_DIR/.env.docker" | cut -d= -f2)
    REDIS_HOST_DOCKER=$(grep "^REDIS_HOST=" "$ROOT_DIR/.env.docker" | cut -d= -f2)
    [[ "$DB_HOST_DOCKER" == "postgres" ]]  || err ".env.docker DB_HOST is '$DB_HOST_DOCKER', expected 'postgres'."
    [[ "$REDIS_HOST_DOCKER" == "redis" ]]  || err ".env.docker REDIS_HOST is '$REDIS_HOST_DOCKER', expected 'redis'."
    ok ".env.docker values verified."

    # 3. Ensure .env.docker is gitignored
    if ! grep -q "\.env\.docker" "$ROOT_DIR/.gitignore" 2>/dev/null; then
        echo ".env.docker" >> "$ROOT_DIR/.gitignore"
        ok ".env.docker added to .gitignore."
    fi

    echo ""
    ok "Setup complete. You can now run:  bash vpproduction.sh deploy"
}

# ─── DEPLOY ─────────────────────────────────────────────────────────────────
cmd_deploy() {
    echo "== VendorPulse: Production deploy =="

    [[ -f "$ROOT_DIR/.env.docker" ]] || \
        err ".env.docker missing. Run 'bash vpproduction.sh setup' first."

    stop_host_workers

    # Bring up / rebuild
    echo "-- Rebuilding and restarting containers --"
    "${COMPOSE[@]}" up -d --build --force-recreate

    # Wait for app to be up
    echo "-- Waiting for app container --"
    local attempts=0
    until docker_exec app php -r 'exit(0);' 2>/dev/null; do
        attempts=$((attempts+1))
        [[ $attempts -ge 30 ]] && err "App container did not become ready in time."
        sleep 2
    done

    # Clear caches inside container (uses .env.docker, so correct DB host)
    echo "-- Clearing and rebuilding Laravel caches --"
    docker_exec app php artisan optimize:clear
    docker_exec app php artisan migrate --force
    docker_exec app php artisan optimize

    # Ensure Playwright browser binaries exist in container runtime used by queue workers.
    echo "-- Installing Playwright Chromium in Docker runtime --"
    docker_exec horizon npx playwright install chromium >/dev/null
    docker_exec app npx playwright install chromium >/dev/null

    # Restart worker services so they pick fresh env and browser cache state.
    echo "-- Restarting worker services (horizon/scheduler) --"
    "${COMPOSE[@]}" restart horizon scheduler >/dev/null

    ok "Deploy complete."
    echo ""
    echo "Run 'bash vpproduction.sh verify' to confirm everything is healthy."
}

# ─── VERIFY ─────────────────────────────────────────────────────────────────
cmd_verify() {
    echo "== VendorPulse: Post-deploy verification =="

    # DB connectivity
    echo -n "DB connection... "
    docker_exec app php artisan tinker \
        --execute='DB::connection()->getPdo(); echo "ok";' 2>/dev/null | grep -q "ok" \
        && ok "DB reachable" || err "DB not reachable."

    # .env.docker DB host sanity check
    echo -n ".env.docker DB host... "
    DB_HOST=$(grep "^DB_HOST=" "$ROOT_DIR/.env.docker" | cut -d= -f2)
    [[ "$DB_HOST" == "postgres" ]] && ok "postgres (correct)" || warn "is '$DB_HOST' — should be 'postgres'."

    # User + monitoring check counts
    echo "-- Data counts --"
    docker_exec app php artisan tinker \
        --execute='echo "Users: ".App\Models\User::count()."\nMonitoring checks: ".App\Models\MonitoringCheck::withoutGlobalScopes()->count();' \
        2>/dev/null || warn "Could not read counts."

    # Latest monitoring log
    echo "-- Latest monitoring log --"
    docker_exec app php artisan tinker \
        --execute='$l=App\Models\MonitoringLog::latest()->first(); echo $l ? json_encode($l->only(["monitoring_check_id","status","created_at"])) : "none";' \
        2>/dev/null || warn "Could not read monitoring log."

    # Horizon status
    echo -n "Horizon... "
    docker_exec horizon php artisan horizon:status 2>/dev/null || warn "Horizon container not responding."

    # Playwright binary sanity check (experience monitoring jobs depend on this).
    echo -n "Playwright browser cache... "
    if docker_exec horizon sh -lc 'test -x /var/www/html/.playwright-browsers/chromium_headless_shell-1223/chrome-headless-shell-linux64/chrome-headless-shell || find /var/www/html/.playwright-browsers -name chrome-headless-shell | grep -q .'; then
        ok "installed"
    else
        warn "missing in horizon runtime (run: docker compose exec horizon npx playwright install chromium)"
    fi

    # Failed jobs
    echo "-- Failed queue jobs --"
    docker_exec app php artisan queue:failed 2>/dev/null || true

    # Mixed host + container workers can cause runtime drift and inconsistent queue behavior.
    if pgrep -af "artisan horizon|artisan schedule:work" >/dev/null 2>&1; then
        warn "Host worker processes detected. Prefer Docker-only workers to avoid drift."
        pgrep -af "artisan horizon|artisan schedule:work" || true
    fi

    echo ""
    ok "Verification complete."
}

# ─── HELP ───────────────────────────────────────────────────────────────────
cmd_help() {
    echo ""
    echo "  vpproduction.sh — VendorPulse production helper"
    echo ""
    echo "  Commands:"
    echo "    setup    — Run ONCE on fresh server: creates .env.docker, gitignores it."
    echo "    deploy   — Pull latest containers, migrate, optimize."
    echo "    verify   — Check DB, counts, Horizon, failed jobs after deploy."
    echo ""
    echo "  Typical first-time flow:"
    echo "    1. Copy deploy/liveproduction.env.template → .env and fill secrets."
    echo "    2. bash vpproduction.sh setup"
    echo "    3. bash vpproduction.sh deploy"
    echo "    4. bash vpproduction.sh verify"
    echo ""
    echo "  Every future deploy:"
    echo "    git pull origin kalpesh && bash vpproduction.sh deploy"
    echo ""
}

case "$ACTION" in
    setup)   cmd_setup   ;;
    deploy)  cmd_deploy  ;;
    verify)  cmd_verify  ;;
    *)       cmd_help    ;;
esac
