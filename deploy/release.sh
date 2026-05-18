#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
FRONTEND_DIR="$ROOT_DIR/web_dashboard"

ACTION="${1:-deploy}"
shift || true

case "$ACTION" in
    deploy)
        TARGET_SCRIPT="$ROOT_DIR/deploy/deploy_host.sh"
        ;;
    rollback)
        TARGET_SCRIPT="$ROOT_DIR/deploy/rollback_host.sh"
        ;;
    *)
        echo "Usage: $0 [deploy|rollback] [script-args...]"
        exit 1
        ;;
esac

LOG_DIR="${RELEASE_LOG_DIR:-$ROOT_DIR/deploy/logs}"
LOG_FILE="${RELEASE_LOG_FILE:-$LOG_DIR/releases.log}"
mkdir -p "$LOG_DIR"

current_commit() {
    local repo_dir="$1"
    git -C "$repo_dir" rev-parse --short HEAD 2>/dev/null || echo "unknown"
}

current_branch() {
    local repo_dir="$1"
    git -C "$repo_dir" rev-parse --abbrev-ref HEAD 2>/dev/null || echo "unknown"
}

HOSTNAME_VALUE="$(hostname -s 2>/dev/null || hostname || echo unknown)"
START_TS="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
START_EPOCH="$(date +%s)"

BACKEND_BEFORE="$(current_commit "$ROOT_DIR")"
FRONTEND_BEFORE="$(current_commit "$FRONTEND_DIR")"
BACKEND_BRANCH="$(current_branch "$ROOT_DIR")"
FRONTEND_BRANCH="$(current_branch "$FRONTEND_DIR")"

echo "== release =="
echo "action: $ACTION"
echo "backend: $BACKEND_BRANCH @ $BACKEND_BEFORE"
echo "frontend: $FRONTEND_BRANCH @ $FRONTEND_BEFORE"
echo "started: $START_TS"

set +e
"$TARGET_SCRIPT" "$@"
RC=$?
set -e

END_TS="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
END_EPOCH="$(date +%s)"
DURATION="$((END_EPOCH - START_EPOCH))"

BACKEND_AFTER="$(current_commit "$ROOT_DIR")"
FRONTEND_AFTER="$(current_commit "$FRONTEND_DIR")"

STATUS="success"
if [[ $RC -ne 0 ]]; then
    STATUS="failed"
fi

printf '%s\n' "ts=$END_TS host=$HOSTNAME_VALUE action=$ACTION status=$STATUS duration_s=$DURATION backend_before=$BACKEND_BEFORE backend_after=$BACKEND_AFTER frontend_before=$FRONTEND_BEFORE frontend_after=$FRONTEND_AFTER backend_branch=$BACKEND_BRANCH frontend_branch=$FRONTEND_BRANCH" >> "$LOG_FILE"

echo "status: $STATUS"
echo "duration: ${DURATION}s"
echo "log: $LOG_FILE"

exit "$RC"