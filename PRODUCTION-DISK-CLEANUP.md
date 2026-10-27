# Production disk cleanup (80 GB host)

Written 27 Oct 2026. Run on the server (`/var/www/vendorpulse`).  
Goal: find what is using space, free the safe junk, then shrink old monitoring data if needed.

VendorPulse already prunes some data automatically (if the scheduler is healthy):

| What | Default | Schedule |
|---|---|---|
| `monitoring_logs` rows | 90 days (`SITE_MONITORING_LOG_RETENTION_DAYS`) | daily 03:20 — `monitoring:prune-logs` |
| Email deliverability tests | 7 days | daily 03:40 — `email-test:prune` |
| Laravel daily log files | 14 days (`LOG_DAILY_DAYS`) | Laravel `daily` channel |

If disk is still ~88%, the usual culprits are **Docker image/build cache**, **Postgres data**, **Playwright browsers**, and **Laravel / Docker logs** — not only monitoring rows.

---

## 1. Diagnose first (copy-paste block)

```bash
cd /var/www/vendorpulse
C="docker compose -f docker-compose.yml -f docker-compose.bind.yml"

echo "=== Host disk ==="
df -h /

echo "=== Top folders under /var/www and /var/lib/docker ==="
sudo du -xh --max-depth=1 /var/www 2>/dev/null | sort -h | tail -20
sudo du -xh --max-depth=1 /var/lib/docker 2>/dev/null | sort -h | tail -20

echo "=== Project hotspots ==="
sudo du -xh --max-depth=1 /var/www/vendorpulse 2>/dev/null | sort -h | tail -30
sudo du -sh /var/www/vendorpulse/storage/logs \
  /var/www/vendorpulse/.playwright-browsers \
  /var/www/vendorpulse/storage/app \
  /var/www/vendorpulse/web_dashboard/node_modules \
  /var/www/vendorpulse/node_modules 2>/dev/null

echo "=== Docker usage ==="
docker system df -v | head -80

echo "=== Postgres table sizes (biggest first) ==="
$C exec -T postgres psql -U vendorpulse -d vendorpulse -c "
SELECT relname AS table,
       pg_size_pretty(pg_total_relation_size(c.oid)) AS total,
       pg_size_pretty(pg_relation_size(c.oid)) AS data,
       n_live_tup AS approx_rows
FROM pg_class c
JOIN pg_namespace n ON n.oid = c.relnamespace
LEFT JOIN pg_stat_user_tables s ON s.relid = c.oid
WHERE n.nspname = 'public' AND c.relkind = 'r'
ORDER BY pg_total_relation_size(c.oid) DESC
LIMIT 20;"

echo "=== monitoring_logs age ==="
$C exec -T postgres psql -U vendorpulse -d vendorpulse -c "
SELECT count(*) AS total,
       count(*) FILTER (WHERE created_at < now() - interval '90 days') AS older_than_90d,
       count(*) FILTER (WHERE created_at < now() - interval '60 days') AS older_than_60d,
       count(*) FILTER (WHERE created_at < now() - interval '30 days') AS older_than_30d,
       min(created_at) AS oldest,
       max(created_at) AS newest
FROM monitoring_logs;"

echo "=== Scheduler prune jobs present? ==="
$C exec -T scheduler php artisan schedule:list | grep -E 'prune|email-test' || true
```

Paste that output here if you want help deciding what to delete.  
If `postgres` user/db names differ, check `.env.docker` for `DB_USERNAME` / `DB_DATABASE`.

---

## 2. Safe cleanup (almost always free space, no app data loss)

Do these in order. Re-check `df -h /` after each.

### 2.1 Docker unused images, stopped containers, build cache

Often the biggest win on this stack (rebuilds leave old layers).

```bash
# Preview
docker system df

# Remove unused images, stopped containers, dangling networks, build cache
# Does NOT delete named volumes (Postgres data stays).
docker system prune -af
docker builder prune -af

df -h /
```

### 2.2 Laravel / app log files

```bash
cd /var/www/vendorpulse
du -sh storage/logs
ls -lah storage/logs | head -40

# Keep last 7 days of rotated logs; truncate today's giant laravel.log if needed
find storage/logs -type f -name '*.log' -mtime +7 -print -delete

# If laravel.log alone is huge (e.g. > 500 MB), truncate safely:
# truncate -s 0 storage/logs/laravel.log

df -h /
```

