# Email Deliverability Test & Email Security — Setup Guide

Written 12 Oct 2026. Follow the steps in order; each part says where to run it (your laptop or the server).

---

## Update 13 Oct 2026 — production is live

Production uses the cPanel mailbox `vendorpulse@my.veravalonline.com` (Exim on the Vogue Hosting VPS), not Google:

```bash
EMAIL_TEST_ADDRESS=vendorpulse@my.veravalonline.com
EMAIL_TEST_IMAP_HOST=mail.my.veravalonline.com
EMAIL_TEST_IMAP_USERNAME=vendorpulse@my.veravalonline.com
EMAIL_TEST_IMAP_PASSWORD=<mailbox password>
EMAIL_TEST_TRUSTED_AUTHSERV_ID=          # empty (NOT mx.google.com — this mailbox is not on Google)
```

Lessons from the setup:
- Settings must be in **both** `.env` and `.env.docker`, then
  `exec app php artisan optimize:clear && exec app php artisan optimize` and restart `app horizon scheduler`
  (an old `bootstrap/cache/config.php` made the tool look "not configured").
- Never list a variable twice — the **last** line wins.
- **cPanel:** Email Accounts → Manage `vendorpulse@` → *Automatically Create Folders for Plus Addressing* →
  **Do not automatically create folders**. Otherwise each test email is filed into its own `vp-xxxx` folder
  and VendorPulse (which reads INBOX) never sees it.
- If using a cPanel mailbox for a new environment, apply the same setting.

The sections below were written for a Google mailbox and still apply if you ever switch to one.

---

## 0. Where things stood on 12 Oct (before setup)

