<?php

namespace AmminaISP\Core\Addons;

use AmminaISP\Core\FilesSynchronizer;
use AmminaISP\Core\ISPManager;
use AmminaISP\Core\Utils;
use function AmminaISP\Core\addJob;
use function AmminaISP\Core\boolToFlag;
use function AmminaISP\Core\checkDirPath;
use function AmminaISP\Core\execShellCommand;
use function AmminaISP\Core\findFile;

abstract class SiteEditAbstract extends AddonAbstract
{
	public ?string $nginxConfigName = null;
	public ?string $siteOwner = null;
	public ?string $siteIdnName = null;

	public array $params = [
		'site_platform' => [
			'type' => 'string',
			'ini' => 'platform',
			'default' => 'default',
		],
		'site_bitrix_settings_20100' => [
			'type' => 'bool',
			'ini' => 'bitrix_settings_20100',
			'default' => false,
		],
		'site_bitrix_settings_b24' => [
			'type' => 'bool',
			'ini' => 'bitrix_settings_b24',
			'default' => false,
		],
		'site_bitrix_settings_pushserver' => [
			'type' => 'bool',
			'ini' => 'bitrix_settings_pushserver',
			'default' => false,
		],
		'site_bitrix_settings_composite' => [
			'type' => 'bool',
			'ini' => 'bitrix_settings_composite',
			'default' => false,
		],
		'site_bitrix_settings_multisite' => [
			'type' => 'bool',
			'ini' => 'bitrix_settings_multisite',
			'default' => false,
		],
		'site_bitrix_settings_multisite_main' => [
			'type' => 'string',
			'ini' => 'bitrix_settings_multisite_main',
		],
		'site_bitrix_settings_composer' => [
			'type' => 'bool',
			'ini' => 'bitrix_settings_composer',
			'default' => false,
		],
		'site_bitrix_operation_make_cron' => [
			'type' => 'bool',
			'ini' => 'bitrix_operation_make_cron',
			'default' => false,
		],
		'site_bitrix_operation_make_cache_memcached' => [
			'type' => 'bool',
			'ini' => 'bitrix_operation_make_cache_memcached',
			'default' => false,
		],
		'site_bitrix_operation_make_cache_redis' => [
			'type' => 'bool',
			'ini' => 'bitrix_operation_make_cache_redis',
			'default' => false,
		],
		'site_bitrix_operation_make_errorlog' => [
			'type' => 'bool',
			'ini' => 'bitrix_operation_make_errorlog',
			'default' => false,
		],
		'site_bitrix_modules_ammina_optimizer' => [
			'type' => 'bool',
			'ini' => 'bitrix_modules_ammina_optimizer',
			'default' => false,
		],
		'site_bitrix_modules_ammina_regions' => [
			'type' => 'bool',
			'ini' => 'bitrix_modules_ammina_regions',
			'default' => false,
		],
		'site_bitrix_modules_ammina_backup' => [
			'type' => 'bool',
			'ini' => 'bitrix_modules_ammina_backup',
			'default' => false,
		],
		'site_seo_settings_deny_robots' => [
			'type' => 'bool',
			'ini' => 'seo_settings_deny_robots',
			'default' => false,
		],
		'site_seo_settings_https' => [
			'type' => 'bool',
			'ini' => 'seo_settings_https',
			'default' => true,
		],
		'site_seo_settings_www' => [
			'type' => 'bool',
			'ini' => 'seo_settings_www',
			'default' => false,
		],
		'site_seo_settings_nowww' => [
			'type' => 'bool',
			'ini' => 'seo_settings_nowww',
			'default' => false,
		],
		'site_seo_settings_slash' => [
			'type' => 'bool',
			'ini' => 'seo_settings_slash',
			'default' => false,
		],
		'site_seo_settings_noslash' => [
			'type' => 'bool',
			'ini' => 'seo_settings_noslash',
			'default' => false,
		],
		'site_seo_settings_noindex' => [
			'type' => 'bool',
			'ini' => 'seo_settings_noindex',
			'default' => false,
		],
		'site_seo_settings_nomultislash' => [
			'type' => 'bool',
			'ini' => 'seo_settings_nomultislash',
			'default' => false,
		],
		'site_laravel_settings_composer' => [
			'type' => 'bool',
			'ini' => 'laravel_settings_composer',
			'default' => true,
		],
	];

