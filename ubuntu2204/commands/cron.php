<?php
include_once(__DIR__ . "/../include.php");
$files = \AmminaISP\Ubuntu2204\Cron\Cron::getInstance();
$files->run();