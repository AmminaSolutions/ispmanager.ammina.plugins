#!/bin/sh

ABSOLUTE_FILENAME=$(readlink -e "$0")
ROOT=$(dirname "$ABSOLUTE_FILENAME")
. $ROOT/core/shell/tools.sh
echo $PATH > $ROOT/.local/shell_path

detect_os
make_osdir
get_php

. $OSDIR/shell/config.update.sh
