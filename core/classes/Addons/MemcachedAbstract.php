<?php

namespace AmminaISP\Core\Addons;

use function AmminaISP\Core\addJob;
use function AmminaISP\Core\execShellCommand;
use function AmminaISP\Core\isOn;

abstract class MemcachedAbstract extends AddonAbstract
{

	public string $templateFileName = '/usr/local/mgr5/etc/templates/amminaisp/memcached.template';
	public string $redisOptionsFile = '/etc/memcached.conf';
	public string $memcachedRun = '/var/run/memcached';
	public string $memcachedUser = 'memcache';
	public ?string $settingsFile = 'memcached.ini';

	public array $params = [
		'ammina_cachesize' => [
			'type' => 'int',
			'ini' => 'cachesize',
			'default' => 64,
		],
		'ammina_maxconn' => [
			'type' => 'int',
			'ini' => 'maxconn',
			'default' => 1024,
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
		$this->fillDataAppendFromIni();
	}

	public function saveForm(): void
	{
		@execShellCommand("service memcached stop");
		$this->fillIniFromParams();
		$this->saveIni();
		$this->fillDataAppendFromIni();

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
			@execShellCommand("service memcached start");
		}
	}

	protected function checkMonitoring(): void
	{
		addJob("ammina.memcached.monitoring", []);
	}

	protected abstract function checkSystemSettings(): void;

}