	public function __construct()
	{
		parent::__construct();
		$domainName = $this->getFromParam('site_name') ?? '';
		if ($domainName !== '') {
			$this->siteOwner = ISPManager::getInstance()->getWebdomainOwner($domainName);
			$this->siteIdnName = Utils::idn_to_ascii($domainName);
			$this->settingsFile = "site.{$this->siteIdnName}.ini";
			$this->nginxConfigName = '/etc/nginx/vhosts/' . $this->siteOwner . "/" . $domainName . ".conf";
		} else {
			$this->siteOwner = $this->getFromParam('site_owner');
		}
		$this->makeListMultisite();
	}

	public function openForm(): void
	{
		if (strlen($this->getFromParam('sv_field')) > 0) {
			$this->fillIniFromParams();
		}
		$this->dataAppend['use_php'] = boolToFlag($this->getFromParam('site_handler') == 'handler_php');
		$this->fillDataAppendFromIni();
		$this->fillDataAppendFromParams();
		if ($this->getFromParam('sv_field') === 'site_platform') {
			$domain = $this->getFromParam('site_name');
			if ($this->getFromParam('site_platform') === 'laravel') {
				$this->dataAppend['site_home'] = "www/{$domain}/public";
				$this->dataAppend['site_laravel_settings_composer'] = "on";
			} else {
				$this->dataAppend['site_home'] = "www/{$domain}";
			}
			if ($this->getFromParam('site_platform') !== 'default') {
				$this->dataAppend['site_gzip_level'] = 7;
				$this->dataAppend['site_expire_times'] = 'expire_times_max';
				$this->dataAppend['site_hsts'] = 'on';
				$this->dataAppend['site_ssi'] = 'off';
			}
		}
		if ($this->getFromParam('site_platform') !== 'default') {
			$this->xml = str_replace('<site_phpcomposer>on</site_phpcomposer>', '<site_phpcomposer>off</site_phpcomposer>', $this->xml);
		}
	}

	public function saveForm(): void
	{
		$this->fillIniFromParams();
		$this->saveIni();
		$this->fillDataAppendFromIni();
		$this->phpFcgiWrapper();
		$this->fillDataAppendFromParams();
		$this->checkOperations();
	}

	protected function renderXml(bool $return = false): ?string
	{
		$xml = parent::renderXml(true);
		[$xml, $platformField] = $this->getFormXmlField($xml, 'site_platform', '');
		[$xml] = $this->getFormXmlField($xml, 'site_handler', null, $platformField);
		echo $xml;
		return null;
	}

	protected function getFormXmlField(string $xml, string $name, ?string $replace = null, ?string $after = null): array
	{
		$resultXml = $xml;
		$resultField = null;
		$findContent = "<field name=\"{$name}\"";
		$startPos = strpos($xml, $findContent);
		if ($startPos !== false) {
			$ar = explode($findContent, $xml, 2);
			$ar2 = explode('</field>', $ar[1], 2);
			$resultField = $findContent . $ar2[0] . '</field>';
			$resultXml = $ar[0] . (is_null($replace) ? $resultField : $replace) . (is_null($after) ? '' : $after) . $ar2[1];
		}
		return [$resultXml, $resultField];
	}

	protected function makeListMultisite(): void
	{
		$list = ['<val key=""></val>'];
		$siteName = $this->getFromParam('site_name');
		$siteOwner = $this->getFromParam('site_owner');
		if (strlen($siteName) <= 0) {
			$siteName = null;
		}
		if (strlen($siteOwner) <= 0) {
			$siteOwner = null;
		}
		$data = ISPManager::getInstance()->getWebdomainForBxMultisite($siteName, $siteOwner);
		foreach ($data as $item) {
			$list[] = "<val key=\"{$item}\">{$item}</val>";
		}
		$this->stringsAppend[] = '<slist name="site_bitrix_settings_multisite_main">' . implode("", $list) . '</slist>';
	}

	protected function phpVersion(): string
	{
		return match ($this->getFromParam('site_php_mode')) {
			'php_mode_mod' => $this->getFromParam('site_php_apache_version'),
			'php_mode_cgi', 'php_mode_fcgi_apache' => $this->getFromParam('site_php_cgi_version'),
			'php_mode_fcgi_nginxfpm' => $this->getFromParam('site_php_fpm_version'),
		};
	}

