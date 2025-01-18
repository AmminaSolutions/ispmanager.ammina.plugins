<?php
include_once(__DIR__ . "/../include.php");
$files = \AmminaISP\Debian12\Cron\Cron::getInstance();
$files->run();