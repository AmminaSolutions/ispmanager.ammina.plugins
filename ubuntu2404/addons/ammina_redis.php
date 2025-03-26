<?php

use function AmminaISP\Core\execShellCommand;

include_once(__DIR__ . "/../include.php");

execShellCommand("systemctl stop cron");
$redis = new \AmminaISP\Ubuntu2404\Addons\Redis();
$redis->run();
execShellCommand("systemctl start cron");