### 2.3 Journal / system logs (if host itself is full)

```bash
sudo journalctl --disk-usage
sudo journalctl --vacuum-size=200M
sudo journalctl --vacuum-time=7d
```

### 2.4 Old Playwright browser copies (only if folder is large)

```bash
du -sh /var/www/vendorpulse/.playwright-browsers
ls /var/www/vendorpulse/.playwright-browsers

# After cleanup, reinstall the one Chromium the app needs:
C="docker compose -f docker-compose.yml -f docker-compose.bind.yml"
$C exec horizon sh -c 'PLAYWRIGHT_BROWSERS_PATH=/var/www/html/.playwright-browsers npx playwright install chromium'
```

Only delete the whole `.playwright-browsers` folder if you are ready to reinstall immediately (experience monitoring needs it).

---

## 3. Database: prune monitoring logs (app data)

Default retention is **90 days**. History charts only go back that far anyway.

### 3.1 Dry-run (counts only)

```bash
cd /var/www/vendorpulse
C="docker compose -f docker-compose.yml -f docker-compose.bind.yml"

$C exec -T app php artisan monitoring:prune-logs --dry-run
# Optional: see how much a shorter window would free
$C exec -T app php artisan monitoring:prune-logs --days=60 --dry-run
$C exec -T app php artisan monitoring:prune-logs --days=30 --dry-run
```

### 3.2 Delete (default 90 days)

```bash
$C exec -T app php artisan monitoring:prune-logs
```

### 3.3 Reclaim disk inside Postgres after a big delete

Deleting rows does not shrink the data file until vacuum:

```bash
$C exec -T postgres psql -U vendorpulse -d vendorpulse -c "VACUUM (VERBOSE, ANALYZE) monitoring_logs;"
# If the table was huge and you need space back to the OS (locks briefly):
# $C exec -T postgres psql -U vendorpulse -d vendorpulse -c "VACUUM (FULL, ANALYZE) monitoring_logs;"
```

`VACUUM FULL` rewrites the table and needs free space roughly equal to the table size while it runs. Prefer normal `VACUUM` first unless disk is critical.

### 3.4 Optional: keep less than 90 days going forward

In **both** `.env` and `.env.docker`:

```bash
SITE_MONITORING_LOG_RETENTION_DAYS=60
```

Then:

```bash
C="docker compose -f docker-compose.yml -f docker-compose.bind.yml"
$C exec app php artisan optimize:clear
$C exec app php artisan optimize
$C restart app horizon scheduler
$C exec -T app php artisan monitoring:prune-logs
```

### 3.5 Email deliverability tests

```bash
$C exec -T app php artisan tinker --execute='echo app(App\Services\Vapt\Email\EmailDeliverabilityTestService::class)->prune();'
```

---

## 4. Failed jobs / queue clutter (small, but tidy)

```bash
C="docker compose -f docker-compose.yml -f docker-compose.bind.yml"
$C exec -T app php artisan queue:prune-failed --hours=168   # older than 7 days
$C exec -T postgres psql -U vendorpulse -d vendorpulse -c "SELECT count(*) FROM failed_jobs;"
```

---

## 5. What NOT to delete

| Path / volume | Why |
|---|---|
| Docker volume for Postgres (`docker volume ls`) | Live database |
| `.env` / `.env.docker` | Secrets |
| `storage/app/private` without checking | May hold experience-monitoring artefacts |
| Running container images you still use | Prefer `docker system prune` over manual `rmi` of tagged images |

---

## 6. Confirm scheduler is pruning nightly

If old rows keep growing forever, the prune schedule may not be running:

```bash
C="docker compose -f docker-compose.yml -f docker-compose.bind.yml"
$C ps
$C exec -T scheduler php artisan schedule:list | grep prune
$C logs --tail=50 scheduler
```

`scheduler` must be **Up**. You should see `site-monitoring:prune-logs` and `email-test:prune`.

---

## 7. Target after cleanup

| Before | After (typical) |
|---|---|
| ~88% of 80 GB | Often back under ~70% after Docker prune alone |
| Plus monitoring prune + vacuum | More if `monitoring_logs` was the bulk |

If after steps 2–3 you are still above ~85%, paste the diagnose output from section 1 and we pick the next target (screenshots, duplicate Playwright trees, large Docker volumes, etc.).
