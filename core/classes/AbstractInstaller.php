<?php

namespace AmminaISP\Core;

use AmminaISP\Core\Exceptions\ISPManagerModuleException;
use AmminaISP\Core\Exceptions\ISPManagerMysqlSettingException;

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

	/**
	 * Расчет настроек OpCache
	 * @return void
	 */
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

	/**
	 * Расчет параметров настроек в зависимости от доступной памяти
	 * @return void
	 */
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

	/**
	 * Установка и конфигурирование сервисов
	 * @return void
	 * @throws Exceptions\ISPManagerFeatureException
	 * @throws ISPManagerModuleException
	 * @throws ISPManagerMysqlSettingException
	 */
	public function install(): void
	{
		$this->makeCharset();
		$this->installFilesMgr();
		$this->installIspMgrConfig();
		$this->setBrandInfo();
		$this->installFeatures();
		$this->installModules();
		$this->installPhpExtensions();
		$this->installPhpSettingsShowUsers();
		$this->installPhpSettings();
		$this->installMysqlSettings();
		$this->installFiles();
		$this->installAmminaIspCron();
	}

	/**
	 * Создаем список кодировок для сайтов
	 *
	 * @return void
	 */
	public function makeCharset(): void
	{
		$options = Settings::getInstance()->get("main.make_charsets");
		if ($options['make']) {
			ISPManager::getInstance()->makeCharset($options['list']);
			Console::success("Создан набор кодировок доменов: " . implode(", ", $options['list']));
		}
	}

	public function installFilesMgr(): void
	{
		Console::showColoredString('Синхронизация файлов для ISPManager', 'light_green', null, true);
		FilesSynchronizer::getInstance()
			->setOnlyIspManagerRules()
			->run(true)
			->setDefaultRules();
	}

	/**
	 * Устанавливаем файлы в каталоги операционной системы
	 *
	 * @return void
	 */
	public function installFiles(): void
	{
		Console::showColoredString('Синхронизация файлов', 'light_green', null, true);
		FilesSynchronizer::getInstance()->run(true);
	}

	/**
	 * Настраиваем основной конфиг ISPManager
	 *
	 * @return void
	 */
	public function installIspMgrConfig(): void
	{
		Console::showColoredString("Проверяем конфиг ISPManager", 'light_green', null, true);
		foreach (Settings::getInstance()->get("ispmanager") as $option => $value) {
			ISPManager::getInstance()->checkConfig($option, $value, "Настройка ISPManager: {$option} -> {$value}");
		}
	}

	public function setBrandInfo(): void
	{
		$themes = [
			"orion",
			"z-mobile",
			"dragon",
		];
		foreach ($themes as $theme) {
			$params = [
				"h" => 26,
				"s" => 61,
				"l" => -8,
				"site" => '"https://ammina-isp.ru/"',
				"signature" => '"Веб-студия Ammina"',
				"homepage" => '"https://ammina-isp.ru/"',
				"contacts" => '"https://ammina-isp.ru/contact/"',
				"mainlogo" => "logo.svg",
				"theme" => $theme,
				"sok" => "ok",
				'clicked_button' => 'ok',
			];
			ISPManager::getInstance()->command('brand', $params);
			$xml = simplexml_load_string(file_get_contents(ISPManager::getInstance()->managerPath . '/etc/brand_settings.xml'));
			$json = json_encode($xml);
			$data = json_decode($json, true);
			foreach ($data['brand'] as $brand) {
				if ($brand['@attributes']['theme'] == $theme) {
					$path = ISPManager::getInstance()->managerPath . '/' . $brand['@attributes']['path'] . '/logo.svg';
					file_put_contents($path, file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/core/logo.svg'));
				}
			}
		}
	}

	/**
	 * Установка ПО (features)
	 *
	 * @return void
	 * @throws Exceptions\ISPManagerFeatureException
	 */
	public function installFeatures(): void
	{
		Console::showColoredString("Установить APACHE? Если нет, то будет установлен только NGINX и работа веб-сервера всегда будет в режиме PHP-FPM. (Y/n): ", 'light_red', null, false);
		$apache = (strtolower(readline("")) !== 'n');
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
		$this->installFeaturePhp();
	}

	/**
	 * Установка версий PHP
	 * @return void
	 * @throws Exceptions\ISPManagerFeatureException
	 */
	public function installFeaturePhp(): void
	{
		foreach (Settings::getInstance()->get("features.php") as $version => $options) {
			ISPManager::getInstance()->commandFeaturePhp($version);
		}
	}

	/**
	 * Установка модулей ISPManager
	 * @return void
	 * @throws ISPManagerModuleException
	 */
	public function installModules(): void
	{
		foreach (Settings::getInstance()->get("modules") as $module => $status) {
			ISPManager::getInstance()->commandModuleInstall($module, $status, "Настройка Модули -> " . $module . ". " . ($status ? "Установка" : "Удаление"));
		}
	}

	/**
	 * Устанавливаем расширения PHP
	 *
	 * @return void
	 */
	public function installPhpExtensions(): void
	{
		Console::showColoredString('Установка расширений PHP', 'light_green', null, true);
		$phpVersions = ISPManager::getInstance()->commandPhpVersionsList();
		foreach ($phpVersions as $version) {
			$phpPath = $this->phpPathByVersionName($version);
			if (is_null($phpPath)) {
				continue;
			}
			/*
			$this->installPhpExtensionZstd($version);
			$this->installPhpExtensionLzf($version);
			$this->installPhpExtensionIgbinary($version);
			$this->installPhpExtensionMsgPack($version);
			$this->installPhpExtensionBrotli($version);
			$this->installPhpExtensionRedis($version);
			$this->installPhpExtensionSwoole($version);
			$this->installPhpExtensionOpenSwoole($version);
*/
			$this->installPhpExtensionsForVersion($version);
		}
	}

	/**
	 * Получить путь к каталогу PHP по номеру версии
	 * @param string $phpVersion
	 * @return string|null
	 */
	protected function phpPathByVersionName(string $phpVersion): ?string
	{
		$phpPath = Settings::getInstance()->get("php-path.{$phpVersion}");
		if (!file_exists($phpPath)) {
			return null;
		}
		return $phpPath;
	}

	/**
	 * Выполнение команды компиляции расширения pecl для PHP
	 * @param string $phpVersion
	 * @param string $command
	 * @param string $iniBaseName
	 * @param string $extBaseName
	 * @return void
	 */
	protected function runInstallPhpExtensionCommand(string $phpVersion, string $command, string $iniBaseName, string $extBaseName): void
	{
		Console::showColoredString("Выполняем команду " . $command . " для PHP v.{$phpVersion}", 'yellow', null, true);
		system($command);
		$phpPath = $this->phpPathByVersionName($phpVersion);
		file_put_contents("{$phpPath}/etc/mods-available/{$iniBaseName}.ini", "extension={$extBaseName}.so");
	}

	/**
	 * Проверка настроек - нужно ли устанавливать расширение pecl для PHP выбранной версии
	 * @param string $phpVersion
	 * @param string $extension
	 * @return bool
	 */
	protected function hasInstallPhpExtension(string $phpVersion, string $extension): bool
	{
		$default = Settings::getInstance()->get("php_extensions_install.default.{$extension}");
		$forVersion = Settings::getInstance()->get("php_extensions_install.{$phpVersion}.{$extension}");
		$result = is_null($forVersion) ? $default : $forVersion;
		if (($result === true) || (is_array($result) && $result['install'] === true)) {
			return true;
		}
		return false;
	}

	/**
	 * Возвращает дополнительные настройки pecl расширения для php выбранной версии
	 * @param string $phpVersion
	 * @param string $extension
	 * @return array
	 */
	protected function optionsInstallPhpExtension(string $phpVersion, string $extension): array
	{
		$result = Settings::getInstance()->get("php_extensions_install.default_options.{$extension}");
		if (is_null($result)) {
			$result = [];
		}
		$forVersion = Settings::getInstance()->get("php_extensions_install.{$phpVersion}.{$extension}");
		if (is_array($forVersion) && array_key_exists('options', $forVersion) && is_array($forVersion['options'])) {
			$result = [...$result, ...$forVersion['options']];
		}
		return $result;
	}

	/**
	 * Преобразуем параметр настройки pecl расширения в параметр настройки
	 * @param bool $value
	 * @param bool $addQuote
	 * @return string
	 */
	protected function boolOptionToStringPhpExtension(bool $value, bool $addQuote = true): string
	{
		if (!$addQuote) {
			return $value ? 'yes' : 'no';
		}
		return $value ? '"yes"' : '"no"';
	}

	/**
	 * Установка pecl расширения Zstd
	 * @param string $phpVersion
	 * @return void
	 */
	public function installPhpExtensionZstd(string $phpVersion): void
	{
		if (!$this->hasInstallPhpExtension($phpVersion, 'zstd')) {
			return;
		}
		$phpPath = $this->phpPathByVersionName($phpVersion);
		$command = "{$phpPath}/bin/pecl install zstd";
		if ($phpVersion <= 56) {
			$command = "{$phpPath}/bin/pecl install zstd-0.11.0";
		}
		$this->runInstallPhpExtensionCommand($phpVersion, $command, 'zstd', 'zstd');
	}

	/**
	 * Установка pecl расширения Lzf
	 * @param string $phpVersion
	 * @return void
	 */
	public function installPhpExtensionLzf(string $phpVersion): void
	{
		if (!$this->hasInstallPhpExtension($phpVersion, 'lzf')) {
			return;
		}
		$phpPath = $this->phpPathByVersionName($phpVersion);
		$options = $this->optionsInstallPhpExtension($phpVersion, 'lzf');
		if ($phpVersion <= 71) {
			$lineOptions = implode("\\n", [
				$this->boolOptionToStringPhpExtension($options['better-compression'], false),
				'',
			]);
			$command = "echo \"{$lineOptions}\" | {$phpPath}/bin/pecl install lzf-1.6.8";
		} else {
			$lineOptions = implode(" ", [
				'enable-lzf-better-compression=' . $this->boolOptionToStringPhpExtension($options['better-compression']),
			]);
			$command = "{$phpPath}/bin/pecl install -D '{$lineOptions}' lzf";
		}
		$this->runInstallPhpExtensionCommand($phpVersion, $command, 'lzf', 'lzf');
	}

	/**
	 * Установка pecl расширения Igbinary
	 * @param string $phpVersion
	 * @return void
	 */
	public function installPhpExtensionIgbinary(string $phpVersion): void
	{
		if (!$this->hasInstallPhpExtension($phpVersion, 'igbinary')) {
			return;
		}
		$phpPath = $this->phpPathByVersionName($phpVersion);
		$command = "{$phpPath}/bin/pecl install igbinary";
		if ($phpVersion <= 56) {
			$command = "{$phpPath}/bin/pecl install igbinary-2.0.8";
		}
		$this->runInstallPhpExtensionCommand($phpVersion, $command, 'igbinary', 'igbinary');
	}

	/**
	 * Установка pecl расширения MsgPack
	 * @param string $phpVersion
	 * @return void
	 */
	public function installPhpExtensionMsgPack(string $phpVersion): void
	{
		if (!$this->hasInstallPhpExtension($phpVersion, 'msgpack')) {
			return;
		}
		$phpPath = $this->phpPathByVersionName($phpVersion);
		$command = "{$phpPath}/bin/pecl install msgpack";
		if ($phpVersion <= 56) {
			$command = "{$phpPath}/bin/pecl install msgpack-0.5.7";
		}
		$this->runInstallPhpExtensionCommand($phpVersion, $command, 'msgpack', 'msgpack');
	}

	/**
	 * Установка pecl расширения Brotli
	 * @param string $phpVersion
	 * @return void
	 */
	public function installPhpExtensionBrotli(string $phpVersion): void
	{
		if (!$this->hasInstallPhpExtension($phpVersion, 'brotli')) {
			return;
		}
		if ($phpVersion <= 56) {
			return;
		}
		$phpPath = $this->phpPathByVersionName($phpVersion);
		$command = "{$phpPath}/bin/pecl install brotli";
		$this->runInstallPhpExtensionCommand($phpVersion, $command, 'brotli', 'brotli');
	}

	/**
	 * Установка pecl расширения Redis
	 * @param string $phpVersion
	 * @return void
	 */
	public function installPhpExtensionRedis(string $phpVersion): void
	{
		if (!$this->hasInstallPhpExtension($phpVersion, 'redis')) {
			return;
		}
		if ($phpVersion <= 52) {
			return;
		}
		$phpPath = $this->phpPathByVersionName($phpVersion);
		$options = $this->optionsInstallPhpExtension($phpVersion, 'redis');
		if ($phpVersion <= 56) {
			$lineOptions = implode("\\n", [
				$this->boolOptionToStringPhpExtension($options['igbinary'], false),
				$this->boolOptionToStringPhpExtension($options['lzf'], false),
				'',
			]);
			$command = "echo \"{$lineOptions}\" | {$phpPath}/bin/pecl install redis-4.3.0";
		} elseif ($phpVersion <= 71) {
			$lineOptions = implode("\\n", [
				$this->boolOptionToStringPhpExtension($options['igbinary'], false),
				$this->boolOptionToStringPhpExtension($options['lzf'], false),
				$this->boolOptionToStringPhpExtension($options['zstd'], false),
				'',
			]);
			$command = "echo \"{$lineOptions}\" | {$phpPath}/bin/pecl install redis-5.3.7";
		} else {
			$lineOptions = implode(" ", [
				'enable-redis-igbinary=' . $this->boolOptionToStringPhpExtension($options['igbinary']),
				'enable-redis-lzf=' . $this->boolOptionToStringPhpExtension($options['lzf']),
				'enable-redis-zstd=' . $this->boolOptionToStringPhpExtension($options['zstd']),
				'enable-redis-msgpack=' . $this->boolOptionToStringPhpExtension($options['msgpack']),
				'enable-redis-lz4=' . $this->boolOptionToStringPhpExtension($options['lz4']),
				'with-liblz4=' . $this->boolOptionToStringPhpExtension($options['lz4']),
			]);
			$command = "{$phpPath}/bin/pecl install -D '{$lineOptions}' redis" . ($phpVersion <= 73 ? '-6.0.2' : '');
		}
		$this->runInstallPhpExtensionCommand($phpVersion, $command, 'lzf', 'lzf');
	}

	/**
	 * Установка pecl расширения Swoole
	 * @param string $phpVersion
	 * @return void
	 */
	public function installPhpExtensionSwoole(string $phpVersion): void
	{
		if (!$this->hasInstallPhpExtension($phpVersion, 'swoole')) {
			return;
		}
		if ($phpVersion <= 80) {
			return;
		}
		$phpPath = $this->phpPathByVersionName($phpVersion);
		$options = $this->optionsInstallPhpExtension($phpVersion, 'swoole');
		$lineOptions = implode(" ", [
			'enable-sockets=' . $this->boolOptionToStringPhpExtension($options['sockets']),
			'enable-openssl=' . $this->boolOptionToStringPhpExtension($options['openssl']),
			'enable-http2=' . $this->boolOptionToStringPhpExtension($options['http2']),
			'enable-mysqlnd=' . $this->boolOptionToStringPhpExtension($options['mysqlnd']),
			'enable-swoole-json=' . $this->boolOptionToStringPhpExtension($options['json']),
			'enable-swoole-curl=' . $this->boolOptionToStringPhpExtension($options['curl']),
			'enable-cares=' . $this->boolOptionToStringPhpExtension($options['cares']),
			'enable-swoole-pgsql=' . $this->boolOptionToStringPhpExtension($options['pgsql']),
			'enable-brotli=' . $this->boolOptionToStringPhpExtension($options['brotli']),
			'enable-zstd=' . $this->boolOptionToStringPhpExtension($options['zstd']),
			'with-swoole-odbc=' . $this->boolOptionToStringPhpExtension($options['odbc']),
			'with-swoole-oracle=' . $this->boolOptionToStringPhpExtension($options['oracle']),
			'enable-swoole-sqlite=' . $this->boolOptionToStringPhpExtension($options['sqlite']),
			'enable-swoole-thread=' . $this->boolOptionToStringPhpExtension($options['thread']),
			'enable-iouring=' . $this->boolOptionToStringPhpExtension($options['iouring']),
		]);
		$command = "{$phpPath}/bin/pecl install -D '{$lineOptions}' swoole";
		$this->runInstallPhpExtensionCommand($phpVersion, $command, 'swoole', 'swoole');
	}

	/**
	 * Установка pecl расширения OpenSwoole
	 * @param string $phpVersion
	 * @return void
	 */
	public function installPhpExtensionOpenSwoole(string $phpVersion): void
	{
		if (!$this->hasInstallPhpExtension($phpVersion, 'openswoole')) {
			return;
		}
		if ($phpVersion <= 80) {
			return;
		}
		$phpPath = $this->phpPathByVersionName($phpVersion);
		$options = $this->optionsInstallPhpExtension($phpVersion, 'openswoole');
		$lineOptions = implode(" ", [
			'enable-sockets=' . $this->boolOptionToStringPhpExtension($options['sockets']),
			'enable-openssl=' . $this->boolOptionToStringPhpExtension($options['openssl']),
			'enable-http2=' . $this->boolOptionToStringPhpExtension($options['http2']),
			'enable-mysqlnd=' . $this->boolOptionToStringPhpExtension($options['mysqlnd']),
			'enable-hook-curl=' . $this->boolOptionToStringPhpExtension($options['hook-curl']),
			'enable-cares=' . $this->boolOptionToStringPhpExtension($options['cares']),
			'with-postgres=' . $this->boolOptionToStringPhpExtension($options['postgres']),
		]);
		$command = "{$phpPath}/bin/pecl install -D '{$lineOptions}' openswoole";
		$this->runInstallPhpExtensionCommand($phpVersion, $command, 'openswoole', 'openswoole');
	}

	/**
	 * Включаем или отключаем расширения PHP для версии
	 * @param string $phpVersion
	 * @return void
	 */
	public function installPhpExtensionsForVersion(string $phpVersion): void
	{
		Console::showColoredString("Настройка расширений для PHP v." . $phpVersion, 'light_green', null, true);
		$ispManager = IspManager::getInstance();
		$extensions = $ispManager->commandPhpExtensionsList($phpVersion);
		$options = Settings::getInstance()->get('php_extensions.all');
		$versionOptions = Settings::getInstance()->get("php_extensions.{$phpVersion}");
		if (is_array($versionOptions)) {
			$options = [...$options, ...$versionOptions];
		}
		$extensionsEnable = [];
		$extensionsDisable = [];
		foreach ($extensions as $extension => $status) {
			if (!array_key_exists($extension, $options)) {
				continue;
			}
			if ($options[$extension] && !$status) {
				$extensionsEnable[] = $extension;
			} elseif (!$options[$extension] && $status) {
				$extensionsDisable[] = $extension;
			}
		}
		if (!empty($extensionsEnable)) {
			Console::showColoredString('Включить расширения: ' . implode(", ", $extensionsEnable), 'yellow', null, true);
			$ispManager->commandPhpExtensionsEnable($phpVersion, $extensionsEnable);
		}
		if (!empty($extensionsDisable)) {
			Console::showColoredString('Выключить расширения: ' . implode(", ", $extensionsDisable), 'yellow', null, true);
			$ispManager->commandPhpExtensionsDisable($phpVersion, $extensionsDisable);
		}
	}

	/**
	 * Настраиваем параметры PHP для доступности редактирования пользователями
	 * @return void
	 */
	public function installPhpSettingsShowUsers(): void
	{
		Console::showColoredString("Выбираем параметры для показа пользователям панели", 'light_green', null, true);
		$phpVersions = ISPManager::getInstance()->commandPhpVersionsList();
		foreach ($phpVersions as $version) {
			$phpPath = $this->phpPathByVersionName($version);
			if (is_null($phpPath)) {
				continue;
			}
			$this->installPhpSettingsShowUsersForVersion($version);
		}
	}

	/**
	 * Настраиваем параметры PHP для доступности редактирования пользователями для версии PHP
	 * @param string $phpVersion
	 * @return void
	 */
	public function installPhpSettingsShowUsersForVersion(string $phpVersion): void
	{
		$ispManager = IspManager::getInstance();
		$options = Settings::getInstance()->get('php_settings_show_user.all');
		$versionOptions = Settings::getInstance()->get("php_settings_show_user.{$phpVersion}");
		if (is_array($versionOptions)) {
			$options = [...$options, ...$versionOptions];
		}
		Console::showColoredString("PHP v.{$phpVersion}: \n\t" . implode("\n\t", $options), 'yellow', false, true);
		foreach ($options as $option) {
			$ispManager->commandPhpOptionShowUser($phpVersion, $option);
		}
	}

	/**
	 * Настраиваем параметры PHP
	 * @return void
	 */
	public function installPhpSettings(): void
	{
		Console::showColoredString("Настройка параметров для PHP", 'light_green', null, true);
		$phpVersions = ISPManager::getInstance()->commandPhpVersionsList();
		foreach ($phpVersions as $version) {
			$phpPath = $this->phpPathByVersionName($version);
			if (is_null($phpPath)) {
				continue;
			}
			$this->installPhpSettingsForVersion($version);
		}
	}

	/**
	 * Настройка параметров PHP для версии
	 * @param string $phpVersion
	 * @return void
	 */
	public function installPhpSettingsForVersion(string $phpVersion): void
	{
		$ispManager = IspManager::getInstance();
		$options = Settings::getInstance()->get('php_settings.all');
		$versionOptions = Settings::getInstance()->get("php_settings.{$phpVersion}");
		$matchedOptions = $this->matchOptions['php'];
		if (is_array($versionOptions)) {
			$options = [...$options, ...$versionOptions];
		}
		$options = [...$options, ...$matchedOptions];
		$optionForText = [];
		foreach ($options as $option => $value) {
			$optionForText[] = "\t{$option} = {$value}";
		}
		Console::showColoredString("PHP v.{$phpVersion}: \n" . implode("\n", $optionForText), 'yellow', false, true);
		foreach ($options as $option => $value) {
			$ispManager->commandPhpSettings($phpVersion, $option, $value);
		}
	}

	/**
	 * Настройка параметров mysql серверов
	 *
	 * @return void
	 * @throws ISPManagerMysqlSettingException
	 */
	public function installMysqlSettings(): void
	{
		Console::showColoredString("Настройка параметров для Mysql серверов", 'light_green', null, true);
		$mysqlServers = ISPManager::getInstance()->commandMysqlServerList();
		foreach ($mysqlServers as $server => $serverData) {
			$this->installMysqlSettingsVersion($server);
		}
	}

	/**
	 * Настраиваем mysql сервер
	 * @param string $serverName
	 * @return void
	 * @throws ISPManagerMysqlSettingException
	 */
	public function installMysqlSettingsVersion(string $serverName): void
	{
		Console::showColoredString("Mysql сервер {$serverName}:", 'yellow', null, true);
		$ispManager = IspManager::getInstance();
		$mysqlServers = $ispManager->commandMysqlServerList();
		if (!array_key_exists($serverName, $mysqlServers)) {
			return;
		}
		$info = $ispManager->getMysqlVersion($serverName);
		$versionOptions = null;
		$versionFullOptions = null;
		if (!is_null($info)) {
			$serverType = $info[0];
			$serverVersion = explode('.', $info[1]);
			$serverVersion = "{$serverVersion[0]}.{$serverVersion[1]}";
			$serverVersionFull = $info[1];
			$versionOptions = (Settings::getInstance()->get("mysql_settings.version.{$serverType}") ?? [])[$serverVersion] ?? null;
			$versionFullOptions = (Settings::getInstance()->get("mysql_settings.version.{$serverType}") ?? [])[$serverVersionFull] ?? null;
		}
		$options = Settings::getInstance()->get('mysql_settings.all');
		$nameOptions = Settings::getInstance()->get("mysql_settings.name.{$serverName}");
		$matchedOptions = $this->matchOptions['mysql'];
		$options = [...$options, ...$matchedOptions];
		if (is_array($versionOptions)) {
			$options = [...$options, ...$versionOptions];
		}
		if (is_array($versionFullOptions)) {
			$options = [...$options, ...$versionFullOptions];
		}
		if (is_array($nameOptions)) {
			$options = [...$options, ...$nameOptions];
		}
		foreach ($options as $option => $optionValue) {
			$options[str_replace('-', '_', $option)] = $optionValue;
		}

		foreach ($options as $option => $optionValue) {
			$parameter = $ispManager->commandMysqlServerSetting($serverName, $option);
			if (is_null($parameter)) {
				continue;
			}
			if ($parameter['value'] != $optionValue) {
				Console::showColoredString("\t{$option}: {$parameter['value']} -> {$optionValue}", 'yellow', null, true);
				$ispManager->commandMysqlServerSetSetting($serverName, $option, $parameter['type'], $optionValue);

				$parameter = $ispManager->commandMysqlServerSetting($serverName, $option);
				if ($parameter['value'] != $optionValue) {
					Console::error("Ошибка применения значения: {$option}: {$parameter['value']} -> {$optionValue}");
				}
			}
		}
	}

	public function installAmminaIspCron()
	{
		Console::showColoredString("Добавляем системное задание AmminaISP", 'light_green', null, true);
		ISPManager::getInstance()->makeAmminaIspCronCommand();
	}
}