	protected function phpFcgiWrapper(bool $onlyReturnValue = false): string
	{
		$phpVersion = $this->phpVersion();
		$phpVersionNum = substr($phpVersion, 7);
		$binPath = "/var/www/{$this->siteOwner}/data/php-bin-{$phpVersion}";
		if ($onlyReturnValue) {
			$wrapperFilePath = "{$binPath}/php.{$this->siteIdnName}";
			return ($this->getFromParam('site_platform') === 'default' || !file_exists($wrapperFilePath)) ? "{$binPath}/php" : $wrapperFilePath;
		}
		if (file_exists($binPath)) {
			$iniOriginalFilePath = "{$binPath}/php.ini";
			$wrapperFilePath = "{$binPath}/php.{$this->siteIdnName}";
			$iniFilePath = "{$binPath}/php.{$this->siteIdnName}.ini";
			$wrapperLinkPath = "/var/www/php-bin-{$phpVersion}/{$this->siteOwner}/php.{$this->siteIdnName}";
			$iniLinkPath = "/var/www/php-bin-{$phpVersion}/{$this->siteOwner}/php.{$this->siteIdnName}.ini";
			$iniOptions = [
				'default_charset' => $this->getFromParam('site_charset'),
			];
			if ($this->getFromParam('site_platform') === 'bitrix') {
				if ($phpVersionNum <= 74) {
					if ($this->getFromParam('site_bitrix_settings_20100') === 'on' && $this->getFromParam('site_charset') === 'UTF-8') {
						$iniOptions['mbstring.func_overload'] = 2;
					} else {
						$iniOptions['mbstring.func_overload'] = 0;
					}
				}
			}
			$iniOptions['mbstring.internal_encoding'] = $this->getFromParam('site_charset');
			$templateWrapperFileName = findFile('addons/siteedit/wrappers/php.wrapper');
			$wrapperContent = '';
			if (!is_null($templateWrapperFileName)) {
				$wrapperContent = file_get_contents($templateWrapperFileName);
				$wrapperContent = str_replace('#PHP_CGI_PATH#', $this->phpBinCgiPath(), $wrapperContent);
				$wrapperContent = str_replace('#PHP_CONFIG#', $iniFilePath, $wrapperContent);
			}
			foreach ([$wrapperFilePath, $iniFilePath, $wrapperLinkPath, $iniLinkPath] as $filePath) {
				if (file_exists($filePath)) {
					@unlink($filePath);
				}
			}
			if ($this->getFromParam('site_platform') !== 'default') {
				checkDirPath($wrapperFilePath);
				file_put_contents($wrapperFilePath, $wrapperContent);
				chmod($wrapperFilePath, 0555);
				chown($wrapperFilePath, $this->siteOwner);
				chgrp($wrapperFilePath, $this->siteOwner);
				execShellCommand("ln " . $wrapperFilePath . ' ' . $wrapperLinkPath);

				$iniContent = file_get_contents($iniOriginalFilePath);
				foreach ($iniOptions as $key => $value) {
					$iniContent .= "\n{$key} = {$value}";
				}
				$iniContent .= "\n";
				checkDirPath($iniFilePath);
				file_put_contents($iniFilePath, $iniContent);
				chmod($iniFilePath, 0400);
				chown($iniFilePath, $this->siteOwner);
				chgrp($iniFilePath, $this->siteOwner);
				execShellCommand("ln " . $iniFilePath . ' ' . $iniLinkPath);
				return "{$wrapperFilePath}";
			}
		}
		return "{$binPath}/php";
	}

	protected function phpBinCgiPath(): string
	{
		$phpVersion = $this->phpVersion();
		$phpVersionNum = substr($phpVersion, 7);
		return "/opt/php{$phpVersionNum}/bin/php-cgi";
	}

	protected function phpBinPath(): string
	{
		$phpVersion = $this->phpVersion();
		$phpVersionNum = substr($phpVersion, 7);
		return "/opt/php{$phpVersionNum}/bin/php";
	}

