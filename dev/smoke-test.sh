#!/usr/bin/env bash
# dev/smoke-test.sh — full rebuild + deploy + endpoint smoke test
#
# Reads dev URL from tests/.env and remote target from config/targets.
# Usage: dev/smoke-test.sh [--skip-deploy]

set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_DIR"

skip_deploy=
for arg in "$@"; do
    case "$arg" in --skip-deploy) skip_deploy=1 ;; esac
done

# ---- Load env files ----
# shellcheck source=../.env
[[ -f "$APP_DIR/.env" ]]       && source "$APP_DIR/.env"
# shellcheck source=../tests/.env
[[ -f "$APP_DIR/tests/.env" ]] && source "$APP_DIR/tests/.env"
DEV_BASE="${DEV_SCHEME:-http}://${DEV_HOST:-localhost}:${DEV_PORT:-8000}"

# ---- Parse first target from config/targets ----
REMOTE_TARGET=""
while IFS= read -r line; do
    [[ "$line" =~ ^[[:space:]]*[#;] || -z "${line// /}" ]] && continue
    REMOTE_TARGET="$line"
    break
done < config/targets
REMOTE_SSH_HOST="${REMOTE_TARGET%%:*}"
REMOTE_PATH="$(sed 's|/$||' <<< "${REMOTE_TARGET#*:}")"
REMOTE_WEB_PATH="$(sed 's|.*/www/||' <<< "$REMOTE_PATH")"
REMOTE_BASE="https://${REMOTE_SSH_HOST}/${REMOTE_WEB_PATH}"

# ---- Rebuild ----
echo "# delete current build and data"
rm -rf data/* bundle/standalone/*

echo ""
echo "# dev/build.php"
./dev/build.php

echo ""
echo "# bin/cron.sh"
./bin/cron.sh
echo ""
du -sk data/*

# ---- Deploy ----
if [ -z "$skip_deploy" ]; then
    echo ""
    echo "# bin/deploy.sh --with-data"
    ./bin/deploy.sh -y --with-data

    echo ""
    echo "# remote: ${REMOTE_SSH_HOST}:${REMOTE_PATH}"
    ssh "$REMOTE_SSH_HOST" "ls -1 '${REMOTE_PATH}/'"
fi

# ---- URL smoke test ----
check_url() {
    local label="$1" url="$2"
    echo ""
    echo "# $label"
    echo "  $url"
    curl -sk "$url" | head -5
}

echo ""
echo "# endpoint checks"
check_url "dev bundle"    "${DEV_BASE}/"
check_url "dev api v2"    "${DEV_BASE}/api/v2/events"
check_url "dev api v3"    "${DEV_BASE}/api/v3/events"
check_url "remote bundle" "${REMOTE_BASE}/"
check_url "remote api v2" "https://${REMOTE_SSH_HOST}/api/v2/events"
check_url "remote api v3" "https://${REMOTE_SSH_HOST}/api/v3/events"
