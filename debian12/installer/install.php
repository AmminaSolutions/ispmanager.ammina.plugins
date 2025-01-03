<?php
include_once(__DIR__ . "/../include.php");

//$installer = new \AmminaISP\Debian12\Installer();

$files=\AmminaISP\Core\FilesSynchronizer::getInstance();
$files->run(true);