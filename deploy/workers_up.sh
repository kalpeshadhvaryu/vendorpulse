#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"

cd "$ROOT_DIR"

echo "== Workers: ensure Horizon + scheduler =="

mkdir -p storage/logs

HORIZON_USER="${HORIZON_USER:-www-data}"
run_horizon() {
    if [[ "$(id -u)" -eq 0 ]] && id "$HORIZON_USER" &>/dev/null; then
        sudo -u "$HORIZON_USER" nohup php artisan horizon > "$ROOT_DIR/storage/logs/horizon-host.log" 2>&1 &
    else
        nohup php artisan horizon > "$ROOT_DIR/storage/logs/horizon-host.log" 2>&1 &
    fi
}

run_scheduler() {
    if [[ "$(id -u)" -eq 0 ]] && id "$HORIZON_USER" &>/dev/null; then
        sudo -u "$HORIZON_USER" nohup php artisan schedule:work > "$ROOT_DIR/storage/logs/scheduler-host.log" 2>&1 &
    else
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
echo "✅ Workers are up"
