<?php

namespace AmminaISP\Core\Cron;

use AmminaISP\Core\FilesSynchronizer;
use AmminaISP\Core\ISPManager;
use AmminaISP\Core\Utils;
use function AmminaISP\Core\addJob;
use function AmminaISP\Core\checkDirPath as checkDirPathAlias;

/**
 * Cron операции
 */
abstract class CronAbstract
{
	protected static ?CronAbstract $instance = null;

	protected string $pathApacheVhosts = '/etc/apache2/vhosts';
	protected string $pathNginxVhosts = '/etc/nginx/vhosts';
	protected string $apache2ConfigClass = '';
	protected string $nginxConfigClass = '';
	protected string $redisServiceName = '';
	protected string $redisServicePid = '';
	protected string $memcachedServiceName = '';
	protected string $memcachedServicePid = '';

	public static function getInstance(): static
	{
		if (is_null(static::$instance)) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	public function run(): void
	{
		$this->checkDeletedDomains();
		$this->runCycle();
		return;

		while (true) {
			$this->runCycle();
			if ((int)date('s') >= 50) {
				break;
			}
			sleep(5);
		}
	}

	protected function runCycle(): void
	{
		$this->checkWebConfigApache();
		$this->checkWebConfigNginx();
		$this->checkCronJobs();
	}

	/**
	 * @param string $path
	 * @param string $ext
	 * @return \SplFileInfo[]
	 */
	protected function findConfFiles(string $path, string $ext = 'conf'): array
	{
		$result = [];
		$directory = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO);
		$iterator = new \RecursiveIteratorIterator($directory);
		/**
		 * @var \SplFileInfo $file
		 */
		foreach ($iterator as $file) {
			if (!$file->isFile()) {
				continue;
			}
			if ($file->getExtension() !== $ext) {
				continue;
			}
			if (($file->getCTime() + 5) < time()) {
				$result[] = $file;
			}
		}
		return $result;
	}

	protected function checkWebConfigApache(): void
	{
		foreach ($this->findConfFiles($this->pathApacheVhosts, 'conf') as $fileInfo) {
			$checker = new $this->apache2ConfigClass($fileInfo->getRealPath());
			$checker->run();
		}
	}

	protected function checkWebConfigNginx(): void
	{
		foreach ($this->findConfFiles($this->pathNginxVhosts, 'conf') as $fileInfo) {
			$checker = new $this->nginxConfigClass($fileInfo->getRealPath());
			$checker->run();
		}
	}

	protected function checkDeletedDomains(): void
	{
		$webdomains = ISPManager::getInstance()->getWebdomains();
		/**
		 * @var NginxConfigAbstract $nginx
		 */
		$nginx = new $this->nginxConfigClass('');
		$existsConfig = $nginx->getExistsConfig();
		foreach ($existsConfig as $existsK => $existsV) {
			$currentDomain = array_filter($webdomains, function ($v, $k) use ($existsV) {
				return ($v['domain'] === $existsV['name']);
			}, ARRAY_FILTER_USE_BOTH);
			if (empty($currentDomain)) {
				@unlink($existsV['path']);
			}
		}

		/**
		 * @var Apache2ConfigAbstract $nginx
		 */
		$apache = new $this->apache2ConfigClass('');
		$existsConfig = $apache->getExistsConfig();
		foreach ($existsConfig as $existsK => $existsV) {
			$currentDomain = array_filter($webdomains, function ($v, $k) use ($existsV) {
				return ($v['domain'] === $existsV['name']);
			}, ARRAY_FILTER_USE_BOTH);
			if (empty($currentDomain)) {
				@unlink($existsV['path']);
			}
		}
	}

