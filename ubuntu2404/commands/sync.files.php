<?php
include_once(__DIR__ . "/../include.php");
define('SHELL_SCOPE', 'sync_files');
include_once($_SERVER['DOCUMENT_ROOT'] . "/core/commands/sync.files.php");
