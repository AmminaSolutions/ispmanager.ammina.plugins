<?php

namespace AmminaISP\Core\Addons;

use function AmminaISP\Core\addJob;
use function AmminaISP\Core\boolToFlag;
use function AmminaISP\Core\checkDirPath;
use function AmminaISP\Core\checkOnOff;
use function AmminaISP\Core\isOn;

set_time_limit(60);

abstract class AbstractRedis
{
	public string $xml = '';
	public string $settingsFile = '/usr/local/mgr5/etc/amminaisp/redis.ini';
	public string $templateFileName = '/usr/local/mgr5/etc/templates/amminaisp/redis.template';
	public string $redisOptionsFile = '/etc/redis/redis.conf';
	public string $dataAppend = '';

	public string $redisRun = '/var/run/redis';
	public string $redisUser = 'redis';

	function __construct()
	{
		if (!file_exists($this->redisRun)) {
			mkdir($this->redisRun, 0755);
			chown($this->redisRun, $this->redisUser);
		}
		checkDirPath($this->settingsFile);
		$this->xml = '';
		while (!feof(STDIN)) {
			$this->xml .= fread(STDIN, 10000);
		}
	}

	/**
	 * Выполнение функции плагина
	 * @return void
	 */
	public function run(): void
	{
		if (isset($_SERVER['PARAM_ammina_memorylimit']) && isset($_SERVER['PARAM_sok']) && $_SERVER['PARAM_sok'] == "ok") {
			$this->saveForm();
		} else {
			$this->openForm();
		}
		if (strlen($this->dataAppend) > 0) {
			$this->xml = str_replace('</doc>', $this->dataAppend . '</doc>', $this->xml);
		}
		echo $this->xml;
	}

	/**
	 * Показ формы
	 *
	 * @return void
	 */
	public function openForm(): void
	{
		$data = [];
		if (file_exists($this->settingsFile)) {
			$data = parse_ini_file($this->settingsFile);
		}
		if (!is_array($data)) {
			$data = [];
		}

		$this->dataAppend .= '<ammina_memorylimit>' . ($data['memorylimit'] ?? 128) . '</ammina_memorylimit>';
		$this->dataAppend .= '<ammina_databases>' . ($data['databases'] ?? 16) . '</ammina_databases>';
		$this->dataAppend .= '<ammina_issocket>' . boolToFlag((bool)($data['issocket'] ?? true)) . '</ammina_issocket>';
	}

	public function saveForm(): void
	{
		@exec("service redis stop");
		$content = [];
		$content[] = 'memorylimit = ' . (int)$_SERVER['PARAM_ammina_memorylimit'];
		$content[] = 'databases = ' . (int)$_SERVER['PARAM_ammina_databases'];
		$content[] = 'issocket = ' . (isOn($_SERVER['PARAM_ammina_issocket']) ? 1 : 0);
		file_put_contents($this->settingsFile, implode("\n", $content));
		$params = [];
		$params[] = '<ammina_memorylimit>' . (int)$_SERVER['PARAM_ammina_memorylimit'] . '</ammina_memorylimit>';
		$params[] = '<ammina_databases>' . (int)$_SERVER['PARAM_ammina_databases'] . '</ammina_databases>';
		$params[] = '<ammina_issocket>' . checkOnOff($_SERVER['PARAM_ammina_issocket']) . '</ammina_issocket>';
		if (!empty($params)) {
			$this->dataAppend .= implode("", $params);
		}

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