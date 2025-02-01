<?php

namespace AmminaISP\Core\Addons;

use AmminaISP\Core\ISPManager;
use AmminaISP\Core\Utils;
use function AmminaISP\Core\addJob;
use function AmminaISP\Core\checkDirPath as checkDirPathAlias;
use function AmminaISP\Core\isOn;

abstract class BitrixPushServerAbstract extends AddonAbstract
{
	public string $templateFileName = '/usr/local/mgr5/etc/templates/amminaisp/push-server-multi.template';

	protected array $ipAddresses = [];
	protected array $sslCertificates = [];
	public $pushServerOptionsFileName = '/etc/sysconfig/push-server-multi';
	public $pushServerOptionsFileNameOwner = 'www-data';

	public $templateRtcServerFileName = '/usr/local/mgr5/etc/templates/amminaisp/rtc-server.template';
	protected $rtcServerConfigFileName = '/etc/nginx/conf.d/rtc-server.conf';
	public ?string $settingsFile = 'pushserver.ini';
	public array $params = [
		'ammina_active' => [
			'type' => 'bool',
			'ini' => 'active',
			'default' => true,
		],
		'ammina_base_port_sub' => [
			'type' => 'int',
			'ini' => 'base_port_sub',
			'default' => 801,
		],
		'ammina_base_port_pub' => [
			'type' => 'int',
			'ini' => 'base_port_pub',
			'default' => 901,
		],
		'ammina_cnt_sub' => [
			'type' => 'int',
			'ini' => 'cnt_sub',
			'default' => 5,
		],
		'ammina_cnt_pub' => [
			'type' => 'int',
			'ini' => 'cnt_pub',
			'default' => 1,
		],
		'ammina_ws_port' => [
			'type' => 'int',
			'ini' => 'ws_port',
			'default' => 1337,
		],
		'ammina_ip_list' => [
			'type' => 'string',
			'ini' => 'ip_list',
			'default' => "127.0.0.1, 10.0.0.0/24",
		],
		'ammina_ip_addr_external' => [
			'type' => 'string',
			'ini' => 'ip_addr_external',
			'default' => '',
		],
		'ammina_security_key' => [
			'type' => 'string',
			'ini' => 'security_key',
			'default' => '',
		],
		'ammina_cert' => [
			'type' => 'string',
			'ini' => 'cert',
			'default' => '',
		],
	];

	public function __construct()
	{
		parent::__construct();
		$this->sslCertificates = ISPManager::getInstance()->getListCertificates();
		$this->ipAddresses = ISPManager::getInstance()->getListIpAddr();
		$this->params['ammina_ip_addr_external']['default'] = $this->ipAddresses[0] ?? '';
		$this->params['ammina_cert']['default'] = $this->sslCertificates[array_keys($this->sslCertificates)[0]]['name'] ?? '';
		$vals = [];
		foreach ($this->sslCertificates as $sslKey => $sslCertificate) {
			$vals[] = "<val key=\"{$sslKey}\">{$sslCertificate['name']}</val>";
		}
		$vals = implode("", $vals);
		$this->stringsAppend[] = "<slist name=\"ammina_cert\">{$vals}</slist>";
		$vals = [];
		foreach ($this->ipAddresses as $ipAddress) {
			$vals[] = "<val key=\"{$ipAddress}\">{$ipAddress}</val>";
		}
		$vals = implode("", $vals);
		$this->stringsAppend[] = "<slist name=\"ammina_ip_addr_external\">{$vals}</slist>";
	}

	/**
	 * Показ формы
	 *
	 * @return void
	 */
	public function openForm(): void
	{
		if (strlen($this->fromIni('security_key')) <= 0) {
			$key = Utils::randString(128);
			$this->setIni('security_key', $key);
			$this->saveIni();
		}
		$this->fillDataAppendFromIni();
	}

