# Experience Monitoring (VAPT) — Production

Browser-based login and dashboard checks run via **Playwright (Chromium only)** in **Horizon**.

Always work from **`/var/www/vendorpulse`** — not `/var/www/exportdoc`.

---

## Production deploy path (Contabo)

This server uses **host Horizon**, not Docker workers:

```bash
cd /var/www/vendorpulse
FORCE_RESET=1 ./deploy/release.sh deploy
```

`deploy/release.sh` → `deploy/deploy_host.sh`, which:

- Starts **postgres/redis** in Docker only
- Runs **Laravel + Horizon + scheduler on the host**
- Installs **Playwright Chromium** on the host
- Deploys **Next.js** via pm2

It does **not** start Docker `horizon` / `app`. If those containers are still running, they can conflict with host Horizon.

After deploy, verify VAPT:

```bash
cd /var/www/vendorpulse
./deploy/verify_vapt.sh
```

---

## If VAPT broke after `release.sh deploy`

### 1. Wrong Node path cached in config (most common)

Production `.env` must **not** contain a dev-machine nvm path, e.g.:

```env
EXPERIENCE_MONITORING_RUNNER_COMMAND=/home/veraval/.nvm/versions/node/v24.15.0/bin/node
```

`php artisan optimize` caches that path. Horizon then cannot run Playwright.

**Fix on server:**

```bash
cd /var/www/vendorpulse
# Use your server's node path:
which node
# Edit .env — set or replace:
# EXPERIENCE_MONITORING_RUNNER_COMMAND=/usr/bin/node
# PLAYWRIGHT_BROWSERS_PATH=/var/www/vendorpulse/.playwright-browsers

npm run experience-monitoring:install
php artisan config:clear
php artisan optimize
./deploy/workers_up.sh
./deploy/verify_vapt.sh
```

### 2. Docker Horizon still running

```bash
cd /var/www/vendorpulse
docker compose stop horizon scheduler app
./deploy/workers_up.sh
```

### 3. Failed jobs — read the error

```bash
cd /var/www/vendorpulse
php artisan queue:failed
tail -n 80 storage/logs/horizon-host.log
```

### 4. Create test fails — organization not selected

Global admin must pick **one organization** in the dashboard (not “All organizations”) before creating a VAPT test.

### 5. Status stuck on **pending** after clicking Play

**Pending** means `last_status` is empty — the test has **never finished a run** (or the queue job never completed).

Play only **queues** a job. Horizon must process the `experience-monitoring` queue within ~1–3 minutes.

**Diagnose on server:**

```bash
cd /var/www/vendorpulse

# Is host Horizon running?
php artisan horizon:status
pgrep -af "artisan horizon"

# Failed jobs (Playwright / node errors show here)
php artisan queue:failed

# Horizon log
tail -n 80 storage/logs/horizon-host.log

# Test row in DB
php artisan tinker --execute="
\$t = \\App\\Models\\ExperienceMonitoringTest::withoutGlobalScopes()->where('name', 'LIKE', '%exportdoc%')->first();
if (\$t) {
  echo \$t->name.' enabled='.(\$t->enabled?'yes':'no').' last_status='.(\$t->last_status ?? 'pending').PHP_EOL;
  echo 'last_error='.(\$t->last_error ?? 'null').PHP_EOL;
  echo 'last_run_at='.(\$t->last_run_at ?? 'never').PHP_EOL;
}
"

# Re-queue manually
php artisan tinker --execute="
\$id = \\App\\Models\\ExperienceMonitoringTest::withoutGlobalScopes()->value('id');
if (\$id) { \\App\\ExperienceMonitoring\\Jobs\\RunExperienceMonitoringTestJob::dispatch(\$id); echo \"queued \$id\"; }
"
```

**Common causes:**

| Cause | Fix |
|-------|-----|
| Host Horizon not running | `./deploy/workers_up.sh` |
| Docker Horizon still running (broken Playwright) | `docker compose stop horizon scheduler app` |
| Test **disabled** | Edit test → enable |
| Jobs in `queue:failed` | Read exception, fix Node/Playwright, `php artisan queue:retry all` |
| Stale overlap lock (re-triggered many times) | `php artisan horizon:terminate` then `./deploy/workers_up.sh` |

After Play succeeds, status should change from **pending** to `ok`, `js_error`, `error`, etc. within about 60 seconds.

---

## Quick deploy (minimal)

```bash
cd /var/www/vendorpulse
git pull
docker compose build --no-cache horizon
docker compose up -d app horizon scheduler
docker compose exec app php artisan config:clear
docker compose exec horizon npx playwright --version
```

Then trigger one test from the dashboard and confirm the screenshot loads.

---

## 1. Deploy latest code

```bash
cd /var/www/vendorpulse
git pull
```

Includes Chromium-only browser handling, shared storage for screenshots, and Dockerfile Playwright install.

If the dashboard is deployed separately:

```bash
cd /var/www/vendorpulse/web_dashboard
npm ci
npm run build
# restart Next.js (pm2/systemd — however you run it)
```

---

## 2. Rebuild and restart Docker workers

Horizon runs Playwright — **rebuild the image** after code or Dockerfile changes:

```bash
cd /var/www/vendorpulse
docker compose build --no-cache horizon
docker compose up -d app horizon scheduler
```

