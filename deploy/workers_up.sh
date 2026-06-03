#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"

cd "$ROOT_DIR"

echo "== Workers: ensure Horizon + scheduler =="

mkdir -p storage/logs storage/app/private/experience-monitoring

HORIZON_USER="${HORIZON_USER:-www-data}"
PLAYWRIGHT_DIR="${PLAYWRIGHT_BROWSERS_PATH:-$ROOT_DIR/.playwright-browsers}"

if [[ -f .env ]]; then
    configured_browsers="$(grep -E '^PLAYWRIGHT_BROWSERS_PATH=' .env 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '"' | tr -d "'" | xargs || true)"
    if [[ -n "$configured_browsers" ]]; then
        PLAYWRIGHT_DIR="$configured_browsers"
    fi
fi

mkdir -p "$PLAYWRIGHT_DIR"

if id "$HORIZON_USER" &>/dev/null; then
    chown -R "$HORIZON_USER:$HORIZON_USER" "$PLAYWRIGHT_DIR" 2>/dev/null || true
    chown -R "$HORIZON_USER:$HORIZON_USER" storage/app/private/experience-monitoring 2>/dev/null || true
fi

if ! find "$PLAYWRIGHT_DIR" -name 'chrome-headless-shell' -o -name 'chrome' 2>/dev/null | grep -q .; then
    echo "⚠️  Playwright browsers missing under $PLAYWRIGHT_DIR — installing chromium..."
    export PLAYWRIGHT_BROWSERS_PATH="$PLAYWRIGHT_DIR"
    npx playwright install chromium
    if id "$HORIZON_USER" &>/dev/null; then
        chown -R "$HORIZON_USER:$HORIZON_USER" "$PLAYWRIGHT_DIR" 2>/dev/null || true
    fi
fi

run_horizon() {
    if [[ "$(id -u)" -eq 0 ]] && id "$HORIZON_USER" &>/dev/null; then
        sudo -u "$HORIZON_USER" env PLAYWRIGHT_BROWSERS_PATH="$PLAYWRIGHT_DIR" \
            nohup php artisan horizon > "$ROOT_DIR/storage/logs/horizon-host.log" 2>&1 &
    else
        env PLAYWRIGHT_BROWSERS_PATH="$PLAYWRIGHT_DIR" \
            nohup php artisan horizon > "$ROOT_DIR/storage/logs/horizon-host.log" 2>&1 &
    fi
}

run_scheduler() {
    if [[ "$(id -u)" -eq 0 ]] && id "$HORIZON_USER" &>/dev/null; then
        sudo -u "$HORIZON_USER" env PLAYWRIGHT_BROWSERS_PATH="$PLAYWRIGHT_DIR" \
            nohup php artisan schedule:work > "$ROOT_DIR/storage/logs/scheduler-host.log" 2>&1 &
    else
        env PLAYWRIGHT_BROWSERS_PATH="$PLAYWRIGHT_DIR" \
            nohup php artisan schedule:work > "$ROOT_DIR/storage/logs/scheduler-host.log" 2>&1 &
    fi
}

if pgrep -f "artisan horizon" >/dev/null 2>&1; then
    php artisan horizon:terminate || true
fi

run_horizon

horizon_ok=0
for _ in {1..12}; do
    if php artisan horizon:status 2>/dev/null | grep -Eiq "running|active"; then
        horizon_ok=1
        break
    fi

    if ! pgrep -f "artisan horizon" >/dev/null 2>&1; then
        run_horizon
    fi

    sleep 1
done

if [[ "$horizon_ok" != "1" ]]; then
    echo "❌ Horizon did not become active"
    echo "Last Horizon log lines:"
    tail -n 80 "$ROOT_DIR/storage/logs/horizon-host.log" || true
    exit 1
fi

if ! pgrep -f "artisan schedule:work" >/dev/null 2>&1; then
    run_scheduler
fi

php artisan horizon:status || true
echo "✅ Workers are up (PLAYWRIGHT_BROWSERS_PATH=$PLAYWRIGHT_DIR, user=$HORIZON_USER)"