	public function saveForm(): void
	{
		$this->fillIniFromParams();
		$this->saveIni();
		$this->fillDataAppendFromIni();

		$redistData = ISPManager::getInstance()->getRedisOptions();
		$srvData = ISPManager::getInstance()->getSrvParams();

		$certPath = false;
		$certDHParam = 2048;
		$cert = $this->getFromParam('ammina_cert');
		if (strlen($cert) > 0) {
			$sertList = ISPManager::getInstance()->getListCertificates();
			foreach ($sertList as $k => $v) {
				if ($k == $cert) {
					$certPath = '/var/www/httpd-cert/' . $v['owner'] . '/' . $v['name'];
					$command = 'openssl x509 -in ' . $certPath . '.crt -text -noout';
					$res = [];
					exec($command, $res);
					if (str_contains(implode("\n", $res), '4096 bit')) {
						$certDHParam = 4096;
					}
				}
			}
		}
		$serversSub = [];
		$serversPub = [];
		for ($i = 0; $i <= intval($this->getFromParam('ammina_cnt_sub')); $i++) {
			$serversSub[] = 'server ' . $srvData['srvname'] . ':' . intval($this->getFromParam('ammina_base_port_sub')) . intval($i) . ';';
		}
		for ($i = 0; $i <= intval($this->getFromParam('ammina_cnt_pub')); $i++) {
			$serversPub[] = 'server ' . $srvData['srvname'] . ':' . intval($this->getFromParam('ammina_base_port_pub')) . intval($i) . ';';
		}
		$iPList = explode(",", trim($this->getFromParam('ammina_ip_list')));
		foreach ($iPList as $k => $v) {
			$iPList[$k] = trim($v);
			if (strlen($iPList[$k]) <= 0) {
				unset($iPList[$k]);
			}
		}
		$tmpIpAddr = [];
		foreach ($iPList as $k => $v) {
			$v = trim($v);
			if (strlen($v) > 0) {
				$tmpIpAddr[] = '"' . $v . '"';
			}
		}

		$templateReplace = [
			"AMMINA_MAIN_HOST" => $srvData['srvname'],
			"AMMINA_BASE_PORT_SUB" => intval($this->getFromParam('ammina_base_port_sub')),
			"AMMINA_BASE_PORT_PUB" => intval($this->getFromParam('ammina_base_port_pub')),
			"AMMINA_CNT_SUB" => intval($this->getFromParam('ammina_cnt_sub')),
			"AMMINA_CNT_PUB" => intval($this->getFromParam('ammina_cnt_pub')),
			"AMMINA_IP_LIST" => "'" . implode(", ", $tmpIpAddr) . "'",
			"AMMINA_WS_PORT" => intval($this->getFromParam('ammina_ws_port')),
			"AMMINA_REDIS_SOCK" => ($redistData['issocket'] ? '/var/run/redis/redis.sock' : ''),
			"AMMINA_REDIS_HOST" => "127.0.0.1",
			"AMMINA_REDIS_PORT" => "6379",
			"AMMINA_IP_ADDR_EXTERNAL" => trim($this->getFromParam('ammina_ip_addr_external')),
			"AMMINA_SECURITY_KEY" => trim($this->fromIni('security_key')),
			"AMMINA_CERT" => (file_exists($certPath . ".crtca") ? $certPath . ".crtca" : $certPath . ".crt"),
			"AMMINA_CERT_KEY" => $certPath . ".key",
			"AMMINA_DHPARAM" => $certDHParam,
			"AMMINA_DHPARAM_CERT" => '/etc/ssl/certs/dhparam' . $certDHParam . '.pem',
			"AMMINA_SUB_SERVERS" => implode("\n\t", $serversSub),
			"AMMINA_PUB_SERVERS" => implode("\n\t", $serversPub),
		];

		$template = '';
		if (file_exists($this->templateFileName)) {
			$template = file_get_contents($this->templateFileName);
		}
		foreach ($templateReplace as $k => $v) {
			$template = str_replace('[% ' . $k . ' %]', $v, $template);
		}
		if (trim($template) != '') {
			checkDirPathAlias($this->pushServerOptionsFileName);
			file_put_contents($this->pushServerOptionsFileName, $template);
			chown($this->pushServerOptionsFileName, $this->pushServerOptionsFileNameOwner);
		}
		if ($this->getFlagFromParam('ammina_active') === 'on') {
			$template = '';
			if (file_exists($this->templateRtcServerFileName)) {
				$template = file_get_contents($this->templateRtcServerFileName);
			}
			foreach ($templateReplace as $k => $v) {
				$template = str_replace('[% ' . $k . ' %]', $v, $template);
			}
			file_put_contents($this->rtcServerConfigFileName, $template);
			@exec("/etc/init.d/nginx restart");
			@exec("systemctl enable push-server.service");
			@exec("/etc/init.d/push-server-multi reset");
			@exec("service push-server-multi start");
			addJob("ammina.bitrix.install.pushserver", []);
		} else {
			@unlink($this->rtcServerConfigFileName);
			@exec("/etc/init.d/nginx restart");
			@exec("service push-server-multi stop");
			@exec("systemctl disable push-server.service");
		}
	}

}