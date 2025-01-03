#!/bin/sh

ABSOLUTE_FILENAME=$(readlink -e "$0")
ROOT=$(dirname "$ABSOLUTE_FILENAME")
ROOT=$(dirname "$ROOT")
. $ROOT/core/shell/tools.sh

detect_os
make_osdir
get_php

. $OSDIR/shell/commands/sync.files.sh