	protected function checkCronJobs(): void
	{
		foreach (Utils::getAllJob() as $jobPath) {
			$command = unserialize(file_get_contents($jobPath));
			$runStatus = false;
			switch ($command['COMMAND']) {
				case 'ammina.bitrix.skeleton':
					$runStatus = $this->jonAmminaBitrixSkeleton($command['OPTIONS']);
					break;
				case 'ammina.bitrix.makedb':
					$runStatus = $this->jonAmminaBitrixMakeDb($command['OPTIONS']);
					break;
				case 'ammina.bitrix.multisite':
					$runStatus = $this->jonAmminaBitrixMultisite($command['OPTIONS']);
					break;
				case 'ammina.bitrix.cron':
					$runStatus = $this->jonAmminaBitrixCron($command['OPTIONS']);
					break;
				case 'ammina.bitrix.cache.memcached':
					$runStatus = $this->jonAmminaBitrixCacheMemcached($command['OPTIONS']);
					break;
				case 'ammina.bitrix.cache.redis':
					$runStatus = $this->jonAmminaBitrixCacheRedis($command['OPTIONS']);
					break;
				case 'ammina.bitrix.error.log':
					$runStatus = $this->jonAmminaBitrixErrorLog($command['OPTIONS']);
					break;
				case 'ammina.bitrix.composer':
					$runStatus = $this->jonAmminaBitrixComposer($command['OPTIONS']);
					break;
				case 'ammina.bitrix.commands':
					$runStatus = $this->jonAmminaBitrixCommands($command['OPTIONS']);
					break;
				case 'ammina.memcached.monitoring':
					$runStatus = $this->jonAmminaMemcachedMonitoring($command['OPTIONS']);
					break;
				case 'ammina.redis.monitoring':
					$runStatus = $this->jonAmminaRedisMonitoring($command['OPTIONS']);
					break;

			}
			if ($runStatus) {
				@unlink($jobPath);
			}
		}
	}

	protected function jonAmminaBitrixSkeleton(array $option): bool
	{
		$docRoot = ISPManager::getInstance()->getSiteDocroot($option['site']);
		if (strlen($docRoot) > 0 && file_exists($docRoot)) {
			if (file_exists("{$docRoot}/index.html")) {
				unlink("{$docRoot}/index.html");
			}
			$filesSynchronizer = FilesSynchronizer::getInstance();
			$filesSynchronizer->clearRules();
			$filesSynchronizer->addRule('skeleton/bitrix', $docRoot);
			$filesSynchronizer->run();
			$filesSynchronizer->setDefaultRules();
			exec("chown -R " . $option['owner'] . ":" . $option['owner'] . " " . $docRoot);
			exec("find " . $docRoot . " -type d -exec chmod 0755 '{}' \;");
			exec("find " . $docRoot . " -type f -exec chmod 0644 '{}' \;");

			$tmpDir = Utils::getUserHomeDir($option['owner']) . '/tmp/' . $option['site'];
			if (!file_exists($tmpDir)) {
				mkdir($tmpDir, 0755, true);
				exec("chown -R " . $option['owner'] . ":" . $option['owner'] . " " . $tmpDir);
			}
			if (file_exists($docRoot . "/bitrix/php_interface/dbconn.php")) {
				$strFileContent = trim(file_get_contents($docRoot . "/bitrix/php_interface/dbconn.php"));
				$arFileData = explode("\n", $strFileContent);
				$strOldLine = array_pop($arFileData);
				if ($strOldLine != "?>") {
					$arFileData[] = $strOldLine;
				}
				$finded = false;
				foreach ($arFileData as $k => $v) {
					if (str_contains($v, 'BX_TEMPORARY_FILES_DIRECTORY')) {
						$arFileData[$k] = 'define("BX_TEMPORARY_FILES_DIRECTORY", "' . $tmpDir . '");';
						$finded = true;
					}
				}
				if (!$finded) {
					$arFileData[] = 'define("BX_TEMPORARY_FILES_DIRECTORY", "' . $tmpDir . '");';
				}
				$arFileData[] = "";
				file_put_contents($docRoot . "/bitrix/php_interface/dbconn.php", implode("\n", $arFileData));

			}
			chown($docRoot . "/bitrix/php_interface/dbconn.php", $option['owner']);

			return true;
		}
		return false;
	}

