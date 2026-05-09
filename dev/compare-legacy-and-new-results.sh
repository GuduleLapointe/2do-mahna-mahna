#!/usr/bin/env bash

# Compare fetch results and cache between live server (Stable 0.2.0)
# and local current dev version (3.0.0-dev)

set -euo pipefail

CACHE_TABLE=${CACHE_TABLE:-oshelpers_cache}
DEBUG=1

source $(dirname "$0")/../bin/bash-init
[ -f tests/.env ] && source tests/.env

log PGM: $PGM
log APP_DIR: $APP_DIR
cd "$APP_DIR"

log "LIVE_HOST: $LIVE_HOST"
log "LIVE_APP_DIR:  $LIVE_APP_DIR"
log "LIVE_CRON: $LIVE_CRON"

cd "$APP_DIR"

log Build
./dev/build.php

log Run tests
./tests/run-tests.sh

log Deploy
./bin/deploy.sh

log Get sources list from $LIVE_HOST
rsync -Wavz $LIVE_HOST:$LIVE_APP_DIR/config/sources.csv config/
count=$(grep -v "#" config/sources.csv | grep "[a-z]" | wc -l | xargs echo)
log "-> $count sources"

log Send php helper to $LIVE_HOST
rsync -Wavz dev/dump-events-for-diff.php $LIVE_HOST:$LIVE_APP_DIR/dev/

log Delete cache and execute full cron on $LIVE_HOST
ssh $LIVE_HOST "cd $LIVE_APP_DIR && rm -f cache/* && ./cron.sh -v" | tee -a ${LOG/.log/.live.log}

log Delete local cache
rm -f cache/*
echo "DELETE FROM $CACHE_TABLE" | mysql $SEARCH_DB_NAME

log Execute local cron
./bin/cron.sh -v | tee -a ${LOG/.log/.local.log}

log Dump live data for diff
ssh $LIVE_HOST "cd $LIVE_APP_DIR && php dev/dump-events-for-diff.php" > ${LOG/.log/.live.dump}

log Dump dev data for diff
php dev/dump-events-for-diff.php > ${LOG/.log/.local.dump}

diff ${LOG/.log/.live.dump} ${LOG/.log/.local.dump} && diff_result=$? || diff_result=$?

log "Diff exit code $diff_result. Detailed results available in:
	${LOG}
	${LOG/.log/.live.log}
	${LOG/.log/.local.log}
	${LOG/.log/.local.dump}
	${LOG/.log/.live.dump}"
