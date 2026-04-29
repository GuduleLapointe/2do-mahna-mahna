#!/usr/bin/env bash
# dev/smoke-test.sh — full rebuild + deploy + endpoint smoke test
#
# Reads dev URL from tests/.env and remote target from config/targets.
# Usage: dev/smoke-test.sh [--skip-deploy]

set -euo pipefail

PGM=$(basename "$0")
TMP=$(mktemp -t "$PGM" || echo /tmp/$PGM.$$)

trap 'rm -rf "$TMP"' EXIT
echo "TMP $TMP $TMP.*"

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_DIR"
echo "APP_DIR $APP_DIR"

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
echo "DEV_BASE $DEV_BASE"
echo $DEV_BASE > $TMP.urls

# ---- Parse targets from config/targets ----
if [ -z "$skip_deploy" ]; then
	REMOTE_TARGET=""
	while IFS= read -r line; do
	    # [[ "$line" =~ ^[[:space:]]*[#;] || -z "${line// /}" ]] && continue
	    REMOTE_TARGET="$line"
		REMOTE_SSH_HOST="${REMOTE_TARGET%%:*}"
		REMOTE_PATH="$(sed 's|/$||' <<< "${REMOTE_TARGET#*:}")"
		REMOTE_WEB_PATH="$(sed 's|.*/www/||' <<< "$REMOTE_PATH")"
		REMOTE_BASE="https://${REMOTE_SSH_HOST}/${REMOTE_WEB_PATH}"
		echo "Remote: $REMOTE_BASE"
		echo $REMOTE_BASE >> $TMP.urls
	done < config/targets
fi

# ---- Confirm ----
echo ""
echo "This will DELETE and rebuild:"
for folder in $PWD/data $PWD/bundle/standalone; do
   	echo "- $folder:"
        ls "$folder" 2>/dev/null || echo "(already empty)"
    # (
    #     ls "$folder" 2>/dev/null || echo "(already empty)"
    # ) | sed "s/^/  → /"
done
if [ -z "$skip_deploy" ] && [ -f "$TMP.urls" ]; then
	echo "- remotes:"
    while IFS= read -r line; do
        [ "$line" = "$DEV_BASE" ] && continue
        echo "  → $line (remote deploy)"
    done < "$TMP.urls"
fi
echo ""
read -n1 -p "Proceed? [Y/n] " answer
echo
[[ "$answer" =~ ^[Nn]$ ]] && exit 0

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
echo ""
echo "## endpoint checks"
while IFS= read -r index_url; do
    api_url="$(sed -E 's|(//[^/]+)/.*|\1|' <<< "$index_url")"
    echo ""
    echo "# ${api_url}/api/v3/events (expect 501)"
    curl -sk -o /dev/null -w "%{http_code}" "${api_url}/api/v3/events" || true
    echo ""

	# Expect text output
    for url in \
        "${index_url}" \
        "${api_url}/api/v2/events" \
        "${api_url}/api/v3/events/lsl" \
        "${api_url}/api/v3/events/json" \
        "${index_url}/?api=v3" \
        "${index_url}/events.php?api=v2" \
        "${index_url}/events.lsl2"
    do
        echo ""
    	echo "# $url"
        curl -sk $url | head -5 ||true
        # add line if $url ends with json
    done

	# Expect image output
    for url in \
        "${api_url}/api/v3/events/board.png" \
        "${index_url}/?format=png" \
        "${index_url}/events.php?format=png"
    do
        echo ""
    	echo "# $url"
     	curl -sk -o $TMP.board2.png "$url" \
      	&& identify $TMP.board2.png
    done
done < $TMP.urls
