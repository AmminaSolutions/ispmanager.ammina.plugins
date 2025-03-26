<?php

namespace AmminaISP\Core\Cron;

use AmminaISP\Core\TemplateGenerator;

abstract class Apache2ConfigAbstract extends WebConfigAbstract
{
	public string $resultDir = '/etc/apache2/amminaisp/vhosts';

	public string $configTestCommand = 'apachectl configtest';
	public string $restartCommand = 'service apache2 restart';
	public string $pidFile = '/run/apache2/apache2.pid';

	public function makeConfig(): void
	{
		if (strlen(trim($this->webdomainInfo['owner'])) <= 0 || strlen(trim($this->webdomainInfo['name'])) <= 0) {
			return;
		}
		$resultFileName = "{$this->resultDir}/{$this->webdomainInfo['owner']}/{$this->webdomainInfo['name']}.conf";
		$content = TemplateGenerator::getInstance()->run('apache2-vhost.php', $this->webdomainInfo);
		if ($this->updateConfig($resultFileName, $content)) {
			$this->saveToOriginalConfig($resultFileName);
		}
	}

}