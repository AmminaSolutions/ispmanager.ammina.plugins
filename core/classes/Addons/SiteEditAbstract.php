<?php

namespace AmminaISP\Core\Addons;

use AmminaISP\Core\ISPManager;
use AmminaISP\Core\Utils;
use function AmminaISP\Core\boolToFlag;
use function AmminaISP\Core\checkDirPath;
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
	];

	public function __construct()
	{
		parent::__construct();
		$domainName = $_SERVER['PARAM_site_name'] ?? '';
		if ($domainName !== '') {
			$this->siteOwner = ISPManager::getInstance()->getWebdomainOwner($domainName);
			$this->siteIdnName = Utils::idn_to_ascii($domainName);
			$this->settingsFile = "site.{$this->siteIdnName}.ini";
			$this->makeListMultisite();
			$this->nginxConfigName = '/etc/nginx/vhosts/' . $this->siteOwner . "/" . $domainName . ".conf";
		}
	}

	public function openForm(): void
	{
		$this->dataAppend['use_php'] = boolToFlag($_SERVER['PARAM_site_handler'] == 'handler_php');
		$this->fillDataAppendFromIni();
		if ($this->getFromParam('site_platform') !== 'default') {
			$this->xml = str_replace('<site_phpcomposer>on</site_phpcomposer>', '<site_phpcomposer>off</site_phpcomposer>', $this->xml);
		}
		$this->dataAppend['site_php_bin_wrapper_ammina'] = $this->phpFcgiWrapper(true);

		$this->stringsAppend[] = '<slist name="site_platform"><msg>default</msg><msg>bitrix</msg><msg>laravel</msg></slist>';
	}

	public function saveForm(): void
	{
		$this->fillIniFromParams();
		$this->saveIni();
		$this->fillDataAppendFromIni();
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
		$data = ISPManager::getInstance()->getWebdomainForBxMultisite($_SERVER['PARAM_site_name']);
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
			if ($this->getFlagFromParam('site_platform') !== 'default') {
				checkDirPath($wrapperFilePath);
				file_put_contents($wrapperFilePath, $wrapperContent);
				chmod($wrapperFilePath, 0555);
				chown($wrapperFilePath, $this->siteOwner);
				chgrp($wrapperFilePath, $this->siteOwner);
				exec("ln " . $wrapperFilePath . ' ' . $wrapperLinkPath);

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
				exec("ln " . $iniFilePath . ' ' . $iniLinkPath);
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
}
