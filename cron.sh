#!/bin/bash

# Single crontab entry point — runs crunz, which decides what is due:
#   * * * * * /path/to/smart-connect/cron.sh

APPLICATION_ENV=${1:-production}
export APPLICATION_ENV

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)

cd "$SCRIPT_DIR" || exit 1

"$SCRIPT_DIR"/vendor/bin/crunz schedule:run