| Service     | Role |
|------------|------|
| `horizon`  | Runs Playwright jobs on the `experience-monitoring` queue |
| `scheduler`| Dispatches due tests every minute (`routes/console.php`) |
| `app`      | Serves API + screenshot files (must share storage with horizon) |

---

## 3. Production `.env`

In `/var/www/vendorpulse/.env`:

| Variable | Production value |
|----------|------------------|
| `APP_URL` | Real public API URL (not `127.0.0.1:3000`) |
| `WEB_DASHBOARD_URL` | Real dashboard URL |
| `EXPERIENCE_MONITORING_RUNNER_COMMAND` | **Unset in Docker** — use default `node`. Do not copy a local nvm path (e.g. `/home/.../.nvm/...`) |
| `EXPERIENCE_MONITORING_ALLOWED_BROWSERS` | Optional; default `chromium` |
| `APP_PORT` | Use `8001` if port 8000 is taken by another app |

Clear config after `.env` changes:

```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear
```

---

## 4. Verify Playwright inside Horizon

```bash
docker compose exec horizon node -v
docker compose exec horizon npx playwright --version
docker compose exec horizon ls -la /root/.cache/ms-playwright/ 2>/dev/null || true
```

If Chromium is missing:

```bash
docker compose exec horizon npm run experience-monitoring:install
```

Rebuilding the Docker image is the preferred long-term fix (`Dockerfile` runs `npx playwright install --with-deps chromium`).

---

## 5. Shared storage (screenshot 404 fix)

Horizon writes PNGs; the API serves them. Both **`app`** and **`horizon`** must mount the same `vendorpulse_storage` volume (see `docker-compose.yml`).

After changing compose volumes:

```bash
docker compose up -d app horizon
```

Verify both containers see the same files after a test run:

```bash
docker compose exec horizon ls -la storage/app/experience-monitoring/screenshots/
docker compose exec app ls -la storage/app/experience-monitoring/screenshots/
```

Do **not** mix host `php artisan serve` for the API with Docker Horizon unless you use a bind mount (`docker-compose.bind.yml`).

Old runs may have DB rows but no file — re-trigger the test after fixing storage.

---

## 6. Verify queues and scheduler

Horizon must process the `experience-monitoring` queue (see `config/horizon.php`):

```bash
docker compose ps
docker compose logs -f horizon --tail=50
docker compose exec app php artisan horizon:status
docker compose exec app php artisan queue:failed
```

Scheduler must be running:

```bash
docker compose logs scheduler --tail=20
```

---

## 7. Fix legacy browser types (optional)

Only **Chromium** is installed. Tests saved as `firefox` or `webkit` still run as Chromium automatically. To normalize the database:

```bash
docker compose exec app php artisan tinker --execute="
\App\Models\ExperienceMonitoringTest::whereIn('browser_type', ['firefox','webkit'])->update(['browser_type' => 'chromium']);
"
```

Or edit and save each test in the UI (browser picker is hidden; always uses Chromium).

---

## 8. Run a live test

1. Dashboard → **Monitoring → Experience Monitoring** → **Run** (trigger)
2. Wait ~30–60 seconds
3. Open test → **History** → check status
4. Open **Screenshots** — image should load (not 404)

CLI check:

```bash
docker compose exec app php artisan tinker --execute="
\$t = \App\Models\ExperienceMonitoringTest::latest()->first();
echo \$t?->name.' last='.\$t?->last_status.' at='.\$t?->last_run_at;
"
```

---

## Run status meanings

| Status | Meaning |
|--------|---------|
| `ok` | Login and dashboard load succeeded |
| `js_error` | Playwright ran, but the target site logged `console.error` (target app issue, not VendorPulse) |
| `failed_login` / `timeout` / `error` | Real test failure — inspect run history, browser logs, failed requests |

For `js_error`: open run **History → browser logs / failed requests** on the target site (e.g. ExportDoc 404 assets, Vite, API calls). Login can succeed while status is `js_error`.

---

## Common production mistakes

1. Running commands in **`/var/www/exportdoc`** instead of **`/var/www/vendorpulse`**
2. Host **nvm Node path** in `EXPERIENCE_MONITORING_RUNNER_COMMAND` inside Docker
3. Rebuilding only **`app`**, not **`horizon`**
4. **`scheduler`** not running — tests never get queued
5. Mixing **host API + Docker Horizon** without shared storage
6. Expecting screenshots on **old runs** after a storage fix — re-trigger the test

---

## API on port 8001

When port 8000 is used by ExportDoc or another app:

**`/var/www/vendorpulse/.env`:**

```
APP_PORT=8001
APP_URL=http://127.0.0.1:8001
```

**`web_dashboard/.env.local`:**

```
NEXT_PUBLIC_API_URL=http://127.0.0.1:8001/api/v1
```

Then:

```bash
docker compose up -d --build app horizon scheduler
```

API: `http://127.0.0.1:8001/api/v1`

---

## Host workers (non-Docker Horizon)

If Horizon runs on the host instead of Docker:

```bash
cd /var/www/vendorpulse
npm run experience-monitoring:install
npm run experience-monitoring:verify
./deploy/workers_up.sh
```

Set `EXPERIENCE_MONITORING_RUNNER_COMMAND` to your Node binary if `node` is not on PATH.
