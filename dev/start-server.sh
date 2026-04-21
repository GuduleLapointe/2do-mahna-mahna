#!/bin/bash

LOCAL_PORT=8082

set -euo pipefail

BASEDIR=$(cd "$(dirname "$0")/.." && pwd)
WEBROOT="$BASEDIR/output"

cd "$BASEDIR"

echo "Starting 2do-aggregator dev server..."
echo "  Project : $BASEDIR"
echo "  Webroot : $WEBROOT"
echo ""

# Initial sync of templates to output/
cp templates/events.php "$WEBROOT/events.php"
echo "Synced templates/ → output/"

# Get local IP
if [[ "$OSTYPE" == "darwin"* ]]; then
    LOCAL_IP=$(ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null || echo "localhost")
else
    LOCAL_IP=$(hostname -I | awk '{print $1}')
fi

echo ""
echo "Server available at:"
echo "  https://localhost:$LOCAL_PORT"
echo "  https://${LOCAL_IP}:$LOCAL_PORT"
echo ""
echo "Quick tests:"
echo "  https://localhost:$LOCAL_PORT/events.php               (lsl2)"
echo "  https://localhost:$LOCAL_PORT/events.php?format=png    (board image)"
echo "  https://localhost:$LOCAL_PORT/events.php?format=layout (click map)"
echo ""

# Auto-sync events.php on change (requires fswatch: brew install fswatch)
if command -v fswatch >/dev/null 2>&1; then
    echo "Watching templates/ for changes (fswatch)..."
    fswatch -o "$BASEDIR/templates/events.php" | while read; do
        cp "$BASEDIR/templates/events.php" "$WEBROOT/events.php"
        echo "  [sync] templates/ → output/"
    done &
    FSWATCH_PID=$!
    trap "kill $FSWATCH_PID 2>/dev/null" EXIT
else
    echo "Note: fswatch not found — run 'cp templates/events.php output/events.php' after edits."
    echo "      (brew install fswatch for auto-sync)"
fi

# Start server (symfony for HTTPS, php -S as fallback)
if command -v symfony >/dev/null 2>&1; then
    symfony serve --port=$LOCAL_PORT --dir=output --allow-all-ip "$@"
else
    echo "Symfony CLI not found, using plain php -S (no HTTPS — Safari may complain)"
    php -S "0.0.0.0:$LOCAL_PORT" -t "$WEBROOT"
fi
