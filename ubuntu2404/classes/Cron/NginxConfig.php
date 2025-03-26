<?php

namespace AmminaISP\Ubuntu2404\Cron;

use AmminaISP\Core\Cron\NginxConfigAbstract;

class NginxConfig extends NginxConfigAbstract
{

	public function run(): void
	{
		$this->makeWebdomainInfo();
		$this->makeConfig();
	}
}