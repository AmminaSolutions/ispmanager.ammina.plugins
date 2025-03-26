<?php

namespace AmminaISP\Core\Cron;

use AmminaISP\Core\TemplateGenerator;

abstract class NginxConfigAbstract extends WebConfigAbstract
{
	public string $resultDir = '/etc/nginx/amminaisp/vhosts';

	public string $configTestCommand = 'nginx -t';
	public string $restartCommand = 'service nginx restart';
	public string $pidFile = '/run/nginx.pid';

	public function makeConfig(): void
	{
		if (strlen(trim($this->webdomainInfo['owner'])) <= 0 || strlen(trim($this->webdomainInfo['name'])) <= 0) {
			return;
		}
		$resultFileName = "{$this->resultDir}/{$this->webdomainInfo['owner']}/{$this->webdomainInfo['name']}.conf";
		$content = TemplateGenerator::getInstance()->run('nginx-vhost.php', $this->webdomainInfo);
		if ($this->updateConfig($resultFileName, $content)) {
			$this->saveToOriginalConfig($resultFileName);
		}
	}

}