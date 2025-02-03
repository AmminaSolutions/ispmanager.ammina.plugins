<?php

use function AmminaISP\Core\execShellCommand;

include_once(__DIR__ . "/../include.php");

execShellCommand("systemctl stop cron");
$memcached = new \AmminaISP\Ubuntu2204\Addons\Memcached();
$memcached->run();
execShellCommand("systemctl start cron");