	public function randString($length = 10, $extendsChars = ''): string
	{
		return Utils::randString($length, $extendsChars);
	}

	protected function jonAmminaBitrixMakeDb(array $option): bool
	{
		$docRoot = ISPManager::getInstance()->getSiteDocroot($option['site']);
		if (strlen($docRoot) <= 0 || !file_exists($docRoot)) {
			return false;
		}
		$requiredName = $option['name'];
		if (strlen($requiredName) <= 0) {
			$requiredName = str_replace("www.", "", $option['site']);
		}
		$requiredName = strtolower(Utils::translitString($requiredName));
		if (strlen($requiredName) >= 64) {
			$requiredName = substr($requiredName, 0, 55);
		}
		$password = Utils::randString(16, '!@#%*()_');

		$listDb = ISPManager::getInstance()->getListDatabases();
		$finded = array_key_exists($requiredName, $listDb);

		if ($finded) {
			return true;
		}

		$utf8List = ISPManager::getInstance()->getDatabasesUtfList();

		$defaultUtf8 = 'utf8';
		if (!in_array($defaultUtf8, $utf8List)) {
			$defaultUtf8 = 'utf8mb4';
		}
		if (!in_array($defaultUtf8, $utf8List)) {
			$defaultUtf8 = 'utf8mb3';
		}
		if (!in_array($defaultUtf8, $utf8List)) {
			$defaultUtf8 = array_shift($utf8List);
		}
		$charset = ($option['charset'] != "UTF-8" ? "cp1251" : $defaultUtf8);
		$createDbRes = ISPManager::getInstance()->makeMysqlDatabase($requiredName, $option['owner'], $charset, $password);
		if (!$createDbRes) {
			return false;
		}

		$arSettings = [];
		if (file_exists($docRoot . "/bitrix/.settings.php")) {
			$arSettings = include($docRoot . "/bitrix/.settings.php");
		}
		$arSettings['connections'] = [
			'value' =>
				[
					'default' =>
						[
							'className' => '\\Bitrix\\Main\\DB\\MysqliConnection',
							'host' => 'localhost',
							'database' => $requiredName,
							'login' => $requiredName,
							'password' => $password,
							'options' => 2,
						],
				],
			'readonly' => true,
		];
		file_put_contents($docRoot . "/bitrix/.settings.php", '<' . '? return ' . var_export($arSettings, true) . ';');
		chown($docRoot . "/bitrix/.settings.php", $option['owner']);

		if (file_exists($docRoot . "/bitrix/php_interface/dbconn.php")) {
			$strFileContent = trim(file_get_contents($docRoot . "/bitrix/php_interface/dbconn.php"));
			$arFileData = explode("\n", $strFileContent);
			$strOldLine = array_pop($arFileData);
			if ($strOldLine != "?>") {
				$arFileData[] = $strOldLine;
			}
			foreach ($arFileData as $k => $v) {
				if (str_contains($v, '$DBLogin')) {
					$arFileData[$k] = '$DBLogin = "' . $requiredName . '";';
				}
				if (str_contains($v, '$DBName')) {
					$arFileData[$k] = '$DBName = "' . $requiredName . '";';
				}
				if (str_contains($v, '$DBPassword')) {
					$arFileData[$k] = '$DBPassword = "' . $password . '";';
				}
			}
			$arFileData[] = "";
			file_put_contents($docRoot . "/bitrix/php_interface/dbconn.php", implode("\n", $arFileData));

		}
		chown($docRoot . "/bitrix/php_interface/dbconn.php", $option['owner']);

		if (file_exists($docRoot . "/bitrix/php_interface/after_connect_d7.php")) {
			$strFileContent = trim(file_get_contents($docRoot . "/bitrix/php_interface/after_connect_d7.php"));
			$arFileData = explode("\n", $strFileContent);
			$strOldLine = array_pop($arFileData);
			if ($strOldLine != "?>") {
				$arFileData[] = $strOldLine;
			}
			foreach ($arFileData as $k => $v) {
				if (str_contains($v, 'SET NAMES')) {
					$arFileData[$k] = '$this->queryExecute("SET NAMES \'' . $charset . '\'");';
				}
				if (str_contains($v, 'SET collation_connection')) {
					$arFileData[$k] = '$this->queryExecute("SET collation_connection = \'' . $charset . '_general_ci\'");';
				}
			}
			$arFileData[] = "";
			file_put_contents($docRoot . "/bitrix/php_interface/after_connect_d7.php", implode("\n", $arFileData));

		}
		chown($docRoot . "/bitrix/php_interface/after_connect_d7.php", $option['owner']);

		return true;
	}

