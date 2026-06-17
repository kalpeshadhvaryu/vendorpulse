#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
BRANCH="${1:-kalpesh}"
COMPOSE=(docker compose -f "$ROOT_DIR/docker-compose.yml" -f "$ROOT_DIR/docker-compose.bind.yml")

require_cmd() {
    local cmd="$1"
    if ! command -v "$cmd" >/dev/null 2>&1; then
        echo "❌ Missing required command: $cmd"
        exit 1
    fi
}

echo "🚀 Starting VendorPulse Deployment..."

require_cmd git
require_cmd docker

cd "$ROOT_DIR"

echo "📥 Pulling backend repo ($BRANCH)..."
git pull origin "$BRANCH" --rebase

if [[ -d "$ROOT_DIR/web_dashboard/.git" ]]; then
    echo "📥 Pulling web_dashboard repo ($BRANCH)..."
    git -C "$ROOT_DIR/web_dashboard" pull origin "$BRANCH" --rebase
else
    echo "ℹ️ web_dashboard is not a separate git repo here. Skipping nested pull."
fi

echo "🧹 Clearing local host cache before deploy..."
php artisan optimize:clear || true

echo "⚙️ Running VendorPulse deploy flow..."
bash "$ROOT_DIR/vpproduction.sh" deploy
bash "$ROOT_DIR/vpproduction.sh" verify

echo "📦 Rebuilding frontend image (no cache)..."
"${COMPOSE[@]}" build --no-cache frontend
"${COMPOSE[@]}" up -d frontend

echo "🔍 Frontend quick log scan..."
"${COMPOSE[@]}" logs --tail=80 frontend | grep -Ei 'error|nextauth|secret' || true

echo "✅ Deployment Complete!"
echo "🌐 Verify: https://vendorpulse.veravalonline.com"