	protected function fillDataAppendFromParams(): void
	{
		$this->dataAppend['site_php_bin_wrapper_ammina'] = $this->phpFcgiWrapper(true);
		$this->dataAppend['site_php_version'] = substr($this->phpVersion(), 7);
		$this->stringsAppend[] = '<slist name="site_platform"><msg>default</msg><msg>bitrix</msg><msg>laravel</msg></slist>';
		if ($this->dataAppend['site_platform'] === 'bitrix' && $this->dataAppend['site_bitrix_settings_composite'] === 'on') {
			$mapFileName = "/etc/nginx/amminaisp/maps/_" . Utils::idn_to_ascii($this->getFromParam('site_name')) . ".conf";
			$compositeVariableSuffix = substr(md5($this->getFromParam('site_name')), 0, 4) . substr(crc32($this->getFromParam('site_name')), 0, 2);
			$docRoot = $this->getFromParam('site_home');
			if (file_exists($docRoot . "/bitrix/html_pages/.enabled") && file_exists($docRoot . "/bitrix/html_pages/.config.php")) {
				$extParams = $this->makeCompositeConfig($mapFileName, $compositeVariableSuffix, $docRoot . "/bitrix/html_pages/.config.php");
				$this->dataAppend['site_bitrix_composite_var_suffix'] = $compositeVariableSuffix;
				$this->dataAppend['site_bitrix_composite_memcached'] = $extParams['memcached'];
				$this->dataAppend['site_bitrix_composite_memcached_pass'] = $extParams['memcached_pass'];
			} else {
				if (file_exists($mapFileName)) {
					@unlink($mapFileName);
				}
			}
		}
	}

	protected function makeCompositeConfig($mapFileName, $compositeVariableSuffix, $bitrixCompositeConfig): array
	{
		$arHTMLPagesOptions = [];
		@include($bitrixCompositeConfig);
		$data = [];
		$hosts = [];
		foreach ($arHTMLPagesOptions['DOMAINS'] as $domain) {
			$hosts[] = '"' . $domain . '" "1";';
		}
		$data[] = 'map $host $config_domain_' . $compositeVariableSuffix . ' {
          hostnames;
          default "0";
          ' . implode("\n", $hosts) . '
        }';

