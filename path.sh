#!/bin/sh

ABSOLUTE_FILENAME=$(readlink -e "$0")
ROOT=$(dirname "$ABSOLUTE_FILENAME")
echo $PATH > $ROOT/.local/shell_path

