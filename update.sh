#!/bin/sh

ABSOLUTE_FILENAME=$(readlink -e "$0")
ROOT=$(dirname "$ABSOLUTE_FILENAME")
cd $ROOT

git add .
git reset --hard HEAD
git pull
