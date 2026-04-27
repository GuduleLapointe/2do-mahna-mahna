#!/bin/bash
# Deploy standalone bundle to configured targets.
#
# Syncs bundle/standalone/ to each target in config/targets.
# Does NOT run the aggregator — use cron.sh or bin/aggregator.php for data refresh.
#
# Usage: deploy.sh [--with-data]
#   --with-data   also sync data/ to targets (useful for first deploy or manual force)

set -euo pipefail

BASE_DIR=$(realpath $(dirname $0)/..)
DATA_DIR=${DATA_DIR:-$BASE_DIR/data}
BUNDLE=$BASE_DIR/bundle/standalone

with_data=
[ "${1:-}" = "--with-data" ] && with_data=1

if [ ! -d "$BUNDLE" ] || [ -z "$(ls -A $BUNDLE)" ]; then
    echo "bundle/standalone/ is empty or missing — run dev/build.php first" >&2
    exit 1
fi

if [ ! -f "$BASE_DIR/config/targets" ]; then
    echo "No config/targets file found — nothing to deploy" >&2
    exit 1
fi

errors=0
grep . "$BASE_DIR/config/targets" | grep -Ev "#|^\s*$" | while read target; do
    echo "deploy bundle/standalone/ → $target/"
    rsync --delete -Waz "$BUNDLE/" "$target/" || { echo "rsync failed for $target" >&2; errors=$((errors+1)); }

    if [ "$with_data" ]; then
        echo "deploy data/ → $target/"
        rsync -Waz "$DATA_DIR/" "$target/" || { echo "rsync data failed for $target" >&2; errors=$((errors+1)); }
    fi
done

exit $errors
