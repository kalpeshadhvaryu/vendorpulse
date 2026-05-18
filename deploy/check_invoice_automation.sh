#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

pass() {
  printf '[PASS] %s\n' "$1"
}

warn() {
  printf '[WARN] %s\n' "$1"
}

fail() {
  printf '[FAIL] %s\n' "$1"
}

echo "== Invoice Automation Health Check =="
echo "root: $ROOT_DIR"

if php artisan horizon:status >/tmp/vendorpulse_horizon_status.txt 2>&1; then
  pass "Horizon reachable"
else
  fail "Horizon not reachable"
  cat /tmp/vendorpulse_horizon_status.txt || true
fi

FAILED_COUNT="$(php artisan queue:failed --json 2>/dev/null | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo is_array($d)?count($d):0;')"
if [[ "$FAILED_COUNT" =~ ^[0-9]+$ ]] && (( FAILED_COUNT == 0 )); then
  pass "No failed queue jobs"
else
  warn "Failed queue jobs: ${FAILED_COUNT:-unknown}"
fi

AUTO_CFG="$(php artisan tinker --execute="echo json_encode(config('email-monitoring.invoice_automation'));" 2>/dev/null || true)"
if [[ -n "$AUTO_CFG" && "$AUTO_CFG" != "null" ]]; then
  pass "Invoice automation config loaded: $AUTO_CFG"
else
  fail "Invoice automation config is null/missing"
fi

AUTO_EVENTS="$(php artisan tinker --execute="echo App\\Models\\EmailLog::query()->whereRaw(\"processing_meta::text like '%invoice_automation%'\")->where('created_at','>=',now()->subDay())->count();" 2>/dev/null || true)"
if [[ "$AUTO_EVENTS" =~ ^[0-9]+$ ]] && (( AUTO_EVENTS > 0 )); then
  pass "Automation events in last 24h: $AUTO_EVENTS"
else
  warn "No automation events found in last 24h"
fi

PAID_RECENT="$(php artisan tinker --execute="echo App\\Models\\Invoice::query()->where('status','paid')->whereNotNull('paid_at')->where('updated_at','>=',now()->subDay())->count();" 2>/dev/null || true)"
if [[ "$PAID_RECENT" =~ ^[0-9]+$ ]]; then
  pass "Paid invoices updated in last 24h: $PAID_RECENT"
else
  warn "Could not read recent paid invoice count"
fi

echo "== Done =="