| Item | Status |
|---|---|
| Code (email security checks, DKIM finder, send-a-test-email tool) | Done and working **locally**. **Not deployed** to production yet. |
| Test mailbox | **Not created yet.** |
| Local `.env` (lines 97–113) | Block added, but lines 97, 98, 101, 102 still contain the **placeholders** (`<...>`). |
| Local `docker-compose.override.yml` | Not created yet (already listed in `.gitignore`, so it can't be committed by mistake). |
| Production `.env` / `.env.docker` | Nothing added yet. |

**Why the tool says "not available":** it switches on only when all four of these have real values:
`EMAIL_TEST_ADDRESS`, `EMAIL_TEST_IMAP_HOST`, `EMAIL_TEST_IMAP_USERNAME`, `EMAIL_TEST_IMAP_PASSWORD`.
Until then the dashboard page shows "The test mailbox is not configured" and the public page hides the section.

**Where the tool appears once it is on:**
- Dashboard: **Cybersecurity & VAPT → Email Deliverability Test** (`/web_dashboard/vapt-web-health/email-test/`)
- Public page: **Email deliverability test** section under the domain check box.

---

## 1. Create the test mailbox (once, in Google Workspace admin)

Use a **dedicated** mailbox that receives nothing else, e.g. `mailtest@veravalonline.com`.
Matched test emails are deleted from it after they are read.

1. **Google Admin → Users → Add user** → `mailtest@veravalonline.com`.
2. Sign in as that user once (accept terms).
3. Turn on **2-Step Verification**: Google Account → Security → 2-Step Verification.
   (If the option is missing, the Workspace admin must allow 2SV: Admin → Security → Authentication.)
4. Create an **App password**: Google Account → Security → App passwords → name it `VendorPulse`.
   Google shows 16 letters in 4 groups, e.g. `abcd efgh ijkl mnop`.
   **Write it without spaces:** `abcdefghijklmnop`.
5. Check IMAP is on: Gmail → Settings → See all settings → *Forwarding and POP/IMAP* → **Enable IMAP** → Save.
6. **Stop test emails landing in Spam** (the tool only reads the Inbox):
   Gmail → Settings → *Filters and Blocked Addresses* → Create a new filter →
   To: `mailtest+*` → *Create filter* → tick **Never send it to Spam** → Create.

Plus-addressing (`mailtest+anything@veravalonline.com`) works automatically in Google Workspace.

> **Using Microsoft 365 instead?** Create the user, enable IMAP for it
> (Admin center → Users → Mail → Manage email apps → IMAP), check plus addressing is on
> (Exchange admin → Settings → Mail flow), and use host `outlook.office365.com`.
> Leave `EMAIL_TEST_TRUSTED_AUTHSERV_ID` empty for Microsoft 365.

---

## 2. The env block (same values for local and server)

Replace the whole block in `.env` with this, filling in the real app password. **No `<` `>` brackets, no spaces in the password.**

```bash
# --- Email deliverability test (send-a-test-email tool) ---
EMAIL_TEST_ADDRESS=mailtest@veravalonline.com
EMAIL_TEST_IMAP_HOST=imap.gmail.com
EMAIL_TEST_IMAP_PORT=993
EMAIL_TEST_IMAP_ENCRYPTION=ssl
EMAIL_TEST_IMAP_USERNAME=mailtest@veravalonline.com
EMAIL_TEST_IMAP_PASSWORD=abcdefghijklmnop
EMAIL_TEST_IMAP_FOLDER=INBOX
EMAIL_TEST_DELETE_AFTER_READ=true
EMAIL_TEST_TRUSTED_AUTHSERV_ID=mx.google.com
EMAIL_TEST_EXPIRES_MINUTES=30
EMAIL_TEST_INBOX_CHECK_INTERVAL=5
EMAIL_TEST_RETENTION_DAYS=7

# --- Email security checks on DNS Check ---
EMAIL_SECURITY_BLACKLIST_ENABLED=true
EMAIL_SECURITY_BLACKLIST_MAX_IPS=2
EMAIL_SECURITY_SPAMHAUS_DQS_KEY=
```

What each one does:

| Variable | Meaning |
|---|---|
| `EMAIL_TEST_ADDRESS` | The mailbox address. Test addresses are built from it: `mailtest+vp-xxxx@…` |
| `EMAIL_TEST_IMAP_HOST / PORT / ENCRYPTION` | How VendorPulse logs in to read it (Gmail: `imap.gmail.com`, 993, ssl) |
| `EMAIL_TEST_IMAP_USERNAME / PASSWORD` | Mailbox login (password = app password) |
| `EMAIL_TEST_TRUSTED_AUTHSERV_ID` | Only trust SPF/DKIM/DMARC results written by Google's server, so senders can't fake them. Empty for Microsoft 365. |
| `EMAIL_TEST_EXPIRES_MINUTES` | How long a test address stays valid |
| `EMAIL_TEST_INBOX_CHECK_INTERVAL` | Seconds between inbox checks while someone waits |
| `EMAIL_TEST_RETENTION_DAYS` | Old test results deleted after this many days |
| `EMAIL_SECURITY_SPAMHAUS_DQS_KEY` | Optional. Free key from spamhaus.com (Data Query Service). Without it, Spamhaus blacklists show as "unverified" because Spamhaus blocks queries from public DNS. |

---

## 3. Local setup (your laptop, WSL)

The local containers do **not** read `.env` directly; they only get variables listed in the compose file.
So locally you need one extra file. (Production does not need it — see step 5.)

**3.1** Fix the placeholders in `.env` (step 2) and **save** (Ctrl+S).

**3.2** Create `docker-compose.override.yml` in the project root:

```yaml
services:
  app:
    environment: &email_env
      EMAIL_TEST_ADDRESS: ${EMAIL_TEST_ADDRESS:-}
      EMAIL_TEST_IMAP_HOST: ${EMAIL_TEST_IMAP_HOST:-}
      EMAIL_TEST_IMAP_USERNAME: ${EMAIL_TEST_IMAP_USERNAME:-}
      EMAIL_TEST_IMAP_PASSWORD: ${EMAIL_TEST_IMAP_PASSWORD:-}
      EMAIL_TEST_TRUSTED_AUTHSERV_ID: ${EMAIL_TEST_TRUSTED_AUTHSERV_ID:-}
      EMAIL_SECURITY_SPAMHAUS_DQS_KEY: ${EMAIL_SECURITY_SPAMHAUS_DQS_KEY:-}
  scheduler:
    environment: *email_env
  horizon:
    environment: *email_env
```

**3.3** Restart:

```bash
cd ~/projects/vendorpulse
docker compose up -d app scheduler horizon
```

**3.4** Check (see step 6 for what the output means):

```bash
docker compose exec app php artisan tinker --execute='var_dump(app(App\Services\Vapt\Email\EmailDeliverabilityTestService::class)->isAvailable());'
docker compose exec app php artisan tinker --execute='var_dump(app(App\Services\Vapt\Email\TestMailboxReader::class)->findMessages(["zzzzzzzzzz"]));'
```

---

## 4. Push the code (your laptop)

Production has two repos (root + nested `web_dashboard`). Push both.

```bash
# Backend / root
cd ~/projects/vendorpulse
git status                      # .env and docker-compose.override.yml must NOT appear
git add -A
git commit -m "Email security checks, DKIM finder, email deliverability test"
git push origin kalpesh

# Frontend
cd ~/projects/vendorpulse/web_dashboard
git add -A
git commit -m "Email deliverability test page, DKIM finder"
git push origin kalpesh
```

---

## 5. Production setup (server)

Production mounts `.env.docker` into the app, horizon and scheduler containers, so **only the env files are needed — no `.yml` file.**

**5.1** Add the block from step 2 to **both** files (keep them in sync):

```bash
cd /var/www/vendorpulse
nano .env          # paste block at the end, save (Ctrl+O, Enter, Ctrl+X)
nano .env.docker   # paste the same block
```

Make sure the block starts on a **new line** (not joined to the previous last line).

**5.2** Pull and deploy (this also runs the new database migration and rebuilds the config cache):

```bash
cd /var/www/vendorpulse && git pull origin kalpesh && \
cd /var/www/vendorpulse/web_dashboard && git pull origin kalpesh && \
cd /var/www/vendorpulse && bash vpproduction.sh deploy && bash vpproduction.sh verify && \
docker compose -f docker-compose.yml -f docker-compose.bind.yml build frontend && \
docker compose -f docker-compose.yml -f docker-compose.bind.yml up -d frontend
```

**5.3** Check:

```bash
cd /var/www/vendorpulse
docker compose -f docker-compose.yml -f docker-compose.bind.yml exec app php artisan tinker --execute='var_dump(app(App\Services\Vapt\Email\EmailDeliverabilityTestService::class)->isAvailable());'
docker compose -f docker-compose.yml -f docker-compose.bind.yml exec app php artisan tinker --execute='var_dump(app(App\Services\Vapt\Email\TestMailboxReader::class)->findMessages(["zzzzzzzzzz"]));'
docker compose -f docker-compose.yml -f docker-compose.bind.yml exec scheduler php artisan schedule:list | grep email-test
```

> Changing env values later? Production caches config. After editing `.env.docker`, run
> `bash vpproduction.sh deploy` again, or:
> `docker compose -f docker-compose.yml -f docker-compose.bind.yml exec app php artisan optimize`
> then `docker compose -f docker-compose.yml -f docker-compose.bind.yml restart app horizon scheduler`.

---

## 6. What the checks should print

| Command | Good | Problem |
|---|---|---|
| `isAvailable()` | `bool(true)` | `bool(false)` → one of the four required values is empty or not reaching the container (local: override file missing / `.env` not saved; server: not in `.env.docker` or config not refreshed). |
| `findMessages([...])` | `array(0) {}` → login works | `Test mailbox connection failed: ...` → see troubleshooting. |
| `schedule:list` | shows `email-test:process-inbox` and `email-test:prune` | nothing → scheduler container is on old code; rebuild/restart it. |

---

## 7. Try it end to end

1. Open the dashboard → **Cybersecurity & VAPT → Email Deliverability Test** → **Get test address**
   (or the public page → *Email deliverability test*).
2. Copy the address (`mailtest+vp-xxxxxxxxxx@veravalonline.com`).
3. Send any email to it from Outlook / Gmail / your website form.
4. Within about 5–60 seconds the page shows the grade, SPF/DKIM/DMARC results, alignment, sending server, TLS and the DKIM selector.

---

## 8. Troubleshooting

| Symptom | Fix |
|---|---|
| `AUTHENTICATIONFAILED` / `Invalid credentials` | Wrong app password, spaces left in it, or normal password used instead of an app password. Create a new app password. |
| `Web login required` / `Please log in via your web browser` | Sign in to that Gmail account once in a browser and approve the security prompt. |
| `IMAP access is disabled` | Enable IMAP in Gmail settings (step 1.5), or in Workspace Admin → Apps → Gmail → End user access → POP and IMAP access. |
| Test page keeps waiting | Check the email actually reached the mailbox Inbox (not Spam — add the filter in step 1.6). If your system rejects the `+` address, send to the base address with the subject shown on the page. |
| Result shows "No Authentication-Results header" | `EMAIL_TEST_TRUSTED_AUTHSERV_ID` doesn't match the mailbox provider. Gmail: `mx.google.com`; Microsoft 365: empty. |
| Spamhaus lists show "unverified" | Normal without a DQS key. Add `EMAIL_SECURITY_SPAMHAUS_DQS_KEY` if you want Spamhaus results. |
| Logs | Local: `docker compose logs --tail=80 scheduler app` · Server: `docker compose -f docker-compose.yml -f docker-compose.bind.yml logs --tail=80 scheduler app` |

---

## 9. Other open items (not env)

- Public DNS/WHOIS check shares its rate-limit counter with forgot/reset-password routes (unnamed `throttle:X,Y` groups share one per-IP counter). One-line fix per group if wanted.
- 8 tests in `tests/Feature/Api/OrganizationManagementTest.php` fail (admin routes return 403/422). Not caused by the email work; they never ran before because the laptop had no SQLite driver.
