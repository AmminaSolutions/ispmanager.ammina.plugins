<?php
include_once(__DIR__ . "/../include.php");
define('SHELL_SCOPE', 'installer');
$installer = new \AmminaISP\Ubuntu2404\ConfigUpdate();
$installer->install();