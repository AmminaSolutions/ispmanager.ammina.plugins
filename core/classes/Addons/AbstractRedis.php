<?php

namespace AmminaISP\Core\Addons;

use function AmminaISP\Core\addJob;
use function AmminaISP\Core\boolToFlag;
use function AmminaISP\Core\checkDirPath;
use function AmminaISP\Core\checkOnOff;
use function AmminaISP\Core\isOn;

abstract class AbstractRedis extends AbstractAddon
{
	public string $templateFileName = '/usr/local/mgr5/etc/templates/amminaisp/redis.template';
	public string $redisOptionsFile = '/etc/redis/redis.conf';

	public string $redisRun = '/var/run/redis';
	public string $redisUser = 'redis';
	public ?string $settingsFile = 'redis.ini';

	public function __construct()
	{
		parent::__construct();
		if (!file_exists($this->redisRun)) {
			mkdir($this->redisRun, 0755);
			chown($this->redisRun, $this->redisUser);
		}
	}

	/**
	 * Показ формы
	 *
	 * @return void
	 */
	public function openForm(): void
	{
		$this->dataAppend['ammina_memorylimit'] = $this->fromIni('memorylimit', 128);
		$this->dataAppend['ammina_databases'] = $this->fromIni('databases', 16);
		$this->dataAppend['ammina_issocket'] = $this->fromIniFlag('issocket', true);
	}

	public function saveForm(): void
	{
		@exec("service redis stop");
		$this
			->setIni('memorylimit', (int)$_SERVER['PARAM_ammina_memorylimit'])
			->setIni('databases', (int)$_SERVER['PARAM_ammina_databases'])
			->setIniFlag('issocket', $_SERVER['PARAM_ammina_issocket'])
			->saveIni();

		$this->dataAppend['ammina_memorylimit'] = (int)$_SERVER['PARAM_ammina_memorylimit'];
		$this->dataAppend['ammina_databases'] = (int)$_SERVER['PARAM_ammina_databases'];
		$this->dataAppend['ammina_issocket'] = checkOnOff($_SERVER['PARAM_ammina_issocket']);

		$templateReplace = [
			"AMMINA_MAXMEMORY" => (int)$_SERVER['PARAM_ammina_memorylimit'] . "mb",
			"AMMINA_DATABASES" => (int)$_SERVER['PARAM_ammina_databases'],
			"AMMINA_CONNECTION" => (isOn($_SERVER['PARAM_ammina_issocket']) ? "unixsocket {$this->redisRun}/redis.sock\nunixsocketperm 766\nport 0" : "bind 127.0.0.1\nport 6379"),
		];
		$template = '';
		if (file_exists($this->templateFileName)) {
			$template = file_get_contents($this->templateFileName);
		}
		foreach ($templateReplace as $k => $v) {
			$template = str_replace('[% ' . $k . ' %]', $v, $template);
		}
		if (trim($template) != '') {
			file_put_contents($this->redisOptionsFile, $template);
			$this->checkMonitoring();
			$this->checkSystemSettings();
			@exec("service redis start");
		}
	}

	protected function checkMonitoring(): void
	{
		addJob("ammina.redis.monitoring", []);
	}

	protected abstract function checkSystemSettings(): void;

}