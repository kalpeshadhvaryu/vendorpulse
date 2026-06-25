#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
BRANCH="${1:-kalpesh}"
WEB_DASHBOARD_PULL_MODE="${WEB_DASHBOARD_PULL_MODE:-fail}"
FRONTEND_NO_CACHE="${FRONTEND_NO_CACHE:-0}"
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

# Avoid noisy warning on hosts without buildx; compose falls back to classic build.
export COMPOSE_BAKE="${COMPOSE_BAKE:-false}"

echo "📥 Pulling backend repo ($BRANCH)..."
git pull origin "$BRANCH" --rebase

if [[ -d "$ROOT_DIR/web_dashboard/.git" ]]; then
    echo "📥 Pulling web_dashboard repo ($BRANCH)..."
    if [[ -n "$(git -C "$ROOT_DIR/web_dashboard" status --porcelain)" ]]; then
        case "$WEB_DASHBOARD_PULL_MODE" in
            skip)
                echo "⚠️ web_dashboard has local changes; skipping pull because WEB_DASHBOARD_PULL_MODE=skip"
                ;;
            stash)
                echo "⚠️ web_dashboard has local changes; stashing before pull because WEB_DASHBOARD_PULL_MODE=stash"
                git -C "$ROOT_DIR/web_dashboard" stash push -u -m "deploy.sh:auto-stash $(date -u +%Y-%m-%dT%H:%M:%SZ)"
                git -C "$ROOT_DIR/web_dashboard" pull origin "$BRANCH" --rebase
                ;;
            fail|*)
                echo "❌ web_dashboard has local unstaged/staged changes."
                echo "   Resolve first, then rerun deploy."
                echo "   Options:"
                echo "   1) Commit:   git -C $ROOT_DIR/web_dashboard add -A && git -C $ROOT_DIR/web_dashboard commit -m 'save local changes'"
                echo "   2) Stash:    git -C $ROOT_DIR/web_dashboard stash push -u -m 'pre-deploy stash'"
                echo "   3) Skip pull this run: WEB_DASHBOARD_PULL_MODE=skip bash deploy.sh $BRANCH"
                echo "   4) Auto-stash in script: WEB_DASHBOARD_PULL_MODE=stash bash deploy.sh $BRANCH"
                exit 1
                ;;
        esac
    else
        git -C "$ROOT_DIR/web_dashboard" pull origin "$BRANCH" --rebase
    fi
else
    echo "ℹ️ web_dashboard is not a separate git repo here. Skipping nested pull."
fi

echo "🧹 Clearing local host cache before deploy..."
php artisan optimize:clear || true

echo "⚙️ Running VendorPulse deploy flow..."
bash "$ROOT_DIR/vpproduction.sh" deploy
bash "$ROOT_DIR/vpproduction.sh" verify

if [[ "$FRONTEND_NO_CACHE" == "1" ]]; then
    echo "📦 Rebuilding frontend image (no cache)..."
    "${COMPOSE[@]}" build --no-cache frontend
else
    echo "📦 Rebuilding frontend image (using cache)..."
    "${COMPOSE[@]}" build frontend
fi
"${COMPOSE[@]}" up -d frontend

echo "🔍 Frontend quick log scan..."
"${COMPOSE[@]}" logs --tail=80 frontend | grep -Ei 'error|nextauth|secret' || true

echo "✅ Deployment Complete!"
echo "🌐 Verify: https://vendorpulse.veravalonline.com"
