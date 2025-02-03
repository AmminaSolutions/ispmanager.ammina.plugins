<?php
include_once(__DIR__ . "/../include.php");
define('SHELL_SCOPE', 'installer');
$installer = new \AmminaISP\Debian12\Installer();
$installer->install();