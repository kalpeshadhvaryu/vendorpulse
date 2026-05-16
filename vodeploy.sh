#!/usr/bin/env bash

if [ -z "${BASH_VERSION:-}" ]; then
	echo "❌ This script must be run with bash. Use: bash vodeploy.sh"
	exit 1
fi

set -Eeuo pipefail
trap 'echo "❌ Deployment failed at line $LINENO"' ERR

echo "🚀 Starting deployment..."

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
FRONTEND_DIR="$ROOT_DIR/web_dashboard"
BACKEND_BRANCH="${BACKEND_BRANCH:-kalpesh}"
FRONTEND_BRANCH="${FRONTEND_BRANCH:-kalpesh}"
FRONTEND_HEALTH_URL="${FRONTEND_HEALTH_URL:-http://127.0.0.1:3000/web_dashboard/login}"
FRONTEND_DEPLOY_CACHE_FILE="${FRONTEND_DEPLOY_CACHE_FILE:-/tmp/vendorpulse-web_dashboard.package-json.sha256}"

require_clean_git_tree() {
	local dir="$1"
	local label="$2"

	cd "$dir"

	if ! git diff --quiet || ! git diff --cached --quiet || [ -n "$(git ls-files --others --exclude-standard)" ]; then
		echo "❌ $label has local changes. Commit, stash, or discard them before deploy."
		git status --short
		exit 1
	fi
}

pull_branch_ff_only() {
	local dir="$1"
	local branch="$2"
	local label="$3"

	cd "$dir"
	git fetch origin "$branch"
	git checkout "$branch"
	git pull --ff-only origin "$branch"
	echo "✅ $label updated to origin/$branch"
}

wait_for_frontend() {
	local attempts=20
	local sleep_seconds=2

	for ((i=1; i<=attempts; i++)); do
		if curl -fsS "$FRONTEND_HEALTH_URL" >/dev/null 2>&1; then
			echo "✅ Frontend healthy at $FRONTEND_HEALTH_URL"
			return 0
		fi

		echo "⏳ Waiting for frontend to become healthy ($i/$attempts)..."
		sleep "$sleep_seconds"
	done

	echo "❌ Frontend did not become healthy at $FRONTEND_HEALTH_URL"
	pm2 status vendorpulse-frontend || true
	pm2 logs vendorpulse-frontend --lines 80 --nostream || true
	exit 1
}

ensure_frontend_dependencies() {
	local package_hash
	package_hash="$(sha256sum package.json | awk '{print $1}')"

	if [ ! -d node_modules ] || [ ! -f "$FRONTEND_DEPLOY_CACHE_FILE" ] || [ "$(cat "$FRONTEND_DEPLOY_CACHE_FILE")" != "$package_hash" ]; then
		echo "🧰 Installing frontend dependencies..."
		npm install
		printf '%s\n' "$package_hash" > "$FRONTEND_DEPLOY_CACHE_FILE"
	else
		echo "✅ Frontend dependencies already up to date"
	fi
}

echo "📦 Validating git worktrees..."
require_clean_git_tree "$ROOT_DIR" "Backend repository"
require_clean_git_tree "$FRONTEND_DIR" "Frontend repository"

echo "⬇️ Pulling latest code..."
pull_branch_ff_only "$ROOT_DIR" "$BACKEND_BRANCH" "Backend"
pull_branch_ff_only "$FRONTEND_DIR" "$FRONTEND_BRANCH" "Frontend"

echo "🗃️ Running backend updates..."
cd "$ROOT_DIR"
COMPOSE_CMD=(docker compose -f docker-compose.yml -f docker-compose.bind.yml)
"${COMPOSE_CMD[@]}" up -d app horizon scheduler
"${COMPOSE_CMD[@]}" exec -T app php artisan optimize:clear
"${COMPOSE_CMD[@]}" exec -T app php artisan migrate --force
"${COMPOSE_CMD[@]}" exec -T app php artisan optimize

echo "🧱 Building frontend..."
cd "$FRONTEND_DIR"
ensure_frontend_dependencies
npm run build

if [ ! -f .next/BUILD_ID ]; then
	echo "❌ Frontend build artifact missing (.next/BUILD_ID). Aborting restart."
	exit 1
fi

echo "🔁 Restarting frontend process..."
pm2 delete vendorpulse-frontend >/dev/null 2>&1 || true
pm2 start npm --name vendorpulse-frontend --cwd "$FRONTEND_DIR" -- start
wait_for_frontend

echo "✅ Deployment complete"