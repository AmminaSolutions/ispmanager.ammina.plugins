<?php


namespace AmminaISP\Core\Cron;

use AmminaISP\Core\TemplateGenerator;
use function AmminaISP\Core\checkDirPath;

abstract class Apache2ConfigAbstract extends WebConfigAbstract
{
	public string $resultDir = '/etc/apache2/amminaisp/vhosts';

	public string $configTestCommand = 'apachectl configtest';
	public string $restartCommand = 'service apache2 restart';
	public string $pidFile = '/run/apache2/apache2.pid';

	public function makeConfig(): void
	{
		$resultFileName = "{$this->resultDir}/{$this->webdomainInfo['owner']}/{$this->webdomainInfo['name']}.conf";
		$content = TemplateGenerator::getInstance()->run('apache2-vhost.php', $this->webdomainInfo);
		$this->updateConfig($resultFileName, $content);
	}


}