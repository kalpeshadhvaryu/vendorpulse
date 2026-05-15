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

echo "📦 Validating git worktrees..."
require_clean_git_tree "$ROOT_DIR" "Backend repository"
require_clean_git_tree "$FRONTEND_DIR" "Frontend repository"

echo "⬇️ Pulling latest code..."
pull_branch_ff_only "$ROOT_DIR" "$BACKEND_BRANCH" "Backend"
pull_branch_ff_only "$FRONTEND_DIR" "$FRONTEND_BRANCH" "Frontend"

echo "🗃️ Running backend updates..."
cd "$ROOT_DIR"
docker compose up -d --build app horizon scheduler
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize

echo "🧱 Building frontend..."
cd "$FRONTEND_DIR"
npm install
rm -rf .next
npm run build

if [ ! -f .next/BUILD_ID ]; then
	echo "❌ Frontend build artifact missing (.next/BUILD_ID). Aborting restart."
	exit 1
fi

echo "🔁 Restarting frontend process..."
pm2 restart vendorpulse-frontend

echo "✅ Deployment complete"