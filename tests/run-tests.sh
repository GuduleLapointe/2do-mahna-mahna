#!/bin/bash

# Script to run all tests for the 2do-aggregator project

BASEDIR=$(cd "$(dirname "$0")/.." && pwd)
WEBROOT="$BASEDIR/output"

[ -f $BASEDIR/.env ] && source $BASEDIR/.env
[ -f $BASEDIR/tests/.env ] && source $BASEDIR/tests/.env

DEV_PORT=${DEV_PORT:-8000}
DEV_HOST=${DEV_HOST:-localhost}
DEV_SCHEME=${DEV_SCHEME:-http}
LISTEN_IP=${LISTEN_IP:-0.0.0.0}

DEV_URL="$DEV_SCHEME://$DEV_HOST:$DEV_PORT"
[ -n "$MAGICK_FONT_PATH" ] && export MAGICK_FONT_PATH

# Check if the dev server is running
if ! curl -s "$DEV_URL" > /dev/null; then
    echo "Error: Dev server is not running. Please start it with dev/start_server.sh"
    exit 1
fi

# Run PHPUnit tests
php vendor/bin/phpunit --testdox --testdox-summary tests/

# Exit with the status of the last command
exit $?
