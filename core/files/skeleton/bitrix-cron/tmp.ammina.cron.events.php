<?php
$_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__) . "/../..");
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define('BX_NO_ACCELERATOR_RESET', true);
define('CHK_EVENT', true);
define('BX_WITH_ON_AFTER_EPILOG', true);

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

@set_time_limit(0);
@ignore_user_abort(true);

COption::SetOptionString("main", "agents_use_crontab", "N");
COption::SetOptionString("main", "check_agents", "N");
COption::SetOptionString("main", "mail_event_bulk", "20");
