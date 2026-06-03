#!/usr/bin/env bash
# Post-deploy checks for VAPT / Experience Monitoring (host Horizon + Playwright).
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

COMPOSE=(docker compose -f "$ROOT_DIR/docker-compose.yml" -f "$ROOT_DIR/docker-compose.bind.yml")

fail=0

check() {
    local label="$1"
    shift
    if "$@"; then
        echo "OK   $label"
    else
        echo "FAIL $label"
        fail=1
    fi
}

echo "== VAPT / Experience Monitoring verification =="
echo "path: $ROOT_DIR"
echo ""

NODE_BIN="$(command -v node || true)"
check "node on PATH" test -n "$NODE_BIN"
if [[ -n "$NODE_BIN" ]]; then
    echo "     node=$NODE_BIN ($("$NODE_BIN" -v 2>/dev/null || echo unknown))"
fi

if [[ -f .env ]]; then
    RUNNER="$(grep -E '^EXPERIENCE_MONITORING_RUNNER_COMMAND=' .env 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '"' | tr -d "'" | xargs || true)"
    if [[ -n "$RUNNER" ]]; then
        check "EXPERIENCE_MONITORING_RUNNER_COMMAND executable" test -x "$RUNNER"
        echo "     runner=$RUNNER"
    else
        echo "OK   EXPERIENCE_MONITORING_RUNNER_COMMAND unset (will resolve node from PATH)"
    fi

    BROWSERS="$(grep -E '^PLAYWRIGHT_BROWSERS_PATH=' .env 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '"' | tr -d "'" | xargs || true)"
    if [[ -n "$BROWSERS" ]]; then
        echo "     PLAYWRIGHT_BROWSERS_PATH=$BROWSERS"
        check "Playwright browsers directory exists" test -d "$BROWSERS"
    fi
fi

check "playwright npm module" test -d node_modules/playwright
if [[ -x "${NODE_BIN:-}" && -f package.json ]]; then
    check "playwright CLI" "$NODE_BIN" node_modules/playwright/cli.js --version
fi

check "host Horizon process" pgrep -f "artisan horizon"
check "host scheduler process" pgrep -f "artisan schedule:work"

if command -v docker >/dev/null 2>&1; then
    if "${COMPOSE[@]}" ps -q horizon 2>/dev/null | grep -q .; then
        HSTATUS="$("${COMPOSE[@]}" ps horizon 2>/dev/null | tail -n +2 || true)"
        if echo "$HSTATUS" | grep -qi running; then
            echo "FAIL Docker horizon is running (conflicts with host Horizon on this deploy path)"
            fail=1
        else
            echo "OK   Docker horizon not running"
        fi
    else
        echo "OK   Docker horizon container absent/stopped"
    fi
fi

echo ""
php artisan horizon:status 2>/dev/null || true
echo ""
php artisan queue:failed 2>/dev/null | head -n 20 || true
echo ""

php artisan tinker --execute="
\$test = \\App\\Models\\ExperienceMonitoringTest::query()->latest('updated_at')->first();
if (! \$test) { echo 'No experience tests in DB'.PHP_EOL; return; }
echo 'Latest test: '.\$test->name.PHP_EOL;
echo '  browser='.\$test->browser_type.' enabled='.(\$test->enabled ? 'yes' : 'no').PHP_EOL;
echo '  last_status='.(\$test->last_status ?? 'null').PHP_EOL;
echo '  last_error='.(\$test->last_error ?? 'null').PHP_EOL;
echo '  last_run_at='.(\$test->last_run_at ?? 'null').PHP_EOL;
" 2>/dev/null || true

echo ""
if [[ "$fail" -eq 0 ]]; then
    echo "✅ VAPT verification passed"
    exit 0
fi

echo "❌ VAPT verification failed — see FAIL lines above"
echo "   Fix .env (runner + PLAYWRIGHT_BROWSERS_PATH), run: npm run experience-monitoring:install"
echo "   Then: php artisan config:clear && php artisan optimize && ./deploy/workers_up.sh"
exit 1
