<?php
include_once(__DIR__ . "/../include.php");
define('SHELL_SCOPE', 'cron');
$files = \AmminaISP\Debian12\Cron\Cron::getInstance();
$files->run();