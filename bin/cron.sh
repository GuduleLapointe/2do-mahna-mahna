#!/usr/bin/env bash

# Cron script for 2do-aggregator.
#
# Executes the aggregator and syncs the output to the targets specified in config/targets.
# Use crontab to execute this script periodically (hourly or daily),
# or ln -s it to /etc/cron.hourly/ or /etc/cron.daily/
#
# e.g.: sudo ln -s /path/to/2do-aggregator/cron.sh /etc/cron.hourly/2do-aggregator-cron
#
# Aggregator execution time can be long, depending on the number of configured sources and
# the number of events in each source, so it is recommended to run this task max once per hour.

set -euo pipefail

CLEAR_CACHE=${CLEAR_CACHE:-}

source $(dirname $0)/bash-init

for arg in "$@"; do
    case "$arg" in
        -c|--clear-cache) CLEAR_CACHE=1 ;;
    esac
done

log OS $OS

who=$(whoami)
[ "$OS" = "darwin" ] && owner=$(stat -f %Su $0) || owner=$(stat -c %U $0)
if [ "$owner" != "$who" ]
then
    log "relaunching as $owner"
    sudo -u $owner "$0" "$*"
    exit $?
fi
log running as $who

touch $TMP.processing
varg=
if [ "$DEBUG" ]; then
    varg="-v"
    tail -f $TMP.processing &
    tailpid=$!
fi
if [ "$CLEAR_CACHE" ]; then
	varg="$varg --clear-cache"
fi

cd $APP_DIR || fail $? could not cd to $APP_DIR
[ -d $DATA_DIR ] || mkdir -p $DATA_DIR || fail $? could not create $DATA_DIR

if [ -z "$DRY_RUN" ]; then
    log "starting aggregation to  $DATA_DIR/"
    $APP_DIR/bin/aggregator.php $varg $DATA_DIR/ >> $TMP.processing 2>&1 || fail $? error while executing aggregator.php
else
    log "dry-run: skipping aggregation"
fi

rsync_opts="-Waz"
[ "$DRY_RUN" ] && rsync_opts="$rsync_opts --dry-run"

errors=0
[ -f $APP_DIR/config/targets ] && sed -E "s:/+$::" $APP_DIR/config/targets | egrep -v "#|^\s*$" | while read target; do
    log updating $target/
    rsync $rsync_opts $DATA_DIR/ $target/ && continue
    log $? rsync to target $target failed
    errors=$((errors+1))
done

end $errors end processing
