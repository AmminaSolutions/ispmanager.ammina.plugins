<?php

namespace AmminaISP\Debian12\Cron;

use AmminaISP\Core\Cron\CronAbstract;

class Cron extends CronAbstract
{
	public function __construct()
	{
		$this->apache2ConfigClass = Apache2Config::class;
		$this->nginxConfigClass = NginxConfig::class;
	}
}