	protected function jonAmminaBitrixMultisite(array $option): bool
	{
		if (strlen($option['site']) <= 0) {
			return false;
		}
		if (strlen($option['mainSite']) <= 0) {
			return false;
		}
		$docrootFrom = ISPManager::getInstance()->getSiteDocroot($option['mainSite']);
		$docrootTo = ISPManager::getInstance()->getSiteDocroot($option['site']);
		if (strlen($docrootFrom) <= 0 || !file_exists($docrootFrom) || strlen($docrootTo) <= 0 || !file_exists($docrootTo)) {
			return false;
		}
		if (file_exists($docrootFrom . "/bitrix/")) {
			if (!file_exists($docrootTo . "/bitrix")) {
				symlink($docrootFrom . "/bitrix/", $docrootTo . "/bitrix");
				chown($docrootTo . "/bitrix", $option['owner']);
			}
		}
		if (file_exists($docrootFrom . "/upload/")) {
			if (!file_exists($docrootTo . "/upload")) {
				symlink($docrootFrom . "/upload/", $docrootTo . "/upload");
				chown($docrootTo . "/upload", $option['owner']);
			}
		}
		if (file_exists($docrootFrom . "/local/")) {
			if (!file_exists($docrootTo . "/local")) {
				symlink($docrootFrom . "/local/", $docrootTo . "/local");
				chown($docrootTo . "/local", $option['owner']);
			}
		}
		return true;
	}

