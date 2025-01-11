<?php

namespace AmminaISP\Core\Addons;

use function AmminaISP\Core\addJob;
use function AmminaISP\Core\boolToFlag;
use function AmminaISP\Core\checkDirPath;
use function AmminaISP\Core\checkOnOff;
use function AmminaISP\Core\isOn;

abstract class AbstractMemcached extends AbstractAddon
{

	public string $templateFileName = '/usr/local/mgr5/etc/templates/amminaisp/memcached.template';
	public string $redisOptionsFile = '/etc/memcached.conf';
	public string $memcachedRun = '/var/run/memcached';
	public string $memcachedUser = 'memcache';
	public ?string $settingsFile = 'memcached.ini';

	public function __construct()
	{
		parent::__construct();
		if (!file_exists($this->memcachedRun)) {
			mkdir($this->memcachedRun, 0755);
			chown($this->memcachedRun, $this->memcachedUser);
		}
	}

	/**
	 * Показ формы
	 *
	 * @return void
	 */
	public function openForm(): void
	{
		$this->dataAppend['ammina_cachesize'] = $this->fromIni('cachesize', 64);
		$this->dataAppend['ammina_maxconn'] = $this->fromIni('maxconn', 1024);
		$this->dataAppend['ammina_issocket'] = $this->fromIniFlag('issocket', true);
	}

	public function saveForm(): void
	{
		@exec("service memcached stop");
		$this
			->setIni('cachesize', (int)$_SERVER['PARAM_ammina_cachesize'])
			->setIni('maxconn', (int)$_SERVER['PARAM_ammina_maxconn'])
			->setIniFlag('issocket', $_SERVER['PARAM_ammina_issocket'])
			->saveIni();
		$this->dataAppend['ammina_cachesize'] = (int)$_SERVER['PARAM_ammina_cachesize'];
		$this->dataAppend['ammina_maxconn'] = (int)$_SERVER['PARAM_ammina_maxconn'];
		$this->dataAppend['ammina_issocket'] = checkOnOff($_SERVER['PARAM_ammina_issocket']);

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