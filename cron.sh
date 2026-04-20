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

BASEDIR=$(realpath $(dirname $0))
PGM=2do-aggregator-cron
TMP=/tmp/$PGM.$$
LOG=/tmp/$PGM.log
builddir=$BASEDIR/output/

log() {
    echo "$@" >> $TMP.processing
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
    [ "$tailpid" ] && kill -9 $tailpid
    exit $1
}

# sudo -nv 2>/dev/null || SUDO_GID="no_sudo"
who=$(whoami)
user=$(stat -c %U $0)
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

cd $BASEDIR || fail $? could not cd to $BASEDIR
[ -d $builddir ] || mkdir -p $builddir || fail $? could not create $builddir

log "starting aggregation"
$BASEDIR/aggregator.php $varg $builddir/ >> $TMP.processing 2>&1 || fail $? error while executing aggregator.php

errors=0
[ -f $BASEDIR/config/targets ] && grep . $BASEDIR/config/targets | egrep -v "#|^\s*$" | while read target; do
    log updating $target/
    rsync -Waz $builddir/ $target/ && continue 
    log $? rsync to target $target failed
    errors=$((errors+1))
done

end $errors
