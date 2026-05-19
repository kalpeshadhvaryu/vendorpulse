#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"

cd "$ROOT_DIR"

echo "== Workers: ensure Horizon + scheduler =="

mkdir -p storage/logs

if pgrep -f "artisan horizon" >/dev/null 2>&1; then
    php artisan horizon:terminate || true
fi

nohup php artisan horizon > "$ROOT_DIR/storage/logs/horizon-host.log" 2>&1 &

horizon_ok=0
for _ in {1..12}; do
    if php artisan horizon:status 2>/dev/null | grep -Eiq "running|active"; then
        horizon_ok=1
        break
    fi

    if ! pgrep -f "artisan horizon" >/dev/null 2>&1; then
        nohup php artisan horizon > "$ROOT_DIR/storage/logs/horizon-host.log" 2>&1 &
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
    nohup php artisan schedule:work > "$ROOT_DIR/storage/logs/scheduler-host.log" 2>&1 &
fi

php artisan horizon:status || true
echo "✅ Workers are up"
