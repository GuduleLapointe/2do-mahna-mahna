#!/bin/bash

set -euo pipefail

BASE_DIR=$(cd "$(dirname "$0")/.." && pwd)

[ -f $BASE_DIR/.env ] && source $BASE_DIR/.env
[ -f $BASE_DIR/tests/.env ] && source $BASE_DIR/tests/.env

DEV_PORT=${DEV_PORT:-8000}
DEV_HOST=${DEV_HOST:-localhost}
LISTEN_IP=${LISTEN_IP:-0.0.0.0}

WEBROOT="${WEBROOT:-$BASE_DIR/bundle/standalone}"
DATA_DIR="${DATA_DIR:-$BASE_DIR/data}"

[ -n "$MAGICK_FONT_PATH" ] && export MAGICK_FONT_PATH

echo "Starting 2do-aggregator dev server..."
echo "  Project: $BASE_DIR"
echo "  Webroot: $WEBROOT"
echo "  Datadir: $DATA_DIR"
echo "  Magick font path: ${MAGICK_FONT_PATH:-}"

# updateFiles() {
# 	rsync -a src/bundle/standalone/index.php src/bundle/standalone/events.php "$WEBROOT/"
# 	echo "√ index.php, events.php → bundle/standalone/"
# 	rsync -a src/bundle/standalone/bootstrap.php src/bundle/standalone/functions.php "$WEBROOT/"
# 	echo "√ bootstrap.php, functions.php → bundle/standalone/"
# }
# Initial sync of sources to bundle/standalone/
# updateFiles

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
echo "  https://$DEV_HOST:$DEV_PORT/                         (static html)"
echo "  https://$DEV_HOST:$DEV_PORT/events.lsl2              (v2)"
echo "  https://$DEV_HOST:$DEV_PORT/api/v3/events/lsl        (v3)"
echo "  https://$DEV_HOST:$DEV_PORT/api/v3/events/board.png  (board img)"
echo ""

# Auto-sync events.php on change (requires fswatch: brew install fswatch)
# if command -v fswatch >/dev/null 2>&1; then
#     echo "Watching src/ for changes (fswatch)..."
#     fswatch -o "$BASE_DIR/src/bundle/standalone/events.php" "$BASE_DIR/src/bundle/standalone/bootstrap.php" "$BASE_DIR/src/bundle/standalone/functions.php" \
#         | while read; do
#         updateFiles
#     done &
#     FSWATCH_PID=$!
#     trap "kill $FSWATCH_PID 2>/dev/null" EXIT
# else
#     echo "Note: fswatch not found — run 'cp src/events.php public/events.php' after edits."
#     echo "      (brew install fswatch for auto-sync)"
# fi

export DATA_DIR
# Start server (symfony for HTTPS, php -S as fallback)
if command -v symfony >/dev/null 2>&1; then
    symfony serve --port=$DEV_PORT --dir=bundle/standalone --allow-all-ip "$@"
else
    echo "Symfony CLI not found, using plain php -S (no HTTPS — Safari may complain)"
    php -S "$LISTEN_IP:$DEV_PORT" -t "$WEBROOT"
fi
