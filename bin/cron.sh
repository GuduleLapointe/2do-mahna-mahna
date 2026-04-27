#!/bin/bash

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

BASE_DIR=$(realpath $(dirname $0)/..)
PGM=$(basename $0)
TMP=/tmp/$PGM.$$
LOG=$BASE_DIR/logs/$PGM.log
OS=$(uname | tr [:upper:] [:lower:])
DEBUG=${DEBUG:-}
TRACE=${TRACE:-}
DRY_RUN=${DRY_RUN:-}

DATA_DIR=${DATA_DIR:-$BASE_DIR/data}

for arg in "$@"; do
    case "$arg" in
        -n|--dry-run) DRY_RUN=1 ;;
    esac
done

mkdir -p $BASE_DIR/logs

log() {
    echo "$@" >> $TMP.processing
    error=
    echo "$1" | cut -d " " -f 1 | grep -E -q '^[0-9]+$' && error=$1 && shift
    [ "$error" = "0" ] && error=
    [ -z "$error" -a -z "$DEBUG" ] && return
    if [ "$error" ]; then
        echo "$PGM [ERROR $error]: $@" >&2
        return
    fi
    # [ -z "$DEBUG" ] && return
    echo "$PGM: $@" >&2
}

fail() {
    cat $TMP.processing >&2
    echo "$1" | cut -d " " -f 1 | grep -E -q '^[0-9]+$' && error=$1 && shift || error=1
    log $error "$@"
    end $error
}

end() {
    log $@ " - end processing, full log in $LOG"
    mv $TMP.processing $LOG
    [ "${tailpid:-}" ] && kill -9 $tailpid
    exit $1
}

# sudo -nv 2>/dev/null || SUDO_GID="no_sudo"
who=$(whoami)
# [ "$os" = "darwin" ] && user=
user=$(stat -c %U $0 2>/dev/null || stat -f %Su $0)
if [ "$user" != "$who" ]
then
    log "relaunching as $user"
    sudo -u $user $0
    exit $?
fi
log running as $who

touch $TMP.processing
varg=
[ "$DEBUG" = "yes" -o "$DEBUG" = "true" -o "$DEBUG" = "1" ] && DEBUG=1 || DEBUG=
if [ "$DEBUG" ]; then
    varg="-v"
    tail -f $TMP.processing &
    tailpid=$!
fi

[ "$TRACE" = "yes" -o "$TRACE" = "true" -o "$TRACE" = "1" ] && TRACE=1 && DEBUG=1 || TRACE=
[ "$TRACE" ] && set -x

cd $BASE_DIR || fail $? could not cd to $BASE_DIR
[ -d $DATA_DIR ] || mkdir -p $DATA_DIR || fail $? could not create $DATA_DIR

if [ -z "$DRY_RUN" ]; then
    log "starting aggregation"
    $BASE_DIR/bin/aggregator.php $varg $DATA_DIR/ >> $TMP.processing 2>&1 || fail $? error while executing aggregator.php
else
    log "dry-run: skipping aggregation"
fi

rsync_opts="-Waz"
[ "$DRY_RUN" ] && rsync_opts="$rsync_opts --dry-run"

errors=0
[ -f $BASE_DIR/config/targets ] && grep . $BASE_DIR/config/targets | egrep -v "#|^\s*$" | while read target; do
    log updating $target/
    rsync $rsync_opts $DATA_DIR/ $target/ && continue
    log $? rsync to target $target failed
    errors=$((errors+1))
done

end $errors
