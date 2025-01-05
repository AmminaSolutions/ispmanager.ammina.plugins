<?php

namespace AmminaISP\Core\Addons;

use function AmminaISP\Core\addJob;
use function AmminaISP\Core\boolToFlag;
use function AmminaISP\Core\checkDirPath;
use function AmminaISP\Core\checkOnOff;
use function AmminaISP\Core\isOn;

set_time_limit(60);

abstract class AbstractMemcached
{
	public string $xml = '';
	public string $settingsFile = '/usr/local/mgr5/etc/amminaisp/memcached.ini';
	public string $templateFileName = '/usr/local/mgr5/etc/templates/amminaisp/memcached.template';
	public string $redisOptionsFile = '/etc/memcached.conf';
	public string $dataAppend = '';

	public string $memcachedRun = '/var/run/memcached';
	public string $memcachedUser = 'memcache';

	function __construct()
	{
		if (!file_exists($this->memcachedRun)) {
			mkdir($this->memcachedRun, 0755);
			chown($this->memcachedRun, $this->memcachedUser);
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
		if (isset($_SERVER['PARAM_ammina_cachesize']) && isset($_SERVER['PARAM_sok']) && $_SERVER['PARAM_sok'] == "ok") {
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

		$this->dataAppend .= '<ammina_cachesize>' . ($data['cachesize'] ?? 64) . '</ammina_cachesize>';
		$this->dataAppend .= '<ammina_maxconn>' . ($data['maxconn'] ?? 1024) . '</ammina_maxconn>';
		$this->dataAppend .= '<ammina_issocket>' . boolToFlag((bool)($data['issocket'] ?? true)) . '</ammina_issocket>';
	}

	public function saveForm(): void
	{
		@exec("service memcached stop");
		$content = [];
		$content[] = 'cachesize = ' . (int)$_SERVER['PARAM_ammina_cachesize'];
		$content[] = 'maxconn = ' . (int)$_SERVER['PARAM_ammina_maxconn'];
		$content[] = 'issocket = ' . (isOn($_SERVER['PARAM_ammina_issocket']) ? 1 : 0);
		file_put_contents($this->settingsFile, implode("\n", $content));
		$params = [];
		$params[] = '<ammina_cachesize>' . (int)$_SERVER['PARAM_ammina_cachesize'] . '</ammina_cachesize>';
		$params[] = '<ammina_maxconn>' . (int)$_SERVER['PARAM_ammina_maxconn'] . '</ammina_maxconn>';
		$params[] = '<ammina_issocket>' . checkOnOff($_SERVER['PARAM_ammina_issocket']) . '</ammina_issocket>';
		if (!empty($params)) {
			$this->dataAppend .= implode("", $params);
		}

		$templateReplace = [
			"AMMINA_CACHESIZE" => (int)$_SERVER['PARAM_ammina_cachesize'],
			"AMMINA_MAXCONN" => (int)$_SERVER['PARAM_ammina_maxconn'],
			"AMMINA_CONNECTION" => (isOn($_SERVER['PARAM_ammina_issocket']) ? "-s {$this->memcachedRun}/memcached.sock\n-a 766" : "-l 127.0.0.1"),
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
			@exec("service memcached start");
		}
	}

	protected function checkMonitoring(): void
	{
		addJob("ammina.memcached.monitoring", []);
	}

	protected abstract function checkSystemSettings(): void;

}