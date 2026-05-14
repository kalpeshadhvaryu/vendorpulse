#!/bin/bash
echo "🚀 Starting Deployment..."

# 1. Pull Code
git pull origin kalpesh
cd web_dashboard && git pull origin kalpesh && cd ..

# 2. Update Backend
docker exec vendorpulse-app-1 php artisan migrate --force
docker exec vendorpulse-app-1 php artisan optimize

# 3. Update Frontend
cd web_dashboard
npm install
npm run build
pm2 restart vendorpulse-frontend

echo "✅ Deployment Complete!"