<?php

namespace AmminaISP\Core\Addons;

use function AmminaISP\Core\addJob;
use function AmminaISP\Core\execShellCommand;
use function AmminaISP\Core\isOn;

abstract class RedisAbstract extends AddonAbstract
{
	public string $templateFileName = '/usr/local/mgr5/etc/templates/amminaisp/redis.template';
	public string $redisOptionsFile = '/etc/redis/redis.conf';

	public string $redisRun = '/var/run/redis';
	public string $redisUser = 'redis';
	public ?string $settingsFile = 'redis.ini';
	public array $params = [
		'ammina_memorylimit' => [
			'type' => 'int',
			'ini' => 'memorylimit',
			'default' => 128,
		],
		'ammina_databases' => [
			'type' => 'int',
			'ini' => 'databases',
			'default' => 16,
		],
		'ammina_issocket' => [
			'type' => 'bool',
			'ini' => 'issocket',
			'default' => true,
		],
	];

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
		$this->fillDataAppendFromIni();
	}

	public function saveForm(): void
	{
		@execShellCommand("service redis stop");
		$this->fillIniFromParams();
		$this->saveIni();
		$this->fillDataAppendFromIni();

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
			@execShellCommand("service redis start");
		}
	}

	protected function checkMonitoring(): void
	{
		addJob("ammina.redis.monitoring", []);
	}

	protected abstract function checkSystemSettings(): void;

}