	protected function jonAmminaBitrixCron(array $option): bool
	{
		$docroot = ISPManager::getInstance()->getSiteDocroot($option['site']);
		$scheduler = ISPManager::getInstance()->getUserScheduler($option['owner']);
		foreach ($scheduler as $job) {
			if (str_contains($job['command'], '/ammina.cron.events.php') && str_contains($job['command'], $docroot . '/bitrix/')) {
				ISPManager::getInstance()->schedulerDelete($option['owner'], $job['key']);
			}
			if ((str_contains($job['command'], '/bitrix/modules/main/tools/cron_events.php') || str_contains($job['command'], '/php_interface/cron_events.php')) && str_contains($job['command'], $docroot . '/bitrix/')) {
				if ($job['active']) {
					ISPManager::getInstance()->schedulerSuspend($option['owner'], $job['key']);
				}
			}
		}
		$siteInfo = ISPManager::getInstance()->getSiteInfo($option['site']);
		$siteName = Utils::idn_to_ascii($option['site']);
		$binPath = "/var/www/{$option['owner']}/data/php-bin-{$siteInfo['php_version']}";
		$phpCommand = "/opt/php{$siteInfo['php_version']}/bin/php -c {$binPath}/php.{$siteName}.ini";
		$file = "{$docroot}/bitrix/php_interface/ammina.cron.events.php";
		$command = "{$phpCommand} -f {$file}";

		$content = file_get_contents($_SERVER['DOCUMENT_ROOT'] . "/core/files/skeleton/bitrix-cron/ammina.cron.events.php");
		if ($siteInfo['bitrix_modules_ammina_backup'] !== 'on') {
			$content = str_replace('//#REMOVE_IF_NOT_AMMINA_BACKUP#', '', $content);
		}
		if (file_get_contents($file) !== $content) {
			file_put_contents($file, $content);
			chown($file, $option['owner']);
		}

		if (file_exists("{$docroot}/bitrix/php_interface/dbconn.php")) {
			$fileContent = trim(file_get_contents("{$docroot}/bitrix/php_interface/dbconn.php"));
			$fileData = explode("\n", $fileContent);

			$bUpdate = false;
			foreach ($fileData as $k => $v) {
				if (str_contains($v, 'BX_CRONTAB_SUPPORT') && !str_contains($v, 'CHK_EVENT')) {
					unset($fileData[$k]);
					$bUpdate = true;
				}
				if (str_contains($v, 'BX_CRONTAB')) {
					unset($fileData[$k]);
					$bUpdate = true;
				}
			}
			if ($bUpdate) {
				$fileContent = implode("\n", $fileData);
			}
			$fileData = array_values($fileData);
			if (!str_contains($fileContent, 'CHK_EVENT')) {
				$oldLine = array_pop($fileData);
				if ($oldLine != "?>") {
					$fileData[] = $oldLine;
				}
				$fileData[] = 'if(!(defined("CHK_EVENT") && CHK_EVENT===true)) define("BX_CRONTAB_SUPPORT", true);';
				$fileData[] = "";
				$bUpdate = true;
			}
			if ($bUpdate) {
				file_put_contents("{$docroot}/bitrix/php_interface/dbconn.php", implode("\n", $fileData));
				chown("{$docroot}/bitrix/php_interface/dbconn.php", $option['owner']);
			}
		}

		ISPManager::getInstance()->schedulerAdd($option['owner'], '"' . $command . '"');

		file_put_contents("{$docroot}/bitrix/php_interface/tmp.ammina.cron.events.php", file_get_contents($_SERVER['DOCUMENT_ROOT'] . "/core/files/skeleton/bitrix-cron/tmp.ammina.cron.events.php"));
		chown("{$docroot}/bitrix/php_interface/tmp.ammina.cron.events.php", $option['owner']);

		$command = "sudo -u {$option['owner']} {$phpCommand} -f {$docroot}/bitrix/php_interface/tmp.ammina.cron.events.php";
		@exec($command);
		@unlink("{$docroot}/bitrix/php_interface/tmp.ammina.cron.events.php");
		return true;
	}

	protected function jonAmminaBitrixCacheMemcached(array $option): bool
	{
		$docroot = ISPManager::getInstance()->getSiteDocroot($option['site']);
		$strIdentSid = substr(md5($option['site']), 0, 4) . substr(crc32($option['site']), 0, 2) . "|";
		$memcachedData = ISPManager::getInstance()->getMemcachedOptions();
		if (is_null($memcachedData)) {
			return false;
		}
		$settings = [];
		if (file_exists("{$docroot}/bitrix/.settings_extra.php")) {
			$settings = include("{$docroot}/bitrix/.settings_extra.php");
		}
		$settings['cache'] = [
			'value' => [
				'type' => [
					'class_name' => '\\Bitrix\\Main\\Data\\CacheEngineMemcache',
					'extension' => 'memcache',
				],
				'memcache' => [
					'host' => $memcachedData['issocket'] ? 'unix:///var/run/memcached/memcached.sock' : '127.0.0.1',
					'port' => $memcachedData['issocket'] ? '0' : '11211',
				],
				'sid' => $strIdentSid,
			],
		];
		file_put_contents("{$docroot}/bitrix/.settings_extra.php", '<' . '? return ' . var_export($settings, true) . ';');
		chown("{$docroot}/bitrix/.settings_extra.php", $option['owner']);

		return true;
	}

