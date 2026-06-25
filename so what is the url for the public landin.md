Final 25-06-2026

The public landing page is served by Laravel at the site root:

Local dev: http://127.0.0.1:8000/ (or http://localhost:8000/)

Production: your main domain root, e.g. https://vendorpulse.veravalonline.com/

It is not on the Next.js port. The dashboard stays at http://127.0.0.1:3000/web_dashboard/ in local dev.

So for the production do i need any other docker or simply upload the code and start the docker it will work ?

Checking how production is set up so I can give you an accurate answer.

You do not need a new Docker service. The public landing page is part of the existing Laravel app container (routes + Blade view).

Production deploy (same stack as today)
cd /var/www/vendorpulse
git pull
# Rebuild so the image includes the new landing code
docker compose build app horizon scheduler
docker compose up -d app horizon scheduler

If you run the dashboard via Docker’s frontend service, rebuild that too when the Next app changes:

docker compose build frontend
docker compose up -d frontend

Or, if Next runs outside Docker:

cd web_dashboard && npm ci && npm run build
# restart your Next process (pm2/systemd)

.env (important)
From your production template, set:

APP_URL=https://vendorpulse.veravalonline.com
WEB_DASHBOARD_URL=https://vendorpulse.veravalonline.com
WEB_DASHBOARD_URL is what makes /web_dashboard/login redirect to the real dashboard URL.

Then:

docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear

One thing to verify: Nginx / reverse proxy
The landing page only works if / goes to Laravel, not straight to Next.

Typical production routing:

Path	Backend
/
Laravel app (port APP_PORT, e.g. 8001)
/api/*
Laravel app
/web_dashboard/*
Next.js
If Nginx currently redirects / → /web_dashboard, update that config. After deploy, check:

https://vendorpulse.veravalonline.com/ → DNS landing page
https://vendorpulse.veravalonline.com/web_dashboard/ → dashboard
https://vendorpulse.veravalonline.com/web_dashboard/login → login
Summary: upload/pull code, rebuild/restart existing Docker services, set WEB_DASHBOARD_URL, and confirm Nginx sends / to Laravel. No extra container is required.

