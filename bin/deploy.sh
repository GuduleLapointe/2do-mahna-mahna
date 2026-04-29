#!/bin/bash
# Deploy standalone bundle to configured targets.
#
# Syncs bundle/standalone/ to each target in config/targets.
# Does NOT run the aggregator — use cron.sh or bin/aggregator.php for data refresh.
#
# Usage: deploy.sh [--with-data] [-n|--dry-run] [-y]
#   --with-data      also include data/ files in the sync
#   -n, --dry-run    show what would be transferred, without making changes
#   -y               skip confirmation prompt

set -euo pipefail

BASE_DIR=$(realpath $(dirname $0)/..)
DATA_DIR=${DATA_DIR:-$BASE_DIR/data}
BUNDLE=$BASE_DIR/bundle/standalone
PGM=$(basename "$0")

TMP=$(mktemp 2>/dev/null || echo /tmp/$PGM.$$)
trap "rm -f $TMP" EXIT

with_data=
dry_run=
yes=

for arg in "$@"; do
    case "$arg" in
        --with-data)       with_data=1 ;;
        -n|--dry-run)      dry_run=1 ;;
        -y)                yes=1 ;;
    esac
done

if [ ! -d "$BUNDLE" ] || [ -z "$(ls -A $BUNDLE)" ]; then
    echo "bundle/standalone/ is empty or missing — run dev/build.php first" >&2
    exit 1
fi

if [ ! -f "$BASE_DIR/config/targets" ]; then
    echo "No config/targets file found — nothing to deploy" >&2
    exit 1
fi

grep . "$BASE_DIR/config/targets" | grep -Ev "#|^\s*$" | sed -E "s:/+$::" > $TMP.targets

if [ ! -s $TMP.targets ]; then
    echo "No targets defined in config/targets" >&2
    exit 1
fi

echo "Sources:"
echo "  $BUNDLE/"
sources="$BUNDLE/.htaccess $BUNDLE/"*
[ "$with_data" ] && echo "  $DATA_DIR/" && sources="$sources $DATA_DIR/"*

echo "Targets:"
cat $TMP.targets | sed 's/^/  /; s:$:/:'

if [ -z "$yes" ]; then
    read -n1 -p "Deploy and replace content in targets? [Y/n] " answer
    [ -n "$answer" ] && echo || answer=y
    echo "$answer" | grep -qi "^y$" || exit 0
fi


rsync_opts="-Wavz"
[ "$dry_run" ] && rsync_opts="$rsync_opts --dry-run"

while read target; do
    echo "→ $target/"
    rsync $rsync_opts $sources "$target/"
done < $TMP.targets
