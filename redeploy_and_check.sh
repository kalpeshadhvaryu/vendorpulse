#!/usr/bin/env bash
# Use this to restart the Docker stack and then verify database access, queue health, and the latest monitoring log.
set -euo pipefail

ROOT="/var/www/vendorpulse"
COMPOSE=(docker compose -f "$ROOT/docker-compose.yml" -f "$ROOT/docker-compose.override.yml")

printf '\n== Restarting app stack ==\n'
"${COMPOSE[@]}" up -d app horizon scheduler

printf '\n== App / DB counts ==\n'
"${COMPOSE[@]}" exec app php artisan tinker --execute="dump(App\\Models\\User::count()); dump(App\\Models\\MonitoringCheck::count());"

printf '\n== DB connectivity ==\n'
"${COMPOSE[@]}" exec app php -r '
$h="172.19.0.1";
$p=5433;
$e=0;
$s="";
$c=@fsockopen($h,$p,$e,$s,5);
var_export((bool)$c);
echo PHP_EOL;
echo "errno=$e err=$s".PHP_EOL;
'

printf '\n== Horizon / queue ==\n'
"${COMPOSE[@]}" exec horizon php artisan horizon:status || true
"${COMPOSE[@]}" exec app php artisan queue:failed || true

printf '\n== Latest monitoring log ==\n'
"${COMPOSE[@]}" exec app php artisan tinker --execute="dump(App\\Models\\MonitoringLog::query()->latest('created_at')->first()?->only(['monitoring_check_id','status','message','created_at']));"

printf '\n== Done ==\n'