	protected function jonAmminaBitrixCacheRedis(array $option): bool
	{
		$docroot = ISPManager::getInstance()->getSiteDocroot($option['site']);
		$strIdentSid = substr(md5($option['site']), 0, 4) . substr(crc32($option['site']), 0, 2) . "|";
		$redisData = ISPManager::getInstance()->getRedisOptions();
		if (is_null($redisData)) {
			return false;
		}
		$settings = [];
		if (file_exists("{$docroot}/bitrix/.settings_extra.php")) {
			$settings = include("{$docroot}/bitrix/.settings_extra.php");
		}
		$settings['cache'] = [
			'value' => [
				'type' => [
					'class_name' => '\\Bitrix\\Main\\Data\\CacheEngineRedis',
					'extension' => 'redis',
				],
				'redis' => [
					'host' => $redisData ? 'unix:///var/run/redis/redis.sock' : '127.0.0.1',
					'port' => $redisData ? '0' : '6379',
				],
				'sid' => $strIdentSid,
				"use_lock" => false,
				"ttl_multiplier" => 2,
				"serializer" => 2,
				"persistent" => true,
			],
		];
		file_put_contents("{$docroot}/bitrix/.settings_extra.php", '<' . '? return ' . var_export($settings, true) . ';');
		chown("{$docroot}/bitrix/.settings_extra.php", $option['owner']);

		return true;
	}

	protected function jonAmminaBitrixErrorLog(array $option): bool
	{
		$docroot = ISPManager::getInstance()->getSiteDocroot($option['site']);
		$settings = [];
		if (file_exists("{$docroot}/bitrix/.settings_extra.php")) {
			$settings = include("{$docroot}/bitrix/.settings_extra.php");
		}
		if (!isset($settings['exception_handling'])) {
			$settings['exception_handling'] = [
				'value' => [
					'debug' => true,
					'handled_errors_types' => 4437,
					'exception_errors_types' => 4437,
					'ignore_silence' => false,
					'assertion_throws_exception' => true,
					'assertion_error_type' => 256,
					'log' => [
						'settings' => [
							'file' => 'bitrix/error.log',
							'log_size' => 1000000,
						],
					],
				],
				'readonly' => false,
			];
			file_put_contents("{$docroot}/bitrix/.settings_extra.php", '<' . '? return ' . var_export($settings, true) . ';');
			chown("{$docroot}/bitrix/.settings_extra.php", $option['owner']);
		}
		return true;
	}

	protected function jonAmminaBitrixComposer(array $option): bool
	{
		$this->checkPhpRunCommand($option['site'], $option['owner']);
		$homeDir = Utils::getUserHomeDir($option['owner']);
		$siteInfo = ISPManager::getInstance()->getSiteInfo($option['site']);
		$siteName = Utils::idn_to_ascii($option['site']);
		$phpCommand = "{$homeDir}/bin/{$siteName}/php -d allow_url_fopen=on";
		$runDir = "{$homeDir}/bin/{$siteName}";
		$fileNameSig = "{$runDir}/composer.sig";
		$fileNameSetup = "{$runDir}/composer-setup.php";
		$fileNamePhar = "{$runDir}/composer.phar";
		$fileNameComposer = "{$runDir}/composer";
		exec("sudo -u {$option['owner']} wget -o /dev/null -O {$fileNameSig} https://composer.github.io/installer.sig");
		exec("sudo -u {$option['owner']} wget -o /dev/null -O {$fileNameSetup} https://getcomposer.org/installer");
		if (file_exists($fileNameSetup) && file_exists($fileNameSig) && hash_file('sha384', $fileNameSetup) == trim(file_get_contents($fileNameSig))) {
			exec("su - {$option['owner']} -c 'cd {$runDir} && {$phpCommand} {$fileNameSetup} --quiet'");
			unlink($fileNameSetup);
			unlink($fileNameSig);
			if (file_exists($fileNamePhar)) {
				rename($fileNamePhar, $fileNameComposer);
				return true;
			}
		}
		return false;
	}

