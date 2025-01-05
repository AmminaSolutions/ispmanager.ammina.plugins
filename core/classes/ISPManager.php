<?php

namespace AmminaISP\Core;

use AmminaISP\Core\Exceptions\ISPManagerFeatureException;
use AmminaISP\Core\Exceptions\ISPManagerModuleException;

class ISPManager
{
	protected static ?ISPManager $instance = null;

	protected string $managerCommand = '/usr/local/mgr5/sbin/mgrctl';
	protected string $managerPath = '/usr/local/mgr5';

	protected static int $maxRetryWait = 300;

	public static function getInstance(): static
	{
		if (is_null(static::$instance)) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	public function __construct()
	{

	}

	/**
	 * Выполнение команды
	 *
	 * @param string $function
	 * @param array $arParams
	 * @param string $manager
	 * @return array
	 */
	public function command(string $function, array $arParams = [], string $manager = "ispmgr"): array
	{
		$strCommand = $this->managerCommand . " -m " . $manager . " -o json " . $function;
		foreach ($arParams as $k => $v) {
			if (is_array($v)) {
				$strCommand .= ' ' . $k . '="' . implode(", ", $v) . '"';
			} else {
				$strCommand .= ' ' . $k . '=' . $v;
			}
		}
		$strResult = [];
		exec($strCommand, $strResult);
		return json_decode(implode("\n", $strResult), true);
	}

	/**
	 * Создать список кодировок
	 *
	 * @param array $list
	 * @return void
	 */
	public function makeCharset(array $list): void
	{
		file_put_contents(joinPaths($this->managerPath, 'etc/charset'), implode("\n", $list));
	}

	/**
	 * Сообщение о начале выполнения команды
	 *
	 * @param string $message
	 * @param array $params
	 * @return void
	 */
	protected function messageCommand(string $message, array $params = []): void
	{
		Console::showColoredString($message, 'light_green', null, true);
		if (!empty($params)) {
			$content = '';
			foreach ($params as $k => $v) {
				$content .= "\t" . $k . ' = ' . $v . "\n";
			}
			Console::showColoredString("Параметры: \n" . $content, 'yellow', null, true);
		}
	}

	/**
	 * Вывод статуса выполнения команды
	 *
	 * @param bool $complete
	 * @return void
	 */
	protected function messageCommandResult(bool $complete = true): void
	{
		if ($complete) {
			Console::success('Успешно выполнено');
		} else {
			Console::error('Ошибка выполнения');
		}
	}

	/**
	 * Сообщение об ожидании завершения процесса
	 *
	 * @param string $text
	 * @return void
	 */
	protected function messageCommandWait(string $text = "Ожидание завершения процесса..."): void
	{
		Console::showColoredString($text, 'light_blue', null, true);
	}

	/***************************
	 * Группа операций feature *
	 **************************/

	/**
	 * Команда feature.edit
	 *
	 * @param string $type
	 * @param array $params
	 * @param string $message
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandFeatureInstall(string $type, array $params, string $message = ''): void
	{
		$this->messageCommand($message, $params);
		$params['elid'] = $type;
		$params['sok'] = 'ok';
		$params['clicked_button'] = 'ok';
		$this->command("feature.edit", $params);
		$result = $this->featureWait($type);
		$this->messageCommandResult($result);
		if (!$result) {
			throw new ISPManagerFeatureException();
		}
	}

	/**
	 * Возвращает значение группы параметров для feature group
	 *
	 * @param bool|null $value
	 * @param string $option
	 * @param string $default
	 * @return string
	 */
	protected function featureTurnValue(?bool $value, string $option, string $default): string
	{
		if (is_null($value) || $value === true) {
			$value = Settings::getInstance()->get($option);
		}
		if ($value === false) {
			$value = 'turn_off';
		}
		if ($value === true) {
			$value = $default;
		}
		return $value;
	}

	/**
	 * Возвращает строковое значение флага для feature
	 *
	 * @param bool|null $value
	 * @param string $option
	 * @return string
	 */
	protected function featureFlagValue(?bool $value, string $option): string
	{
		return boolToFlag(is_null($value) ? Settings::getInstance()->get($option) : $value);
	}

	/**
	 * Группа features Web
	 *
	 * @param string|bool|null $apache
	 * @param bool|null $nginx
	 * @param bool|null $logrotate
	 * @param bool|null $awstats
	 * @param bool|null $php
	 * @param bool|null $phpFpm
	 * @param bool|null $pagespeed
	 * @param bool|null $phpComposer
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandFeatureWeb(?bool $apache = null, ?bool $nginx = null, ?bool $logrotate = null, ?bool $awstats = null, ?bool $php = null, ?bool $phpFpm = null, ?bool $pagespeed = null, ?bool $phpComposer = null): void
	{
		$params = [
			'packagegroup_apache' => $this->featureTurnValue($apache, 'features.web.apache', 'apache-prefork'),
			'package_nginx' => $this->featureFlagValue($nginx, 'features.web.nginx'),
			'package_logrotate' => $this->featureFlagValue($logrotate, 'features.web.logrotate'),
			'package_awstats' => $this->featureFlagValue($awstats, 'features.web.awstats'),
			'package_php' => $this->featureFlagValue($php, 'features.web.php'),
			'package_php-fpm' => $this->featureFlagValue($phpFpm, 'features.web.php_fpm'),
			'package_pagespeed' => $this->featureFlagValue($pagespeed, 'features.web.pagespeed'),
			'package_phpcomposer' => $this->featureFlagValue($phpComposer, 'features.web.phpcomposer'),
		];
		$this->commandFeatureInstall("web", $params, "Настройка Возможности -> Веб-сервер (WWW)");
	}

	/**
	 * Группа features EMail
	 *
	 * @param bool|null $mta
	 * @param bool|null $dovecot
	 * @param bool|null $greylisting
	 * @param bool|null $opendkim
	 * @param bool|null $spamassassin
	 * @param bool|null $clamav
	 * @param bool|null $sieve
	 * @param bool|null $roundcube
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandFeatureEMail(?bool $mta = null, ?bool $dovecot = null, ?bool $greylisting = null, ?bool $opendkim = null, ?bool $spamassassin = null, ?bool $clamav = null, ?bool $sieve = null, ?bool $roundcube = null): void
	{
		$params = [
			'packagegroup_mta' => $this->featureTurnValue($mta, 'features.mail.mta', 'exim'),
			'package_dovecot' => $this->featureFlagValue($dovecot, 'features.mail.dovecot'),
			'package_postgrey' => $this->featureFlagValue($greylisting, 'features.mail.greylisting'),
			'package_opendkim' => $this->featureFlagValue($opendkim, 'features.mail.opendkim'),
			'package_spamassassin' => $this->featureFlagValue($spamassassin, 'features.mail.spamassassin'),
			'package_clamav' => $this->featureFlagValue($clamav, 'features.mail.clamav'),
			'package_sieve' => $this->featureFlagValue($sieve, 'features.mail.sieve'),
			'package_roundcube' => $this->featureFlagValue($roundcube, 'features.mail.roundcube'),
		];
		$this->commandFeatureInstall("email", $params, "Настройка Возможности -> Почтовый сервер (SMTP/POP3/IMAP)");
	}

	/**
	 * Группа features DNS
	 *
	 * @param bool|null $dns
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandFeatureDns(?bool $dns = null): void
	{
		$params = [
			'packagegroup_dns' => $this->featureTurnValue($dns, 'features.dns.dns', 'bind'),
		];
		$this->commandFeatureInstall("dns", $params, "Настройка Возможности -> Сервер имен (DNS)");
	}

	/**
	 * Группа features FTP
	 *
	 * @param bool|null $ftp
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandFeatureFtp(?bool $ftp = null): void
	{
		$params = [
			'packagegroup_ftp' => $this->featureTurnValue($ftp, 'features.ftp.ftp', 'proftp'),
		];
		$this->commandFeatureInstall("ftp", $params, "Настройка Возможности -> FTP-сервер");
	}

	/**
	 * Группа features MySQL
	 *
	 * @param bool|null $mysql
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandFeatureMySql(?bool $mysql = null): void
	{
		$params = [
			'package_mysql' => $this->featureFlagValue($mysql, 'features.mysql.mysql'),
		];
		$this->commandFeatureInstall("mysql", $params, "Настройка Возможности -> Сервер СУБД MySQL");
	}

	/**
	 * Группа features PhpMyAdmin
	 *
	 * @param bool|null $phpmyadmin
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandFeaturePhpMyAdmin(?bool $phpmyadmin = null): void
	{
		$params = [
			'package_phpmyadmin' => $this->featureFlagValue($phpmyadmin, 'features.phpmyadmin.phpmyadmin'),
		];
		$this->commandFeatureInstall("phpmyadmin", $params, "Настройка Возможности -> Веб-интерфейс администрирования MySQL (phpMyAdmin)");
	}

	/**
	 * Группа features PostgreSQL
	 *
	 * @param bool|null $postgresql
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandFeaturePostgreeSql(?bool $postgresql = null): void
	{
		$params = [
			'package_postgresql' => $this->featureFlagValue($postgresql, 'features.postgresql.postgresql'),
		];
		$this->commandFeatureInstall("postgresql", $params, "Настройка Возможности -> Сервер СУБД PostgreSQL");
	}

	/**
	 * Группа features PhpPgAdmin
	 *
	 * @param bool|null $phppgadmin
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandFeaturePhpPgAdmin(?bool $phppgadmin = null): void
	{
		$params = [
			'package_phppgadmin' => $this->featureFlagValue($phppgadmin, 'features.phppgadmin.phppgadmin'),
		];
		$this->commandFeatureInstall("phppgadmin", $params, "Настройка Возможности -> Веб-интерфейс администрирования PostgreSQL (phpPgAdmin)");
	}

	/**
	 * Группа features Quota
	 *
	 * @param bool|null $quota
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandFeatureQuota(?bool $quota = null): void
	{
		$params = [
			'package_quota' => $this->featureFlagValue($quota, 'features.quota.quota'),
		];
		$this->commandFeatureInstall("quota", $params, "Настройка Возможности -> Дисковые квоты");
	}

	/**
	 * Группа features Fail2ban
	 *
	 * @param bool|null $fail2ban
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandFeatureFail2ban(?bool $fail2ban = null): void
	{
		$params = [
			'package_fail2ban' => $this->featureFlagValue($fail2ban, 'features.fail2ban.fail2ban'),
		];
		$this->commandFeatureInstall("fail2ban", $params, "Настройка Возможности -> Fail2ban");
	}

	/**
	 * Группа features Ansible
	 *
	 * @param bool|null $ansible
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandFeatureAnsible(?bool $ansible = null): void
	{
		$params = [
			'package_ansible' => $this->featureFlagValue($ansible, 'features.ansible.ansible'),
		];
		$this->commandFeatureInstall("ansible", $params, "Настройка Возможности -> Ansible (установка Web-скриптов)");
	}

	/**
	 * Группа features Docker (доступно не на всех тарифах ISPManager)
	 *
	 * @param bool|null $docker
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandFeatureDocker(?bool $docker = null): void
	{
		$params = [
			'package_docker' => $this->featureFlagValue($docker, 'features.docker.docker'),
		];
		$this->commandFeatureInstall("docker", $params, "Настройка Возможности -> Docker");
	}

	/**
	 * Группа features Nodejs
	 *
	 * @param bool|null $nodejs
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandFeatureNodejs(?bool $nodejs = null): void
	{
		$params = [
			'package_nodejs' => $this->featureFlagValue($nodejs, 'features.nodejs.nodejs'),
		];
		$this->commandFeatureInstall("nodejs", $params, "Настройка Возможности -> Node.js");
	}

	/**
	 * Группа features Python
	 *
	 * @param bool|null $altpythongr
	 * @param bool|null $isppython38
	 * @param bool|null $isppython39
	 * @param bool|null $isppython310
	 * @param bool|null $isppython311
	 * @param bool|null $isppython312
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandFeaturePython(?bool $altpythongr = null, ?bool $isppython38 = null, ?bool $isppython39 = null, ?bool $isppython310 = null, ?bool $isppython311 = null, ?bool $isppython312 = null): void
	{
		$params = [
			'packagegroup_altpythongr' => $this->featureTurnValue($altpythongr, 'features.python.altpythongr', 'Python'),
			'package_isppython38' => $this->featureFlagValue($isppython38, 'features.python.isppython38'),
			'package_isppython39' => $this->featureFlagValue($isppython39, 'features.python.isppython39'),
			'package_isppython310' => $this->featureFlagValue($isppython310, 'features.python.isppython310'),
			'package_isppython311' => $this->featureFlagValue($isppython311, 'features.python.isppython311'),
			'package_isppython312' => $this->featureFlagValue($isppython312, 'features.python.isppython312'),
		];
		$this->commandFeatureInstall("python", $params, "Настройка Возможности -> Python");
	}

	/**
	 * Группа features WireGuard
	 *
	 * @param bool|null $wireguard
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandFeatureWireGuard(?bool $wireguard = null): void
	{
		$params = [
			'package_wireguard' => $this->featureFlagValue($wireguard, 'features.wireguard.wireguard'),
		];
		$this->commandFeatureInstall("wireguard", $params, "Настройка Возможности -> WireGuard");
	}

	/**
	 * Группа features Redis
	 *
	 * @param bool|null $redis
	 * @param int|null $memorylimit
	 * @param int|null $databases
	 * @param bool|null $issocket
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandFeatureRedis(?bool $redis = null, ?int $memorylimit = null, ?int $databases = null, ?bool $issocket = null): void
	{
		$params = [
			'package_redis' => $this->featureFlagValue($redis, 'features.redis.redis'),
		];
		$this->commandFeatureInstall("redis", $params, "Настройка Возможности -> Redis");
		if ($params['package_redis'] !== 'off') {
			$this->commandAmminaRedisOptions($memorylimit, $databases, $issocket);
		}
	}

	/**
	 * Группа features Memcached
	 *
	 * @param bool|null $memcached
	 * @param int|null $cachesize
	 * @param int|null $maxconn
	 * @param bool|null $issocket
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandFeatureMemcached(?bool $memcached = null, ?int $cachesize = null, ?int $maxconn = null, ?bool $issocket = null): void
	{
		$params = [
			'package_memcached' => $this->featureFlagValue($memcached, 'features.memcached.memcached'),
		];
		$this->commandFeatureInstall("memcached", $params, "Настройка Возможности -> Memcached");
		if ($params['package_memcached'] !== 'off') {
			$this->commandAmminaMemcachedOptions($cachesize, $maxconn, $issocket);
		}
	}

	/**
	 * Ожидаем окончания операций feature
	 *
	 * @param string $feature
	 * @return bool
	 */
	protected function featureWait(string $feature): bool
	{
		$this->messageCommandWait();
		sleep(5);
		$maxRetry = static::$maxRetryWait;
		while (true) {
			sleep(1);
			$result = $this->command("feature");
			$findStatus = false;
			foreach ($result['doc']['elem'] as $v) {
				if ($v['name']['$'] == $feature) {
					if (isset($v['status'])) {
						$findStatus = true;
					}
				}
			}
			if (!$findStatus) {
				return true;
			}
			$maxRetry--;
			if ($maxRetry < 0) {
				return false;
			}
		}
	}


	/**************************************
	 * Группа операций настройки плагинов *
	 *************************************/

	/**
	 * Получаем значение для вызова плагина Ammina
	 *
	 * @param mixed $value
	 * @param string $option
	 * @param mixed $default
	 * @return string
	 */
	protected function amminaAddonValue(mixed $value, string $option, mixed $default): string
	{
		if (is_null($value)) {
			$value = Settings::getInstance()->get($option);
		}
		if (is_null($value)) {
			return $default;
		}
		return $value;
	}

	/**
	 * Выполняем команду обращения к плагину Ammina
	 * @param string $function
	 * @param array $params
	 * @param string $message
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandAmminaAddonsOptions(string $function, array $params, string $message = ''): void
	{
		$this->messageCommand($message, $params);
		$params['sok'] = 'ok';
		$result = $this->command($function, $params);
		$result = !array_key_exists('error', $result['doc']);
		$this->messageCommandResult($result);
		if (!$result) {
			throw new ISPManagerFeatureException();
		}
	}

	/**
	 * Настройка плагина Redis
	 * @param int|null $memorylimit
	 * @param int|null $databases
	 * @param bool|null $issocket
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandAmminaRedisOptions(?int $memorylimit = null, ?int $databases = null, ?bool $issocket = null): void
	{
		$params = [
			'ammina_memorylimit' => $this->amminaAddonValue($memorylimit, 'features.redis.options.memorylimit', 128),
			'ammina_databases' => $this->amminaAddonValue($databases, 'features.redis.options.databases', 16),
			'ammina_issocket' => boolToFlag($this->amminaAddonValue($issocket, 'features.redis.options.issocket', true)),
		];
		$this->commandAmminaAddonsOptions('ammina_redis', $params, 'Настройка параметров Redis');
	}

	/**
	 * Настройка плагина Memcached
	 *
	 * @param int|null $cachesize
	 * @param int|null $maxconn
	 * @param bool|null $issocket
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function commandAmminaMemcachedOptions(?int $cachesize = null, ?int $maxconn = null, ?bool $issocket = null): void
	{
		$params = [
			'ammina_cachesize' => $this->amminaAddonValue($cachesize, 'features.memcached.options.cachesize', 64),
			'ammina_maxconn' => $this->amminaAddonValue($maxconn, 'features.memcached.options.maxconn', 1024),
			'ammina_issocket' => boolToFlag($this->amminaAddonValue($issocket, 'features.memcached.options.issocket', true)),
		];
		$this->commandAmminaAddonsOptions('ammina_memcached', $params, 'Настройка параметров Memcached');
	}

	/***************************
	 * Группа операций modules *
	 **************************/

	/**
	 * Установка или удаление модуля ISPManager
	 *
	 * @param string $module
	 * @param bool $install
	 * @param string $message
	 * @return void
	 * @throws ISPManagerModuleException
	 */
	public function commandModuleInstall(string $module, bool $install = true, string $message = ''): void
	{
		$this->messageCommand($message);
		$params = [
			'name' => "ispmanager-plugin-{$module}",
			'clicked_button' => ($install ? 'install' : 'delete'),
			'sok' => 'ok',
		];
		$this->command("plugin", $params);
		$result = $this->moduleWait($module, $install);
		$this->messageCommandResult($result);
		if (!$result) {
			throw new ISPManagerModuleException();
		}
	}

	/**
	 * Ожидание процесса установки или удаления модуля ISPManager
	 * @param string $module
	 * @param bool $install
	 * @return bool
	 */
	protected function moduleWait(string $module, bool $install = true): bool
	{
		$this->messageCommandWait();
		sleep(5);
		$maxRetry = static::$maxRetryWait;
		while (true) {
			sleep(1);
			$result = $this->command("plugin");
			$findButton = false;
			foreach ($result['doc']['list'] as $group) {
				foreach ($group['elem'] as $v) {
					if ($v['name']['$'] == 'ispmanager-plugin-' . $module) {
						if (isset($v['setup_action']['button']['$name'])) {
							$v['setup_action']['button'] = [$v['setup_action']['button']];
						}
						foreach ($v['setup_action']['button'] as $button) {
							if ($install) {
								if ($button['$name'] == "delete") {
									$findButton = true;
								}
							} else {
								if ($button['$name'] == "install" || $button['$name'] == "buy") {
									$findButton = true;
								}
							}
						}
					}
				}
			}
			if ($findButton) {
				return true;
			}
			$maxRetry--;
			if ($maxRetry < 0) {
				return false;
			}
		}
	}
}