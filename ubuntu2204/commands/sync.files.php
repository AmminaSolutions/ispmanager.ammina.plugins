<?php
include_once(__DIR__ . "/../include.php");

$files = \AmminaISP\Core\FilesSynchronizer::getInstance();
$files->run(true);