	protected function jonAmminaMemcachedMonitoring(array $option): bool
	{
		ISPManager::getInstance()->makeMemcachedMonitoring($this->memcachedServiceName, $this->memcachedServicePid);
		return true;
	}

	protected function jonAmminaRedisMonitoring(array $option): bool
	{
		ISPManager::getInstance()->makeRedisMonitoring($this->redisServiceName, $this->redisServicePid);
		return true;
	}

	protected function checkPhpRunCommand(string $site, string $owner): void
	{
		$homeDir = Utils::getUserHomeDir($owner);
		$siteInfo = ISPManager::getInstance()->getSiteInfo($site);
		$siteName = Utils::idn_to_ascii($site);
		$binPath = "/var/www/{$owner}/data/php-bin-{$siteInfo['php_version']}";
		$phpCommand = "/opt/php{$siteInfo['php_version']}/bin/php -c {$binPath}/php.{$siteName}.ini";
		$fileContent = [
			"#!/bin/bash",
			"",
			"",
			"exec {$phpCommand} $@",
		];
		$filePath = "{$homeDir}/bin/{$siteName}/php";
		checkDirPathAlias($filePath);
		file_put_contents($filePath, implode("\n", $fileContent));
		exec("chown -R {$owner}:{$owner} {$homeDir}/bin/");
		exec("chmod 0744 {$filePath}");
	}

	protected function jonAmminaBitrixCommands(array $option): bool
	{
		$homeDir = Utils::getUserHomeDir($option['owner']);
		$this->checkPhpRunCommand($option['site'], $option['owner']);
		$siteName = Utils::idn_to_ascii($option['site']);

		$bashRcFile = "{$homeDir}/.bashrc";
		if (!file_exists($bashRcFile)) {
			$content = [
				'',
				'export PATH_DEFAULT="$HOME/bin:$PATH"',
				'',
				'cd ~',
				'. ~/bin/use.default',
				'',
			];
		} else {
			$content = explode("\n", file_get_contents($bashRcFile));
		}
		$aliasCommand = "alias \"use.{$siteName}\"=\"source ~/bin/.use.{$siteName}\"";
		if (!str_contains(implode("\n", $content), $aliasCommand)) {
			$content[] = $aliasCommand;
			$content[] = '';
			file_put_contents($bashRcFile, implode("\n", $content));
			exec("chown {$option['owner']}:{$option['owner']} {$bashRcFile}");
			exec("chmod 0644 {$bashRcFile}");
		}
		$useSiteDefault = "{$homeDir}/bin/use.default";
		if (!file_exists($useSiteDefault)) {
			$content = [
				'#!/bin/bash',
				'',
				'export PATH="$HOME/bin/' . $siteName . ':$PATH_DEFAULT"',
				'cd ~/www/' . $siteName,
			];
			file_put_contents($useSiteDefault, implode("\n", $content));
			exec("chown {$option['owner']}:{$option['owner']} {$useSiteDefault}");
			exec("chmod 0744 {$useSiteDefault}");
		}

		$useSitePaths = "{$homeDir}/bin/.use.{$siteName}";
		if (!file_exists($useSitePaths)) {
			$content = [
				"#!/bin/bash",
				'',
				'echo \'#!/bin/bash\' > $HOME/bin/use.default',
				'echo "" >> $HOME/bin/use.default',
				'echo \'export PATH="$HOME/bin/' . $siteName . ':$PATH_DEFAULT"\' >> $HOME/bin/use.default',
				'echo \'cd ~/www/' . $siteName . '\' >> $HOME/bin/use.default',
				'',
				'source $HOME/bin/use.default',
				'',
			];
			file_put_contents($useSitePaths, implode("\n", $content));
			exec("chown {$option['owner']}:{$option['owner']} {$useSitePaths}");
			exec("chmod 0744 {$useSitePaths}");
		}

		return true;
	}


}