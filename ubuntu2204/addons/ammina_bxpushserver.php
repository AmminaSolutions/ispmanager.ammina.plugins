<?php
use function AmminaISP\Core\execShellCommand;

include_once(__DIR__ . "/../include.php");

execShellCommand("systemctl stop cron");
$redis = new \AmminaISP\Ubuntu2204\Addons\BitrixPushServer();
$redis->run();
execShellCommand("systemctl start cron");