<?php

include_once(__DIR__ . "/../include.php");

@exec("systemctl stop cron");
$memcached = new \AmminaISP\Debian12\Addons\Memcached();
$memcached->run();
@exec("systemctl start cron");