<?php

include_once(__DIR__ . "/../include.php");

@exec("systemctl stop cron");
$redis = new \AmminaISP\Ubuntu2204\Addons\Redis();
$redis->run();
@exec("systemctl start cron");