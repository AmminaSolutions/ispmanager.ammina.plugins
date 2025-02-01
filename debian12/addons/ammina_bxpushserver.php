<?php

include_once(__DIR__ . "/../include.php");

@exec("systemctl stop cron");
$redis = new \AmminaISP\Debian12\Addons\BitrixPushServer();
$redis->run();
@exec("systemctl start cron");