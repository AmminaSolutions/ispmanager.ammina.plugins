<?php

namespace AmminaISP\Core\Cron;

use AmminaISP\Core\ISPManager;
use function AmminaISP\Core\checkDirPath;

abstract class WebConfigAbstract
{
	public string $filePath = '';
	public string $resultDir = '';
	public array $webdomainInfo = [];
	public string $configTestCommand = '';
	public string $restartCommand = '';
	public string $pidFile = '';

	public function __construct(string $filePath)
	{
		$this->filePath = $filePath;
	}

	/**
	 * Информация о домене
	 * @return void
	 */
	protected function makeWebdomainInfo(): void
	{
		$fileInfo = new \SplFileInfo($this->filePath);
		$domain = $fileInfo->getBasename("." . $fileInfo->getExtension());
		$this->webdomainInfo = ISPManager::getInstance()->getSiteInfo($domain);
		$configFile = explode("\n", file_get_contents($this->filePath));
		foreach ($configFile as $line) {
			$line = trim($line);
			if (empty($line)) {
				continue;
			}
			if (!str_starts_with($line, "__")) {
				continue;
			}
			$line = substr($line, 2);
			$category = substr($line, 0, strpos($line, "_"));
			$line = substr($line, strpos($line, "_") + 1);
			$arLine = explode('=', $line, 2);
			$arLine[1] = trim($arLine[1]);
			if (str_starts_with($arLine[1], '"') && str_ends_with($arLine[1], '"')) {
				$arLine[1] = substr($arLine[1], 1, -1);
			}
			$this->webdomainInfo['__'][$category][trim($arLine[0])] = $arLine[1];
		}
		//print_r($this->webdomainInfo);
	}

	public function configTest(): bool
	{
		if ($this->configTestCommand == '') {
			return true;
		}
		$descriptor = [
			["pipe", "r"],
			["pipe", "w"],
			["pipe", "w"],
		];
		$pipes = [];
		$proc = proc_open($this->configTestCommand, $descriptor, $pipes);
		if (is_resource($proc)) {
			sleep(2);
			foreach ($pipes as $pipe) {
				fclose($pipe);
			}
			$return = proc_close($proc);
			if ($return === 0) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Перезапускаем сервис
	 * @return void
	 */
	public function restartService(): void
	{
		if ($this->restartCommand == '') {
			return;
		}
		$complete = false;
		$descriptor = [
			["pipe", "r"],
			["pipe", "w"],
			["pipe", "w"],
		];
		$pipes = [];
		$proc = proc_open($this->restartCommand, $descriptor, $pipes);
		if (is_resource($proc)) {
			foreach ($pipes as $pipe) {
				fclose($pipe);
			}
			$return = proc_close($proc);
			if ($return === 0) {
				$complete = true;
			}
		}
		if ($complete) {
			if ($this->pidFile != '') {
				$i = 30;
				while ($i > 0) {
					sleep(1);
					if (file_exists($this->pidFile)) {
						return;
					}
					$i--;
				}
			}
		}
		/**
		 * @todo сообщение об ошибке
		 */
	}

	/**
	 * Обновляем конфиг
	 * @param string $fileName
	 * @param string $content
	 * @return void
	 */
	public function updateConfig(string $fileName, string $content): void
	{
		checkDirPath($fileName);
		$oldContent = null;
		if (file_exists($fileName)) {
			$oldContent = file_get_contents($fileName);
		}
		file_put_contents($fileName, $content);
		if ($this->configTest()) {
			$this->restartService();
		} else {
			if (!is_null($oldContent)) {
				file_put_contents($fileName, $oldContent);
			} else {
				@unlink($fileName);
			}
			/**
			 * @todo Добавить сообщение об ошибке
			 */
		}
	}

	abstract public function run(): void;

	abstract public function makeConfig(): void;

}