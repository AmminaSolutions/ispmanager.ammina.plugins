<?php

use function AmminaISP\Core\execShellCommand;

include_once(__DIR__ . "/../include.php");

execShellCommand("systemctl stop cron");
$memcached = new \AmminaISP\Debian12\Addons\Memcached();
$memcached->run();
execShellCommand("systemctl start cron");