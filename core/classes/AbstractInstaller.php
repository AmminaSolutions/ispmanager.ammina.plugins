<?php

namespace AmminaISP\Core;
abstract class AbstractInstaller
{
	public int $memorySize;
	public int $cpuCore;
	public array $matchOptions = [];

	public function __construct()
	{
		$this->matchOptions();
	}

	/**
	 * Вычисление настроек в зависимости от размера памяти
	 *
	 * @return void
	 */
	public function matchOptions(): void
	{
		$command = "free | awk '/Mem/{print $2}'";
		$this->memorySize = (int)exec($command) * 1024;
		$command = "grep 'model name' -c /proc/cpuinfo";
		$this->cpuCore = (int)exec($command);
		$this->matchOptionsOpcache();
		$this->matchOptionsByMemory();
	}

	protected function matchOptionsOpcache(): void
	{
		$this->matchOptions['php']['opcache.memory_consumption'] = intval($this->memorySize / 8388608);
		if ($this->matchOptions['php']['opcache.memory_consumption'] < 64) {
			$this->matchOptions['php']['opcache.memory_consumption'] = 64;
		} elseif ($this->matchOptions['php']['opcache.memory_consumption'] > 2048) {
			$this->matchOptions['php']['opcache.memory_consumption'] = 2048;
		}
		$this->matchOptions['php']['opcache.interned_strings_buffer'] = intval($this->matchOptions['php']['opcache.memory_consumption'] / 4);
	}

	protected function matchOptionsByMemory(): void
	{
		$settings = Settings::getInstance();
		$memoryList = [
			512,
			1024,
			1536,
			2048,
			3072,
			4096,
			5120,
			6144,
			8192,
			16384,
			24576,
			32768,
			65536,
			131072,
		];
		$systemType = 1;
		$checkSize = intval($this->memorySize / 1048576);
		foreach ($memoryList as $val) {
			if ($checkSize >= $val) {
				$systemType++;
			}
		}

		$memory = $settings->get("memory_params");
		$memoryIndependent = $settings->get("memory_params_independent");
		$memoryCoefficient = $settings->get("memory_params_coefficient");
		foreach ($memoryCoefficient as $k => $v) {
			if ($v == "K") {
				$memoryCoefficient[$k] = 1024;
			} elseif ($v == "M") {
				$memoryCoefficient[$k] = 1024 * 1024;
			} elseif ($v == "G") {
				$memoryCoefficient[$k] = 1024 * 1024 * 1024;
			} elseif ($v == "T") {
				$memoryCoefficient[$k] = 1024 * 1024 * 1024 * 1024;
			}
		}
		foreach ($memory as $k => $v) {
			$value = $v[$systemType];
			if (isset($memoryIndependent[$k]) && $memoryIndependent[$k] > 0) {
				$value = $memoryIndependent[$k];
			}
			if (isset($memoryCoefficient[$k])) {
				$value = $value * $memoryCoefficient[$k];
			}
			$arV = explode(",", $value);
			foreach ($arV as $newVal) {
				$newVal = trim($newVal);
				if (strlen($newVal) <= 0) {
					continue;
				}
				if (str_contains($k, '|')) {
					$ar = explode("|", $k);
					$this->matchOptions[$ar[0]][$ar[1]] = $newVal;
				} else {
					$this->matchOptions[$k] = $newVal;
				}
			}
		}
	}

	public function makeCharset(): void
	{
		$options = Settings::getInstance()->get("main.make_charsets");
		if ($options['make']) {
			ISPManager::getInstance()->makeCharset($options['list']);
			Console::success("Создан набор кодировок доменов: " . implode(", ", $options['list']));
		}
	}

	public function install(): void
	{
		$this->makeCharset();
		$this->installFeatures();


	}

	public function installFeatures(): void
	{
		Console::showColoredString("Установить APACHE? Если нет, то будет установлен только NGINX и работа веб-сервера всегда будет в режиме PHP-FPM. (Y/n): ", 'light_red', null, false);
		$apache = (strtolower(readline("")) !== 'n');
		$settings = Settings::getInstance();
		$ispManager = ISPManager::getInstance();
		$ispManager->commandFeatureWeb(false);
		if ($apache) {
			$ispManager->commandFeatureWeb(true);
		}
		$ispManager->commandFeatureEMail();
		$ispManager->commandFeatureDns();
		$ispManager->commandFeatureFtp();
		$ispManager->commandFeatureMySql();
		$ispManager->commandFeaturePhpMyAdmin();
		$ispManager->commandFeaturePostgreeSql();
		$ispManager->commandFeaturePhpPgAdmin();
		$ispManager->commandFeatureQuota();
		$ispManager->commandFeatureFail2ban();
		$ispManager->commandFeatureAnsible();
		$ispManager->commandFeatureDocker();
		$ispManager->commandFeatureNodejs();
		$ispManager->commandFeaturePython();
		$ispManager->commandFeatureWireGuard();
		$ispManager->commandFeatureRedis();
		$ispManager->commandFeatureMemcached();
	}
}