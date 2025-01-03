<?php
error_reporting(E_ERROR);
set_time_limit(0);
ini_set('display_errors', 0);
ini_set("error_log", "/var/log/amminaisp.log");

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
include_once(__DIR__ . "/helpers.php");
include_once(__DIR__ . "/classes/Autoloader.php");
\AmminaISP\Core\Autoloader::registerNamespace("AmminaISP\\Core", __DIR__ . "/classes/");

