#!/bin/bash

set -euo pipefail

BASEDIR=$(cd "$(dirname "$0")/.." && pwd)
WEBROOT="$BASEDIR/public"

[ -f $BASEDIR/.env ] && source $BASEDIR/.env
[ -f $BASEDIR/tests/.env ] && source $BASEDIR/tests/.env

DEV_PORT=${DEV_PORT:-8000}
DEV_HOST=${DEV_HOST:-localhost}
LISTEN_IP=${LISTEN_IP:-0.0.0.0}

[ -n "$MAGICK_FONT_PATH" ] && export MAGICK_FONT_PATH

echo "Starting 2do-aggregator dev server..."
echo "  Project: $BASEDIR"
echo "  Webroot: $WEBROOT"
echo "  Magick font path: ${MAGICK_FONT_PATH:-}"

updateFiles() {
	rsync -a src/index.php src/events.php "$WEBROOT/"
	echo "√ index.php, events.php → public/"
	rsync -a includes/bootstrap.php includes/helpers.php "$WEBROOT/includes/"
	echo "√ bootstrap.php, helpers.php → public/includes/"
}
# Initial sync of templates to public/
updateFiles

# Get local IP
if [ $LISTEN_IP != "0.0.0.0" ]; then
    DEV_IP=$LISTEN_IP
else
    if [[ "$OSTYPE" == "darwin"* ]]; then
        DEV_IP=$(ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null || echo "localhost")
    else
        DEV_IP=$(hostname -I | awk '{print $1}')
    fi
fi

echo ""
echo "Server available at:"
echo "  https://$DEV_HOST:$DEV_PORT"
echo "  https://$DEV_IP:$DEV_PORT"
echo ""
echo "Quick tests:"
echo "  https://$DEV_HOST:$DEV_PORT/events.php               (lsl2)"
echo "  https://$DEV_HOST:$DEV_PORT/events.php?format=png    (board image)"
echo "  https://$DEV_HOST:$DEV_PORT/events.php?format=layout (click map)"
echo ""

# Auto-sync events.php on change (requires fswatch: brew install fswatch)
if command -v fswatch >/dev/null 2>&1; then
    echo "Watching src/ for changes (fswatch)..."
    fswatch -o "$BASEDIR/src/events.php" "$BASEDIR/includes/bootstrap.php" "$BASEDIR/includes/helpers.php" \
        | while read; do
        updateFiles
    done &
    FSWATCH_PID=$!
    trap "kill $FSWATCH_PID 2>/dev/null" EXIT
else
    echo "Note: fswatch not found — run 'cp src/events.php public/events.php' after edits."
    echo "      (brew install fswatch for auto-sync)"
fi

# Start server (symfony for HTTPS, php -S as fallback)
if command -v symfony >/dev/null 2>&1; then
    symfony serve --port=$DEV_PORT --dir=public --allow-all-ip "$@"
else
    echo "Symfony CLI not found, using plain php -S (no HTTPS — Safari may complain)"
    php -S "$LISTEN_IP:$DEV_PORT" -t "$WEBROOT"
fi
