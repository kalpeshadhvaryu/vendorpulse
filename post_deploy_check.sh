#!/usr/bin/env bash
# Run this after deploy to verify API, Horizon, scheduler, DB connectivity, and the latest monitoring log.
set -euo pipefail

ROOT="/var/www/vendorpulse"
COMPOSE=(docker compose -f "$ROOT/docker-compose.yml" -f "$ROOT/docker-compose.override.yml")
APP_PHP="${COMPOSE[@]} exec app php"

echo "== Next.js API URL =="
grep -E '^NEXT_PUBLIC_API_URL=' "$ROOT/web_dashboard/.env.production" || true

echo

echo "== Horizon / Queue =="
${COMPOSE[@]} exec horizon php artisan horizon:status || true
${COMPOSE[@]} exec app php artisan queue:failed || true

echo

echo "== Scheduler containers =="
${COMPOSE[@]} ps scheduler horizon app || true

echo

echo "== DB reachability from app container =="
${APP_PHP} -r '$h="172.19.0.1";$p=5433;$e=0;$s="";$c=@fsockopen($h,$p,$e,$s,5); var_export((bool)$c); echo PHP_EOL; echo "errno=$e err=$s".PHP_EOL;'

echo

echo "== App DB counts =="
${APP_PHP} artisan tinker --execute="dump(App\\Models\\User::count()); dump(App\\Models\\MonitoringCheck::count());"

echo

echo "== Latest monitoring log =="
${APP_PHP} artisan tinker --execute="dump(App\\Models\\MonitoringLog::query()->latest('created_at')->first()?->only(['monitoring_check_id','status','message','created_at']));"

echo

echo "== Done =="
