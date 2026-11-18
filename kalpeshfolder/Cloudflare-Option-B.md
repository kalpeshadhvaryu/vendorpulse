# Cloudflare Option B — Company account, assign domains to clients

Implemented 18 Nov 2026.

## Model

- Domains live in **your** Cloudflare account (VeravalOnline / VendorPulse).
- VendorPulse syncs zones into `cloudflare_zones`.
- An admin **assigns** each zone to a client `organization_id`.
- Client users only see domains assigned to their organization.
- Clients do **not** need a Cloudflare login.

## Env

```bash
CLOUDFLARE_API_TOKEN=...          # API token with Zone:Read (and Cache Purge if used)
CLOUDFLARE_ACCOUNT_ID=...         # optional; limits sync to one account
CLOUDFLARE_ALLOW_ORG_PURGE_CACHE=true
```

Token permissions (minimum):
- Account → Zone → Zone:Read
- Zone → Cache Purge (if org purge is enabled)
- Zone → SSL and Certificates:Read (for SSL mode refresh)

## Screens

| Who | URL |
|---|---|
| Admin catalog + assign | `/admin/cloudflare-domains` |
| Client view | `/cloudflare/domains` |

## Flow

1. Set env → recreate `app` container / deploy.
2. Admin → **Sync from Cloudflare**.
3. Admin assigns each domain to an organization.
4. Client selects that org → **My Cloudflare Domains** → Refresh / Purge cache.

## API

- `GET /api/v1/cloudflare/status`
- `GET/POST /api/v1/admin/cloudflare/zones` (+ sync)
- `PATCH /api/v1/admin/cloudflare/zones/{id}/assign`
- `GET /api/v1/cloudflare/domains` (org-scoped)
- `POST .../refresh`, `POST .../purge-cache`
