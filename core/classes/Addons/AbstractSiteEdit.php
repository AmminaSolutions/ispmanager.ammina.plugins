<?php

namespace AmminaISP\Core\Addons;

use AmminaISP\Core\ISPManager;
use AmminaISP\Core\Utils;
use function AmminaISP\Core\boolToFlag;
use function AmminaISP\Core\checkDirPath;

abstract class AbstractSiteEdit extends AbstractAddon
{
	public ?string $nginxConfigName = null;
	public ?string $nginxBackDir = null;
	public ?string $siteOwner = null;

	public function __construct()
	{
		parent::__construct();
		$domainName = $_SERVER['PARAM_site_name'] ?? '';
		if ($domainName !== '') {
			$this->siteOwner = ISPManager::getInstance()->getWebdomainOwner($domainName);
			$this->settingsFile = "site." . Utils::idn_to_ascii($domainName) . ".ini";
			$this->makeListMultisite();
			$this->nginxConfigName = '/etc/nginx/vhosts/' . $this->siteOwner . "/" . $domainName . ".conf";
			$this->nginxBackDir = '/etc/nginx/vhosts/' . $this->siteOwner . "/" . $domainName . ".back/";
		}
	}

	public function openForm(): void
	{
		$this->dataAppend['use_php'] = boolToFlag($_SERVER['PARAM_site_handler'] == 'handler_php');
		$this->dataAppend['site_platform'] = $this->fromIni('site_platform', 'default');
		$this->dataAppend['site_platform_bitrix'] = $this->fromIniFlag('site_platform_bitrix', false);
		$this->dataAppend['site_platform_laravel'] = $this->fromIniFlag('site_platform_laravel', false);

		$this->dataAppend['site_bitrix_settings_20100'] = $this->fromIniFlag('site_bitrix_settings_20100', false);
		$this->dataAppend['site_bitrix_settings_b24'] = $this->fromIniFlag('site_bitrix_settings_b24', false);
		$this->dataAppend['site_bitrix_settings_pushserver'] = $this->fromIniFlag('site_bitrix_settings_pushserver', false);
		$this->dataAppend['site_bitrix_settings_composite'] = $this->fromIniFlag('site_bitrix_settings_composite', false);
		$this->dataAppend['site_bitrix_settings_multisite'] = $this->fromIniFlag('site_bitrix_settings_multisite', false);
		$this->dataAppend['site_bitrix_settings_multisite_main'] = $this->fromIni('site_bitrix_settings_multisite_main', '');
		$this->dataAppend['site_bitrix_settings_composer'] = $this->fromIniFlag('site_bitrix_settings_composer', true);

		$this->dataAppend['site_bitrix_operation_make_cron'] = $this->fromIniFlag('site_bitrix_operation_make_cron', false);
		$this->dataAppend['site_bitrix_operation_make_cache_memcached'] = $this->fromIniFlag('site_bitrix_operation_make_cache_memcached', false);
		$this->dataAppend['site_bitrix_operation_make_cache_redis'] = $this->fromIniFlag('site_bitrix_operation_make_cache_redis', false);
		$this->dataAppend['site_bitrix_operation_make_errorlog'] = $this->fromIniFlag('site_bitrix_operation_make_errorlog', false);

		$this->dataAppend['site_bitrix_modules_ammina_optimizer'] = $this->fromIniFlag('site_bitrix_modules_ammina_optimizer', false);
		$this->dataAppend['site_bitrix_modules_ammina_regions'] = $this->fromIniFlag('site_bitrix_modules_ammina_regions', false);
		$this->dataAppend['site_bitrix_modules_ammina_backup'] = $this->fromIniFlag('site_bitrix_modules_ammina_backup', false);

		$this->dataAppend['site_seo_settings_deny_robots'] = $this->fromIniFlag('site_seo_settings_deny_robots', false);
		$this->dataAppend['site_seo_settings_https'] = $this->fromIniFlag('site_seo_settings_https', true);
		$this->dataAppend['site_seo_settings_www'] = $this->fromIniFlag('site_seo_settings_www', false);
		$this->dataAppend['site_seo_settings_nowww'] = $this->fromIniFlag('site_seo_settings_nowww', true);
		$this->dataAppend['site_seo_settings_slash'] = $this->fromIniFlag('site_seo_settings_slash', false);
		$this->dataAppend['site_seo_settings_noslash'] = $this->fromIniFlag('site_seo_settings_noslash', false);
		$this->dataAppend['site_seo_settings_noindex'] = $this->fromIniFlag('site_seo_settings_noindex', false);
		$this->dataAppend['site_seo_settings_nomultislash'] = $this->fromIniFlag('site_seo_settings_nomultislash', false);

		if ($_SERVER['PARAM_site_platform'] !== 'default') {
			$this->xml = str_replace('<site_phpcomposer>on</site_phpcomposer>', '<site_phpcomposer>off</site_phpcomposer>', $this->xml);
		}

		$this->stringsAppend[] = '<slist name="site_platform"><msg>default</msg><msg>bitrix</msg><msg>laravel</msg></slist>';
	}

	public function saveForm(): void
	{
		if (file_exists($this->nginxConfigName)) {
			checkDirPath($this->nginxBackDir);
			file_put_contents($this->nginxBackDir . (new \DateTime())->format("Y_m_d_H_i_s_u") . '.back', file_get_contents($this->nginxConfigName));
		}
		$_SERVER['PARAM_site_platform_bitrix'] = boolToFlag($this->getFromParam('site_platform') === 'bitrix');
		$_SERVER['PARAM_site_platform_laravel'] = boolToFlag($this->getFromParam('site_platform') === 'laravel');
		$this
			->setIniFromParam('site_platform')
			->setIniFlagFromParam('site_platform_bitrix')
			->setIniFlagFromParam('site_platform_laravel')
			->setIniFlagFromParam('site_bitrix_settings_20100')
			->setIniFlagFromParam('site_bitrix_settings_b24')
			->setIniFlagFromParam('site_bitrix_settings_pushserver')
			->setIniFlagFromParam('site_bitrix_settings_composite')
			->setIniFlagFromParam('site_bitrix_settings_multisite')
			->setIniFromParam('site_bitrix_settings_multisite_main')
			->setIniFlagFromParam('site_bitrix_settings_composer')
			->setIniFlagFromParam('site_bitrix_operation_make_cron')
			->setIniFlagFromParam('site_bitrix_operation_make_cache_memcached')
			->setIniFlagFromParam('site_bitrix_operation_make_cache_redis')
			->setIniFlagFromParam('site_bitrix_operation_make_errorlog')
			->setIniFlagFromParam('site_bitrix_modules_ammina_optimizer')
			->setIniFlagFromParam('site_bitrix_modules_ammina_regions')
			->setIniFlagFromParam('site_bitrix_modules_ammina_backup')
			->setIniFlagFromParam('site_seo_settings_deny_robots')
			->setIniFlagFromParam('site_seo_settings_https')
			->setIniFlagFromParam('site_seo_settings_www')
			->setIniFlagFromParam('site_seo_settings_nowww')
			->setIniFlagFromParam('site_seo_settings_slash')
			->setIniFlagFromParam('site_seo_settings_noslash')
			->setIniFlagFromParam('site_seo_settings_noindex')
			->setIniFlagFromParam('site_seo_settings_nomultislash')
			->saveIni();
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

}