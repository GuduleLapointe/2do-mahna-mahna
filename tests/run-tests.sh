#!/bin/bash

# Script to run all tests for the 2do-aggregator project

BASE_DIR=$(cd "$(dirname "$0")/.." && pwd)
WEBROOT="$BASE_DIR/public"

[ -f $BASE_DIR/.env ] && source $BASE_DIR/.env
[ -f $BASE_DIR/tests/.env ] && source $BASE_DIR/tests/.env

DEV_PORT=${DEV_PORT:-8000}
DEV_HOST=${DEV_HOST:-localhost}
DEV_SCHEME=${DEV_SCHEME:-http}
LISTEN_IP=${LISTEN_IP:-0.0.0.0}

DEV_URL="$DEV_SCHEME://$DEV_HOST:$DEV_PORT"
[ -n "$MAGICK_FONT_PATH" ] && export MAGICK_FONT_PATH

PGM="$(basename "$0")"
TMP="$(mktemp -t "$PGM" || echo /tmp/$PGM.$$)"
trap 'rm -f "$TMP.*"' EXIT

# Check if the dev server is running
if ! curl -s "$DEV_URL" > /dev/null; then
    echo "Error: Dev server is not running. Please start it with dev/start_server.sh"
    exit 1
fi

# Run tests with Pest
# php vendor/bin/pest tests/ --testsuite Requirements,Features,Units $*
php vendor/bin/pest $* 2>&1 | tee "$TMP.pest.output"

# Capture exit code from test run
EXIT_CODE=${PIPESTATUS[0]}

# Extract failed test lines (⨯ lines with timing) and display as summary
FAILURES=$(egrep "^\s*(⨯|-) " "$TMP.pest.output" || true)

if [ -n "$FAILURES" ]; then
	echo ""
    echo "FAILURES SUMMARY"
    echo "$FAILURES"
fi

# Exit with the exit status of previous test run
exit $EXIT_CODE
