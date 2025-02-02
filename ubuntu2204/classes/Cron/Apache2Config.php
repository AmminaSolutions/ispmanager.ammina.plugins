<?php

namespace AmminaISP\Ubuntu2204\Cron;

use AmminaISP\Core\Cron\Apache2ConfigAbstract;

class Apache2Config extends Apache2ConfigAbstract
{

	public function run(): void
	{
		$this->makeWebdomainInfo();
		$this->makeConfig();
	}
}