		$arIncludePath = [];
		foreach ($arHTMLPagesOptions['~INCLUDE_MASK'] as $mask) {
			if (str_starts_with($mask, "'")) {
				$mask = substr($mask, 1);
			}
			if (str_ends_with($mask, "'")) {
				$mask = substr($mask, 0, strlen($mask) - 1);
			}
			$arIncludePath[] = '"~*' . $mask . '" "1";';
		}
		$data[] = 'map $uri $is_include_uri_' . $compositeVariableSuffix . ' {
          default  "0";
          ' . implode("\n", $arIncludePath) . '
        }';

		$arExcludePath = [];
		foreach ($arHTMLPagesOptions['~EXCLUDE_MASK'] as $mask) {
			if (str_starts_with($mask, "'")) {
				$mask = substr($mask, 1);
			}
			if (str_ends_with($mask, "'")) {
				$mask = substr($mask, 0, strlen($mask) - 1);
			}
			$arExcludePath[] = '"~*' . $mask . '" "0";';
		}
		$data[] = 'map $uri $not_exclude_uri_' . $compositeVariableSuffix . ' {
          default  "1";
          ' . implode("\n", $arExcludePath) . '
        }';

		$arExcludeParams = [];
		foreach ($arHTMLPagesOptions['~EXCLUDE_PARAMS'] as $param) {
			$arExcludeParams[] = '"~' . $param . '" "0";';
		}
		$data[] = 'map $args $not_exclude_params_' . $compositeVariableSuffix . ' {
          default  "1";
          ' . implode("\n", $arExcludeParams) . '
        }';

		$data[] = 'map "${config_domain_' . $compositeVariableSuffix . '}${is_include_uri_' . $compositeVariableSuffix . '}${not_exclude_uri_' . $compositeVariableSuffix . '}${not_exclude_params_' . $compositeVariableSuffix . '}" $is_site_composite_' . $compositeVariableSuffix . ' {
          default   "1";
          ~0        "0";
        }';
		checkDirPath($mapFileName);
		file_put_contents($mapFileName, implode("\n\n", $data));
		$result = [
			'memcached' => ($arHTMLPagesOptions['STORAGE'] == "memcached" ? "on" : "off"),
		];
		if ($arHTMLPagesOptions['STORAGE'] == "memcached") {
			$result['memcached_pass'] = ($arHTMLPagesOptions['MEMCACHED_PORT'] > 0 ? $arHTMLPagesOptions['MEMCACHED_HOST'] . ":" . $arHTMLPagesOptions['MEMCACHED_PORT'] : str_replace("unix:///", "unix:/", $arHTMLPagesOptions['MEMCACHED_HOST']));
		}
		return $result;
	}

	protected function checkOperations(): void
	{
		if ($this->getFromParam('site_platform') === 'bitrix') {
			$this->makeBitrixCommands();
			if ($this->getFlagFromParam('site_bitrix_settings_multisite') !== 'on') {
				if ($this->getFlagFromParam('site_bitrix_operation_make_skeleton') === 'on') {
					$this->makeBitrixSkeleton();
				}
				if ($this->getFlagFromParam('site_bitrix_operation_make_database') === 'on') {
					$this->makeBitrixDatabase(trim($this->getFromParam('site_bitrix_operation_make_database_name')));
				}

				if ($this->getFlagFromParam('site_bitrix_operation_make_cron') === 'on') {
					$this->makeBitrixCron();
				}
				if ($this->getFlagFromParam('site_bitrix_operation_make_cache_memcached') === 'on') {
					$this->makeBitrixCacheMemcached();
				}
				if ($this->getFlagFromParam('site_bitrix_operation_make_cache_redis') === 'on') {
					$this->makeBitrixCacheRedis();
				}
				if ($this->getFlagFromParam('site_bitrix_operation_make_errorlog') === 'on') {
					$this->makeBitrixErrorLog();
				}
				if ($this->getFlagFromParam('site_bitrix_settings_composer') === 'on') {
					$this->makeBitrixComposer();
				}
			}

			if ($this->getFlagFromParam('site_bitrix_settings_multisite') === 'on' && strlen($this->getFromParam('site_bitrix_settings_multisite_main')) > 0) {
				$this->makeBitrixMultisite($this->getFromParam('site_bitrix_settings_multisite_main'));
			}
			if ($this->getFlagFromParam('site_bitrix_settings_b24') === 'on' && $this->getFlagFromParam('site_bitrix_settings_pushserver') === 'on') {
				$this->makeBitrix24PushServer();
			}
		}
		if ($this->getFromParam('site_platform') === 'laravel') {
			$this->makeLaravelCommands();
			if ($this->getFlagFromParam('site_laravel_settings_composer') === 'on') {
				$this->makeLaravelComposer();
			}
		}
	}

	protected function makeBitrixSkeleton(): void
	{
		addJob("ammina.bitrix.skeleton", [
			'site' => $this->getFromParam('site_name'),
			'owner' => $this->siteOwner,
		]);
	}

	protected function makeBitrixDatabase(?string $databaseName = null): void
	{
		if (empty($databaseName)) {
			$databaseName = null;
		}
		addJob("ammina.bitrix.makedb", [
			'name' => $databaseName,
			'site' => $this->getFromParam('site_name'),
			'owner' => $this->siteOwner,
			'charset' => $this->getFromParam('site_charset'),
		]);
	}

	protected function makeBitrixMultisite(string $mainSite): void
	{
		addJob("ammina.bitrix.multisite", [
			'mainSite' => $mainSite,
			'site' => $this->getFromParam('site_name'),
			'owner' => $this->siteOwner,
		]);
	}

	protected function makeBitrixCron(): void
	{
		addJob("ammina.bitrix.cron", [
			'site' => $this->getFromParam('site_name'),
			'owner' => $this->siteOwner,
		]);
	}

	protected function makeBitrixCacheMemcached(): void
	{
		addJob("ammina.bitrix.cache.memcached", [
			'site' => $this->getFromParam('site_name'),
			'owner' => $this->siteOwner,
		]);
	}

	protected function makeBitrixCacheRedis(): void
	{
		addJob("ammina.bitrix.cache.redis", [
			'site' => $this->getFromParam('site_name'),
			'owner' => $this->siteOwner,
		]);
	}

	protected function makeBitrixErrorLog(): void
	{
		addJob("ammina.bitrix.error.log", [
			'site' => $this->getFromParam('site_name'),
			'owner' => $this->siteOwner,
		]);
	}

	protected function makeBitrixComposer(): void
	{
		addJob("ammina.bitrix.composer", [
			'site' => $this->getFromParam('site_name'),
			'owner' => $this->siteOwner,
		]);
	}

	protected function makeBitrixCommands(): void
	{
		addJob("ammina.bitrix.commands", [
			'site' => $this->getFromParam('site_name'),
			'owner' => $this->siteOwner,
		]);
	}

	protected function makeBitrix24PushServer(): void
	{
		addJob("ammina.bitrix.pushserver", [
			'site' => $this->getFromParam('site_name'),
			'owner' => $this->siteOwner,
		]);
	}

	protected function makeLaravelComposer(): void
	{
		addJob("ammina.laravel.composer", [
			'site' => $this->getFromParam('site_name'),
			'owner' => $this->siteOwner,
		]);
	}

	protected function makeLaravelCommands(): void
	{
		addJob("ammina.laravel.commands", [
			'site' => $this->getFromParam('site_name'),
			'owner' => $this->siteOwner,
		]);
	}
}
