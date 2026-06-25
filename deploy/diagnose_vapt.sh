#!/usr/bin/env bash
# Diagnose and optionally run one VAPT test synchronously (bypasses queue).
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

COMPOSE=(docker compose -f "$ROOT_DIR/docker-compose.yml" -f "$ROOT_DIR/docker-compose.bind.yml")
TEST_NAME="${1:-}"
RUN_SYNC="${RUN_SYNC:-0}"

echo "== VAPT diagnose =="
echo "path: $ROOT_DIR"
echo ""

echo "-- Horizon config (cached) --"
php artisan tinker --execute="
echo 'supervisor-1: '.implode(', ', (array) config('horizon.defaults.supervisor-1.queue', [])).PHP_EOL;
\$vapt = config('horizon.defaults.supervisor-experience-monitoring.queue', null);
echo 'supervisor-experience-monitoring: '.(\$vapt ? implode(', ', (array) \$vapt) : 'MISSING').PHP_EOL;
echo 'VAPT job queue: '.config('experience-monitoring.queue', 'experience-monitoring').PHP_EOL;
"

echo ""
echo "-- Horizon workers (host) --"
pgrep -af "artisan horizon" || echo "(no horizon on host)"
pgrep -af "horizon:work" || echo "(no horizon:work on host)"

echo ""
echo "-- Horizon workers (docker) --"
if command -v docker >/dev/null 2>&1 && "${COMPOSE[@]}" ps -q horizon 2>/dev/null | grep -q .; then
    "${COMPOSE[@]}" exec horizon pgrep -af "horizon:work" 2>/dev/null || echo "(no workers in container)"
else
    echo "(docker horizon not running)"
fi

echo ""
echo "-- Redis queue depth --"
php artisan tinker --execute="
\$name = config('experience-monitoring.queue', 'experience-monitoring');
\$redis = Illuminate\Support\Facades\Redis::connection();
\$prefix = config('database.redis.options.prefix', '');
\$key = \$prefix.'queues:'.\$name;
echo 'key='.\$key.' depth='.\$redis->llen(\$key).PHP_EOL;
"

echo ""
echo "-- Failed jobs (experience) --"
php artisan queue:failed 2>/dev/null | grep -i experience || echo "(none matching experience)"

echo ""
echo "-- Test: ${TEST_NAME:-latest} --"
if [[ -n "$TEST_NAME" ]]; then
    php artisan tinker --execute="
\$test = \\App\\Models\\ExperienceMonitoringTest::withoutGlobalScopes()->where('name', '$TEST_NAME')->first();
if (! \$test) { echo 'Test not found'.PHP_EOL; return; }
echo 'id='.\$test->id.PHP_EOL;
echo 'name='.\$test->name.PHP_EOL;
echo 'enabled='.(\$test->enabled ? 'yes' : 'no').PHP_EOL;
echo 'browser='.\$test->browser_type.PHP_EOL;
echo 'last_status='.(\$test->last_status ?? 'pending').PHP_EOL;
echo 'last_run_at='.(\$test->last_run_at ?? 'never').PHP_EOL;
echo 'last_error='.(\$test->last_error ?? 'none').PHP_EOL;
file_put_contents('/tmp/vapt-diagnose-test-id', \$test->id);
"
else
    php artisan tinker --execute="
\$test = \\App\\Models\\ExperienceMonitoringTest::withoutGlobalScopes()->orderByDesc('updated_at')->first();
if (! \$test) { echo 'Test not found'.PHP_EOL; return; }
echo 'id='.\$test->id.PHP_EOL;
echo 'name='.\$test->name.PHP_EOL;
echo 'enabled='.(\$test->enabled ? 'yes' : 'no').PHP_EOL;
echo 'browser='.\$test->browser_type.PHP_EOL;
echo 'last_status='.(\$test->last_status ?? 'pending').PHP_EOL;
echo 'last_run_at='.(\$test->last_run_at ?? 'never').PHP_EOL;
echo 'last_error='.(\$test->last_error ?? 'none').PHP_EOL;
file_put_contents('/tmp/vapt-diagnose-test-id', \$test->id);
"
fi

if [[ "$RUN_SYNC" == "1" && -f /tmp/vapt-diagnose-test-id ]]; then
    TEST_ID="$(cat /tmp/vapt-diagnose-test-id)"
    echo ""
    echo "-- Running Playwright synchronously (test $TEST_ID) --"
    php artisan tinker --execute="
    try {
        app(\\App\\ExperienceMonitoring\\Services\\ExperienceMonitoringExecutionService::class)->execute('$TEST_ID', 1);
        \$t = \\App\\Models\\ExperienceMonitoringTest::withoutGlobalScopes()->find('$TEST_ID');
        echo 'done last_status='.(\$t->last_status ?? 'pending').PHP_EOL;
        echo 'last_error='.(\$t->last_error ?? 'none').PHP_EOL;
    } catch (Throwable \$e) {
        echo 'FAILED: '.\$e->getMessage().PHP_EOL;
    }
    "
fi

echo ""
echo "To run Playwright directly (skip queue): RUN_SYNC=1 $0 ${TEST_NAME:+"$TEST_NAME"}"
echo "To fix queue workers: php artisan config:clear && php artisan optimize"
echo "  docker compose -f docker-compose.yml -f docker-compose.bind.yml exec horizon php artisan horizon:terminate"
echo "  docker compose -f docker-compose.yml -f docker-compose.bind.yml restart horizon"
