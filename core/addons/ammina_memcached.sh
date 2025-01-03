#!/bin/bash

. $ROOT/core/shell/tools.sh
detect_os
make_osdir
get_php

. $OSDIR/addons/ammina_memcached.sh
