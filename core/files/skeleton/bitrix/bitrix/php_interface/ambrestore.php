<?

ob_start();
error_reporting(0);
ini_set("memory_limit", "512M");
ini_set("display_errors", true);


$arBackupConfigs = [
];

//#BACKUP_CONFIG_ARRAY#

abstract class AmminaBackupDriverBase
{
	protected $arSettings = [];

	public function __construct($arSettings)
	{
		$this->arSettings = $arSettings;
		if ($_SERVER['REQUEST_METHOD'] == "POST") {
			$this->doCheckPost();
		}
	}

	abstract function doRenderSettings($bAllowEdit = true);

	abstract function doCheckPost();

	protected function getUserAgent()
	{
		if (strlen($_SERVER['HTTP_USER_AGENT']) > 0) {
			return $_SERVER['HTTP_USER_AGENT'];
		}
		return 'Ammina.Backup module client';
	}

	abstract public function GetListAllFiles($strDir, $bRecursive = true);

	abstract public function GetRemoteFile($strRemoteName, $strLocalName);

	public function GetListAllRemoteBackup()
	{
		global $APPLICATION;
		$arResult = [];
		$arAllFiles = $this->GetListAllFiles($APPLICATION->getBackupPath(), true);
		foreach ($arAllFiles as $k => $arFile) {
			if ($arFile['TYPE'] == "FILE") {
				$strRelName = substr($arFile['PATH'], strlen($APPLICATION->getBackupPath()));
				if (substr($strRelName, 0, 1) == "/") {
					$strRelName = substr($strRelName, 1);
				}
				$iPosDir = strpos($strRelName, '/');
				if ($iPosDir !== false) {
					$strBackup = substr($strRelName, 0, $iPosDir);
					$strFileName = substr($strRelName, $iPosDir + 1);
					if (strlen($strBackup) > 0 && strlen($strFileName) > 0) {
						if (!isset($arResult[$strBackup])) {
							$strTime = substr($strBackup, 0, 4) . "." . substr($strBackup, 4, 2) . "." . substr($strBackup, 6, 2) . "." . substr($strBackup, 9, 2) . "." . substr($strBackup, 11, 2) . "." . substr($strBackup, 13, 2);
							$iTime = mktime(substr($strBackup, 9, 2), substr($strBackup, 11, 2), substr($strBackup, 13, 2), substr($strBackup, 4, 2), substr($strBackup, 6, 2), substr($strBackup, 0, 4));
							$arResult[$strBackup] = [
								"BACKUP_NAME" => $strBackup,
								"REMOTE_DIR" => $APPLICATION->getBackupPath() . $strBackup . "/",
								"BACKUP_TIME" => $iTime,
								"TOTAL_SIZE" => 0,
							];
						}
						$arResult[$strBackup]['TOTAL_SIZE'] += $arFile['SIZE'];
						$arResult[$strBackup]['FILES'][$strFileName] = [
							"FILE_NAME" => $strFileName,
							"FULL_NAME" => $arFile['PATH'],
							"SIZE" => $arFile['SIZE'],
						];
					}
				}
			}
		}
		return $arResult;
	}
}

class AmminaBackupDriverCloudMailRu extends AmminaBackupDriverBase
{

	function doRenderSettings($bAllowEdit = true)
	{
		?>
		<div class="form-group">
			<label for="DRIVER_SETTINGS_CLIENT_LOGIN">Логин</label>
			<input type="text" class="form-control" id="DRIVER_SETTINGS[CLIENT_LOGIN]" name="DRIVER_SETTINGS[CLIENT_LOGIN]" value="<?= htmlspecialchars($this->arSettings['CLIENT_LOGIN']) ?>"<?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>>
		</div>
		<div class="form-group">
			<label for="DRIVER_SETTINGS_CLIENT_PASSWORD">Пароль пользователя или приложения (при двухфакторной
				авторизации)</label>
			<input type="text" class="form-control" id="DRIVER_SETTINGS[CLIENT_PASSWORD]" name="DRIVER_SETTINGS[CLIENT_PASSWORD]" value="<?= htmlspecialchars($this->arSettings['CLIENT_PASSWORD']) ?>"<?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>>
		</div>
		<?
	}

	function doCheckPost()
	{
	}

	protected function _sendRequest($strMethod, $strUri, $arOptions = [], $arHeaders = [])
	{
		$arResult = [];

		if (strlen($this->arSettings['CLIENT_LOGIN']) > 0 && strlen($this->arSettings['CLIENT_PASSWORD']) > 0) {
			$client = new HttpClient(
				[
					'redirect' => true,
					'redirectMax' => 10,
					'socketTimeout' => 30,
					'streamTimeout' => (isset($arOptions['STREAM_TIMEOUT']) ? $arOptions['STREAM_TIMEOUT'] : 30),
					'disableSslVerification' => true,
				]
			);
			$client->setAuthorization($this->arSettings['CLIENT_LOGIN'], $this->arSettings['CLIENT_PASSWORD']);
			$client->setHeader("Accept", '*/*');
			$client->setHeader("User-Agent", $this->getUserAgent());
			foreach ($arHeaders as $k => $v) {
				if (is_array($v)) {
					foreach ($v as $v1) {
						$client->setHeader($k, $v1, false);
					}
				} else {
					$client->setHeader($k, $v);
				}
			}
			$rFile = false;
			if (strlen($arOptions['SAVE_TO_FILE']) > 0) {
				CheckDirPath(dirname($arOptions['SAVE_TO_FILE']));
				$rFile = fopen($arOptions['SAVE_TO_FILE'], "w+");
				if ($rFile) {
					$client->setOutputStream($rFile);
				}
			}
			if ($client->query($strMethod, "https://webdav.cloud.mail.ru" . $strUri, $arOptions['BODY'])) {
				$strAnswer = trim($client->getResult());
				$status = intval($client->getStatus());
				$arData = false;
				if (strlen($strAnswer) > 0) {
					$oRes = @simplexml_load_string($strAnswer);
					if (is_object($oRes)) {
						$arData = json_decode(json_encode($oRes->children('DAV:')), true);
					}
				}
				$arResult = [
					"STATUS" => "SUCCESS",
					"RESPONSE_STATUS" => $status,
					"ANSWER" => $strAnswer,
					"DATA" => $arData,
				];
			} else {
				$status = intval($client->getStatus());
				$arResult = [
					"STATUS" => "ERROR",
					"RESPONSE_STATUS" => $status,
					"ERRORS" => $client->getError(),
				];
				if (strlen($arOptions['SAVE_TO_FILE']) > 0 && $rFile) {
					fclose($rFile);
					if (file_exists($arOptions['SAVE_TO_FILE'])) {
						@unlink($arOptions['SAVE_TO_FILE']);
					}
				}
			}
			if (strlen($arOptions['SAVE_TO_FILE']) > 0 && $rFile) {
				fclose($rFile);
			}
		} else {
			$arResult['STATUS'] = "ERROR";
			$arResult['ERROR_MESSAGE'] = "Некорректные параметры приложения";
		}
		return $arResult;
	}

	public function GetListAllFiles($strDir, $bRecursive = true)
	{
		$arAllFiles = [];
		$strBody = '<?xml version="1.0" encoding="utf-8" ?><D:propfind xmlns:D="DAV:"><D:allprop/></D:propfind>';
		$arResult = $this->_sendRequest("PROPFIND", $strDir, ["BODY" => $strBody], ["Depth" => 1]);
		if ($arResult['RESPONSE_STATUS'] != 207) {
			$arResult = [
				"STATUS" => "ERROR",
			];
		}
		if (isset($arResult['DATA']['response'])) {
			if (isset($arResult['DATA']['response']['href'])) {
				$arResult['DATA']['response'] = [$arResult['DATA']['response']];
			}
			foreach ($arResult['DATA']['response'] as $arData) {
				if ($arData['href'] == $strDir || $arData['href'] . "/" == $strDir) {
					continue;
				}
				if (isset($arData['propstat']['prop']['resourcetype']['collection'])) {
					$arAllFiles[$arData['href']] = [
						"PATH" => $arData['href'],
						"TYPE" => "DIR",
					];
					if ($bRecursive) {
						$arChildFiles = $this->GetListAllFiles($arData['href'], $bRecursive);
						if (is_array($arChildFiles)) {
							$arAllFiles = array_merge($arAllFiles, $arChildFiles);
						}
					}
				} else {
					$arAllFiles[$arData['href']] = [
						"PATH" => $arData['href'],
						"TYPE" => "FILE",
						"SIZE" => $arData['propstat']['prop']['getcontentlength'],
					];
				}
			}
		}

		return $arAllFiles;
	}

	public function GetRemoteFile($strRemoteName, $strLocalName)
	{
		$arOptions = [
			"SAVE_TO_FILE" => $strLocalName,
			"STREAM_TIMEOUT" => 3600,
		];
		$arResult = $this->_sendRequest("GET", $strRemoteName, $arOptions, []);
		if ($arResult['STATUS'] == "SUCCESS" && $arResult['RESPONSE_STATUS'] != 200) {
			if (file_exists($strLocalName)) {
				@unlink($strLocalName);
			}
			return false;
		}
		return true;
	}
}

class AmminaBackupDriverDiskYandexRu extends AmminaBackupDriverBase
{

	function doRenderSettings($bAllowEdit = true)
	{
		?>
		<div class="form-group">
			<label for="DRIVER_SETTINGS_CLIENT_ID">Идентификатор приложения</label>
			<input type="text" class="form-control" id="DRIVER_SETTINGS[CLIENT_ID]" name="DRIVER_SETTINGS[CLIENT_ID]" value="<?= htmlspecialchars($this->arSettings['CLIENT_ID']) ?>"<?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>>
		</div>
		<div class="form-group">
			<label for="DRIVER_SETTINGS_CLIENT_SECRET">Пароль приложения</label>
			<input type="text" class="form-control" id="DRIVER_SETTINGS[CLIENT_SECRET]" name="DRIVER_SETTINGS[CLIENT_SECRET]" value="<?= htmlspecialchars($this->arSettings['CLIENT_SECRET']) ?>"<?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>>
		</div>
		<div class="form-group">
			<label for="DRIVER_SETTINGS_ACCESS_TOKEN">OAuth-токен</label>
			<input type="text" class="form-control" id="DRIVER_SETTINGS[ACCESS_TOKEN]" name="DRIVER_SETTINGS[ACCESS_TOKEN]" value="<?= htmlspecialchars($this->arSettings['ACCESS_TOKEN']) ?>"<?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>>
		</div>
		<?
	}

	function doCheckPost()
	{
	}

	protected function _sendRequest($strMethod, $strUri, $arOptions = [], $arHeaders = [])
	{
		$arResult = [];
		$bAllowRedirect = true;
		if ($strMethod == "PUT") {
			$bAllowRedirect = false;
		}
		if (strlen($this->arSettings['ACCESS_TOKEN']) > 0 && strlen($this->arSettings['CLIENT_ID']) > 0 && strlen($this->arSettings['CLIENT_SECRET']) > 0) {
			$client = new HttpClient(
				[
					'redirect' => $bAllowRedirect,
					'redirectMax' => 10,
					'socketTimeout' => 30,
					'streamTimeout' => (isset($arOptions['STREAM_TIMEOUT']) ? $arOptions['STREAM_TIMEOUT'] : 30),
					'disableSslVerification' => true,
				]
			);
			$client->setHeader('Authorization', 'OAuth ' . $this->arSettings['ACCESS_TOKEN']);
			$client->setHeader("Accept", '*/*');
			$client->setHeader("User-Agent", $this->getUserAgent());
			foreach ($arHeaders as $k => $v) {
				if (is_array($v)) {
					foreach ($v as $v1) {
						$client->setHeader($k, $v1, false);
					}
				} else {
					$client->setHeader($k, $v);
				}
			}
			$rFile = false;
			if (strlen($arOptions['SAVE_TO_FILE']) > 0) {
				CheckDirPath(dirname($arOptions['SAVE_TO_FILE']));
				$rFile = fopen($arOptions['SAVE_TO_FILE'], "w+");
				if ($rFile) {
					$client->setOutputStream($rFile);
				}
			}
			if ($client->query($strMethod, "https://webdav.yandex.ru" . $strUri, $arOptions['BODY'])) {
				$strAnswer = trim($client->getResult());
				$status = intval($client->getStatus());
				$arData = false;
				if (strlen($strAnswer) > 0) {
					$oRes = @simplexml_load_string($strAnswer);
					if (is_object($oRes)) {
						$arData = json_decode(json_encode($oRes->children('DAV:')), true);
					}
				}
				$arResult = [
					"STATUS" => "SUCCESS",
					"RESPONSE_STATUS" => $status,
					"ANSWER" => $strAnswer,
					"DATA" => $arData,
				];
			} else {
				$status = intval($client->getStatus());
				$arResult = [
					"STATUS" => "ERROR",
					"RESPONSE_STATUS" => $status,
					"ERRORS" => $client->getError(),
				];
				if (strlen($arOptions['SAVE_TO_FILE']) > 0 && $rFile) {
					fclose($rFile);
					if (file_exists($arOptions['SAVE_TO_FILE'])) {
						@unlink($arOptions['SAVE_TO_FILE']);
					}
				}
			}
			if (strlen($arOptions['SAVE_TO_FILE']) > 0 && $rFile) {
				fclose($rFile);
			}
		} else {
			$arResult['STATUS'] = "ERROR";
			$arResult['ERROR_MESSAGE'] = "Некорректные параметры приложения";
		}
		return $arResult;
	}

	public function GetListAllFiles($strDir, $bRecursive = true)
	{
		$arAllFiles = [];
		$strBody = '<?xml version="1.0" encoding="utf-8" ?><D:propfind xmlns:D="DAV:"><D:allprop/></D:propfind>';
		$arResult = $this->_sendRequest("PROPFIND", $strDir, ["BODY" => $strBody], ["Depth" => 1]);
		if ($arResult['RESPONSE_STATUS'] != 207) {
			$arResult = [
				"STATUS" => "ERROR",
			];
		}
		if (isset($arResult['DATA']['response'])) {
			if (isset($arResult['DATA']['response']['href'])) {
				$arResult['DATA']['response'] = [$arResult['DATA']['response']];
			}
			foreach ($arResult['DATA']['response'] as $arData) {
				if ($arData['href'] == $strDir || $arData['href'] . "/" == $strDir) {
					continue;
				}
				if (isset($arData['propstat']['prop']['resourcetype']['collection'])) {
					$arAllFiles[$arData['href']] = [
						"PATH" => $arData['href'],
						"TYPE" => "DIR",
					];
					if ($bRecursive) {
						$arChildFiles = $this->GetListAllFiles($arData['href'], $bRecursive);
						if (is_array($arChildFiles)) {
							$arAllFiles = array_merge($arAllFiles, $arChildFiles);
						}
					}
				} else {
					$arAllFiles[$arData['href']] = [
						"PATH" => $arData['href'],
						"TYPE" => "FILE",
						"SIZE" => $arData['propstat']['prop']['getcontentlength'],
					];
				}
			}
		}

		return $arAllFiles;
	}

	public function GetRemoteFile($strRemoteName, $strLocalName)
	{
		$arOptions = [
			"SAVE_TO_FILE" => $strLocalName,
			"STREAM_TIMEOUT" => 3600,
		];
		$arResult = $this->_sendRequest("GET", $strRemoteName, $arOptions, []);
		if ($arResult['STATUS'] == "SUCCESS" && $arResult['RESPONSE_STATUS'] != 200) {
			if (file_exists($strLocalName)) {
				@unlink($strLocalName);
			}
			return false;
		}
		return true;
	}
}

class AmminaBackupDriverDropboxCom extends AmminaBackupDriverBase
{

	function doRenderSettings($bAllowEdit = true)
	{
		?>
		<div class="form-group">
			<label for="DRIVER_SETTINGS_CLIENT_ID">ID приложения (App key)</label>
			<input type="text" class="form-control" id="DRIVER_SETTINGS[CLIENT_ID]" name="DRIVER_SETTINGS[CLIENT_ID]" value="<?= htmlspecialchars($this->arSettings['CLIENT_ID']) ?>"<?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>>
		</div>
		<div class="form-group">
			<label for="DRIVER_SETTINGS_CLIENT_PASSWORD">Пароль приложения (App secret)</label>
			<input type="text" class="form-control" id="DRIVER_SETTINGS[CLIENT_PASSWORD]" name="DRIVER_SETTINGS[CLIENT_PASSWORD]" value="<?= htmlspecialchars($this->arSettings['CLIENT_PASSWORD']) ?>"<?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>>
		</div>
		<div class="form-group">
			<label for="DRIVER_SETTINGS_ACCESS_TOKEN">Токен (Generated access token)</label>
			<input type="text" class="form-control" id="DRIVER_SETTINGS[ACCESS_TOKEN]" name="DRIVER_SETTINGS[ACCESS_TOKEN]" value="<?= htmlspecialchars($this->arSettings['ACCESS_TOKEN']) ?>"<?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>>
		</div>
		<?
	}

	function doCheckPost()
	{
	}

	protected function _sendRequest($strMethod, $strUri, $arOptions = [], $arHeaders = [])
	{
		$arResult = [];

		if (strlen($this->arSettings['CLIENT_ID']) > 0 && strlen($this->arSettings['CLIENT_PASSWORD']) > 0 && strlen($this->arSettings['ACCESS_TOKEN']) > 0) {
			$client = new HttpClient(
				[
					'redirect' => true,
					'redirectMax' => 10,
					'socketTimeout' => 30,
					'streamTimeout' => (isset($arOptions['STREAM_TIMEOUT']) ? $arOptions['STREAM_TIMEOUT'] : 30),
					'disableSslVerification' => true,
				]
			);
			$client->clearHeaders();
			$client->setHeader("Authorization", "Bearer " . $this->arSettings['ACCESS_TOKEN']);
			$client->setHeader("Accept", '*/*');
			$client->setHeader("User-Agent", $this->getUserAgent());
			$client->setHeader("Content-Type", "application/json");
			foreach ($arHeaders as $k => $v) {
				if (is_array($v)) {
					foreach ($v as $v1) {
						$client->setHeader($k, $v1, false);
					}
				} else {
					$client->setHeader($k, $v);
				}
			}
			if (!isset($arOptions['BODY'])) {
				$client->setHeader("Content-Type", false);
			}
			$strBaseUrl = "https://api.dropboxapi.com/2";
			if ($arOptions['IS_CONTENT_URL']) {
				$strBaseUrl = "https://content.dropboxapi.com/2";
			}
			$rFile = false;
			if (strlen($arOptions['SAVE_TO_FILE']) > 0) {
				CheckDirPath(dirname($arOptions['SAVE_TO_FILE']));
				$rFile = fopen($arOptions['SAVE_TO_FILE'], "w+");
				if ($rFile) {
					$client->setOutputStream($rFile);
				}
			}
			if ($client->query($strMethod, $strBaseUrl . $strUri, $arOptions['BODY'])) {
				$strAnswer = trim($client->getResult());
				$status = intval($client->getStatus());
				$arData = false;
				$arResult['STATUS'] = "ERROR";
				if (strlen($strAnswer) > 0) {
					$arData = @json_decode($strAnswer, true);
				}
				$arResult = [
					"STATUS" => "SUCCESS",
					"RESPONSE_STATUS" => $status,
					"ANSWER" => $strAnswer,
					"DATA" => $arData,
				];
			} else {
				$status = intval($client->getStatus());
				$arResult = [
					"STATUS" => "ERROR",
					"RESPONSE_STATUS" => $status,
					"ERRORS" => $client->getError(),
				];
				if (strlen($arOptions['SAVE_TO_FILE']) > 0 && $rFile) {
					fclose($rFile);
					if (file_exists($arOptions['SAVE_TO_FILE'])) {
						@unlink($arOptions['SAVE_TO_FILE']);
					}
				}
			}
			if (strlen($arOptions['SAVE_TO_FILE']) > 0 && $rFile) {
				fclose($rFile);
			}
		} else {
			$arResult['STATUS'] = "ERROR";
			$arResult['ERROR_MESSAGE'] = "Некорректные параметры приложения";
		}
		return $arResult;
	}

	public function GetListAllFiles($strDir, $bRecursive = true)
	{
		$arAllFiles = [];
		$arParams = [
			"path" => $strDir,
			"recursive" => $bRecursive,
			"include_media_info" => false,
			"include_deleted" => false,
			"include_has_explicit_shared_members" => false,
			"include_mounted_folders" => true,
			"include_non_downloadable_files" => true,
			"limit" => 1000,
		];
		$arOptions = [
			"BODY" => json_encode($arParams),
		];
		$arResult = $this->_sendRequest("POST", "/files/list_folder", $arOptions, []);
		if (isset($arResult['DATA']['entries'])) {
			foreach ($arResult['DATA']['entries'] as $arData) {
				if ($arData['.tag'] == "folder") {
					$arAllFiles[$arData['path_display']] = [
						"PATH" => $arData['path_display'],
						"TYPE" => "DIR",
					];
				} elseif ($arData['.tag'] == "file") {
					$arAllFiles[$arData['path_display']] = [
						"PATH" => $arData['path_display'],
						"TYPE" => "FILE",
						"SIZE" => $arData['size'],
					];
				}
			}
			if ($arResult['DATA']['has_more']) {
				$bComplete = false;
				while (!$bComplete) {
					$arParams = [
						"cursor" => $arResult['DATA']['cursor'],
					];
					$arOptions = [
						"BODY" => json_encode($arParams),
					];
					$arResult = $this->_sendRequest("POST", "/files/list_folder/continue", $arOptions, []);
					$bComplete = !$arResult['DATA']['has_more'];
					if (isset($arResult['DATA']['entries'])) {
						foreach ($arResult['DATA']['entries'] as $arData) {
							if ($arData['.tag'] == "folder") {
								$arAllFiles[$arData['path_display']] = [
									"PATH" => $arData['path_display'],
									"TYPE" => "DIR",
								];
							} elseif ($arData['.tag'] == "file") {
								$arAllFiles[$arData['path_display']] = [
									"PATH" => $arData['path_display'],
									"TYPE" => "FILE",
									"SIZE" => $arData['size'],
								];
							}
						}
					}
				}
			}
		}
		return $arAllFiles;
	}

	public function GetRemoteFile($strRemoteName, $strLocalName)
	{
		$arOptions = [
			"SAVE_TO_FILE" => $strLocalName,
			"STREAM_TIMEOUT" => 3600,
			"IS_CONTENT_URL" => true,
		];
		$arParams = [
			"path" => $strRemoteName,
		];
		$arHeaders = [
			"Dropbox-API-Arg" => json_encode($arParams),
		];

		$arResult = $this->_sendRequest("POST", "/files/download", $arOptions, $arHeaders);
		if ($arResult['STATUS'] == "SUCCESS" && $arResult['RESPONSE_STATUS'] != 200) {
			if (file_exists($strLocalName)) {
				@unlink($strLocalName);
			}
			return false;
		}
		return true;
	}
}

class AmminaBackupDriverFtp extends AmminaBackupDriverBase
{
	protected $_connection = null;
	protected $iOldTimeNoop = false;
	protected $strSysType = '';

	function __destruct()
	{
		$this->_closeSession();
	}

	function doRenderSettings($bAllowEdit = true)
	{
		?>
		<div class="form-group">
			<label for="DRIVER_SETTINGS_CLIENT_SERVER">Сервер</label>
			<input type="text" class="form-control" id="DRIVER_SETTINGS[CLIENT_SERVER]" name="DRIVER_SETTINGS[CLIENT_SERVER]" value="<?= htmlspecialchars($this->arSettings['CLIENT_SERVER']) ?>"<?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>>
		</div>
		<div class="form-group">
			<label for="DRIVER_SETTINGS_CLIENT_LOGIN">Логин</label>
			<input type="text" class="form-control" id="DRIVER_SETTINGS[CLIENT_LOGIN]" name="DRIVER_SETTINGS[CLIENT_LOGIN]" value="<?= htmlspecialchars($this->arSettings['CLIENT_LOGIN']) ?>"<?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>>
		</div>
		<div class="form-group">
			<label for="DRIVER_SETTINGS_CLIENT_PASSWORD">Пароль</label>
			<input type="text" class="form-control" id="DRIVER_SETTINGS[CLIENT_PASSWORD]" name="DRIVER_SETTINGS[CLIENT_PASSWORD]" value="<?= htmlspecialchars($this->arSettings['CLIENT_PASSWORD']) ?>"<?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>>
		</div>
		<div class="form-group">
			<label for="DRIVER_SETTINGS_CLIENT_PORT">Порт</label>
			<input type="text" class="form-control" id="DRIVER_SETTINGS[CLIENT_PORT]" name="DRIVER_SETTINGS[CLIENT_PORT]" value="<?= htmlspecialchars($this->arSettings['CLIENT_PORT']) ?>"<?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>>
		</div>
		<div class="form-check">
			<input type="hidden" name="DRIVER_SETTINGS[CLIENT_PASV]" value="N"/>
			<input class="form-check-input" type="checkbox" value="Y" id="DRIVER_SETTINGS_CLIENT_PASV" name="DRIVER_SETTINGS[CLIENT_PASV]"<?= ($this->arSettings['CLIENT_PASV'] == "Y" ? ' checked="checked"' : '') ?><?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>/>
			<label class="form-check-label" for="DRIVER_SETTINGS_CLIENT_PASV">Пассивный режим</label>
		</div>
		<div class="form-check">
			<input type="hidden" name="DRIVER_SETTINGS[CLIENT_SSL]" value="N"/>
			<input class="form-check-input" type="checkbox" value="Y" id="DRIVER_SETTINGS_CLIENT_SSL" name="DRIVER_SETTINGS[CLIENT_SSL]"<?= ($this->arSettings['CLIENT_SSL'] == "Y" ? ' checked="checked"' : '') ?><?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>/>
			<label class="form-check-label" for="DRIVER_SETTINGS_CLIENT_SSL">SSL подключение</label>
		</div>
		<div class="form-check">
			<input type="hidden" name="DRIVER_SETTINGS[CLIENT_USENLIST]" value="N"/>
			<input class="form-check-input" type="checkbox" value="Y" id="DRIVER_SETTINGS_CLIENT_USENLIST" name="DRIVER_SETTINGS[CLIENT_USENLIST]"<?= ($this->arSettings['CLIENT_USENLIST'] == "Y" ? ' checked="checked"' : '') ?>/>
			<label class="form-check-label" for="DRIVER_SETTINGS_CLIENT_USENLIST">Использовать собственную функцию
				ftp_nlist вместо PHP функции (может понадобится с некоторыми серверами FTP при возникновении
				ошибок)</label>
		</div>
		<?
	}

	function doCheckPost()
	{
		if (isset($_REQUEST['DRIVER_SETTINGS']['CLIENT_USENLIST'])) {
			$this->arSettings['CLIENT_USENLIST'] = $_REQUEST['DRIVER_SETTINGS']['CLIENT_USENLIST'];
		}
	}

	protected function _closeSession()
	{
		if (!$this->isFtpExists()) {
			return false;
		}
		if ($this->_connection) {
			ftp_close($this->_connection);
			$this->_connection = null;
		}
	}

	public function _getConnection()
	{
		if (!function_exists("ftp_ssl_connect") || !function_exists("ftp_connect")) {
			return false;
		}
		if ($this->_connection) {
			if ($this->iOldTimeNoop === false || ($this->iOldTimeNoop + 30) <= time()) {
				$this->iOldTimeNoop = time();
				$strResult = implode("\n", ftp_raw($this->_connection, 'NOOP'));
				if (stripos($strResult, '200 ') !== false) {
					return $this->_connection;
				}
			} else {
				return $this->_connection;
			}
			$this->_closeSession();
		}
		if ($this->arSettings['CLIENT_SSL'] == "Y") {
			$this->_connection = ftp_ssl_connect($this->arSettings['CLIENT_SERVER'], $this->arSettings['CLIENT_PORT']);
		} else {
			$this->_connection = ftp_connect($this->arSettings['CLIENT_SERVER'], $this->arSettings['CLIENT_PORT']);
		}
		if ($this->_connection) {
			$bLoginResult = @ftp_login($this->_connection, $this->arSettings['CLIENT_LOGIN'], $this->arSettings['CLIENT_PASSWORD']);
			if ($bLoginResult) {
				$this->strSysType = @ftp_systype($this->_connection);
				//ftp_set_option($this->_connection, FTP_TIMEOUT_SEC, 30);
				@ftp_pasv($this->_connection, false);
				/*if ($this->arCurrentAccount['SETTINGS']['CLIENT_PASV'] == "Y") {
					@ftp_pasv($this->_connection, true);
				} else {
					@ftp_pasv($this->_connection, false);
				}*/
				return $this->_connection;
			} else {
				$this->_closeSession();
			}
		}
		return false;
	}

	protected function isFtpExists()
	{
		return (function_exists("ftp_ssl_connect") && function_exists("ftp_connect"));
	}

	protected function _reconnect()
	{
		$this->_closeSession();
		return $this->_getConnection();
	}

	protected function _ftp_nlist($oConnection, $strDir)
	{
		if ($this->arSettings['CLIENT_USENLIST'] != "Y") {
			return ftp_nlist($oConnection, $strDir);
		} else {
			return $this->_ftp_nlist_ext($oConnection, $strDir);
		}
	}

	protected function _ftp_nlist_ext($oConnection, $strDir)
	{
		$arResult = [];
		if ($this->arSettings['CLIENT_PASV'] == "Y") {
			@ftp_pasv($this->_connection, true);
		}
		$arFiles = @$this->ftp_rawlist($oConnection, $strDir);
		if ($arFiles === false) {
			$oConnection = $this->_reconnect();
			if ($this->arSettings['CLIENT_PASV'] == "Y") {
				@ftp_pasv($this->_connection, true);
			}
			$arFiles = @$this->ftp_rawlist($oConnection, $strDir);
		}
		if (!is_array($arFiles)) {
			$arFiles = [];
		}
		foreach ($arFiles as $k => $arFile) {
			$strFullName = $strDir . $arFile['name'];
			//$iSize = ftp_size($oConnection, $strFullName);
			if (in_array($arFile['name'], [".", ".."])) {
				continue;
			}
			$arResult[] = $arFile['name'];
		}
		return $arResult;
	}

	protected function _ftp_mlsd($oConnection, $strDir)
	{
		$arResult = [];
		if ($this->arSettings['CLIENT_PASV'] == "Y") {
			@ftp_pasv($this->_connection, true);
		}
		$arFiles = @$this->ftp_rawlist($oConnection, $strDir);
		if ($arFiles === false) {
			$oConnection = $this->_reconnect();
			if ($this->arCurrentAccount['SETTINGS']['CLIENT_PASV'] == "Y") {
				@ftp_pasv($this->_connection, true);
			}
			$arFiles = @$this->ftp_rawlist($oConnection, $strDir);
		}
		if (!is_array($arFiles)) {
			$arFiles = [];
		}
		foreach ($arFiles as $k => $arFile) {
			$strFullName = $strDir . $arFile['name'];
			//$iSize = ftp_size($oConnection, $strFullName);
			if (in_array($arFile['name'], [".", ".."])) {
				continue;
			}
			if ($arFile['isdir']) {
				$arResult[] = [
					"name" => $arFile['name'],
					"type" => "dir",
					"size" => $arFile['size'],
				];
			} else {
				$arResult[] = [
					"name" => $arFile['name'],
					"type" => "file",
					"size" => $arFile['size'],
				];
			}
		}
		return $arResult;
	}

	protected function ftp_rawlist($ftp_stream, $directory, $recursive = false)
	{
		$arFiles = @ftp_rawlist($ftp_stream, $directory, $recursive);
		$arResult = [];
		if (!is_array($arFiles)) {
			$arFiles = [];
		}
		foreach ($arFiles as $k => $strFile) {
			$arFile = [];
			$arFileInfo = preg_split("/\s+/", $strFile);
			if ($this->strSysType == "Windows_NT") {
				if (is_array($arFileInfo)) {
					if (strpos(strtolower($arFileInfo[2]), 'dir') !== false) {
						$arFile = [
							"name" => $arFileInfo[3],
							"size" => 0,
							"isdir" => true,
						];
					} else {
						$arFile = [
							"name" => $arFileInfo[3],
							"size" => $arFileInfo[2],
							"isdir" => false,
						];
					}
				}
			} else {
				$arFile = [
					"name" => $arFileInfo[8],
					"size" => $arFileInfo[4],
					"isdir" => (strtolower(substr($arFileInfo[0], 0, 1)) == "d"),
				];
			}
			if (!empty($arFile)) {
				$arResult[] = $arFile;
			}
		}
		return $arResult;
	}

	protected function _getListAllFiles($strDir, $bRecursive = true)
	{
		if (!$this->isFtpExists()) {
			return false;
		}
		$arAllFiles = [];
		$oConnection = $this->_getConnection();

		if (function_exists("ftp_mlsd") && $this->strSysType != "Windows_NT") {
			$arFiles = @ftp_mlsd($oConnection, $strDir);
		} else {
			$arFiles = $this->_ftp_mlsd($oConnection, $strDir);
			$oConnection = $this->_getConnection();
		}
		if (!$arFiles) {
			return false;
		}
		foreach ($arFiles as $k => $arFile) {
			$arNewFile = [];
			foreach ($arFile as $k1 => $v1) {
				$arNewFile[strtolower($k1)] = $v1;
			}
			$arFiles[$k] = $arNewFile;
		}
		foreach ($arFiles as $k => $arFile) {
			$strFullName = $strDir . $arFile['name'];
			if (strtolower($arFile['type']) == "dir") {
				$strFullName .= "/";
				$arAllFiles[$strFullName] = [
					"PATH" => $strFullName,
					"TYPE" => "DIR",
				];
				if ($bRecursive) {
					$arChildFiles = $this->_getListAllFiles($strFullName, $bRecursive);
					if (is_array($arChildFiles)) {
						$arAllFiles = array_merge($arAllFiles, $arChildFiles);
					}
				}
			} elseif (strtolower($arFile['type']) == "file") {
				$arAllFiles[$strFullName] = [
					"PATH" => $strFullName,
					"TYPE" => "FILE",
					"SIZE" => $arFile['size'],
				];
			}
		}
		return $arAllFiles;
	}

	public function GetListAllFiles($strDir, $bRecursive = true)
	{
		if (!$this->isFtpExists()) {
			return false;
		}
		$oConnection = $this->_getConnection();
		if ($this->arSettings['CLIENT_PASV'] == "Y") {
			@ftp_pasv($oConnection, true);
		}
		$arResult = $this->_getListAllFiles($strDir, $bRecursive);
		if ($this->arSettings['CLIENT_PASV'] == "Y") {
			@ftp_pasv($oConnection, false);
		}
		return $arResult;
	}

	public function GetRemoteFile($strRemoteName, $strLocalName)
	{
		if (!$this->isFtpExists()) {
			return false;
		}
		$oConnection = $this->_getConnection();
		$arPath = pathinfo($strRemoteName);
		if ($oConnection) {
			if ($this->arSettings['CLIENT_PASV'] == "Y") {
				@ftp_pasv($oConnection, true);
			}
			if (ftp_chdir($oConnection, $arPath['dirname'] . "/")) {
				if (ftp_get($oConnection, $strLocalName, $arPath['basename'], FTP_BINARY)) {
					@ftp_pasv($oConnection, false);
					return true;
				} else {
					if (file_exists($strLocalName)) {
						@unlink($strLocalName);
					}
				}
			}
		}
		@ftp_pasv($oConnection, false);
		return false;
	}

}

class AmminaBackupDriverSftp extends AmminaBackupDriverBase
{
	protected $_connection = null;
	protected $_sftpconnection = null;
	protected $iOldTimeNoop = false;

	function __destruct()
	{
		$this->_closeSession();
	}

	function doRenderSettings($bAllowEdit = true)
	{
		?>
		<div class="form-group">
			<label for="DRIVER_SETTINGS_CLIENT_SERVER">Сервер</label>
			<input type="text" class="form-control" id="DRIVER_SETTINGS[CLIENT_SERVER]" name="DRIVER_SETTINGS[CLIENT_SERVER]" value="<?= htmlspecialchars($this->arSettings['CLIENT_SERVER']) ?>"<?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>>
		</div>
		<div class="form-group">
			<label for="DRIVER_SETTINGS_CLIENT_LOGIN">Логин</label>
			<input type="text" class="form-control" id="DRIVER_SETTINGS[CLIENT_LOGIN]" name="DRIVER_SETTINGS[CLIENT_LOGIN]" value="<?= htmlspecialchars($this->arSettings['CLIENT_LOGIN']) ?>"<?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>>
		</div>
		<div class="form-group">
			<label for="DRIVER_SETTINGS_CLIENT_PASSWORD">Пароль</label>
			<input type="text" class="form-control" id="DRIVER_SETTINGS[CLIENT_PASSWORD]" name="DRIVER_SETTINGS[CLIENT_PASSWORD]" value="<?= htmlspecialchars($this->arSettings['CLIENT_PASSWORD']) ?>"<?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>>
		</div>
		<div class="form-group">
			<label for="DRIVER_SETTINGS_CLIENT_PORT">Порт</label>
			<input type="text" class="form-control" id="DRIVER_SETTINGS[CLIENT_PORT]" name="DRIVER_SETTINGS[CLIENT_PORT]" value="<?= htmlspecialchars($this->arSettings['CLIENT_PORT']) ?>"<?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>>
		</div>
		<div class="form-group">
			<label for="DRIVER_SETTINGS_CLIENT_DIR">Корневой каталог для бекапа (например "/home/backup/". Не указывайте
				корень файловой системы "/"):</label>
			<input type="text" class="form-control" id="DRIVER_SETTINGS[CLIENT_DIR]" name="DRIVER_SETTINGS[CLIENT_DIR]" value="<?= htmlspecialchars($this->arSettings['CLIENT_DIR']) ?>"<?= (!$bAllowEdit ? ' disabled="disabled"' : "") ?>>
		</div>
		<?
	}

	function doCheckPost()
	{
		if (isset($_REQUEST['DRIVER_SETTINGS']['CLIENT_USENLIST'])) {
			$this->arSettings['CLIENT_USENLIST'] = $_REQUEST['DRIVER_SETTINGS']['CLIENT_USENLIST'];
		}
	}

	protected function _closeSession()
	{
		if (!$this->isSftpExists()) {
			return false;
		}
		if ($this->_connection) {
			ssh2_disconnect($this->_connection);
			$this->_connection = null;
		}
		if ($this->_sftpconnection) {
			$this->_sftpconnection = null;
		}
	}

	public function _getConnection()
	{
		if (!function_exists("ssh2_connect")) {
			return false;
		}
		if ($this->_connection && $this->_sftpconnection) {
			if ($this->iOldTimeNoop === false || ($this->iOldTimeNoop + 30) <= time()) {
				$this->iOldTimeNoop = time();
				$dir = (strlen($this->arSettings['CLIENT_DIR']) > 0) ? $this->arSettings['CLIENT_DIR'] : "/home/";
				$arFiles = scandir("ssh2.sftp://" . intval($this->_sftpconnection) . $dir);
				if ($arFiles) {
					return $this->_sftpconnection;
				}
			} else {
				return $this->_sftpconnection;
			}
			$this->_closeSession();
		}
		$this->_connection = ssh2_connect($this->arSettings['CLIENT_SERVER'], $this->arSettings['CLIENT_PORT']);
		if ($this->_connection) {
			$bLoginResult = ssh2_auth_password($this->_connection, $this->arSettings['CLIENT_LOGIN'], $this->arSettings['CLIENT_PASSWORD']);
			if ($bLoginResult) {
				$this->_sftpconnection = ssh2_sftp($this->_connection);
				if ($this->_sftpconnection) {
					return $this->_sftpconnection;
				}
				$this->_closeSession();
			} else {
				$this->_closeSession();
			}
		}
		return false;
	}

	public function GetFileBrowserLink($strFileName)
	{
		return false;
	}

	public function GetListAllFiles($strDir, $bRecursive = true)
	{
		if (!$this->isSftpExists()) {
			return false;
		}
		$oConnection = $this->_getConnection();
		$baseDir = (strlen($this->arSettings['CLIENT_DIR']) > 0) ? $this->arSettings['CLIENT_DIR'] : "/home/";
		$arResult = $this->_getListAllFiles($baseDir . "/" . $strDir, $bRecursive);
		return $arResult;
	}

	protected function _getListAllFiles($strDir, $bRecursive = true)
	{
		if (!$this->isSftpExists()) {
			return false;
		}
		$arAllFiles = [];
		$oConnection = $this->_getConnection();
		if (substr($strDir, -1, 1) !== "/") {
			$strDir .= "/";
		}
		$arFiles = scandir("ssh2.sftp://" . intval($oConnection) . $strDir);
		if (!$arFiles) {
			return false;
		}
		foreach ($arFiles as $k => $strFile) {
			if (in_array($strFile, [".", ".."])) {
				continue;
			}
			$strFullName = $this->_normalizeFileName($strDir . $strFile);
			if (is_dir("ssh2.sftp://" . intval($oConnection) . $strDir . $strFile)) {
				$strFullName .= "/";
				$strFullName = $this->_normalizeFileName($strFullName);
				$arAllFiles[$strFullName] = [
					"PATH" => $strFullName,
					"TYPE" => "DIR",
				];
				if ($bRecursive) {
					$arChildFiles = $this->_getListAllFiles($strFullName, $bRecursive);
					if (is_array($arChildFiles)) {
						$arAllFiles = array_merge($arAllFiles, $arChildFiles);
					}
				}
			} else {
				$arStat = ssh2_sftp_stat($oConnection, $strDir . $strFile);
				$arAllFiles[$strFullName] = [
					"PATH" => $strFullName,
					"TYPE" => "FILE",
					"SIZE" => $arStat['size'],
				];
			}
		}
		return $arAllFiles;
	}

	protected function _normalizeFileName($strFileName)
	{
		while (strpos($strFileName, '//') !== false) {
			$strFileName = str_replace('//', '/', $strFileName);
		}
		return $strFileName;
	}

	public function GetRemoteFile($strRemoteName, $strLocalName)
	{
		if (!$this->isSFtpExists()) {
			return false;
		}
		$oConnection = $this->_getConnection();
		if ($oConnection) {
			$f = fopen($strLocalName, "w+b");
			if ($f) {
				$rf = fopen("ssh2.sftp://" . intval($oConnection) . $strRemoteName, "rb");
				if ($rf) {
					while (!feof($rf)) {
						if (fwrite($f, fread($rf, 65536)) === false) {
							fclose($rf);
							fclose($f);
							if (file_exists($strLocalName)) {
								@unlink($strLocalName);
							}
							return false;
						}
					}
					fclose($rf);
					fclose($f);
					return true;
				}
			}
		}
		return false;
	}

	protected function isSftpExists()
	{
		return (function_exists("ssh2_connect"));
	}

	protected function _reconnect()
	{
		$this->_closeSession();
		return $this->_getConnection();
	}

	public function GetListAllRemoteBackup()
	{
		global $APPLICATION;
		$arResult = [];
		$arAllFiles = $this->GetListAllFiles($APPLICATION->getBackupPath(), true);
		foreach ($arAllFiles as $k => $arFile) {
			if ($arFile['TYPE'] == "FILE") {
				$strRelName = substr($arFile['PATH'], strlen($this->_normalizeFileName($this->arSettings['CLIENT_DIR'] . $APPLICATION->getBackupPath())));
				if (substr($strRelName, 0, 1) == "/") {
					$strRelName = substr($strRelName, 1);
				}
				$iPosDir = strpos($strRelName, '/');
				if ($iPosDir !== false) {
					$strBackup = substr($strRelName, 0, $iPosDir);
					$strFileName = substr($strRelName, $iPosDir + 1);
					if (strlen($strBackup) > 0 && strlen($strFileName) > 0) {
						if (!isset($arResult[$strBackup])) {
							$strTime = substr($strBackup, 0, 4) . "." . substr($strBackup, 4, 2) . "." . substr($strBackup, 6, 2) . "." . substr($strBackup, 9, 2) . "." . substr($strBackup, 11, 2) . "." . substr($strBackup, 13, 2);
							$iTime = mktime(substr($strBackup, 9, 2), substr($strBackup, 11, 2), substr($strBackup, 13, 2), substr($strBackup, 4, 2), substr($strBackup, 6, 2), substr($strBackup, 0, 4));
							$arResult[$strBackup] = [
								"BACKUP_NAME" => $strBackup,
								"REMOTE_DIR" => $APPLICATION->getBackupPath() . $strBackup . "/",
								"BACKUP_TIME" => $iTime,
								"TOTAL_SIZE" => 0,
							];
						}
						$arResult[$strBackup]['TOTAL_SIZE'] += $arFile['SIZE'];
						$arResult[$strBackup]['FILES'][$strFileName] = [
							"FILE_NAME" => $strFileName,
							"FULL_NAME" => $arFile['PATH'],
							"SIZE" => $arFile['SIZE'],
						];
					}
				}
			}
		}
		return $arResult;
	}

}

function ApplicationIsUtfMode()
{
	if (ini_get("mbstring.func_overload") > 0) {
		return true;
	}
	return false;
}

if (ini_get("mbstring.func_overload") > 0) {
	define("BX_UTF", true);
}

function CheckDirPath($path, $bPermission = true)
{
	$path = str_replace(["\\", "//"], "/", $path);

	//remove file name
	if (substr($path, -1) != "/") {
		$p = strrpos($path, "/");
		$path = substr($path, 0, $p);
	}

	$path = rtrim($path, "/");

	if ($path == "") {
		//current folder always exists
		return true;
	}

	if (!file_exists($path)) {
		return mkdir($path, 0755, true);
	}

	return is_dir($path);
}

class InvalidPathException extends Exception
{

}

class ArgumentTypeException extends Exception
{
}

class ArgumentNullException extends Exception
{
}

class FileOpenException extends Exception
{
}

class FileNotOpenedException extends Exception
{
}

class FileNotFoundException extends Exception
{
}

class BinaryString
{
	public static function getLength($str)
	{
		return function_exists('mb_strlen') ? mb_strlen($str, 'latin1') : strlen($str);
	}

	public static function getSubstring($str, $start)
	{
		if (function_exists('mb_substr')) {
			$length = (func_num_args() > 2 ? func_get_arg(2) : self::getLength($str));
			return mb_substr($str, $start, $length, 'latin1');
		}
		if (func_num_args() > 2) {
			return substr($str, $start, func_get_arg(2));
		}
		return substr($str, $start);
	}

	public static function getPosition($haystack, $needle, $offset = 0)
	{
		if (defined("BX_UTF")) {
			if (function_exists("mb_orig_strpos")) {
				return mb_orig_strpos($haystack, $needle, $offset);
			}

			return mb_strpos($haystack, $needle, $offset, "latin1");
		}

		return strpos($haystack, $needle, $offset);
	}

	public static function getLastPosition($haystack, $needle, $offset = 0)
	{
		if (defined("BX_UTF")) {
			if (function_exists("mb_orig_strrpos")) {
				return mb_orig_strrpos($haystack, $needle, $offset);
			}

			return mb_strrpos($haystack, $needle, $offset, "latin1");
		}

		return strrpos($haystack, $needle, $offset);
	}

	public static function getPositionIgnoreCase($haystack, $needle, $offset = 0)
	{
		if (defined("BX_UTF")) {
			if (function_exists("mb_orig_stripos")) {
				return mb_orig_stripos($haystack, $needle, $offset);
			}

			return mb_stripos($haystack, $needle, $offset, "latin1");
		}

		return stripos($haystack, $needle, $offset);
	}

	public static function getLastPositionIgnoreCase($haystack, $needle, $offset = 0)
	{
		if (defined("BX_UTF")) {
			if (function_exists("mb_orig_strripos")) {
				return mb_orig_strripos($haystack, $needle, $offset);
			}

			return mb_strripos($haystack, $needle, $offset, "latin1");
		}

		return strripos($haystack, $needle, $offset);
	}

	public static function changeCaseToLower($str)
	{
		if (defined("BX_UTF")) {
			if (function_exists("mb_orig_strtolower")) {
				return mb_orig_strtolower($str);
			}

			return mb_strtolower($str, "latin1");
		}

		return strtolower($str);
	}
}

class CBXPunycode
{
	const PREFIX = 'xn--';

	private static $prefixUcs4 = null;
	private static $hyphenUcs4 = null;
	private static $punycodePrefix = null;
	private static $punycodePrefixUcs4 = null;
	private static $punycodePrefixLength = null;

	private $encoding;
	private $arErrors = [];

	/** @var CBXPunycode */
	private static $instance;

	/**
	 * Singleton method to return object instance.
	 *
	 * @static
	 * @return CBXPunycode
	 */
	public static function GetConverter()
	{
		if (!isset(self::$instance))
		{
			$c = __CLASS__;
			self::$instance = new $c;
		}

		return self::$instance;
	}

	/**
	 * Encode the given string
	 *
	 * @static
	 * @param   string  $domainName String to be encoded
	 * @param   array   $arErrors   Array of encoding errors (if any)
	 * @return  string              Encoded form of the given string
	 */
	public static function ToASCII($domainName, &$arErrors)
	{
		$arErrors = [];

		$converter = self::GetConverter();
		$result = $converter->Encode($domainName);

		if ($result === false)
			$arErrors = $converter->GetErrors();

		return $result;
	}

	/**
	 * Decode the given string
	 *
	 * @static
	 * @param   string  $domainName String to be decoded
	 * @param   array   $arErrors   Array of decoding errors (if any)
	 * @return  string              Decoded form of the given string
	 */
	public static function ToUnicode($domainName, &$arErrors)
	{
		$arErrors = [];

		$converter = self::GetConverter();
		$result = $converter->Decode($domainName);

		if ($result === false)
			$arErrors = $converter->GetErrors();

		return $result;
	}

	/**
	 * Check if the given string contains encoded parts
	 *
	 * @static
	 * @param   string  $domainName String to be checked
	 * @return  bool                True - if the given string contains encoded parts, false - otherwise
	 */
	public static function CheckASCII($domainName)
	{
		$converter = self::GetConverter();
		return $converter->IsEncoded($domainName);
	}

	public function __construct($encoding = null)
	{
		if (self::$punycodePrefix === null)
		{
			self::$punycodePrefix = "xn--";
			self::$punycodePrefixLength = strlen(self::$punycodePrefix);
			self::$punycodePrefixUcs4 = $this->Utf8ToUcs4(self::$punycodePrefix);
		}

		if (self::$prefixUcs4 === null)
			self::$prefixUcs4 = $this->Utf8ToUcs4(self::PREFIX);

		if (self::$hyphenUcs4 === null)
			self::$hyphenUcs4 = $this->Utf8ToUcs4("-");

		if (!is_null($encoding))
			$this->encoding = $encoding;
		else
			$this->encoding = "utf-8";
	}

	/**
	 * Check if the string contains encoded parts
	 *
	 * @param   string  $domainName Validated string
	 * @return  bool
	 */
	public function IsEncoded($domainName)
	{
		$this->ClearErrors();

		$domainName = mb_strtolower(trim($domainName));

		$schemePosition = mb_strpos($domainName, "://");
		if ($schemePosition !== false)
			$domainName = mb_substr($domainName, $schemePosition + 3);

		if ($this->encoding != "utf-8")
			$domainName = \Bitrix\Main\Text\Encoding::convertEncoding($domainName, $this->encoding, "utf-8");

		$domainNameUcs4 = $this->Utf8ToUcs4($domainName);
		if (empty($domainNameUcs4))
			return false;

		$arDomainNameUcs4 = $this->ExplodeDomainName($domainNameUcs4);

		foreach ($arDomainNameUcs4 as $value)
		{
			$checked = array_slice($value, 0, self::$punycodePrefixLength);
			if (self::$punycodePrefixUcs4 == $checked)
				return true;
		}

		return false;
	}

	/**
	 * Decode the given string
	 *
	 * @param   string  $domainName String to be decoded
	 * @return  string              Decoded form of the given string
	 */
	public function Decode($domainName)
	{
		$this->ClearErrors();

		$domainName = mb_strtolower(trim($domainName));

		$scheme = "";
		$schemePosition = mb_strpos($domainName, "://");
		if ($schemePosition !== false)
		{
			$scheme = mb_substr($domainName, 0, $schemePosition);
			$domainName = mb_substr($domainName, $schemePosition + 3);
		}

		$port = "";
		if (preg_match("/^(.+):([0-9]+)\$/", $domainName, $portMatch))
		{
			$port = $portMatch[2];
			$domainName = $portMatch[1];
		}

		if ($this->encoding != "utf-8")
			$domainName = \Bitrix\Main\Text\Encoding::convertEncoding($domainName, $this->encoding, "utf-8");

		$domainNameUcs4 = $this->Utf8ToUcs4($domainName);
		if (empty($domainNameUcs4))
			return "";

		$arDomainNameUcs4 = $this->ExplodeDomainName($domainNameUcs4);

		foreach ($arDomainNameUcs4 as $key => $value)
		{
			$checked = array_slice($value, 0, self::$punycodePrefixLength);
			if (self::$punycodePrefixUcs4 != $checked)
				continue;

			$arDomainNameUcs4[$key] = $this->DoDecodeUcs4($value);
		}

		$domainNameUcs4 = $this->ImplodeDomainName($arDomainNameUcs4);
		$domainName = $this->Ucs4ToUtf8($domainNameUcs4);

		if ($this->encoding != "utf-8")
			$domainName = \Bitrix\Main\Text\Encoding::convertEncoding($domainName, "utf-8", $this->encoding);

		if (!empty($scheme))
			$domainName = $scheme."://".$domainName;

		if ($port !== "")
			$domainName = $domainName.":".$port;

		return $domainName;
	}

	/**
	 * Encode the given string
	 *
	 * @param   string  $domainName String to be encoded
	 * @return  string              Encoded form of the given string
	 */
	public function Encode($domainName)
	{
		$this->ClearErrors();

		$domainName = mb_strtolower(trim($domainName));

		$scheme = "";
		$schemePosition = mb_strpos($domainName, "://");
		if ($schemePosition !== false)
		{
			$scheme = mb_substr($domainName, 0, $schemePosition);
			$domainName = mb_substr($domainName, $schemePosition + 3);
		}

		if ($this->encoding != "utf-8")
			$domainName = \Bitrix\Main\Text\Encoding::convertEncoding($domainName, $this->encoding, "utf-8");

		$domainNameUcs4 = $this->Utf8ToUcs4($domainName);
		if (empty($domainNameUcs4))
			return "";

		$arDomainNameUcs4 = $this->ExplodeDomainName($domainNameUcs4);

		foreach ($arDomainNameUcs4 as $key => $value)
		{
			$checked = array_slice($value, 0, self::$punycodePrefixLength);
			if (self::$punycodePrefixUcs4 == $checked)
				continue;

			$arDomainNameUcs4[$key] = $this->NamePrepUcs4($value);
			if (!$arDomainNameUcs4[$key] || !is_array($arDomainNameUcs4[$key]))
				return false;

			$arDomainNameUcs4[$key] = $this->DoEncodeUcs4($arDomainNameUcs4[$key]);
		}

		$domainNameUcs4 = $this->ImplodeDomainName($arDomainNameUcs4);
		$domainName = $this->Ucs4ToUtf8($domainNameUcs4);

		if ($this->encoding != "utf-8")
			$domainName = \Bitrix\Main\Text\Encoding::convertEncoding($domainName, "utf-8", $this->encoding);

		if (!empty($scheme))
			$domainName = $scheme."://".$domainName;

		return $domainName;
	}

	private function DoDecodeUcs4($input)
	{
		$result = [];

		$input = array_slice($input, self::$punycodePrefixLength);
		$delimiterPosition = false;
		for ($i = count($input) - 1; $i >= 0; $i--)
		{
			if (array($input[$i]) == self::$hyphenUcs4)
			{
				$delimiterPosition = $i;
				break;
			}
		}

		if ($delimiterPosition !== false)
		{
			for ($i = 0; $i < $delimiterPosition; $i++)
				$result[] = $input[$i];
		}

		$resultLength = count($result);
		$inputLength = count($input);

		$isFirst = true;
		$bias = 72;
		$idx = 0;
		$char = 0x80;

		for ($inputIdx = ($delimiterPosition !== false) ? ($delimiterPosition + 1) : 0;
			 $inputIdx < $inputLength;
			 $resultLength++)
		{
			for ($oldIdx = $idx, $w = 1, $k = 36; 1; $k += 36)
			{
				$c = $input[$inputIdx++];
				$digit = ($c - 48 < 10) ? $c - 22 : (($c - 65 < 26) ? $c - 65 : (($c - 97 < 26) ? $c - 97 : 36));
				$idx += $digit * $w;
				$t = ($k <= $bias) ? 1 : (($k >= $bias + 26) ? 26 : ($k - $bias));
				if ($digit < $t)
					break;
				$w = (int) ($w * (36 - $t));
			}
			$bias = $this->Adapt($idx - $oldIdx, $resultLength + 1, $isFirst);
			$isFirst = false;
			$char += (int) ($idx / ($resultLength + 1));
			$idx %= ($resultLength + 1);
			if ($resultLength > 0)
			{
				for ($i = $resultLength; $i > $idx; $i--)
					$result[$i] = $result[($i - 1)];
			}
			$result[$idx++] = $char;
		}
		return $result;
	}

	private function DoEncodeUcs4($input)
	{
		$inputLength = count($input);
		if (!$inputLength)
			return false;

		$codeCount = 0;
		$result = [];

		for ($i = 0; $i < $inputLength; $i++)
		{
			$char = $input[$i];
			if (($char > 0x2F && $char < 0x40)
				|| ($char > 0x40 && $char < 0x5B)
				|| ($char > 0x60 && $char <= 0x7B)
				|| ($char == 0x2D))
			{
				$result[] = $char;
				$codeCount++;
			}
		}
		if ($codeCount == $inputLength)
			return $result;

		$result = array_merge(self::$prefixUcs4, $result);

		if ($codeCount)
			$result = array_merge($result, self::$hyphenUcs4);

		$isFirst = true;
		$currentCode = 0x80;
		$bias = 72;
		$delta = 0;
		while ($codeCount < $inputLength)
		{
			for ($i = 0, $nextCode = 0x10FFFF; $i < $inputLength; $i++)
			{
				if ($input[$i] >= $currentCode && $input[$i] <= $nextCode)
					$nextCode = $input[$i];
			}
			$delta += ($nextCode - $currentCode) * ($codeCount + 1);
			$currentCode = $nextCode;

			for ($i = 0; $i < $inputLength; $i++)
			{
				if ($input[$i] < $currentCode)
				{
					$delta++;
				}
				elseif ($input[$i] == $currentCode)
				{
					for ($q = $delta, $k = 36; 1; $k += 36)
					{
						$t = ($k <= $bias) ? 1 : (($k >= $bias + 26) ? 26 : $k - $bias);
						if ($q < $t)
							break;
						$d = $t + (($q - $t) % (36 - $t));
						$result[] = $d + 22 + 75 * ($d < 26);
						$q = (int) (($q - $t) / (36 - $t));
					}
					$result[] = $q + 22 + 75 * ($q < 26);
					$bias = $this->Adapt($delta, $codeCount + 1, $isFirst);
					$codeCount++;
					$delta = 0;
					$isFirst = false;
				}
			}
			$delta++;
			$currentCode++;
		}
		return $result;
	}

	private function Adapt($delta, $numPoints, $isFirst)
	{
		$delta = intval($isFirst ? ($delta / 700) : ($delta / 2));
		$delta += intval($delta / $numPoints);
		for ($k = 0; $delta > 455; $k += 36)
			$delta = intval($delta / (36 - 1));
		return intval($k + 36 * $delta / ($delta + 38));
	}

	private function NamePrepUcs4($input)
	{
		$output = [];

		foreach ($input as $v)
		{
			if (in_array($v, self::$rfc3454Table['Skip']))
				continue;

			if (in_array($v, self::$rfc3454Table['Reject'])
				|| in_array($v, self::$rfc3454Table['RejectGeneral']))
			{
				$this->AddError('Rejected code U+'.sprintf('%08X', $v));
				return false;
			}
			foreach (self::$rfc3454Table['RejectRanges'] as $range)
			{
				if ($range[0] <= $v && $v <= $range[1])
				{
					$this->AddError('Rejected code U+'.sprintf('%08X', $v));
					return false;
				}
			}

			if ($v >= 0xAC00 && $v <= 0xD7AF)
			{
				foreach ($this->DecomposeKorean($v) as $out)
					$output[] = (int) $out;
			}
			else
			{
				$output[] = (int) $v;
			}
		}

		$output = $this->ComposeKorean($output);

		$lastClass = 0;
		$lastStarter = 0;
		$outputLength = count($output);
		for ($i = 0; $i < $outputLength; $i++)
		{
			$class = self::$rfc3454Table['CombiningClass'][$output[$i]] ?? 0;
			if ((!$lastClass || $lastClass > $class) && $class)
			{
				$sequenceLength = $i - $lastStarter;
				$out = $this->Combine(array_slice($output, $lastStarter, $sequenceLength));
				if ($out)
				{
					$output[$lastStarter] = $out;
					if (count($output) != $sequenceLength)
					{
						for ($j = $i + 1; $j < $outputLength; $j++)
							$output[$j - 1] = $output[$j];
						unset($output[$outputLength]);
					}
					$i--;
					$outputLength--;
					if ($i == $lastStarter)
					{
						$lastClass = 0;
					}
					else
					{
						$lastClass = self::$rfc3454Table['CombiningClass'][$output[$i - 1]] ?? 0;
					}
					continue;
				}
			}

			if (!$class)
				$lastStarter = $i;
			$lastClass = $class;
		}
		return $output;
	}

	private function Combine($input)
	{
		$inputLength = count($input);
		foreach (self::$rfc3454Table['ReplaceMap'] as $src => $target)
		{
			if ($target[0] != $input[0])
				continue;
			if (count($target) != $inputLength)
				continue;
			$hit = false;
			foreach ($input as $k => $v)
			{
				if ($v == $target[$k])
				{
					$hit = true;
				}
				else
				{
					$hit = false;
					break;
				}
			}
			if ($hit)
				return $src;
		}
		return false;
	}

	private function DecomposeKorean($char)
	{
		$ix = (int) $char - 0xAC00;
		if ($ix < 0 || $ix >= 11172)
			return array($char);
		$result = [];
		$result[] = (int) (0x1100 + $ix / 588);
		$result[] = (int) (0x1161 + ($ix % 588) / 28);
		$t = 0x11A7 + $ix % 28;
		if ($t != 0x11A7)
			$result[] = $t;
		return $result;
	}

	private function ComposeKorean($input)
	{
		$inputLength = count($input);
		if (!$inputLength)
			return [];

		$result = [];
		$last = (int) $input[0];
		$result[] = $last;

		for ($i = 1; $i < $inputLength; $i++)
		{
			$char = (int) $input[$i];
			$six = $last - 0xAC00;
			$lix = $last - 0x1100;
			$vix = $char - 0x1161;
			$tix = $char - 0x11A7;

			if ($six >= 0 && $six < 11172 && ($six % 28 == 0) && $tix >= 0 && $tix <= 28)
			{
				$last += $tix;
				$result[(count($result) - 1)] = $last;
				continue;
			}

			if ($lix >= 0 && $lix < 19 && $vix >= 0 && $vix < 21)
			{
				$last = 0xAC00 + ($lix * 21 + $vix) * 28;
				$result[(count($result) - 1)] = $last;
				continue;
			}

			$last = $char;
			$result[] = $char;
		}
		return $result;
	}

	private function Utf8ToUcs4($input)
	{
		$output = [];
		$outputLength = 0;
		$inputLength = strlen($input);
		$mode = 'next';
		$test = 'none';
		$startByte = 0;
		$nextByte = 0;

		for ($i = 0; $i < $inputLength; $i++)
		{
			$v = ord($input[$i]);
			if ($v < 128)
			{
				$output[$outputLength] = $v;
				$outputLength++;
				if ($mode == 'add')
				{
					$this->AddError("UTF-8 / UCS-4 conversion failed: byte ".$i);
					return false;
				}
				continue;
			}
			if ($mode == 'next')
			{
				$startByte = $v;
				$mode = 'add';
				$test = 'range';
				if ($v >> 5 == 6)
				{
					$nextByte = 0;
					$v = ($v - 192) << 6;
				}
				elseif ($v >> 4 == 14)
				{
					$nextByte = 1;
					$v = ($v - 224) << 12;
				}
				elseif ($v >> 3 == 30)
				{
					$nextByte = 2;
					$v = ($v - 240) << 18;
				}
				elseif ($v >> 2 == 62)
				{
					$nextByte = 3;
					$v = ($v - 248) << 24;
				}
				elseif ($v >> 1 == 126)
				{
					$nextByte = 4;
					$v = ($v - 252) << 30;
				}
				else
				{
					$this->AddError("UTF-8 / UCS-4 conversion failed: byte ".$i);
					return false;
				}

				$output[$outputLength] = $v;
				$outputLength++;
				continue;
			}
			if ($mode == 'add')
			{
				if ($test == 'range')
				{
					$test = 'none';
					if (($v < 0xA0 && $startByte == 0xE0)
						|| ($v < 0x90 && $startByte == 0xF0)
						|| ($v > 0x8F && $startByte == 0xF4))
					{
						$this->AddError("UTF-8 / UCS-4 conversion failed: byte ".$i);
						return false;
					}
				}
				if ($v >> 6 == 2)
				{
					$v = ($v - 128) << ($nextByte * 6);
					$output[($outputLength - 1)] += $v;
					$nextByte--;
				}
				else
				{
					$this->AddError("UTF-8 / UCS-4 conversion failed: byte ".$i);
					return false;
				}
				if ($nextByte < 0)
					$mode = 'next';
			}
		}
		return $output;
	}

	private function Ucs4ToUtf8($input)
	{
		$output = '';
		foreach ($input as $k => $v)
		{
			if ($v < 128)
			{
				$output .= chr($v);
			}
			elseif ($v < (1 << 11))
			{
				$output .= chr(192 + ($v >> 6)).chr(128 + ($v & 63));
			}
			elseif ($v < (1 << 16))
			{
				$output .= chr(224 + ($v >> 12)).chr(128 + (($v >> 6) & 63)).chr(128 + ($v & 63));
			}
			elseif ($v < (1 << 21))
			{
				$output .= chr(240 + ($v >> 18)).chr(128 + (($v >> 12) & 63)).chr(128 + (($v >> 6) & 63)).chr(128 + ($v & 63));
			}
			else
			{
				$this->AddError('UCS-4 / UTF-8 conversion failed: byte '.$k);
				return false;
			}
		}
		return $output;
	}

	private function ExplodeDomainName($domainNameUcs4)
	{
		$arResult = [];

		$ar = [];
		foreach ($domainNameUcs4 as $value)
		{
			if ($value == 0x2E)
			{
				$arResult[] = $ar;
				$ar = [];
			}
			else
			{
				$ar[] = $value;
			}
		}
		$arResult[] = $ar;

		return $arResult;
	}

	private function ImplodeDomainName($arDomainNameUcs4)
	{
		$result = [];

		foreach ($arDomainNameUcs4 as $value)
		{
			if (!empty($result))
				$result[] = 0x2E;
			$result = array_merge($result, $value);
		}

		return $result;
	}

	private function ClearErrors()
	{
		$this->arErrors = [];
	}

	private function AddError($error)
	{
		$this->arErrors[] = $error;
	}

	public function GetErrors()
	{
		return $this->arErrors;
	}

	private static $rfc3454Table = array(
		'Skip' => array(
			0xAD, 0x34F, 0x1806, 0x180B, 0x180C, 0x180D, 0x200B, 0x200C, 0x200D,
			0x2060, 0xFE00, 0xFE01, 0xFE02, 0xFE03, 0xFE04, 0xFE05, 0xFE06, 0xFE07,
			0xFE08, 0xFE09, 0xFE0A, 0xFE0B, 0xFE0C, 0xFE0D, 0xFE0E, 0xFE0F, 0xFEFF
		),
		'RejectGeneral' => array(
			0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19,
			20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32 ,33, 34, 35, 36, 37,
			38, 39, 40, 41, 42, 43, 44, 47, 59, 60, 61, 62, 63, 64, 91, 92, 93, 94,
			95, 96, 123, 124, 125, 126, 127, 0x3002
		),
		'Reject' => array(
			0xA0, 0x340, 0x341, 0x6DD, 0x70F, 0x1680, 0x180E, 0x2000, 0x2001, 0x2002, 0x2003,
			0x2004, 0x2005, 0x2006, 0x2007, 0x2008, 0x2009, 0x200A, 0x200B, 0x200C, 0x200D, 0x200E,
			0x200F, 0x2028, 0x2029, 0x202A, 0x202B, 0x202C, 0x202D, 0x202E, 0x202F, 0x205F, 0x206A,
			0x206B, 0x206C, 0x206D, 0x206E, 0x206F, 0x3000, 0xFEFF, 0xFFF9, 0xFFFA, 0xFFFB, 0xFFFC,
			0xFFFD, 0xFFFE, 0xFFFF, 0x1FFFE, 0x1FFFF, 0x2FFFE, 0x2FFFF, 0x3FFFE, 0x3FFFF, 0x4FFFE,
			0x4FFFF, 0x5FFFE, 0x5FFFF, 0x6FFFE, 0x6FFFF, 0x7FFFE, 0x7FFFF, 0x8FFFE, 0x8FFFF,
			0x9FFFE, 0x9FFFF, 0xAFFFE, 0xAFFFF, 0xBFFFE, 0xBFFFF, 0xCFFFE, 0xCFFFF, 0xDFFFE,
			0xDFFFF, 0xE0001, 0xEFFFE, 0xEFFFF, 0xFFFFE, 0xFFFFF, 0x10FFFE, 0x10FFFF
		),
		'RejectRanges' => array(
			array(0x80, 0x9F), array(0x2060, 0x206F), array(0x1D173, 0x1D17A), array(0xE000, 0xF8FF),
			array(0xF0000, 0xFFFFD), array(0x100000, 0x10FFFD), array(0xFDD0, 0xFDEF),
			array(0xD800, 0xDFFF), array(0x2FF0, 0x2FFB), array(0xE0020, 0xE007F)
		),
		'ReplaceMap' => array(
			0x41 => array(0x61), 0x42 => array(0x62), 0x43 => array(0x63), 0x44 => array(0x64),
			0x45 => array(0x65), 0x46 => array(0x66), 0x47 => array(0x67), 0x48 => array(0x68),
			0x49 => array(0x69), 0x4A => array(0x6A), 0x4B => array(0x6B), 0x4C => array(0x6C),
			0x4D => array(0x6D), 0x4E => array(0x6E), 0x4F => array(0x6F), 0x50 => array(0x70),
			0x51 => array(0x71), 0x52 => array(0x72), 0x53 => array(0x73), 0x54 => array(0x74),
			0x55 => array(0x75), 0x56 => array(0x76), 0x57 => array(0x77), 0x58 => array(0x78),
			0x59 => array(0x79), 0x5A => array(0x7A), 0xB5 => array(0x3BC), 0xC0 => array(0xE0),
			0xC1 => array(0xE1), 0xC2 => array(0xE2), 0xC3 => array(0xE3), 0xC4 => array(0xE4),
			0xC5 => array(0xE5), 0xC6 => array(0xE6), 0xC7 => array(0xE7), 0xC8 => array(0xE8),
			0xC9 => array(0xE9), 0xCA => array(0xEA), 0xCB => array(0xEB), 0xCC => array(0xEC),
			0xCD => array(0xED), 0xCE => array(0xEE), 0xCF => array(0xEF), 0xD0 => array(0xF0),
			0xD1 => array(0xF1), 0xD2 => array(0xF2), 0xD3 => array(0xF3), 0xD4 => array(0xF4),
			0xD5 => array(0xF5), 0xD6 => array(0xF6), 0xD8 => array(0xF8), 0xD9 => array(0xF9),
			0xDA => array(0xFA), 0xDB => array(0xFB), 0xDC => array(0xFC), 0xDD => array(0xFD),
			0xDE => array(0xFE), 0xDF => array(0x73, 0x73), 0x100 => array(0x101),
			0x102 => array(0x103), 0x104 => array(0x105), 0x106 => array(0x107),
			0x108 => array(0x109), 0x10A => array(0x10B), 0x10C => array(0x10D),
			0x10E => array(0x10F), 0x110 => array(0x111), 0x112 => array(0x113),
			0x114 => array(0x115), 0x116 => array(0x117), 0x118 => array(0x119),
			0x11A => array(0x11B), 0x11C => array(0x11D), 0x11E => array(0x11F),
			0x120 => array(0x121), 0x122 => array(0x123), 0x124 => array(0x125),
			0x126 => array(0x127), 0x128 => array(0x129), 0x12A => array(0x12B),
			0x12C => array(0x12D), 0x12E => array(0x12F), 0x130 => array(0x69, 0x307),
			0x132 => array(0x133), 0x134 => array(0x135), 0x136 => array(0x137),
			0x139 => array(0x13A), 0x13B => array(0x13C), 0x13D => array(0x13E),
			0x13F => array(0x140), 0x141 => array(0x142), 0x143 => array(0x144),
			0x145 => array(0x146), 0x147 => array(0x148), 0x149 => array(0x2BC, 0x6E),
			0x14A => array(0x14B), 0x14C => array(0x14D), 0x14E => array(0x14F),
			0x150 => array(0x151), 0x152 => array(0x153), 0x154 => array(0x155),
			0x156 => array(0x157), 0x158 => array(0x159), 0x15A => array(0x15B),
			0x15C => array(0x15D), 0x15E => array(0x15F), 0x160 => array(0x161),
			0x162 => array(0x163), 0x164 => array(0x165), 0x166 => array(0x167),
			0x168 => array(0x169), 0x16A => array(0x16B), 0x16C => array(0x16D),
			0x16E => array(0x16F), 0x170 => array(0x171), 0x172 => array(0x173),
			0x174 => array(0x175), 0x176 => array(0x177), 0x178 => array(0xFF),
			0x179 => array(0x17A), 0x17B => array(0x17C), 0x17D => array(0x17E),
			0x17F => array(0x73), 0x181 => array(0x253), 0x182 => array(0x183),
			0x184 => array(0x185), 0x186 => array(0x254), 0x187 => array(0x188),
			0x189 => array(0x256), 0x18A => array(0x257), 0x18B => array(0x18C),
			0x18E => array(0x1DD), 0x18F => array(0x259), 0x190 => array(0x25B),
			0x191 => array(0x192), 0x193 => array(0x260), 0x194 => array(0x263),
			0x196 => array(0x269), 0x197 => array(0x268), 0x198 => array(0x199),
			0x19C => array(0x26F), 0x19D => array(0x272), 0x19F => array(0x275),
			0x1A0 => array(0x1A1), 0x1A2 => array(0x1A3), 0x1A4 => array(0x1A5),
			0x1A6 => array(0x280), 0x1A7 => array(0x1A8), 0x1A9 => array(0x283),
			0x1AC => array(0x1AD), 0x1AE => array(0x288), 0x1AF => array(0x1B0),
			0x1B1 => array(0x28A), 0x1B2 => array(0x28B), 0x1B3 => array(0x1B4),
			0x1B5 => array(0x1B6), 0x1B7 => array(0x292), 0x1B8 => array(0x1B9),
			0x1BC => array(0x1BD), 0x1C4 => array(0x1C6), 0x1C5 => array(0x1C6),
			0x1C7 => array(0x1C9), 0x1C8 => array(0x1C9), 0x1CA => array(0x1CC),
			0x1CB => array(0x1CC), 0x1CD => array(0x1CE), 0x1CF => array(0x1D0),
			0x1D1 => array(0x1D2), 0x1D3 => array(0x1D4), 0x1D5 => array(0x1D6),
			0x1D7 => array(0x1D8), 0x1D9 => array(0x1DA), 0x1DB => array(0x1DC),
			0x1DE => array(0x1DF), 0x1E0 => array(0x1E1), 0x1E2 => array(0x1E3),
			0x1E4 => array(0x1E5), 0x1E6 => array(0x1E7), 0x1E8 => array(0x1E9),
			0x1EA => array(0x1EB), 0x1EC => array(0x1ED), 0x1EE => array(0x1EF),
			0x1F0 => array(0x6A, 0x30C), 0x1F1 => array(0x1F3), 0x1F2 => array(0x1F3),
			0x1F4 => array(0x1F5), 0x1F6 => array(0x195), 0x1F7 => array(0x1BF),
			0x1F8 => array(0x1F9), 0x1FA => array(0x1FB), 0x1FC => array(0x1FD),
			0x1FE => array(0x1FF), 0x200 => array(0x201), 0x202 => array(0x203),
			0x204 => array(0x205), 0x206 => array(0x207), 0x208 => array(0x209),
			0x20A => array(0x20B), 0x20C => array(0x20D), 0x20E => array(0x20F),
			0x210 => array(0x211), 0x212 => array(0x213), 0x214 => array(0x215),
			0x216 => array(0x217), 0x218 => array(0x219), 0x21A => array(0x21B),
			0x21C => array(0x21D), 0x21E => array(0x21F), 0x220 => array(0x19E),
			0x222 => array(0x223), 0x224 => array(0x225), 0x226 => array(0x227),
			0x228 => array(0x229), 0x22A => array(0x22B), 0x22C => array(0x22D),
			0x22E => array(0x22F), 0x230 => array(0x231), 0x232 => array(0x233),
			0x345 => array(0x3B9), 0x37A => array(0x20, 0x3B9), 0x386 => array(0x3AC),
			0x388 => array(0x3AD), 0x389 => array(0x3AE), 0x38A => array(0x3AF),
			0x38C => array(0x3CC), 0x38E => array(0x3CD), 0x38F => array(0x3CE),
			0x390 => array(0x3B9, 0x308, 0x301), 0x391 => array(0x3B1), 0x392 => array(0x3B2),
			0x393 => array(0x3B3), 0x394 => array(0x3B4), 0x395 => array(0x3B5),
			0x396 => array(0x3B6), 0x397 => array(0x3B7), 0x398 => array(0x3B8),
			0x399 => array(0x3B9), 0x39A => array(0x3BA), 0x39B => array(0x3BB),
			0x39C => array(0x3BC), 0x39D => array(0x3BD), 0x39E => array(0x3BE),
			0x39F => array(0x3BF), 0x3A0 => array(0x3C0), 0x3A1 => array(0x3C1),
			0x3A3 => array(0x3C3), 0x3A4 => array(0x3C4), 0x3A5 => array(0x3C5),
			0x3A6 => array(0x3C6), 0x3A7 => array(0x3C7), 0x3A8 => array(0x3C8),
			0x3A9 => array(0x3C9), 0x3AA => array(0x3CA), 0x3AB => array(0x3CB),
			0x3B0 => array(0x3C5, 0x308, 0x301), 0x3C2 => array(0x3C3), 0x3D0 => array(0x3B2),
			0x3D1 => array(0x3B8), 0x3D2 => array(0x3C5), 0x3D3 => array(0x3CD),
			0x3D4 => array(0x3CB), 0x3D5 => array(0x3C6), 0x3D6 => array(0x3C0),
			0x3D8 => array(0x3D9), 0x3DA => array(0x3DB), 0x3DC => array(0x3DD),
			0x3DE => array(0x3DF), 0x3E0 => array(0x3E1), 0x3E2 => array(0x3E3),
			0x3E4 => array(0x3E5), 0x3E6 => array(0x3E7), 0x3E8 => array(0x3E9),
			0x3EA => array(0x3EB), 0x3EC => array(0x3ED), 0x3EE => array(0x3EF),
			0x3F0 => array(0x3BA), 0x3F1 => array(0x3C1), 0x3F2 => array(0x3C3),
			0x3F4 => array(0x3B8), 0x3F5 => array(0x3B5), 0x400 => array(0x450),
			0x401 => array(0x451), 0x402 => array(0x452), 0x403 => array(0x453),
			0x404 => array(0x454), 0x405 => array(0x455), 0x406 => array(0x456),
			0x407 => array(0x457), 0x408 => array(0x458), 0x409 => array(0x459),
			0x40A => array(0x45A), 0x40B => array(0x45B), 0x40C => array(0x45C),
			0x40D => array(0x45D), 0x40E => array(0x45E), 0x40F => array(0x45F),
			0x410 => array(0x430), 0x411 => array(0x431), 0x412 => array(0x432),
			0x413 => array(0x433), 0x414 => array(0x434), 0x415 => array(0x435),
			0x416 => array(0x436), 0x417 => array(0x437), 0x418 => array(0x438),
			0x419 => array(0x439), 0x41A => array(0x43A), 0x41B => array(0x43B),
			0x41C => array(0x43C), 0x41D => array(0x43D), 0x41E => array(0x43E),
			0x41F => array(0x43F), 0x420 => array(0x440), 0x421 => array(0x441),
			0x422 => array(0x442), 0x423 => array(0x443), 0x424 => array(0x444),
			0x425 => array(0x445), 0x426 => array(0x446), 0x427 => array(0x447),
			0x428 => array(0x448), 0x429 => array(0x449), 0x42A => array(0x44A),
			0x42B => array(0x44B), 0x42C => array(0x44C), 0x42D => array(0x44D),
			0x42E => array(0x44E), 0x42F => array(0x44F), 0x460 => array(0x461),
			0x462 => array(0x463), 0x464 => array(0x465), 0x466 => array(0x467),
			0x468 => array(0x469), 0x46A => array(0x46B), 0x46C => array(0x46D),
			0x46E => array(0x46F), 0x470 => array(0x471), 0x472 => array(0x473),
			0x474 => array(0x475), 0x476 => array(0x477), 0x478 => array(0x479),
			0x47A => array(0x47B), 0x47C => array(0x47D), 0x47E => array(0x47F),
			0x480 => array(0x481), 0x48A => array(0x48B), 0x48C => array(0x48D),
			0x48E => array(0x48F), 0x490 => array(0x491), 0x492 => array(0x493),
			0x494 => array(0x495), 0x496 => array(0x497), 0x498 => array(0x499),
			0x49A => array(0x49B), 0x49C => array(0x49D), 0x49E => array(0x49F),
			0x4A0 => array(0x4A1), 0x4A2 => array(0x4A3), 0x4A4 => array(0x4A5),
			0x4A6 => array(0x4A7), 0x4A8 => array(0x4A9), 0x4AA => array(0x4AB),
			0x4AC => array(0x4AD), 0x4AE => array(0x4AF), 0x4B0 => array(0x4B1),
			0x4B2 => array(0x4B3), 0x4B4 => array(0x4B5), 0x4B6 => array(0x4B7),
			0x4B8 => array(0x4B9), 0x4BA => array(0x4BB), 0x4BC => array(0x4BD),
			0x4BE => array(0x4BF), 0x4C1 => array(0x4C2), 0x4C3 => array(0x4C4),
			0x4C5 => array(0x4C6), 0x4C7 => array(0x4C8), 0x4C9 => array(0x4CA),
			0x4CB => array(0x4CC), 0x4CD => array(0x4CE), 0x4D0 => array(0x4D1),
			0x4D2 => array(0x4D3), 0x4D4 => array(0x4D5), 0x4D6 => array(0x4D7),
			0x4D8 => array(0x4D9), 0x4DA => array(0x4DB), 0x4DC => array(0x4DD),
			0x4DE => array(0x4DF), 0x4E0 => array(0x4E1), 0x4E2 => array(0x4E3),
			0x4E4 => array(0x4E5), 0x4E6 => array(0x4E7), 0x4E8 => array(0x4E9),
			0x4EA => array(0x4EB), 0x4EC => array(0x4ED), 0x4EE => array(0x4EF),
			0x4F0 => array(0x4F1), 0x4F2 => array(0x4F3), 0x4F4 => array(0x4F5),
			0x4F8 => array(0x4F9), 0x500 => array(0x501), 0x502 => array(0x503),
			0x504 => array(0x505), 0x506 => array(0x507), 0x508 => array(0x509),
			0x50A => array(0x50B), 0x50C => array(0x50D), 0x50E => array(0x50F),
			0x531 => array(0x561), 0x532 => array(0x562), 0x533 => array(0x563),
			0x534 => array(0x564), 0x535 => array(0x565), 0x536 => array(0x566),
			0x537 => array(0x567), 0x538 => array(0x568), 0x539 => array(0x569),
			0x53A => array(0x56A), 0x53B => array(0x56B), 0x53C => array(0x56C),
			0x53D => array(0x56D), 0x53E => array(0x56E), 0x53F => array(0x56F),
			0x540 => array(0x570), 0x541 => array(0x571), 0x542 => array(0x572),
			0x543 => array(0x573), 0x544 => array(0x574), 0x545 => array(0x575),
			0x546 => array(0x576), 0x547 => array(0x577), 0x548 => array(0x578),
			0x549 => array(0x579), 0x54A => array(0x57A), 0x54B => array(0x57B),
			0x54C => array(0x57C), 0x54D => array(0x57D), 0x54E => array(0x57E),
			0x54F => array(0x57F), 0x550 => array(0x580), 0x551 => array(0x581),
			0x552 => array(0x582), 0x553 => array(0x583), 0x554 => array(0x584),
			0x555 => array(0x585), 0x556 => array(0x586), 0x587 => array(0x565, 0x582),
			0xE33 => array(0xE4D, 0xE32), 0x1E00 => array(0x1E01), 0x1E02 => array(0x1E03),
			0x1E04 => array(0x1E05), 0x1E06 => array(0x1E07), 0x1E08 => array(0x1E09),
			0x1E0A => array(0x1E0B), 0x1E0C => array(0x1E0D), 0x1E0E => array(0x1E0F),
			0x1E10 => array(0x1E11), 0x1E12 => array(0x1E13), 0x1E14 => array(0x1E15),
			0x1E16 => array(0x1E17), 0x1E18 => array(0x1E19), 0x1E1A => array(0x1E1B),
			0x1E1C => array(0x1E1D), 0x1E1E => array(0x1E1F), 0x1E20 => array(0x1E21),
			0x1E22 => array(0x1E23), 0x1E24 => array(0x1E25), 0x1E26 => array(0x1E27),
			0x1E28 => array(0x1E29), 0x1E2A => array(0x1E2B), 0x1E2C => array(0x1E2D),
			0x1E2E => array(0x1E2F), 0x1E30 => array(0x1E31), 0x1E32 => array(0x1E33),
			0x1E34 => array(0x1E35), 0x1E36 => array(0x1E37), 0x1E38 => array(0x1E39),
			0x1E3A => array(0x1E3B), 0x1E3C => array(0x1E3D), 0x1E3E => array(0x1E3F),
			0x1E40 => array(0x1E41), 0x1E42 => array(0x1E43), 0x1E44 => array(0x1E45),
			0x1E46 => array(0x1E47), 0x1E48 => array(0x1E49), 0x1E4A => array(0x1E4B),
			0x1E4C => array(0x1E4D), 0x1E4E => array(0x1E4F), 0x1E50 => array(0x1E51),
			0x1E52 => array(0x1E53), 0x1E54 => array(0x1E55), 0x1E56 => array(0x1E57),
			0x1E58 => array(0x1E59), 0x1E5A => array(0x1E5B), 0x1E5C => array(0x1E5D),
			0x1E5E => array(0x1E5F), 0x1E60 => array(0x1E61), 0x1E62 => array(0x1E63),
			0x1E64 => array(0x1E65), 0x1E66 => array(0x1E67), 0x1E68 => array(0x1E69),
			0x1E6A => array(0x1E6B), 0x1E6C => array(0x1E6D), 0x1E6E => array(0x1E6F),
			0x1E70 => array(0x1E71), 0x1E72 => array(0x1E73), 0x1E74 => array(0x1E75),
			0x1E76 => array(0x1E77), 0x1E78 => array(0x1E79), 0x1E7A => array(0x1E7B),
			0x1E7C => array(0x1E7D), 0x1E7E => array(0x1E7F), 0x1E80 => array(0x1E81),
			0x1E82 => array(0x1E83), 0x1E84 => array(0x1E85), 0x1E86 => array(0x1E87),
			0x1E88 => array(0x1E89), 0x1E8A => array(0x1E8B), 0x1E8C => array(0x1E8D),
			0x1E8E => array(0x1E8F), 0x1E90 => array(0x1E91), 0x1E92 => array(0x1E93),
			0x1E94 => array(0x1E95), 0x1E96 => array(0x68, 0x331), 0x1E97 => array(0x74, 0x308),
			0x1E98 => array(0x77, 0x30A), 0x1E99 => array(0x79, 0x30A),
			0x1E9A => array(0x61, 0x2BE), 0x1E9B => array(0x1E61), 0x1EA0 => array(0x1EA1),
			0x1EA2 => array(0x1EA3), 0x1EA4 => array(0x1EA5), 0x1EA6 => array(0x1EA7),
			0x1EA8 => array(0x1EA9), 0x1EAA => array(0x1EAB), 0x1EAC => array(0x1EAD),
			0x1EAE => array(0x1EAF), 0x1EB0 => array(0x1EB1), 0x1EB2 => array(0x1EB3),
			0x1EB4 => array(0x1EB5), 0x1EB6 => array(0x1EB7), 0x1EB8 => array(0x1EB9),
			0x1EBA => array(0x1EBB), 0x1EBC => array(0x1EBD), 0x1EBE => array(0x1EBF),
			0x1EC0 => array(0x1EC1), 0x1EC2 => array(0x1EC3), 0x1EC4 => array(0x1EC5),
			0x1EC6 => array(0x1EC7), 0x1EC8 => array(0x1EC9), 0x1ECA => array(0x1ECB),
			0x1ECC => array(0x1ECD), 0x1ECE => array(0x1ECF), 0x1ED0 => array(0x1ED1),
			0x1ED2 => array(0x1ED3), 0x1ED4 => array(0x1ED5), 0x1ED6 => array(0x1ED7),
			0x1ED8 => array(0x1ED9), 0x1EDA => array(0x1EDB), 0x1EDC => array(0x1EDD),
			0x1EDE => array(0x1EDF), 0x1EE0 => array(0x1EE1), 0x1EE2 => array(0x1EE3),
			0x1EE4 => array(0x1EE5), 0x1EE6 => array(0x1EE7), 0x1EE8 => array(0x1EE9),
			0x1EEA => array(0x1EEB), 0x1EEC => array(0x1EED), 0x1EEE => array(0x1EEF),
			0x1EF0 => array(0x1EF1), 0x1EF2 => array(0x1EF3), 0x1EF4 => array(0x1EF5),
			0x1EF6 => array(0x1EF7), 0x1EF8 => array(0x1EF9), 0x1F08 => array(0x1F00),
			0x1F09 => array(0x1F01), 0x1F0A => array(0x1F02), 0x1F0B => array(0x1F03),
			0x1F0C => array(0x1F04), 0x1F0D => array(0x1F05), 0x1F0E => array(0x1F06),
			0x1F0F => array(0x1F07), 0x1F18 => array(0x1F10), 0x1F19 => array(0x1F11),
			0x1F1A => array(0x1F12), 0x1F1B => array(0x1F13), 0x1F1C => array(0x1F14),
			0x1F1D => array(0x1F15), 0x1F28 => array(0x1F20), 0x1F29 => array(0x1F21),
			0x1F2A => array(0x1F22), 0x1F2B => array(0x1F23), 0x1F2C => array(0x1F24),
			0x1F2D => array(0x1F25), 0x1F2E => array(0x1F26), 0x1F2F => array(0x1F27),
			0x1F38 => array(0x1F30), 0x1F39 => array(0x1F31), 0x1F3A => array(0x1F32),
			0x1F3B => array(0x1F33), 0x1F3C => array(0x1F34), 0x1F3D => array(0x1F35),
			0x1F3E => array(0x1F36), 0x1F3F => array(0x1F37), 0x1F48 => array(0x1F40),
			0x1F49 => array(0x1F41), 0x1F4A => array(0x1F42), 0x1F4B => array(0x1F43),
			0x1F4C => array(0x1F44), 0x1F4D => array(0x1F45), 0x1F50 => array(0x3C5, 0x313),
			0x1F52 => array(0x3C5, 0x313, 0x300), 0x1F54 => array(0x3C5, 0x313, 0x301),
			0x1F56 => array(0x3C5, 0x313, 0x342), 0x1F59 => array(0x1F51), 0x1F5B => array(0x1F53),
			0x1F5D => array(0x1F55), 0x1F5F => array(0x1F57), 0x1F68 => array(0x1F60),
			0x1F69 => array(0x1F61), 0x1F6A => array(0x1F62), 0x1F6B => array(0x1F63),
			0x1F6C => array(0x1F64), 0x1F6D => array(0x1F65), 0x1F6E => array(0x1F66),
			0x1F6F => array(0x1F67), 0x1F80 => array(0x1F00, 0x3B9), 0x1F81 => array(0x1F01, 0x3B9),
			0x1F82 => array(0x1F02, 0x3B9), 0x1F83 => array(0x1F03, 0x3B9),
			0x1F84 => array(0x1F04, 0x3B9), 0x1F85 => array(0x1F05, 0x3B9),
			0x1F86 => array(0x1F06, 0x3B9), 0x1F87 => array(0x1F07, 0x3B9),
			0x1F88 => array(0x1F00, 0x3B9), 0x1F89 => array(0x1F01, 0x3B9),
			0x1F8A => array(0x1F02, 0x3B9), 0x1F8B => array(0x1F03, 0x3B9),
			0x1F8C => array(0x1F04, 0x3B9), 0x1F8D => array(0x1F05, 0x3B9),
			0x1F8E => array(0x1F06, 0x3B9), 0x1F8F => array(0x1F07, 0x3B9),
			0x1F90 => array(0x1F20, 0x3B9), 0x1F91 => array(0x1F21, 0x3B9),
			0x1F92 => array(0x1F22, 0x3B9), 0x1F93 => array(0x1F23, 0x3B9),
			0x1F94 => array(0x1F24, 0x3B9), 0x1F95 => array(0x1F25, 0x3B9),
			0x1F96 => array(0x1F26, 0x3B9), 0x1F97 => array(0x1F27, 0x3B9),
			0x1F98 => array(0x1F20, 0x3B9), 0x1F99 => array(0x1F21, 0x3B9),
			0x1F9A => array(0x1F22, 0x3B9), 0x1F9B => array(0x1F23, 0x3B9),
			0x1F9C => array(0x1F24, 0x3B9), 0x1F9D => array(0x1F25, 0x3B9),
			0x1F9E => array(0x1F26, 0x3B9), 0x1F9F => array(0x1F27, 0x3B9),
			0x1FA0 => array(0x1F60, 0x3B9), 0x1FA1 => array(0x1F61, 0x3B9),
			0x1FA2 => array(0x1F62, 0x3B9), 0x1FA3 => array(0x1F63, 0x3B9),
			0x1FA4 => array(0x1F64, 0x3B9), 0x1FA5 => array(0x1F65, 0x3B9),
			0x1FA6 => array(0x1F66, 0x3B9), 0x1FA7 => array(0x1F67, 0x3B9),
			0x1FA8 => array(0x1F60, 0x3B9), 0x1FA9 => array(0x1F61, 0x3B9),
			0x1FAA => array(0x1F62, 0x3B9), 0x1FAB => array(0x1F63, 0x3B9),
			0x1FAC => array(0x1F64, 0x3B9), 0x1FAD => array(0x1F65, 0x3B9),
			0x1FAE => array(0x1F66, 0x3B9), 0x1FAF => array(0x1F67, 0x3B9),
			0x1FB2 => array(0x1F70, 0x3B9), 0x1FB3 => array(0x3B1, 0x3B9),
			0x1FB4 => array(0x3AC, 0x3B9), 0x1FB6 => array(0x3B1, 0x342),
			0x1FB7 => array(0x3B1, 0x342, 0x3B9), 0x1FB8 => array(0x1FB0),
			0x1FB9 => array(0x1FB1), 0x1FBA => array(0x1F70), 0x1FBB => array(0x1F71),
			0x1FBC => array(0x3B1, 0x3B9), 0x1FBE => array(0x3B9), 0x1FC2 => array(0x1F74, 0x3B9),
			0x1FC3 => array(0x3B7, 0x3B9), 0x1FC4 => array(0x3AE, 0x3B9),
			0x1FC6 => array(0x3B7, 0x342), 0x1FC7 => array(0x3B7, 0x342, 0x3B9),
			0x1FC8 => array(0x1F72), 0x1FC9 => array(0x1F73), 0x1FCA => array(0x1F74),
			0x1FCB => array(0x1F75), 0x1FCC => array(0x3B7, 0x3B9),
			0x1FD2 => array(0x3B9, 0x308, 0x300), 0x1FD3 => array(0x3B9, 0x308, 0x301),
			0x1FD6 => array(0x3B9, 0x342), 0x1FD7 => array(0x3B9, 0x308, 0x342),
			0x1FD8 => array(0x1FD0), 0x1FD9 => array(0x1FD1), 0x1FDA => array(0x1F76),
			0x1FDB => array(0x1F77), 0x1FE2 => array(0x3C5, 0x308, 0x300),
			0x1FE3 => array(0x3C5, 0x308, 0x301), 0x1FE4 => array(0x3C1, 0x313),
			0x1FE6 => array(0x3C5, 0x342), 0x1FE7 => array(0x3C5, 0x308, 0x342),
			0x1FE8 => array(0x1FE0), 0x1FE9 => array(0x1FE1), 0x1FEA => array(0x1F7A),
			0x1FEB => array(0x1F7B), 0x1FEC => array(0x1FE5), 0x1FF2 => array(0x1F7C, 0x3B9),
			0x1FF3 => array(0x3C9, 0x3B9), 0x1FF4 => array(0x3CE, 0x3B9),
			0x1FF6 => array(0x3C9, 0x342), 0x1FF7 => array(0x3C9, 0x342, 0x3B9),
			0x1FF8 => array(0x1F78), 0x1FF9 => array(0x1F79), 0x1FFA => array(0x1F7C),
			0x1FFB => array(0x1F7D), 0x1FFC => array(0x3C9, 0x3B9), 0x20A8 => array(0x72, 0x73),
			0x2102 => array(0x63), 0x2103 => array(0xB0, 0x63), 0x2107 => array(0x25B),
			0x2109 => array(0xB0, 0x66), 0x210B => array(0x68), 0x210C => array(0x68),
			0x210D => array(0x68), 0x2110 => array(0x69), 0x2111 => array(0x69),
			0x2112 => array(0x6C), 0x2115 => array(0x6E), 0x2116 => array(0x6E, 0x6F),
			0x2119 => array(0x70), 0x211A => array(0x71), 0x211B => array(0x72),
			0x211C => array(0x72), 0x211D => array(0x72), 0x2120 => array(0x73, 0x6D),
			0x2121 => array(0x74, 0x65, 0x6C), 0x2122 => array(0x74, 0x6D), 0x2124 => array(0x7A),
			0x2126 => array(0x3C9), 0x2128 => array(0x7A), 0x212A => array(0x6B),
			0x212B => array(0xE5), 0x212C => array(0x62), 0x212D => array(0x63),
			0x2130 => array(0x65), 0x2131 => array(0x66), 0x2133 => array(0x6D),
			0x213E => array(0x3B3), 0x213F => array(0x3C0), 0x2145 => array(0x64),
			0x2160 => array(0x2170), 0x2161 => array(0x2171), 0x2162 => array(0x2172),
			0x2163 => array(0x2173), 0x2164 => array(0x2174), 0x2165 => array(0x2175),
			0x2166 => array(0x2176), 0x2167 => array(0x2177), 0x2168 => array(0x2178),
			0x2169 => array(0x2179), 0x216A => array(0x217A), 0x216B => array(0x217B),
			0x216C => array(0x217C), 0x216D => array(0x217D), 0x216E => array(0x217E),
			0x216F => array(0x217F), 0x24B6 => array(0x24D0), 0x24B7 => array(0x24D1),
			0x24B8 => array(0x24D2), 0x24B9 => array(0x24D3), 0x24BA => array(0x24D4),
			0x24BB => array(0x24D5), 0x24BC => array(0x24D6), 0x24BD => array(0x24D7),
			0x24BE => array(0x24D8), 0x24BF => array(0x24D9), 0x24C0 => array(0x24DA),
			0x24C1 => array(0x24DB), 0x24C2 => array(0x24DC), 0x24C3 => array(0x24DD),
			0x24C4 => array(0x24DE), 0x24C5 => array(0x24DF), 0x24C6 => array(0x24E0),
			0x24C7 => array(0x24E1), 0x24C8 => array(0x24E2), 0x24C9 => array(0x24E3),
			0x24CA => array(0x24E4), 0x24CB => array(0x24E5), 0x24CC => array(0x24E6),
			0x24CD => array(0x24E7), 0x24CE => array(0x24E8), 0x24CF => array(0x24E9),
			0x3371 => array(0x68, 0x70, 0x61), 0x3373 => array(0x61, 0x75),
			0x3375 => array(0x6F, 0x76), 0x3380 => array(0x70, 0x61), 0x3381 => array(0x6E, 0x61),
			0x3382 => array(0x3BC, 0x61), 0x3383 => array(0x6D, 0x61), 0x3384 => array(0x6B, 0x61),
			0x3385 => array(0x6B, 0x62), 0x3386 => array(0x6D, 0x62), 0x3387 => array(0x67, 0x62),
			0x338A => array(0x70, 0x66), 0x338B => array(0x6E, 0x66), 0x338C => array(0x3BC, 0x66),
			0x3390 => array(0x68, 0x7A), 0x3391 => array(0x6B, 0x68, 0x7A),
			0x3392 => array(0x6D, 0x68, 0x7A), 0x3393 => array(0x67, 0x68, 0x7A),
			0x3394 => array(0x74, 0x68, 0x7A), 0x33A9 => array(0x70, 0x61),
			0x33AA => array(0x6B, 0x70, 0x61), 0x33AB => array(0x6D, 0x70, 0x61),
			0x33AC => array(0x67, 0x70, 0x61), 0x33B4 => array(0x70, 0x76),
			0x33B5 => array(0x6E, 0x76), 0x33B6 => array(0x3BC, 0x76), 0x33B7 => array(0x6D, 0x76),
			0x33B8 => array(0x6B, 0x76), 0x33B9 => array(0x6D, 0x76), 0x33BA => array(0x70, 0x77),
			0x33BB => array(0x6E, 0x77), 0x33BC => array(0x3BC, 0x77), 0x33BD => array(0x6D, 0x77),
			0x33BE => array(0x6B, 0x77), 0x33BF => array(0x6D, 0x77), 0x33C0 => array(0x6B, 0x3C9),
			0x33C1 => array(0x6D, 0x3C9), 0x33C3 => array(0x62, 0x71),
			0x33C6 => array(0x63, 0x2215, 0x6B, 0x67), 0x33C7 => array(0x63, 0x6F, 0x2E),
			0x33C8 => array(0x64, 0x62), 0x33C9 => array(0x67, 0x79), 0x33CB => array(0x68, 0x70),
			0x33CD => array(0x6B, 0x6B), 0x33CE => array(0x6B, 0x6D), 0x33D7 => array(0x70, 0x68),
			0x33D9 => array(0x70, 0x70, 0x6D), 0x33DA => array(0x70, 0x72),
			0x33DC => array(0x73, 0x76), 0x33DD => array(0x77, 0x62), 0xFB00 => array(0x66, 0x66),
			0xFB01 => array(0x66, 0x69), 0xFB02 => array(0x66, 0x6C),
			0xFB03 => array(0x66, 0x66, 0x69), 0xFB04 => array(0x66, 0x66, 0x6C),
			0xFB05 => array(0x73, 0x74), 0xFB06 => array(0x73, 0x74), 0xFB13 => array(0x574, 0x576),
			0xFB14 => array(0x574, 0x565), 0xFB15 => array(0x574, 0x56B),
			0xFB16 => array(0x57E, 0x576), 0xFB17 => array(0x574, 0x56D), 0xFF21 => array(0xFF41),
			0xFF22 => array(0xFF42), 0xFF23 => array(0xFF43), 0xFF24 => array(0xFF44),
			0xFF25 => array(0xFF45), 0xFF26 => array(0xFF46), 0xFF27 => array(0xFF47),
			0xFF28 => array(0xFF48), 0xFF29 => array(0xFF49), 0xFF2A => array(0xFF4A),
			0xFF2B => array(0xFF4B), 0xFF2C => array(0xFF4C), 0xFF2D => array(0xFF4D),
			0xFF2E => array(0xFF4E), 0xFF2F => array(0xFF4F), 0xFF30 => array(0xFF50),
			0xFF31 => array(0xFF51), 0xFF32 => array(0xFF52), 0xFF33 => array(0xFF53),
			0xFF34 => array(0xFF54), 0xFF35 => array(0xFF55), 0xFF36 => array(0xFF56),
			0xFF37 => array(0xFF57), 0xFF38 => array(0xFF58), 0xFF39 => array(0xFF59),
			0xFF3A => array(0xFF5A), 0x10400 => array(0x10428), 0x10401 => array(0x10429),
			0x10402 => array(0x1042A), 0x10403 => array(0x1042B), 0x10404 => array(0x1042C),
			0x10405 => array(0x1042D), 0x10406 => array(0x1042E), 0x10407 => array(0x1042F),
			0x10408 => array(0x10430), 0x10409 => array(0x10431), 0x1040A => array(0x10432),
			0x1040B => array(0x10433), 0x1040C => array(0x10434), 0x1040D => array(0x10435),
			0x1040E => array(0x10436), 0x1040F => array(0x10437), 0x10410 => array(0x10438),
			0x10411 => array(0x10439), 0x10412 => array(0x1043A), 0x10413 => array(0x1043B),
			0x10414 => array(0x1043C), 0x10415 => array(0x1043D), 0x10416 => array(0x1043E),
			0x10417 => array(0x1043F), 0x10418 => array(0x10440), 0x10419 => array(0x10441),
			0x1041A => array(0x10442), 0x1041B => array(0x10443), 0x1041C => array(0x10444),
			0x1041D => array(0x10445), 0x1041E => array(0x10446), 0x1041F => array(0x10447),
			0x10420 => array(0x10448), 0x10421 => array(0x10449), 0x10422 => array(0x1044A),
			0x10423 => array(0x1044B), 0x10424 => array(0x1044C), 0x10425 => array(0x1044D),
			0x1D400 => array(0x61), 0x1D401 => array(0x62), 0x1D402 => array(0x63),
			0x1D403 => array(0x64), 0x1D404 => array(0x65), 0x1D405 => array(0x66),
			0x1D406 => array(0x67), 0x1D407 => array(0x68), 0x1D408 => array(0x69),
			0x1D409 => array(0x6A), 0x1D40A => array(0x6B), 0x1D40B => array(0x6C),
			0x1D40C => array(0x6D), 0x1D40D => array(0x6E), 0x1D40E => array(0x6F),
			0x1D40F => array(0x70), 0x1D410 => array(0x71), 0x1D411 => array(0x72),
			0x1D412 => array(0x73), 0x1D413 => array(0x74), 0x1D414 => array(0x75),
			0x1D415 => array(0x76), 0x1D416 => array(0x77), 0x1D417 => array(0x78),
			0x1D418 => array(0x79), 0x1D419 => array(0x7A), 0x1D434 => array(0x61),
			0x1D435 => array(0x62), 0x1D436 => array(0x63), 0x1D437 => array(0x64),
			0x1D438 => array(0x65), 0x1D439 => array(0x66), 0x1D43A => array(0x67),
			0x1D43B => array(0x68), 0x1D43C => array(0x69), 0x1D43D => array(0x6A),
			0x1D43E => array(0x6B), 0x1D43F => array(0x6C), 0x1D440 => array(0x6D),
			0x1D441 => array(0x6E), 0x1D442 => array(0x6F), 0x1D443 => array(0x70),
			0x1D444 => array(0x71), 0x1D445 => array(0x72), 0x1D446 => array(0x73),
			0x1D447 => array(0x74), 0x1D448 => array(0x75), 0x1D449 => array(0x76),
			0x1D44A => array(0x77), 0x1D44B => array(0x78), 0x1D44C => array(0x79),
			0x1D44D => array(0x7A), 0x1D468 => array(0x61), 0x1D469 => array(0x62),
			0x1D46A => array(0x63), 0x1D46B => array(0x64), 0x1D46C => array(0x65),
			0x1D46D => array(0x66), 0x1D46E => array(0x67), 0x1D46F => array(0x68),
			0x1D470 => array(0x69), 0x1D471 => array(0x6A), 0x1D472 => array(0x6B),
			0x1D473 => array(0x6C), 0x1D474 => array(0x6D), 0x1D475 => array(0x6E),
			0x1D476 => array(0x6F), 0x1D477 => array(0x70), 0x1D478 => array(0x71),
			0x1D479 => array(0x72), 0x1D47A => array(0x73), 0x1D47B => array(0x74),
			0x1D47C => array(0x75), 0x1D47D => array(0x76), 0x1D47E => array(0x77),
			0x1D47F => array(0x78), 0x1D480 => array(0x79), 0x1D481 => array(0x7A),
			0x1D49C => array(0x61), 0x1D49E => array(0x63), 0x1D49F => array(0x64),
			0x1D4A2 => array(0x67), 0x1D4A5 => array(0x6A), 0x1D4A6 => array(0x6B),
			0x1D4A9 => array(0x6E), 0x1D4AA => array(0x6F), 0x1D4AB => array(0x70),
			0x1D4AC => array(0x71), 0x1D4AE => array(0x73), 0x1D4AF => array(0x74),
			0x1D4B0 => array(0x75), 0x1D4B1 => array(0x76), 0x1D4B2 => array(0x77),
			0x1D4B3 => array(0x78), 0x1D4B4 => array(0x79), 0x1D4B5 => array(0x7A),
			0x1D4D0 => array(0x61), 0x1D4D1 => array(0x62), 0x1D4D2 => array(0x63),
			0x1D4D3 => array(0x64), 0x1D4D4 => array(0x65), 0x1D4D5 => array(0x66),
			0x1D4D6 => array(0x67), 0x1D4D7 => array(0x68), 0x1D4D8 => array(0x69),
			0x1D4D9 => array(0x6A), 0x1D4DA => array(0x6B), 0x1D4DB => array(0x6C),
			0x1D4DC => array(0x6D), 0x1D4DD => array(0x6E), 0x1D4DE => array(0x6F),
			0x1D4DF => array(0x70), 0x1D4E0 => array(0x71), 0x1D4E1 => array(0x72),
			0x1D4E2 => array(0x73), 0x1D4E3 => array(0x74), 0x1D4E4 => array(0x75),
			0x1D4E5 => array(0x76), 0x1D4E6 => array(0x77), 0x1D4E7 => array(0x78),
			0x1D4E8 => array(0x79), 0x1D4E9 => array(0x7A), 0x1D504 => array(0x61),
			0x1D505 => array(0x62), 0x1D507 => array(0x64), 0x1D508 => array(0x65),
			0x1D509 => array(0x66), 0x1D50A => array(0x67), 0x1D50D => array(0x6A),
			0x1D50E => array(0x6B), 0x1D50F => array(0x6C), 0x1D510 => array(0x6D),
			0x1D511 => array(0x6E), 0x1D512 => array(0x6F), 0x1D513 => array(0x70),
			0x1D514 => array(0x71), 0x1D516 => array(0x73), 0x1D517 => array(0x74),
			0x1D518 => array(0x75), 0x1D519 => array(0x76), 0x1D51A => array(0x77),
			0x1D51B => array(0x78), 0x1D51C => array(0x79), 0x1D538 => array(0x61),
			0x1D539 => array(0x62), 0x1D53B => array(0x64), 0x1D53C => array(0x65),
			0x1D53D => array(0x66), 0x1D53E => array(0x67), 0x1D540 => array(0x69),
			0x1D541 => array(0x6A), 0x1D542 => array(0x6B), 0x1D543 => array(0x6C),
			0x1D544 => array(0x6D), 0x1D546 => array(0x6F), 0x1D54A => array(0x73),
			0x1D54B => array(0x74), 0x1D54C => array(0x75), 0x1D54D => array(0x76),
			0x1D54E => array(0x77), 0x1D54F => array(0x78), 0x1D550 => array(0x79),
			0x1D56C => array(0x61), 0x1D56D => array(0x62), 0x1D56E => array(0x63),
			0x1D56F => array(0x64), 0x1D570 => array(0x65), 0x1D571 => array(0x66),
			0x1D572 => array(0x67), 0x1D573 => array(0x68), 0x1D574 => array(0x69),
			0x1D575 => array(0x6A), 0x1D576 => array(0x6B), 0x1D577 => array(0x6C),
			0x1D578 => array(0x6D), 0x1D579 => array(0x6E), 0x1D57A => array(0x6F),
			0x1D57B => array(0x70), 0x1D57C => array(0x71), 0x1D57D => array(0x72),
			0x1D57E => array(0x73), 0x1D57F => array(0x74), 0x1D580 => array(0x75),
			0x1D581 => array(0x76), 0x1D582 => array(0x77), 0x1D583 => array(0x78),
			0x1D584 => array(0x79), 0x1D585 => array(0x7A), 0x1D5A0 => array(0x61),
			0x1D5A1 => array(0x62), 0x1D5A2 => array(0x63), 0x1D5A3 => array(0x64),
			0x1D5A4 => array(0x65), 0x1D5A5 => array(0x66), 0x1D5A6 => array(0x67),
			0x1D5A7 => array(0x68), 0x1D5A8 => array(0x69), 0x1D5A9 => array(0x6A),
			0x1D5AA => array(0x6B), 0x1D5AB => array(0x6C), 0x1D5AC => array(0x6D),
			0x1D5AD => array(0x6E), 0x1D5AE => array(0x6F), 0x1D5AF => array(0x70),
			0x1D5B0 => array(0x71), 0x1D5B1 => array(0x72), 0x1D5B2 => array(0x73),
			0x1D5B3 => array(0x74), 0x1D5B4 => array(0x75), 0x1D5B5 => array(0x76),
			0x1D5B6 => array(0x77), 0x1D5B7 => array(0x78), 0x1D5B8 => array(0x79),
			0x1D5B9 => array(0x7A), 0x1D5D4 => array(0x61), 0x1D5D5 => array(0x62),
			0x1D5D6 => array(0x63), 0x1D5D7 => array(0x64), 0x1D5D8 => array(0x65),
			0x1D5D9 => array(0x66), 0x1D5DA => array(0x67), 0x1D5DB => array(0x68),
			0x1D5DC => array(0x69), 0x1D5DD => array(0x6A), 0x1D5DE => array(0x6B),
			0x1D5DF => array(0x6C), 0x1D5E0 => array(0x6D), 0x1D5E1 => array(0x6E),
			0x1D5E2 => array(0x6F), 0x1D5E3 => array(0x70), 0x1D5E4 => array(0x71),
			0x1D5E5 => array(0x72), 0x1D5E6 => array(0x73), 0x1D5E7 => array(0x74),
			0x1D5E8 => array(0x75), 0x1D5E9 => array(0x76), 0x1D5EA => array(0x77),
			0x1D5EB => array(0x78), 0x1D5EC => array(0x79), 0x1D5ED => array(0x7A),
			0x1D608 => array(0x61), 0x1D609 => array(0x62) ,0x1D60A => array(0x63),
			0x1D60B => array(0x64), 0x1D60C => array(0x65), 0x1D60D => array(0x66),
			0x1D60E => array(0x67), 0x1D60F => array(0x68), 0x1D610 => array(0x69),
			0x1D611 => array(0x6A), 0x1D612 => array(0x6B), 0x1D613 => array(0x6C),
			0x1D614 => array(0x6D), 0x1D615 => array(0x6E), 0x1D616 => array(0x6F),
			0x1D617 => array(0x70), 0x1D618 => array(0x71), 0x1D619 => array(0x72),
			0x1D61A => array(0x73), 0x1D61B => array(0x74), 0x1D61C => array(0x75),
			0x1D61D => array(0x76), 0x1D61E => array(0x77), 0x1D61F => array(0x78),
			0x1D620 => array(0x79), 0x1D621 => array(0x7A), 0x1D63C => array(0x61),
			0x1D63D => array(0x62), 0x1D63E => array(0x63), 0x1D63F => array(0x64),
			0x1D640 => array(0x65), 0x1D641 => array(0x66), 0x1D642 => array(0x67),
			0x1D643 => array(0x68), 0x1D644 => array(0x69), 0x1D645 => array(0x6A),
			0x1D646 => array(0x6B), 0x1D647 => array(0x6C), 0x1D648 => array(0x6D),
			0x1D649 => array(0x6E), 0x1D64A => array(0x6F), 0x1D64B => array(0x70),
			0x1D64C => array(0x71), 0x1D64D => array(0x72), 0x1D64E => array(0x73),
			0x1D64F => array(0x74), 0x1D650 => array(0x75), 0x1D651 => array(0x76),
			0x1D652 => array(0x77), 0x1D653 => array(0x78), 0x1D654 => array(0x79),
			0x1D655 => array(0x7A), 0x1D670 => array(0x61), 0x1D671 => array(0x62),
			0x1D672 => array(0x63), 0x1D673 => array(0x64), 0x1D674 => array(0x65),
			0x1D675 => array(0x66), 0x1D676 => array(0x67), 0x1D677 => array(0x68),
			0x1D678 => array(0x69), 0x1D679 => array(0x6A), 0x1D67A => array(0x6B),
			0x1D67B => array(0x6C), 0x1D67C => array(0x6D), 0x1D67D => array(0x6E),
			0x1D67E => array(0x6F), 0x1D67F => array(0x70), 0x1D680 => array(0x71),
			0x1D681 => array(0x72), 0x1D682 => array(0x73), 0x1D683 => array(0x74),
			0x1D684 => array(0x75), 0x1D685 => array(0x76), 0x1D686 => array(0x77),
			0x1D687 => array(0x78), 0x1D688 => array(0x79), 0x1D689 => array(0x7A),
			0x1D6A8 => array(0x3B1), 0x1D6A9 => array(0x3B2), 0x1D6AA => array(0x3B3),
			0x1D6AB => array(0x3B4), 0x1D6AC => array(0x3B5), 0x1D6AD => array(0x3B6),
			0x1D6AE => array(0x3B7), 0x1D6AF => array(0x3B8), 0x1D6B0 => array(0x3B9),
			0x1D6B1 => array(0x3BA), 0x1D6B2 => array(0x3BB), 0x1D6B3 => array(0x3BC),
			0x1D6B4 => array(0x3BD), 0x1D6B5 => array(0x3BE), 0x1D6B6 => array(0x3BF),
			0x1D6B7 => array(0x3C0), 0x1D6B8 => array(0x3C1), 0x1D6B9 => array(0x3B8),
			0x1D6BA => array(0x3C3), 0x1D6BB => array(0x3C4), 0x1D6BC => array(0x3C5),
			0x1D6BD => array(0x3C6), 0x1D6BE => array(0x3C7), 0x1D6BF => array(0x3C8),
			0x1D6C0 => array(0x3C9), 0x1D6D3 => array(0x3C3), 0x1D6E2 => array(0x3B1),
			0x1D6E3 => array(0x3B2), 0x1D6E4 => array(0x3B3), 0x1D6E5 => array(0x3B4),
			0x1D6E6 => array(0x3B5), 0x1D6E7 => array(0x3B6), 0x1D6E8 => array(0x3B7),
			0x1D6E9 => array(0x3B8), 0x1D6EA => array(0x3B9), 0x1D6EB => array(0x3BA),
			0x1D6EC => array(0x3BB), 0x1D6ED => array(0x3BC), 0x1D6EE => array(0x3BD),
			0x1D6EF => array(0x3BE), 0x1D6F0 => array(0x3BF), 0x1D6F1 => array(0x3C0),
			0x1D6F2 => array(0x3C1), 0x1D6F3 => array(0x3B8) ,0x1D6F4 => array(0x3C3),
			0x1D6F5 => array(0x3C4), 0x1D6F6 => array(0x3C5), 0x1D6F7 => array(0x3C6),
			0x1D6F8 => array(0x3C7), 0x1D6F9 => array(0x3C8) ,0x1D6FA => array(0x3C9),
			0x1D70D => array(0x3C3), 0x1D71C => array(0x3B1), 0x1D71D => array(0x3B2),
			0x1D71E => array(0x3B3), 0x1D71F => array(0x3B4), 0x1D720 => array(0x3B5),
			0x1D721 => array(0x3B6), 0x1D722 => array(0x3B7), 0x1D723 => array(0x3B8),
			0x1D724 => array(0x3B9), 0x1D725 => array(0x3BA), 0x1D726 => array(0x3BB),
			0x1D727 => array(0x3BC), 0x1D728 => array(0x3BD), 0x1D729 => array(0x3BE),
			0x1D72A => array(0x3BF), 0x1D72B => array(0x3C0), 0x1D72C => array(0x3C1),
			0x1D72D => array(0x3B8), 0x1D72E => array(0x3C3), 0x1D72F => array(0x3C4),
			0x1D730 => array(0x3C5), 0x1D731 => array(0x3C6), 0x1D732 => array(0x3C7),
			0x1D733 => array(0x3C8), 0x1D734 => array(0x3C9), 0x1D747 => array(0x3C3),
			0x1D756 => array(0x3B1), 0x1D757 => array(0x3B2), 0x1D758 => array(0x3B3),
			0x1D759 => array(0x3B4), 0x1D75A => array(0x3B5), 0x1D75B => array(0x3B6),
			0x1D75C => array(0x3B7), 0x1D75D => array(0x3B8), 0x1D75E => array(0x3B9),
			0x1D75F => array(0x3BA), 0x1D760 => array(0x3BB), 0x1D761 => array(0x3BC),
			0x1D762 => array(0x3BD), 0x1D763 => array(0x3BE), 0x1D764 => array(0x3BF),
			0x1D765 => array(0x3C0), 0x1D766 => array(0x3C1), 0x1D767 => array(0x3B8),
			0x1D768 => array(0x3C3), 0x1D769 => array(0x3C4), 0x1D76A => array(0x3C5),
			0x1D76B => array(0x3C6), 0x1D76C => array(0x3C7), 0x1D76D => array(0x3C8),
			0x1D76E => array(0x3C9), 0x1D781 => array(0x3C3), 0x1D790 => array(0x3B1),
			0x1D791 => array(0x3B2), 0x1D792 => array(0x3B3), 0x1D793 => array(0x3B4),
			0x1D794 => array(0x3B5), 0x1D795 => array(0x3B6), 0x1D796 => array(0x3B7),
			0x1D797 => array(0x3B8), 0x1D798 => array(0x3B9), 0x1D799 => array(0x3BA),
			0x1D79A => array(0x3BB), 0x1D79B => array(0x3BC), 0x1D79C => array(0x3BD),
			0x1D79D => array(0x3BE), 0x1D79E => array(0x3BF), 0x1D79F => array(0x3C0),
			0x1D7A0 => array(0x3C1), 0x1D7A1 => array(0x3B8), 0x1D7A2 => array(0x3C3),
			0x1D7A3 => array(0x3C4), 0x1D7A4 => array(0x3C5), 0x1D7A5 => array(0x3C6),
			0x1D7A6 => array(0x3C7), 0x1D7A7 => array(0x3C8), 0x1D7A8 => array(0x3C9),
			0x1D7BB => array(0x3C3), 0x3F9 => array(0x3C3), 0x1D2C => array(0x61),
			0x1D2D => array(0xE6), 0x1D2E => array(0x62), 0x1D30 => array(0x64),
			0x1D31 => array(0x65), 0x1D32 => array(0x1DD), 0x1D33 => array(0x67),
			0x1D34 => array(0x68), 0x1D35 => array(0x69), 0x1D36 => array(0x6A),
			0x1D37 => array(0x6B), 0x1D38 => array(0x6C), 0x1D39 => array(0x6D),
			0x1D3A => array(0x6E), 0x1D3C => array(0x6F), 0x1D3D => array(0x223),
			0x1D3E => array(0x70), 0x1D3F => array(0x72), 0x1D40 => array(0x74),
			0x1D41 => array(0x75), 0x1D42 => array(0x77), 0x213B => array(0x66, 0x61, 0x78),
			0x3250 => array(0x70, 0x74, 0x65), 0x32CC => array(0x68, 0x67),
			0x32CE => array(0x65, 0x76), 0x32CF => array(0x6C, 0x74, 0x64),
			0x337A => array(0x69, 0x75), 0x33DE => array(0x76, 0x2215, 0x6D),
			0x33DF => array(0x61, 0x2215, 0x6D)
		),
		'CombiningClass' => array(
			0x334 => 1, 0x335 => 1, 0x336 => 1, 0x337 => 1, 0x338 => 1, 0x93C => 7, 0x9BC => 7,
			0xA3C => 7, 0xABC => 7, 0xB3C => 7, 0xCBC => 7, 0x1037 => 7, 0x3099 => 8,
			0x309A => 8, 0x94D => 9, 0x9CD => 9, 0xA4D => 9, 0xACD => 9, 0xB4D => 9, 0xBCD => 9,
			0xC4D => 9, 0xCCD => 9, 0xD4D => 9, 0xDCA => 9, 0xE3A => 9, 0xF84 => 9, 0x1039 => 9,
			0x1714 => 9, 0x1734 => 9, 0x17D2 => 9, 0x5B0 => 10, 0x5B1 => 11, 0x5B2 => 12,
			0x5B3 => 13, 0x5B4 => 14, 0x5B5 => 15, 0x5B6 => 16, 0x5B7 => 17, 0x5B8 => 18,
			0x5B9 => 19, 0x5BB => 20, 0x5Bc => 21, 0x5BD => 22, 0x5BF => 23, 0x5C1 => 24,
			0x5C2 => 25, 0xFB1E => 26, 0x64B => 27, 0x64C => 28, 0x64D => 29, 0x64E => 30,
			0x64F => 31, 0x650 => 32, 0x651 => 33, 0x652 => 34, 0x670 => 35, 0x711 => 36,
			0xC55 => 84, 0xC56 => 91, 0xE38 => 103, 0xE39 => 103, 0xE48 => 107, 0xE49 => 107,
			0xE4A => 107, 0xE4B => 107, 0xEB8 => 118, 0xEB9 => 118, 0xEC8 => 122, 0xEC9 => 122,
			0xECA => 122, 0xECB => 122, 0xF71 => 129, 0xF72 => 130, 0xF7A => 130, 0xF7B => 130,
			0xF7C => 130, 0xF7D => 130, 0xF80 => 130, 0xF74 => 132, 0x321 => 202, 0x322 => 202,
			0x327 => 202, 0x328 => 202, 0x31B => 216, 0xF39 => 216, 0x1D165 => 216, 0x1D166 => 216,
			0x1D16E => 216, 0x1D16F => 216, 0x1D170 => 216, 0x1D171 => 216, 0x1D172 => 216,
			0x302A => 218, 0x316 => 220, 0x317 => 220, 0x318 => 220, 0x319 => 220, 0x31C => 220,
			0x31D => 220, 0x31E => 220, 0x31F => 220, 0x320 => 220, 0x323 => 220, 0x324 => 220,
			0x325 => 220, 0x326 => 220, 0x329 => 220, 0x32A => 220, 0x32B => 220, 0x32C => 220,
			0x32D => 220, 0x32E => 220, 0x32F => 220, 0x330 => 220, 0x331 => 220, 0x332 => 220,
			0x333 => 220, 0x339 => 220, 0x33A => 220, 0x33B => 220, 0x33C => 220, 0x347 => 220,
			0x348 => 220, 0x349 => 220, 0x34D => 220, 0x34E => 220, 0x353 => 220, 0x354 => 220,
			0x355 => 220, 0x356 => 220, 0x591 => 220, 0x596 => 220, 0x59B => 220, 0x5A3 => 220,
			0x5A4 => 220, 0x5A5 => 220, 0x5A6 => 220, 0x5A7 => 220, 0x5AA => 220, 0x655 => 220,
			0x656 => 220, 0x6E3 => 220, 0x6EA => 220, 0x6ED => 220, 0x731 => 220, 0x734 => 220,
			0x737 => 220, 0x738 => 220, 0x739 => 220, 0x73B => 220, 0x73C => 220, 0x73E => 220,
			0x742 => 220, 0x744 => 220, 0x746 => 220, 0x748 => 220, 0x952 => 220, 0xF18 => 220,
			0xF19 => 220, 0xF35 => 220, 0xF37 => 220, 0xFC6 => 220, 0x193B => 220, 0x20E8 => 220,
			0x1D17B => 220, 0x1D17C => 220, 0x1D17D => 220, 0x1D17E => 220, 0x1D17F => 220,
			0x1D180 => 220, 0x1D181 => 220, 0x1D182 => 220, 0x1D18A => 220, 0x1D18B => 220,
			0x59A => 222, 0x5AD => 222, 0x1929 => 222, 0x302D => 222, 0x302E => 224, 0x302F => 224,
			0x1D16D => 226, 0x5AE => 228, 0x18A9 => 228, 0x302B => 228, 0x300 => 230, 0x301 => 230,
			0x302 => 230, 0x303 => 230, 0x304 => 230, 0x305 => 230, 0x306 => 230, 0x307 => 230,
			0x308 => 230, 0x309 => 230, 0x30A => 230, 0x30B => 230, 0x30C => 230, 0x30D => 230,
			0x30E => 230, 0x30F => 230, 0x310 => 230, 0x311 => 230, 0x312 => 230, 0x313 => 230,
			0x314 => 230, 0x33D => 230, 0x33E => 230, 0x33F => 230, 0x340 => 230, 0x341 => 230,
			0x342 => 230, 0x343 => 230, 0x344 => 230, 0x346 => 230, 0x34A => 230, 0x34B => 230,
			0x34C => 230, 0x350 => 230, 0x351 => 230, 0x352 => 230, 0x357 => 230, 0x363 => 230,
			0x364 => 230, 0x365 => 230, 0x366 => 230, 0x367 => 230, 0x368 => 230, 0x369 => 230,
			0x36A => 230, 0x36B => 230, 0x36C => 230, 0x36D => 230, 0x36E => 230, 0x36F => 230,
			0x483 => 230, 0x484 => 230, 0x485 => 230, 0x486 => 230, 0x592 => 230, 0x593 => 230,
			0x594 => 230, 0x595 => 230, 0x597 => 230, 0x598 => 230, 0x599 => 230, 0x59C => 230,
			0x59D => 230, 0x59E => 230, 0x59F => 230, 0x5A0 => 230, 0x5A1 => 230, 0x5A8 => 230,
			0x5A9 => 230, 0x5AB => 230, 0x5AC => 230, 0x5AF => 230, 0x5C4 => 230, 0x610 => 230,
			0x611 => 230, 0x612 => 230, 0x613 => 230, 0x614 => 230, 0x615 => 230, 0x653 => 230,
			0x654 => 230, 0x657 => 230, 0x658 => 230, 0x6D6 => 230, 0x6D7 => 230, 0x6D8 => 230,
			0x6D9 => 230, 0x6DA => 230, 0x6DB => 230, 0x6DC => 230, 0x6DF => 230, 0x6E0 => 230,
			0x6E1 => 230, 0x6E2 => 230, 0x6E4 => 230, 0x6E7 => 230, 0x6E8 => 230, 0x6EB => 230,
			0x6EC => 230, 0x730 => 230, 0x732 => 230, 0x733 => 230, 0x735 => 230, 0x736 => 230,
			0x73A => 230, 0x73D => 230, 0x73F => 230, 0x740 => 230, 0x741 => 230, 0x743 => 230,
			0x745 => 230, 0x747 => 230, 0x749 => 230, 0x74A => 230, 0x951 => 230, 0x953 => 230,
			0x954 => 230, 0xF82 => 230, 0xF83 => 230, 0xF86 => 230, 0xF87 => 230, 0x170D => 230,
			0x193A => 230, 0x20D0 => 230, 0x20D1 => 230, 0x20D4 => 230, 0x20D5 => 230,
			0x20D6 => 230, 0x20D7 => 230, 0x20DB => 230, 0x20DC => 230, 0x20E1 => 230,
			0x20E7 => 230, 0x20E9 => 230, 0xFE20 => 230, 0xFE21 => 230, 0xFE22 => 230,
			0xFE23 => 230, 0x1D185 => 230, 0x1D186 => 230, 0x1D187 => 230, 0x1D189 => 230,
			0x1D188 => 230, 0x1D1AA => 230, 0x1D1AB => 230, 0x1D1AC => 230, 0x1D1AD => 230,
			0x315 => 232, 0x31A => 232, 0x302C => 232, 0x35F => 233, 0x362 => 233, 0x35D => 234,
			0x35E => 234, 0x360 => 234, 0x361 => 234, 0x345 => 240
		)
	);
}

class CUtil
{
	public static function BinStrlen($buf)
	{
		return (function_exists('mb_strlen') ? mb_strlen($buf, 'latin1') : strlen($buf));
	}

	public static function BinSubstr($buf, $start)
	{
		$length = (func_num_args() > 2 ? func_get_arg(2) : self::BinStrlen($buf));
		return (function_exists('mb_substr') ? mb_substr($buf, $start, $length, 'latin1') : substr($buf, $start, $length));
	}

	public static function BinStrpos($haystack, $needle, $offset = 0)
	{
		if (defined("BX_UTF")) {
			if (function_exists('mb_orig_strpos')) {
				return mb_orig_strpos($haystack, $needle, $offset);
			}
			return mb_strpos($haystack, $needle, $offset, 'latin1');
		}
		return strpos($haystack, $needle, $offset);
	}
}

class UtfSafeString
{
	public static function getLastPosition($haystack, $needle)
	{
		if (ApplicationIsUtfMode()) {
			//mb_strrpos does not work on invalid UTF-8 strings
			$ln = strlen($needle);
			for ($i = strlen($haystack) - $ln; $i >= 0; $i--) {
				if (substr($haystack, $i, $ln) == $needle) {
					return $i;
				}
			}
			return false;
		}

		return strrpos($haystack, $needle);
	}

	public static function rtrimInvalidUtf($string)
	{
		$last4bytes = CUtil::binsubstr($string, -3);
		$reversed = array_reverse(unpack("C*", $last4bytes));
		if (($reversed[0] & 0x80) === 0x00) //ASCII
		{
			return $string;
		} elseif (($reversed[0] & 0xC0) === 0xC0) //Start of utf seq (cut it!)
		{
			return CUtil::binsubstr($string, 0, -1);
		} elseif (($reversed[1] & 0xE0) === 0xE0) //Start of utf seq (longer than 2 bytes)
		{
			return CUtil::binsubstr($string, 0, -2);
		} elseif (($reversed[2] & 0xE0) === 0xF0) //Start of utf seq (longer than 3 bytes)
		{
			return CUtil::binsubstr($string, 0, -3);
		}
		return $string;
	}

	public static function escapeInvalidUtf($string)
	{
		$escape = function ($matches) {
			return (isset($matches[2]) ? '?' : $matches[1]);
		};

		return preg_replace_callback(
			'/([\x00-\x7F]+
			|[\xC2-\xDF][\x80-\xBF]
			|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF])
			|([\x80-\xFF])/x',
			$escape,
			$string
		);
	}

	public static function pad($string, $padLen, $padStr = ' ', $padType = STR_PAD_RIGHT)
	{
		$strLength = strlen($string);
		$padStrLength = strlen($padStr);
		if (!$strLength && ($padType == STR_PAD_RIGHT || $padType == STR_PAD_LEFT)) {
			$strLength = 1; // @debug
		}
		if (!$padLen || !$padStrLength || $padLen <= $strLength) {
			return $string;
		}

		$result = null;
		$repeat = ceil(($padLen - $strLength) / $padStrLength);
		if ($padType == STR_PAD_RIGHT) {
			$result = $string . str_repeat($padStr, $repeat);
			$result = substr($result, 0, $padLen);
		} else {
			if ($padType == STR_PAD_LEFT) {
				$result = str_repeat($padStr, $repeat) . $string;
				$result = substr($result, -$padLen);
			} else {
				if ($padType == STR_PAD_BOTH) {
					$length = ($padLen - $strLength) / 2;
					$repeat = ceil($length / $padStrLength);
					$result = substr(str_repeat($padStr, $repeat), 0, floor($length))
						. $string
						. substr(str_repeat($padStr, $repeat), 0, ceil($length));
				}
			}
		}

		return $result;
	}

	public static function checkEncoding($data)
	{
		if (!ApplicationIsUtfMode()) {
			return true;
		}

		if (!is_string($data) && !is_array($data)) {
			return true;
		}

		if (is_string($data)) {
			return mb_check_encoding($data);
		}

		foreach ($data as $value) {
			if (!static::checkEncoding($value)) {
				return false;
			}
		}

		return true;
	}
}

class Encoding
{
	public static function convertEncoding($data, $charsetFrom, $charsetTo, &$errorMessage = "")
	{
		return iconv($charsetFrom, $charsetTo, $data);
	}
}

class Dictionary implements ArrayAccess, Iterator, Countable
{
	protected $values = [];

	public function __construct(array $values = null)
	{
		if ($values !== null) {
			$this->values = $values;
		}
	}

	public function get($name)
	{
		if (isset($this->values[$name]) || array_key_exists($name, $this->values)) {
			return $this->values[$name];
		}
		return null;
	}

	public function set($name, $value = null)
	{
		if (is_array($name)) {
			$this->values = $name;
		} else {
			$this->values[$name] = $value;
		}
	}

	public function getValues()
	{
		return $this->values;
	}

	public function setValues($values)
	{
		$this->values = $values;
	}

	public function clear()
	{
		$this->values = [];
	}

	public function current()
	{
		return current($this->values);
	}

	public function next()
	{
		return next($this->values);
	}

	public function key()
	{
		return key($this->values);
	}

	public function valid()
	{
		return ($this->key() !== null);
	}

	public function rewind()
	{
		return reset($this->values);
	}

	public function offsetExists($offset)
	{
		return isset($this->values[$offset]) || array_key_exists($offset, $this->values);
	}

	public function offsetGet($offset)
	{
		if (isset($this->values[$offset]) || array_key_exists($offset, $this->values)) {
			return $this->values[$offset];
		}

		return null;
	}

	public function offsetSet($offset, $value)
	{
		if ($offset === null) {
			$this->values[] = $value;
		} else {
			$this->values[$offset] = $value;
		}
	}

	public function offsetUnset($offset)
	{
		unset($this->values[$offset]);
	}

	public function count()
	{
		return count($this->values);
	}

	public function toArray()
	{
		return $this->values;
	}

	public function isEmpty()
	{
		return empty($this->values);
	}
}

class HttpCookies extends Dictionary
{
	public function __construct(array $values = null)
	{
		parent::__construct($values);
	}

	public function toString()
	{
		$str = "";
		foreach ($this->values as $name => $value) {
			$str .= ($str == "" ? "" : "; ") . rawurlencode($name) . "=" . rawurlencode($value);
		}
		return $str;
	}

	public function addFromString($str)
	{
		if (($pos = strpos($str, ';')) !== false && $pos > 0) {
			$cookie = trim(substr($str, 0, $pos));
		} else {
			$cookie = trim($str);
		}
		$arCookie = explode('=', $cookie, 2);

		$this[rawurldecode($arCookie[0])] = rawurldecode($arCookie[1]);
	}
}

class HttpHeaders implements IteratorAggregate
{
	protected $headers = [];

	public function __construct()
	{
	}

	public function add($name, $value)
	{
		$name = $this->refineString($name);
		$value = $this->refineString($value);

		$nameLower = strtolower($name);

		if (!isset($this->headers[$nameLower])) {
			$this->headers[$nameLower] = [
				"name" => $name,
				"values" => [],
			];
		}
		$this->headers[$nameLower]["values"][] = $value;
	}

	private function refineString($string)
	{
		return str_replace(["%0D", "%0A", "\r", "\n"], "", $string);
	}

	public function set($name, $value)
	{
		$name = $this->refineString($name);
		if ($value !== null) {
			$value = $this->refineString($value);
		}
		$nameLower = strtolower($name);

		$this->headers[$nameLower] = [
			"name" => $name,
			"values" => [$value],
		];
	}

	public function get($name, $returnArray = false)
	{
		$nameLower = strtolower($name);

		if (isset($this->headers[$nameLower])) {
			if ($returnArray) {
				return $this->headers[$nameLower]["values"];
			}

			return $this->headers[$nameLower]["values"][0];
		}

		return null;
	}

	public function delete($name)
	{
		$nameLower = strtolower($name);

		if (isset($this->headers[$nameLower])) {
			unset($this->headers[$nameLower]);
		}
	}

	public function clear()
	{
		unset($this->headers);
		$this->headers = [];
	}

	public function toString()
	{
		$str = "";
		foreach ($this->headers as $header) {
			foreach ($header["values"] as $value) {
				$str .= $header["name"] . ": " . $value . "\r\n";
			}
		}

		return $str;
	}

	public function toArray()
	{
		return $this->headers;
	}

	public function getContentType()
	{
		$contentType = $this->get("Content-Type");
		if ($contentType !== null) {
			$parts = explode(";", $contentType);
			return trim($parts[0]);
		}

		return null;
	}

	public function getBoundary()
	{
		$contentType = $this->get("Content-Type");
		if ($contentType !== null) {
			$parts = explode(";", $contentType);
			return $parts[1];
		}

		return null;
	}

	public function getCharset()
	{
		$contentType = $this->get("Content-Type");
		if ($contentType !== null) {
			$parts = explode(";", $contentType);
			foreach ($parts as $part) {
				$values = explode("=", $part);
				if (strtolower(trim($values[0])) == "charset") {
					return trim($values[1]);
				}
			}
		}

		return null;
	}

	public function getContentDisposition()
	{
		$contentDisposition = $this->get("Content-Disposition");
		if ($contentDisposition !== null) {
			$parts = explode(";", $contentDisposition);

			return trim($parts[0]);
		}

		return null;
	}

	public function getFilename()
	{
		$contentDisposition = $this->get('Content-disposition');
		if ($contentDisposition !== null) {
			$filename = null;
			$encoding = null;

			$contentElements = explode(';', $contentDisposition);
			foreach ($contentElements as $contentElement) {
				$contentElement = trim($contentElement);
				if (preg_match('/^filename\*=(.+)\'(.+)?\'(.+)$/', $contentElement, $matches)) {
					$filename = $matches[3];
					$encoding = $matches[1];
					break;
				} elseif (preg_match('/^filename="(.+)"$/', $contentElement, $matches)) {
					$filename = $matches[1];
				} elseif (preg_match('/^filename=(.+)$/', $contentElement, $matches)) {
					$filename = $matches[1];
				}
			}

			if ($filename <> '') {
				$filename = urldecode($filename);

				if ($encoding <> '') {
					//$charset = Context::getCurrent()->getCulture()->getCharset();
					//$filename = Encoding::convertEncoding($filename, $encoding, $charset);
				}
			}

			return $filename;
		}

		return null;
	}

	public function getIterator()
	{
		$toIterate = [];
		foreach ($this->headers as $header) {
			if (count($header["values"]) > 1) {
				$toIterate[$header["name"]] = $header["values"];
			} else {
				$toIterate[$header["name"]] = $header["values"][0];
			}
		}

		return new ArrayIterator($toIterate);
	}
}

class Uri implements JsonSerializable
{
	protected $scheme;
	protected $host;
	protected $port;
	protected $user;
	protected $pass;
	protected $path;
	protected $query;
	protected $fragment;

	public function __construct($url)
	{
		if (strpos($url, "/") === 0) {
			$url = "/" . ltrim($url, "/");
		}

		$parsedUrl = parse_url($url);

		if ($parsedUrl !== false) {
			$this->scheme = (isset($parsedUrl["scheme"]) ? strtolower($parsedUrl["scheme"]) : "http");
			$this->host = (isset($parsedUrl["host"]) ? $parsedUrl["host"] : "");
			if (isset($parsedUrl["port"])) {
				$this->port = $parsedUrl["port"];
			} else {
				$this->port = ($this->scheme == "https" ? 443 : 80);
			}
			$this->user = (isset($parsedUrl["user"]) ? $parsedUrl["user"] : "");
			$this->pass = (isset($parsedUrl["pass"]) ? $parsedUrl["pass"] : "");
			$this->path = (isset($parsedUrl["path"]) ? $parsedUrl["path"] : "/");
			$this->query = (isset($parsedUrl["query"]) ? $parsedUrl["query"] : "");
			$this->fragment = (isset($parsedUrl["fragment"]) ? $parsedUrl["fragment"] : "");
		}
	}

	public function getUrl()
	{
		return $this->getLocator();
	}

	public function getLocator()
	{
		$url = "";
		if ($this->host <> '') {
			$url .= $this->scheme . "://" . $this->host;

			if (($this->scheme == "http" && $this->port <> 80) || ($this->scheme == "https" && $this->port <> 443)) {
				$url .= ":" . $this->port;
			}
		}

		$url .= $this->getPathQuery();

		return $url;
	}

	public function getUri()
	{
		$url = $this->getLocator();

		if ($this->fragment <> '') {
			$url .= "#" . $this->fragment;
		}

		return $url;
	}

	public function getFragment()
	{
		return $this->fragment;
	}

	public function getHost()
	{
		return $this->host;
	}

	public function setHost($host)
	{
		$this->host = $host;
		return $this;
	}

	public function getPass()
	{
		return $this->pass;
	}

	public function setPass($pass)
	{
		$this->pass = $pass;
		return $this;
	}

	public function getPath()
	{
		return $this->path;
	}

	public function setPath($path)
	{
		$this->path = $path;
		return $this;
	}

	public function getPathQuery()
	{
		$pathQuery = $this->path;
		if ($this->query <> "") {
			$pathQuery .= '?' . $this->query;
		}
		return $pathQuery;
	}

	public function getPort()
	{
		return $this->port;
	}

	public function getQuery()
	{
		return $this->query;
	}

	public function getScheme()
	{
		return $this->scheme;
	}

	public function getUser()
	{
		return $this->user;
	}

	public function setUser($user)
	{
		$this->user = $user;
		return $this;
	}

	protected static function parseParams($params)
	{
		$data = preg_replace_callback(
			'/(?:^|(?<=&))[^=[]+/',
			function ($match) {
				return bin2hex(urldecode($match[0]));
			},
			$params
		);

		parse_str($data, $values);

		return array_combine(array_map('hex2bin', array_keys($values)), $values);
	}

	public function deleteParams(array $params, $preserveDots = false)
	{
		if ($this->query <> '') {
			if ($preserveDots) {
				$currentParams = static::parseParams($this->query);
			} else {
				$currentParams = [];
				parse_str($this->query, $currentParams);
			}

			foreach ($params as $param) {
				unset($currentParams[$param]);
			}

			$this->query = http_build_query($currentParams, "", "&");
		}
		return $this;
	}

	public function addParams(array $params, $preserveDots = false)
	{
		$currentParams = [];
		if ($this->query <> '') {
			if ($preserveDots) {
				$currentParams = static::parseParams($this->query);
			} else {
				parse_str($this->query, $currentParams);
			}
		}

		$currentParams = array_replace($currentParams, $params);

		$this->query = http_build_query($currentParams, "", "&");

		return $this;
	}

	public function __toString()
	{
		return $this->getUri();
	}

	public function jsonSerialize()
	{
		return $this->getUri();
	}
}

class IpAddress
{
	protected $ip;

	public function __construct($ip)
	{
		$this->ip = $ip;
	}

	public static function createByName($name)
	{
		$ip = gethostbyname($name);
		return new static($ip);
	}

	public static function createByUri(Uri $uri)
	{
		return static::createByName($uri->getHost());
	}

	public function get()
	{
		return $this->ip;
	}

	public function isPrivate()
	{
		return (filter_var($this->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false);
	}
}

class Path
{
	const DIRECTORY_SEPARATOR = '/';
	const DIRECTORY_SEPARATOR_ALT = '\\';
	const PATH_SEPARATOR = PATH_SEPARATOR;

	const INVALID_FILENAME_CHARS = "\\/:*?\"'<>|~#&;";

	const INVALID_FILENAME_BYTES = "\xE2\x80\xAE";

	protected static $physicalEncoding = "";
	protected static $logicalEncoding = "";

	protected static $directoryIndex = null;

	public static function normalize($path)
	{
		if (!is_string($path) || ($path == "")) {
			return null;
		}

		static $pattern = null, $tailPattern;
		if (!$pattern) {
			if (strncasecmp(PHP_OS, "WIN", 3) == 0) {
				$pattern = "'[\\\\/]+'";
				$tailPattern = "\0.\\/+ ";
			} else {
				$pattern = "'[/]+'";
				$tailPattern = "\0/";
			}
		}
		$pathTmp = preg_replace($pattern, "/", $path);

		if (strpos($pathTmp, "\0") !== false) {
			throw new InvalidPathException($path);
		}

		if (preg_match("#(^|/)(\\.|\\.\\.)(/|\$)#", $pathTmp)) {
			$arPathTmp = explode('/', $pathTmp);
			$arPathStack = [];
			foreach ($arPathTmp as $i => $pathPart) {
				if ($pathPart === '.') {
					continue;
				}

				if ($pathPart === "..") {
					if (array_pop($arPathStack) === null) {
						throw new InvalidPathException($path);
					}
				} else {
					array_push($arPathStack, $pathPart);
				}
			}
			$pathTmp = implode("/", $arPathStack);
		}

		$pathTmp = rtrim($pathTmp, $tailPattern);

		if (substr($path, 0, 1) === "/" && substr($pathTmp, 0, 1) !== "/") {
			$pathTmp = "/" . $pathTmp;
		}

		if ($pathTmp === '') {
			$pathTmp = "/";
		}

		return $pathTmp;
	}

	public static function getExtension($path)
	{
		$path = self::getName($path);
		if ($path != '') {
			$pos = UtfSafeString::getLastPosition($path, '.');
			if ($pos !== false) {
				return substr($path, $pos + 1);
			}
		}
		return '';
	}

	public static function getName($path)
	{
		//$path = self::normalize($path);

		$p = UtfSafeString::getLastPosition($path, self::DIRECTORY_SEPARATOR);
		if ($p !== false) {
			return substr($path, $p + 1);
		}

		return $path;
	}

	public static function getDirectory($path)
	{
		return substr($path, 0, -strlen(self::getName($path)) - 1);
	}

	public static function convertLogicalToPhysical($path)
	{
		if (self::$physicalEncoding == "") {
			self::$physicalEncoding = self::getPhysicalEncoding();
		}

		if (self::$logicalEncoding == "") {
			self::$logicalEncoding = self::getLogicalEncoding();
		}

		if (self::$physicalEncoding == self::$logicalEncoding) {
			return $path;
		}
		return Encoding::convertEncoding($path, self::$logicalEncoding, self::$physicalEncoding);
	}

	public static function convertPhysicalToLogical($path)
	{
		if (self::$physicalEncoding == "") {
			self::$physicalEncoding = self::getPhysicalEncoding();
		}

		if (self::$logicalEncoding == "") {
			self::$logicalEncoding = self::getLogicalEncoding();
		}

		if (self::$physicalEncoding == self::$logicalEncoding) {
			return $path;
		}

		return Encoding::convertEncoding($path, self::$physicalEncoding, self::$logicalEncoding);
	}

	public static function convertLogicalToUri($path)
	{
		if (self::$logicalEncoding == "") {
			self::$logicalEncoding = self::getLogicalEncoding();
		}

		if (self::$directoryIndex == null) {
			self::$directoryIndex = self::getDirectoryIndexArray();
		}

		if (isset(self::$directoryIndex[self::getName($path)])) {
			$path = self::getDirectory($path) . "/";
		}

		if ('utf-8' !== self::$logicalEncoding) {
			$path = Encoding::convertEncoding($path, self::$logicalEncoding, 'utf-8');
		}

		return implode('/', array_map("rawurlencode", explode('/', $path)));
	}

	public static function convertPhysicalToUri($path)
	{
		if (self::$physicalEncoding == "") {
			self::$physicalEncoding = self::getPhysicalEncoding();
		}

		if (self::$directoryIndex == null) {
			self::$directoryIndex = self::getDirectoryIndexArray();
		}

		if (isset(self::$directoryIndex[self::getName($path)])) {
			$path = self::getDirectory($path) . "/";
		}

		if ('utf-8' !== self::$physicalEncoding) {
			$path = Encoding::convertEncoding($path, self::$physicalEncoding, 'utf-8');
		}

		return implode('/', array_map("rawurlencode", explode('/', $path)));
	}

	public static function convertUriToPhysical($path)
	{
		if (self::$physicalEncoding == "") {
			self::$physicalEncoding = self::getPhysicalEncoding();
		}

		if (self::$directoryIndex == null) {
			self::$directoryIndex = self::getDirectoryIndexArray();
		}

		$path = implode('/', array_map("rawurldecode", explode('/', $path)));

		if ('utf-8' !== self::$physicalEncoding) {
			$path = Encoding::convertEncoding($path, 'utf-8', self::$physicalEncoding);
		}

		return $path;
	}

	protected static function getLogicalEncoding()
	{
		if (defined('BX_UTF')) {
			$logicalEncoding = "utf-8";
		} else {
			$logicalEncoding = "windows-1251";
		}

		return strtolower($logicalEncoding);
	}

	protected static function getPhysicalEncoding()
	{
		$physicalEncoding = defined("BX_FILE_SYSTEM_ENCODING") ? BX_FILE_SYSTEM_ENCODING : "";
		if ($physicalEncoding == "") {
			if (strtoupper(substr(PHP_OS, 0, 3)) === "WIN") {
				$physicalEncoding = "windows-1251";
			} else {
				$physicalEncoding = "utf-8";
			}
		}
		return strtolower($physicalEncoding);
	}

	public static function combine()
	{
		$numArgs = func_num_args();
		if ($numArgs <= 0) {
			return "";
		}

		$arParts = [];
		for ($i = 0; $i < $numArgs; $i++) {
			$arg = func_get_arg($i);
			if (is_array($arg)) {
				if (empty($arg)) {
					continue;
				}

				foreach ($arg as $v) {
					if (!is_string($v) || $v == "") {
						continue;
					}
					$arParts[] = $v;
				}
			} elseif (is_string($arg)) {
				if ($arg == "") {
					continue;
				}

				$arParts[] = $arg;
			}
		}

		$result = "";
		foreach ($arParts as $part) {
			if ($result !== "") {
				$result .= self::DIRECTORY_SEPARATOR;
			}
			$result .= $part;
		}

		$result = self::normalize($result);

		return $result;
	}

	public static function convertRelativeToAbsolute($relativePath)
	{
		if (!is_string($relativePath)) {
			throw new ArgumentTypeException("relativePath", "string");
		}
		if ($relativePath == "") {
			throw new ArgumentNullException("relativePath");
		}

		return self::combine($_SERVER["DOCUMENT_ROOT"], $relativePath);
	}

	public static function convertSiteRelativeToAbsolute($relativePath, $site = null)
	{
		$basePath = $_SERVER['DOCUMENT_ROOT'];

		return self::combine($basePath, "/");
	}

	protected static function validateCommon($path)
	{
		if (!is_string($path)) {
			return false;
		}

		if (trim($path) == "") {
			return false;
		}

		if (strpos($path, "\0") !== false) {
			return false;
		}

		if (preg_match("#(" . self::INVALID_FILENAME_BYTES . ")#", $path)) {
			return false;
		}

		return true;
	}

	public static function validate($path)
	{
		if (!static::validateCommon($path)) {
			return false;
		}

		return (preg_match("#^([a-z]:)?/([^\x01-\x1F" . preg_quote(self::INVALID_FILENAME_CHARS, "#") . "]+/?)*$#isD", $path) > 0);
	}

	public static function validateFilename($filename)
	{
		if (!static::validateCommon($filename)) {
			return false;
		}

		return (preg_match("#^[^\x01-\x1F" . preg_quote(self::INVALID_FILENAME_CHARS, "#") . "]+$#isD", $filename) > 0);
	}

	/**
	 * @param string $filename
	 * @param callable $callback
	 * @return string
	 */
	public static function replaceInvalidFilename($filename, $callback)
	{
		return preg_replace_callback(
			"#([\x01-\x1F" . preg_quote(self::INVALID_FILENAME_CHARS, "#") . "]|" . self::INVALID_FILENAME_BYTES . ")#",
			$callback,
			$filename
		);
	}

	/**
	 * @param string $filename
	 * @return string
	 */
	public static function randomizeInvalidFilename($filename)
	{
		return static::replaceInvalidFilename(
			$filename,
			function () {
				return chr(rand(97, 122));
			}
		);
	}

	public static function isAbsolute($path)
	{
		return (substr($path, 0, 1) === "/") || preg_match("#^[a-z]:/#i", $path);
	}

	protected static function getDirectoryIndexArray()
	{
		static $directoryIndexDefault = ["index.php" => 1, "index.html" => 1, "index.htm" => 1, "index.phtml" => 1, "default.html" => 1, "index.php3" => 1];

		return $directoryIndexDefault;
	}
}

interface IFileStream
{
	public function open($mode);
}

class FileStreamOpenMode
{
	const READ = "r";
	const WRITE = "w";
	const APPEND = "a";
}

abstract class FileSystemEntry
{
	protected $path;
	protected $originalPath;
	protected $pathPhysical;
	protected $siteId;

	public function __construct($path, $siteId = null)
	{
		if ($path == '') {
			throw new InvalidPathException($path);
		}

		$this->originalPath = $path;
		$this->path = Path::normalize($path);
		$this->siteId = $siteId;

		if ($this->path == '') {
			throw new InvalidPathException($path);
		}
	}

	public function isSystem()
	{
		if (preg_match("#/\\.#", $this->path)) {
			return true;
		}

		$documentRoot = static::getDocumentRoot($this->siteId);

		if (substr($this->path, 0, strlen($documentRoot)) === $documentRoot) {
			$relativePath = substr($this->path, strlen($documentRoot));
			$relativePath = ltrim($relativePath, "/");
			if (($pos = strpos($relativePath, "/")) !== false) {
				$s = substr($relativePath, 0, $pos);
			} else {
				$s = $relativePath;
			}
			$s = strtolower(rtrim($s, "."));

			$ar = [
				"bitrix" => 1,
				Main\Config\Option::get("main", "upload_dir", "upload") => 1,
				"local" => 1,
				"urlrewrite.php" => 1,
			];
			if (isset($ar[$s])) {
				return true;
			}
		}

		return false;
	}

	public function getName()
	{
		return Path::getName($this->path);
	}

	public function getDirectoryName()
	{
		return Path::getDirectory($this->path);
	}

	public function getPath()
	{
		return $this->path;
	}

	public function getDirectory()
	{
		return new DirectoryClass($this->getDirectoryName());
	}

	abstract public function getCreationTime();

	abstract public function getLastAccessTime();

	abstract public function getModificationTime();

	abstract public function isExists();

	public abstract function isDirectory();

	public abstract function isFile();

	public abstract function isLink();

	public abstract function markWritable();

	public abstract function getPermissions();

	public abstract function delete();

	public function getPhysicalPath()
	{
		if (is_null($this->pathPhysical)) {
			$this->pathPhysical = Path::convertLogicalToPhysical($this->path);
		}

		return $this->pathPhysical;
	}

	public function rename($newPath)
	{
		$newPathNormalized = Path::normalize($newPath);

		$success = true;
		if ($this->isExists()) {
			$success = rename($this->getPhysicalPath(), Path::convertLogicalToPhysical($newPathNormalized));
		}

		if ($success) {
			$this->originalPath = $newPath;
			$this->path = $newPathNormalized;
			$this->pathPhysical = null;
		}

		return $success;
	}

	protected static function getDocumentRoot($siteId)
	{
		if ($siteId === null) {
			$documentRoot = Main\Application::getDocumentRoot();
		} else {
			$documentRoot = Main\SiteTable::getDocumentRoot($siteId);
		}
		return $documentRoot;
	}
}

abstract class DirectoryEntry extends FileSystemEntry
{
	public function __construct($path, $siteId = null)
	{
		parent::__construct($path, $siteId);
	}

	public function create()
	{
		if ($this->isExists()) {
			return;
		}

		$arMissingDirs = [$this->getName()];
		$dir = $this->getDirectory();
		while (!$dir->isExists()) {
			$arMissingDirs[] = $dir->getName();
			$dir = $dir->getDirectory();
		}

		$arMissingDirs = array_reverse($arMissingDirs);
		foreach ($arMissingDirs as $dirName) {
			$dir = $dir->createSubdirectory($dirName);
		}
	}

	/**
	 * @return FileSystemEntry[]
	 */
	abstract public function getChildren();

	/**
	 * @param string $path
	 * @return DirectoryEntry
	 */
	abstract public function createSubdirectory($name);

	public function isDirectory()
	{
		return true;
	}

	public function isFile()
	{
		return false;
	}

	public function isLink()
	{
		return false;
	}
}

abstract class FileEntry extends FileSystemEntry
{
	public function __construct($path, $siteId = null)
	{
		parent::__construct($path, $siteId);
	}

	public function getExtension()
	{
		return Path::getExtension($this->path);
	}

	public abstract function getContents();

	public abstract function putContents($data);

	public abstract function getSize();

	public abstract function isWritable();

	public abstract function isReadable();

	public abstract function readFile();

	public function getFileSize()
	{
		return $this->getSize();
	}

	public function isDirectory()
	{
		return false;
	}

	public function isFile()
	{
		return true;
	}

	public function isLink()
	{
		return false;
	}
}

class File extends FileEntry implements IFileStream
{
	const REWRITE = 0;
	const APPEND = 1;

	/** @var resource */
	protected $filePointer;

	public function __construct($path, $siteId = null)
	{
		parent::__construct($path, $siteId);
	}

	public function open($mode)
	{
		$this->filePointer = fopen($this->getPhysicalPath(), $mode . "b");
		if (!$this->filePointer) {
			throw new FileOpenException($this->originalPath);
		}
		return $this->filePointer;
	}

	public function close()
	{
		if (!$this->filePointer) {
			throw new FileNotOpenedException($this->originalPath);
		}
		fclose($this->filePointer);
		$this->filePointer = null;
	}

	public function isExists()
	{
		$p = $this->getPhysicalPath();
		return file_exists($p) && (is_file($p) || is_link($p));
	}

	public function getContents()
	{
		if (!$this->isExists()) {
			throw new FileNotFoundException($this->originalPath);
		}

		return file_get_contents($this->getPhysicalPath());
	}

	public function putContents($data, $flags = self::REWRITE)
	{
		$dir = $this->getDirectory();
		if (!$dir->isExists()) {
			$dir->create();
		}

		if ($this->isExists() && !$this->isWritable()) {
			$this->markWritable();
		}

		return $flags & self::APPEND
			? file_put_contents($this->getPhysicalPath(), $data, FILE_APPEND)
			: file_put_contents($this->getPhysicalPath(), $data);
	}

	public function getSize()
	{
		if (!$this->isExists()) {
			throw new FileNotFoundException($this->originalPath);
		}

		static $supportLarge32 = null;
		if ($supportLarge32 === null) {
			$supportLarge32 = true;
		}

		$size = 0;
		if (PHP_INT_SIZE < 8 && $supportLarge32) {
			// 32bit
			$this->open(FileStreamOpenMode::READ);

			if (fseek($this->filePointer, 0, SEEK_END) === 0) {
				$size = 0.0;
				$step = 0x7FFFFFFF;
				while ($step > 0) {
					if (fseek($this->filePointer, -$step, SEEK_CUR) === 0) {
						$size += floatval($step);
					} else {
						$step >>= 1;
					}
				}
			}

			$this->close();
		} else {
			// 64bit
			$size = filesize($this->getPhysicalPath());
		}

		return $size;
	}

	public function seek($position)
	{
		if (!$this->filePointer) {
			throw new FileNotOpenedException($this->originalPath);
		}

		if ($position <= PHP_INT_MAX) {
			return fseek($this->filePointer, $position, SEEK_SET);
		} else {
			$res = fseek($this->filePointer, 0, SEEK_SET);
			if ($res === 0) {
				do {
					$offset = ($position < PHP_INT_MAX ? $position : PHP_INT_MAX);
					$res = fseek($this->filePointer, $offset, SEEK_CUR);
					if ($res !== 0) {
						break;
					}
					$position -= PHP_INT_MAX;
				} while ($position > 0);
			}
			return $res;
		}
	}

	public function isWritable()
	{
		if (!$this->isExists()) {
			throw new FileNotFoundException($this->originalPath);
		}

		return is_writable($this->getPhysicalPath());
	}

	public function isReadable()
	{
		if (!$this->isExists()) {
			throw new FileNotFoundException($this->originalPath);
		}

		return is_readable($this->getPhysicalPath());
	}

	public function readFile()
	{
		if (!$this->isExists()) {
			throw new FileNotFoundException($this->originalPath);
		}

		return readfile($this->getPhysicalPath());
	}

	public function getCreationTime()
	{
		if (!$this->isExists()) {
			throw new FileNotFoundException($this->originalPath);
		}

		return filectime($this->getPhysicalPath());
	}

	public function getLastAccessTime()
	{
		if (!$this->isExists()) {
			throw new FileNotFoundException($this->originalPath);
		}

		return fileatime($this->getPhysicalPath());
	}

	public function getModificationTime()
	{
		if (!$this->isExists()) {
			throw new FileNotFoundException($this->originalPath);
		}

		return filemtime($this->getPhysicalPath());
	}

	public function markWritable()
	{
		if (!$this->isExists()) {
			throw new FileNotFoundException($this->originalPath);
		}

		@chmod($this->getPhysicalPath(), BX_FILE_PERMISSIONS);
	}

	public function getPermissions()
	{
		if (!$this->isExists()) {
			throw new FileNotFoundException($this->originalPath);
		}

		return fileperms($this->getPhysicalPath());
	}

	public function delete()
	{
		if ($this->isExists()) {
			return unlink($this->getPhysicalPath());
		}

		return true;
	}

	public function getContentType()
	{
		if (!$this->isExists()) {
			throw new FileNotFoundException($this->originalPath);
		}

		$finfo = \finfo_open(FILEINFO_MIME_TYPE);
		$contentType = \finfo_file($finfo, $this->getPath());
		\finfo_close($finfo);

		return $contentType;
	}

	public static function isFileExists($path)
	{
		$f = new self($path);
		return $f->isExists();
	}

	public static function getFileContents($path)
	{
		$f = new self($path);
		return $f->getContents();
	}

	public static function putFileContents($path, $data, $flags = self::REWRITE)
	{
		$f = new self($path);
		return $f->putContents($data, $flags);
	}

	public static function deleteFile($path)
	{
		$f = new self($path);
		return $f->delete();
	}
}

class DirectoryClass extends DirectoryEntry
{
	public function __construct($path, $siteId = null)
	{
		parent::__construct($path, $siteId);
	}

	public function isExists()
	{
		$p = $this->getPhysicalPath();
		return file_exists($p) && is_dir($p);
	}

	public function delete()
	{
		return self::deleteInternal($this->getPhysicalPath());
	}

	private static function deleteInternal($path)
	{
		if (is_file($path) || is_link($path)) {
			if (!@unlink($path)) {
				throw new FileDeleteException($path);
			}
		} elseif (is_dir($path)) {
			if ($handle = opendir($path)) {
				while (($file = readdir($handle)) !== false) {
					if ($file == "." || $file == "..") {
						continue;
					}

					self::deleteInternal(Path::combine($path, $file));
				}
				closedir($handle);
			}
			if (!@rmdir($path)) {
				throw new FileDeleteException($path);
			}
		}

		return true;
	}

	/**
	 * @return array|FileSystemEntry[]
	 * @throws FileNotFoundException
	 */
	public function getChildren()
	{
		if (!$this->isExists()) {
			throw new FileNotFoundException($this->originalPath);
		}

		$arResult = [];

		if ($handle = opendir($this->getPhysicalPath())) {
			while (($file = readdir($handle)) !== false) {
				if ($file == "." || $file == "..") {
					continue;
				}

				$pathLogical = Path::combine($this->path, Path::convertPhysicalToLogical($file));
				$pathPhysical = Path::combine($this->getPhysicalPath(), $file);
				if (is_dir($pathPhysical)) {
					$arResult[] = new DirectoryClass($pathLogical, $this->siteId);
				} else {
					$arResult[] = new File($pathLogical, $this->siteId);
				}
			}
			closedir($handle);
		}

		return $arResult;
	}

	/**
	 * @param $name
	 * @return Directory|DirectoryEntry
	 */
	public function createSubdirectory($name)
	{
		$dir = new DirectoryClass(Path::combine($this->path, $name));
		if (!$dir->isExists()) {
			mkdir($dir->getPhysicalPath(), BX_DIR_PERMISSIONS, true);
		}
		return $dir;
	}

	public function getCreationTime()
	{
		if (!$this->isExists()) {
			throw new FileNotFoundException($this->originalPath);
		}

		return filectime($this->getPhysicalPath());
	}

	public function getLastAccessTime()
	{
		if (!$this->isExists()) {
			throw new FileNotFoundException($this->originalPath);
		}

		return fileatime($this->getPhysicalPath());
	}

	public function getModificationTime()
	{
		if (!$this->isExists()) {
			throw new FileNotFoundException($this->originalPath);
		}

		return filemtime($this->getPhysicalPath());
	}

	public function markWritable()
	{
		if (!$this->isExists()) {
			throw new FileNotFoundException($this->originalPath);
		}

		@chmod($this->getPhysicalPath(), BX_DIR_PERMISSIONS);
	}

	public function getPermissions()
	{
		return fileperms($this->getPhysicalPath());
	}

	/**
	 * @param $path
	 *
	 * @return Directory
	 */
	public static function createDirectory($path)
	{
		$dir = new self($path);
		$dir->create();

		return $dir;
	}

	public static function deleteDirectory($path)
	{
		$dir = new self($path);
		$dir->delete();
	}

	public static function isDirectoryExists($path)
	{
		$f = new self($path);
		return $f->isExists();
	}
}

class HttpClient
{
	const HTTP_1_0 = "1.0";
	const HTTP_1_1 = "1.1";
	const HTTP_GET = "GET";
	const HTTP_POST = "POST";
	const HTTP_PUT = "PUT";
	const HTTP_HEAD = "HEAD";
	const HTTP_PATCH = "PATCH";
	const HTTP_DELETE = "DELETE";

	const BUF_READ_LEN = 16384;
	const BUF_POST_LEN = 131072;

	protected $proxyHost;
	protected $proxyPort;
	protected $proxyUser;
	protected $proxyPassword;

	protected $resource;
	protected $socketTimeout = 30;
	protected $streamTimeout = 60;
	protected $error = [];
	protected $peerSocketName;

	/** @var HttpHeaders */
	protected $requestHeaders;
	/** @var HttpCookies */
	protected $requestCookies;
	protected $waitResponse = true;
	protected $redirect = true;
	protected $redirectMax = 5;
	protected $redirectCount = 0;
	protected $compress = false;
	protected $version = self::HTTP_1_0;
	protected $requestCharset = '';
	protected $sslVerify = true;
	protected $bodyLengthMax = 0;
	protected $privateIp = true;

	protected $status = 0;
	/** @var HttpHeaders */
	protected $responseHeaders;
	/** @var HttpCookies */
	protected $responseCookies;
	protected $result = '';
	protected $outputStream;

	protected $effectiveUrl;

	protected $contextOptions = [];

	public function __construct(array $options = null)
	{
		$this->requestHeaders = new HttpHeaders();
		$this->responseHeaders = new HttpHeaders();
		$this->requestCookies = new HttpCookies();
		$this->responseCookies = new HttpCookies();

		if ($options === null) {
			$options = [];
		}

		$defaultOptions = null;
		if ($defaultOptions !== null) {
			$options += $defaultOptions;
		}

		if (!empty($options)) {
			if (isset($options["redirect"])) {
				$this->setRedirect($options["redirect"], $options["redirectMax"]);
			}
			if (isset($options["waitResponse"])) {
				$this->waitResponse($options["waitResponse"]);
			}
			if (isset($options["socketTimeout"])) {
				$this->setTimeout($options["socketTimeout"]);
			}
			if (isset($options["streamTimeout"])) {
				$this->setStreamTimeout($options["streamTimeout"]);
			}
			if (isset($options["version"])) {
				$this->setVersion($options["version"]);
			}
			if (isset($options["proxyHost"])) {
				$this->setProxy($options["proxyHost"], $options["proxyPort"], $options["proxyUser"], $options["proxyPassword"]);
			}
			if (isset($options["compress"])) {
				$this->setCompress($options["compress"]);
			}
			if (isset($options["charset"])) {
				$this->setCharset($options["charset"]);
			}
			if (isset($options["disableSslVerification"]) && $options["disableSslVerification"] === true) {
				$this->disableSslVerification();
			}
			if (isset($options["bodyLengthMax"])) {
				$this->setBodyLengthMax($options["bodyLengthMax"]);
			}
			if (isset($options["privateIp"])) {
				$this->setPrivateIp($options["privateIp"]);
			}
		}
	}

	public function __destruct()
	{
		$this->disconnect();
	}

	public function get($url)
	{
		if ($this->query(self::HTTP_GET, $url)) {
			return $this->getResult();
		}
		return false;
	}

	public function head($url)
	{
		if ($this->query(self::HTTP_HEAD, $url)) {
			return $this->getHeaders();
		}
		return false;
	}

	public function post($url, $postData = null, $multipart = false)
	{
		if ($multipart) {
			$postData = $this->prepareMultipart($postData);
		}

		if ($this->query(self::HTTP_POST, $url, $postData)) {
			return $this->getResult();
		}
		return false;
	}

	protected function prepareMultipart($postData)
	{
		if (is_array($postData)) {
			$boundary = 'BXC' . md5(rand() . time());
			$this->setHeader('Content-type', 'multipart/form-data; boundary=' . $boundary);

			$data = '';

			foreach ($postData as $k => $v) {
				$data .= '--' . $boundary . "\r\n";

				if ((is_resource($v) && get_resource_type($v) === 'stream') || is_array($v)) {
					$filename = $k;
					$contentType = 'application/octet-stream';

					if (is_array($v)) {
						$content = '';

						if (isset($v['resource']) && is_resource($v['resource']) && get_resource_type($v['resource']) === 'stream') {
							$resource = $v['resource'];
							$content = stream_get_contents($resource);
						} else {
							if (isset($v['content'])) {
								$content = $v['content'];
							} else {
								$this->error["MULTIPART"] = "File `{$k}` not found for multipart upload";
								trigger_error($this->error["MULTIPART"], E_USER_WARNING);
							}
						}

						if (isset($v['filename'])) {
							$filename = $v['filename'];
						}

						if (isset($v['contentType'])) {
							$contentType = $v['contentType'];
						}
					} else {
						$content = stream_get_contents($v);
					}

					$data .= 'Content-Disposition: form-data; name="' . $k . '"; filename="' . $filename . '"' . "\r\n";
					$data .= 'Content-Type: ' . $contentType . "\r\n\r\n";
					$data .= $content . "\r\n";
				} else {
					$data .= 'Content-Disposition: form-data; name="' . $k . '"' . "\r\n\r\n";
					$data .= $v . "\r\n";
				}
			}

			$data .= '--' . $boundary . "--\r\n";
			$postData = $data;
		}

		return $postData;
	}

	public function query($method, $url, $entityBody = null)
	{
		$queryMethod = $method;
		$this->effectiveUrl = $url;

		if (is_array($entityBody)) {
			$entityBody = http_build_query($entityBody, "", "&");
		}

		$this->redirectCount = 0;

		while (true) {
			$parsedUrl = new Uri($this->effectiveUrl);
			if ($parsedUrl->getHost() == '') {
				$this->error["URI"] = "Incorrect URI: " . $this->effectiveUrl;
				return false;
			}

			if ($this->privateIp == false) {
				$ip = IpAddress::createByUri($parsedUrl);
				if ($ip->isPrivate()) {
					$this->error["PRIVATE_IP"] = "Resolved IP is incorrect or private: " . $ip->get();
					return false;
				}
			}

			$this->disconnect();

			if ($this->connect($parsedUrl) === false) {
				return false;
			}

			$this->sendRequest($queryMethod, $parsedUrl, $entityBody);

			if (!$this->waitResponse) {
				$this->disconnect();
				return true;
			}

			if (!$this->readHeaders()) {
				$this->disconnect();
				return false;
			}

			if ($this->redirect && ($location = $this->responseHeaders->get("Location")) !== null && $location <> '') {
				$this->disconnect();

				if ($this->redirectCount < $this->redirectMax) {
					$this->effectiveUrl = $location;
					if ($this->status == 302 || $this->status == 303) {
						$queryMethod = self::HTTP_GET;
					}
					$this->redirectCount++;
				} else {
					$this->error["REDIRECT"] = "Maximum number of redirects (" . $this->redirectMax . ") has been reached at URL " . $url;
					trigger_error($this->error["REDIRECT"], E_USER_WARNING);
					return false;
				}
			} else {
				break;
			}
		}
		return true;
	}

	public function setHeader($name, $value, $replace = true)
	{
		if ($replace == true || $this->requestHeaders->get($name) === null) {
			$this->requestHeaders->set($name, $value);
		}
		return $this;
	}

	public function clearHeaders()
	{
		$this->requestHeaders->clear();
	}

	public function setCookies(array $cookies)
	{
		$this->requestCookies->set($cookies);
		return $this;
	}

	public function setAuthorization($user, $pass)
	{
		$this->setHeader("Authorization", "Basic " . base64_encode($user . ":" . $pass));
		return $this;
	}

	public function setRedirect($value, $max = null)
	{
		$this->redirect = ($value ? true : false);
		if ($max !== null) {
			$this->redirectMax = intval($max);
		}
		return $this;
	}

	public function waitResponse($value)
	{
		$this->waitResponse = (bool)$value;
		return $this;
	}

	public function setTimeout($value)
	{
		$this->socketTimeout = intval($value);
		return $this;
	}

	public function setStreamTimeout($value)
	{
		$this->streamTimeout = intval($value);
		return $this;
	}

	public function setVersion($value)
	{
		$this->version = $value;
		return $this;
	}

	public function setCompress($value)
	{
		$this->compress = (bool)$value;
		return $this;
	}

	public function setCharset($value)
	{
		$this->requestCharset = $value;
		return $this;
	}

	public function disableSslVerification()
	{
		$this->sslVerify = false;
		return $this;
	}

	public function setPrivateIp($value)
	{
		$this->privateIp = (bool)$value;
		return $this;
	}

	public function setProxy($proxyHost, $proxyPort = null, $proxyUser = null, $proxyPassword = null)
	{
		$this->proxyHost = $proxyHost;
		$this->proxyPort = intval($proxyPort);
		if ($this->proxyPort <= 0) {
			$this->proxyPort = 80;
		}
		$this->proxyUser = $proxyUser;
		$this->proxyPassword = $proxyPassword;

		return $this;
	}

	public function setOutputStream($handler)
	{
		$this->outputStream = $handler;
		return $this;
	}

	public function setBodyLengthMax($bodyLengthMax)
	{
		$this->bodyLengthMax = intval($bodyLengthMax);
		return $this;
	}

	public function download($url, $filePath)
	{
		$dir = Path::getDirectory($filePath);
		DirectoryClass::createDirectory($dir);

		$file = new File($filePath);
		$handler = $file->open("w+");
		if ($handler !== false) {
			$this->setOutputStream($handler);
			$res = $this->query(self::HTTP_GET, $url);
			if ($res) {
				$res = $this->readBody();
			}
			$this->disconnect();

			fclose($handler);
			return $res;
		}

		return false;
	}

	public function getEffectiveUrl()
	{
		return $this->effectiveUrl;
	}

	public function setContextOptions(array $options)
	{
		$this->contextOptions = array_replace_recursive($this->contextOptions, $options);
		return $this;
	}

	protected function connect(Uri $url)
	{
		if ($this->proxyHost <> '') {
			$proto = "";
			$host = $this->proxyHost;
			$port = $this->proxyPort;
		} else {
			$proto = ($url->getScheme() == "https" ? "ssl://" : "");
			$host = $url->getHost();
			$encodingErrors = '';
			$host = CBXPunycode::ToASCII($host, $encodingErrors);
			if (is_array($encodingErrors) && count($encodingErrors) > 0) {
				$this->error["URI"] = "Error converting hostname to punycode: " . implode("\n", $encodingErrors);
				return false;
			}
			$url->setHost($host);

			$port = $url->getPort();
		}

		$context = $this->createContext();

		//$context can be FALSE
		if ($context) {
			$res = stream_socket_client($proto . $host . ":" . $port, $errno, $errstr, $this->socketTimeout, STREAM_CLIENT_CONNECT, $context);
		} else {
			$res = stream_socket_client($proto . $host . ":" . $port, $errno, $errstr, $this->socketTimeout);
		}

		if (is_resource($res)) {
			$this->resource = $res;
			$this->peerSocketName = stream_socket_get_name($this->resource, true);

			if ($this->streamTimeout > 0) {
				stream_set_timeout($this->resource, $this->streamTimeout);
			}

			return true;
		}

		if (intval($errno) > 0) {
			$this->error["CONNECTION"] = "[" . $errno . "] " . $errstr;
		} else {
			$this->error["SOCKET"] = "Socket connection error.";
		}

		return false;
	}

	protected function createContext()
	{
		if ($this->sslVerify === false) {
			$this->contextOptions["ssl"]["verify_peer_name"] = false;
			$this->contextOptions["ssl"]["verify_peer"] = false;
			$this->contextOptions["ssl"]["allow_self_signed"] = true;
		}

		$context = stream_context_create($this->contextOptions);
		return $context;
	}

	protected function disconnect()
	{
		if ($this->resource) {
			fclose($this->resource);
			$this->resource = null;
		}
	}

	protected function send($data)
	{
		return fwrite($this->resource, $data);
	}

	protected function receive($bufLength = null)
	{
		if ($bufLength === null) {
			$bufLength = self::BUF_READ_LEN;
		}

		$buf = stream_get_contents($this->resource, $bufLength);
		if ($buf !== false) {
			if (is_resource($this->outputStream)) {
				//we can write response directly to stream (file, etc.) to minimize memory usage
				fwrite($this->outputStream, $buf);
				fflush($this->outputStream);
			} else {
				$this->result .= $buf;
			}
		}

		return $buf;
	}

	protected function sendRequest($method, Uri $url, $entityBody = null)
	{
		$this->status = 0;
		$this->result = '';
		$this->responseHeaders->clear();
		$this->responseCookies->clear();

		if ($this->proxyHost <> '') {
			$path = $url->getLocator();
			if ($this->proxyUser <> '') {
				$this->setHeader("Proxy-Authorization", "Basic " . base64_encode($this->proxyUser . ":" . $this->proxyPassword));
			}
		} else {
			$path = $url->getPathQuery();
		}

		$request = $method . " " . $path . " HTTP/" . $this->version . "\r\n";

		$this->setHeader("Host", $url->getHost());
		$this->setHeader("Connection", "close", false);
		$this->setHeader("Accept", "*/*", false);
		$this->setHeader("Accept-Language", "en", false);

		if (($user = $url->getUser()) <> '') {
			$this->setAuthorization($user, $url->getPass());
		}

		$cookies = $this->requestCookies->toString();
		if ($cookies <> '') {
			$this->setHeader("Cookie", $cookies);
		}

		if ($this->compress) {
			$this->setHeader("Accept-Encoding", "gzip");
		}

		if (!is_resource($entityBody)) {
			if ($method == self::HTTP_POST) {
				//special processing for POST requests
				if ($this->requestHeaders->get("Content-Type") === null) {
					$contentType = "application/x-www-form-urlencoded";
					if ($this->requestCharset <> '') {
						$contentType .= "; charset=" . $this->requestCharset;
					}
					$this->setHeader("Content-Type", $contentType);
				}
			}
			if ($entityBody <> '' || $method == self::HTTP_POST) {
				//HTTP/1.0 requires Content-Length for POST
				if ($this->requestHeaders->get("Content-Length") === null) {
					$this->setHeader("Content-Length", BinaryString::getLength($entityBody));
				}
			}
		}

		$request .= $this->requestHeaders->toString();
		$request .= "\r\n";

		$this->send($request);

		if (is_resource($entityBody)) {
			//PUT data can be a file resource
			while (!feof($entityBody)) {
				$this->send(fread($entityBody, self::BUF_POST_LEN));
			}
		} elseif ($entityBody <> '') {
			$this->send($entityBody);
		}
	}

	protected function readHeaders()
	{
		$headers = "";
		while (!feof($this->resource)) {
			$line = fgets($this->resource, self::BUF_READ_LEN);
			if ($line == "\r\n") {
				break;
			}
			if ($this->streamTimeout > 0) {
				$info = stream_get_meta_data($this->resource);
				if ($info['timed_out']) {
					$this->error['STREAM_TIMEOUT'] = "Stream reading timeout of " . $this->streamTimeout . " second(s) has been reached";
					return false;
				}
			}
			if ($line === false) {
				$this->error['STREAM_READING'] = "Stream reading error";
				return false;
			}
			$headers .= $line;
		}

		$this->parseHeaders($headers);

		return true;
	}

	protected function readBody()
	{
		$receivedBodyLength = 0;
		if ($this->responseHeaders->get("Transfer-Encoding") == "chunked") {
			while (!feof($this->resource)) {
				/*
				chunk = chunk-size [ chunk-extension ] CRLF
						chunk-data CRLF
				chunk-size = 1*HEX
				chunk-extension = *( ";" chunk-ext-name [ "=" chunk-ext-val ] )
				*/
				$line = fgets($this->resource, self::BUF_READ_LEN);
				if ($line == "\r\n") {
					continue;
				}
				if (($pos = strpos($line, ";")) !== false) {
					$line = substr($line, 0, $pos);
				}

				$length = hexdec($line);
				while ($length > 0) {
					$buf = $this->receive($length);
					if ($this->streamTimeout > 0) {
						$info = stream_get_meta_data($this->resource);
						if ($info['timed_out']) {
							$this->error['STREAM_TIMEOUT'] = "Stream reading timeout of " . $this->streamTimeout . " second(s) has been reached";
							return false;
						}
					}
					if ($buf === false) {
						$this->error['STREAM_READING'] = "Stream reading error";
						return false;
					}
					$currentReceivedBodyLength = BinaryString::getLength($buf);
					$length -= $currentReceivedBodyLength;
					$receivedBodyLength += $currentReceivedBodyLength;
					if ($this->bodyLengthMax > 0 && $receivedBodyLength > $this->bodyLengthMax) {
						$this->error['STREAM_LENGTH'] = "Maximum content length has been reached. Break reading";
						return false;
					}
				}
			}
		} else {
			while (!feof($this->resource)) {
				$buf = $this->receive();
				if ($this->streamTimeout > 0) {
					$info = stream_get_meta_data($this->resource);
					if ($info['timed_out']) {
						$this->error['STREAM_TIMEOUT'] = "Stream reading timeout of " . $this->streamTimeout . " second(s) has been reached";
						return false;
					}
				}
				if ($buf === false) {
					$this->error['STREAM_READING'] = "Stream reading error";
					return false;
				}
				$receivedBodyLength += BinaryString::getLength($buf);
				if ($this->bodyLengthMax > 0 && $receivedBodyLength > $this->bodyLengthMax) {
					$this->error['STREAM_LENGTH'] = "Maximum content length has been reached. Break reading";
					return false;
				}
			}
		}

		if ($this->responseHeaders->get("Content-Encoding") == "gzip") {
			$this->decompress();
		}

		return true;
	}

	protected function decompress()
	{
		if (is_resource($this->outputStream)) {
			$compressed = stream_get_contents($this->outputStream, -1, 10);
			$compressed = BinaryString::getSubstring($compressed, 0, -8);
			if ($compressed <> '') {
				$uncompressed = gzinflate($compressed);

				rewind($this->outputStream);
				$len = fwrite($this->outputStream, $uncompressed);
				ftruncate($this->outputStream, $len);
			}
		} else {
			$compressed = BinaryString::getSubstring($this->result, 10, -8);
			if ($compressed <> '') {
				$this->result = gzinflate($compressed);
			}
		}
	}

	protected function parseHeaders($headers)
	{
		foreach (explode("\n", $headers) as $k => $header) {
			if ($k == 0) {
				if (preg_match('#HTTP\S+ (\d+)#', $header, $find)) {
					$this->status = intval($find[1]);
				}
			} elseif (strpos($header, ':') !== false) {
				[$headerName, $headerValue] = explode(':', $header, 2);
				if (strtolower($headerName) == 'set-cookie') {
					$this->responseCookies->addFromString($headerValue);
				}
				$this->responseHeaders->add($headerName, trim($headerValue));
			}
		}
	}

	public function getHeaders()
	{
		return $this->responseHeaders;
	}

	public function getCookies()
	{
		return $this->responseCookies;
	}

	public function getStatus()
	{
		return $this->status;
	}

	public function getResult()
	{
		if ($this->waitResponse && $this->resource) {
			$this->readBody();
			$this->disconnect();
		}
		return $this->result;
	}

	public function getError()
	{
		return $this->error;
	}

	public function getContentType()
	{
		return $this->responseHeaders->getContentType();
	}

	public function getCharset()
	{
		return $this->responseHeaders->getCharset();
	}

	public function getPeerSocketName()
	{
		return $this->peerSocketName ?: '';
	}

	public function getPeerAddress()
	{
		if (!preg_match('/^(\d+)\.(\d+)\.(\d+)\.(\d+):(\d+)$/', $this->peerSocketName, $matches)) {
			return false;
		}

		return sprintf('%d.%d.%d.%d', $matches[1], $matches[2], $matches[3], $matches[4]);
	}

	public function getPeerPort()
	{
		if (!preg_match('/^(\d+)\.(\d+)\.(\d+)\.(\d+):(\d+)$/', $this->peerSocketName, $matches)) {
			return false;
		}

		return (int)$matches[5];
	}
}

function myPrint(&$Var)
{
	echo '<pre style="text-align:left;background-color:#222222;color:#ffffff;font-size:11px;">';
	echo htmlspecialchars(print_r($Var, true));
	echo '</pre>';
}

function AmminaBackupFormatSize($iSize)
{
	$strResult = '';
	if ($iSize > 1099511627776) {
		$strResult = round($iSize / 1099511627776, 2) . " Тб";
	} elseif ($iSize > 1073741824) {
		$strResult = round($iSize / 1073741824, 2) . " Гб";
	} elseif ($iSize > 1048576) {
		$strResult = round($iSize / 1048576, 2) . " Мб";
	} elseif ($iSize > 1024) {
		$strResult = round($iSize / 1024, 2) . " Кб";
	} else {
		$strResult = round($iSize, 0) . " байт";
	}
	return $strResult;
}

class AmminaBackupApplication
{
	protected $iSelectProgileId = 0;
	protected $arAllProfiles = array();
	protected $iSelectDriverId = false;
	protected $arAllDrivers = array(
		"disk.yandex.ru" => array(
			"NAME" => "Яндекс.Диск (disk.yandex.ru)",
			"CLASS_NAME" => "AmminaBackupDriverDiskYandexRu"
		),
		"cloud.mail.ru" => array(
			"NAME" => "Облако Mail.ru (cloud.mail.ru)",
			"CLASS_NAME" => "AmminaBackupDriverCloudMailRu"
		),
		"dropbox.com" => array(
			"NAME" => "Dropbox (www.dropbox.com)",
			"CLASS_NAME" => "AmminaBackupDriverDropboxCom"
		),
		"ftp" => array(
			"NAME" => "FTP сервер (любой ftp сервер)",
			"CLASS_NAME" => "AmminaBackupDriverFtp"
		),
		"sftp" => array(
			"NAME" => "SFTP сервер (любой sftp сервер)",
			"CLASS_NAME" => "AmminaBackupDriverSFtp"
		),
	);
	protected $strBackupPath = '';
	/**
	 * @var AmminaBackupDriverBase
	 */
	protected $oCurrentDriver = null;
	protected $arErrors = array();
	protected $arNotes = array();
	protected $bIsModeDownload = false;
	protected $strBackupFile = '';

	public function __construct($arAllProfiles = array())
	{
		session_start();
		if (is_array($arAllProfiles)) {
			$this->arAllProfiles = $arAllProfiles;
		}
	}

	public function doActionCheck()
	{
		if ($_SERVER['REQUEST_METHOD'] == "POST") {
			if ($_REQUEST['action'] == "changeProfile") {
				$this->doActionChangeProfile();
				unset($_SESSION['LIST_BACKUPS']);
			} elseif ($_REQUEST['action'] == "changeDriver") {
				$this->doActionChangeDriver();
				unset($_SESSION['LIST_BACKUPS']);
			} elseif ($_REQUEST['action'] == "checkSettings") {
				$this->doActionCheckSettings();
			} elseif ($_REQUEST['action'] == "startDownload") {
				$this->bIsModeDownload = true;
				$this->doActionStartDownload();
			} elseif ($_REQUEST['action'] == "checkDownload") {
				$this->bIsModeDownload = true;
				$this->doActionCheckDownload();
			} elseif ($_REQUEST['action'] == "goRestore") {
				$this->bIsModeDownload = true;
				$this->doActionGoRestore();
			}
		}
	}

	public function doActionChangeProfile()
	{
		$iNewProfileId = intval($_REQUEST['OPTIONS']['PROFILE']);
		if (isset($this->arAllProfiles[$iNewProfileId])) {
			$this->iSelectProgileId = $iNewProfileId;
			$this->strBackupPath = $this->arAllProfiles[$iNewProfileId]['PATH'];
			$this->iSelectDriverId = $this->arAllProfiles[$iNewProfileId]['DRIVER'];
			$this->oCurrentDriver = new $this->arAllDrivers[$this->iSelectDriverId]['CLASS_NAME']($this->arAllProfiles[$iNewProfileId]['DRIVER_SETTINGS']);
		} else {
			$this->iSelectProgileId = 0;
		}
	}

	public function doActionChangeDriver()
	{
		$iNewDriverId = trim($_REQUEST['OPTIONS']['DRIVER']);
		if (isset($this->arAllDrivers[$iNewDriverId])) {
			$this->iSelectDriverId = $iNewDriverId;
			$this->oCurrentDriver = new $this->arAllDrivers[$this->iSelectDriverId]['CLASS_NAME'](array());
		} else {
			$this->iSelectDriverId = false;
		}
	}

	public function doActionCheckSettings()
	{
		set_time_limit(300);
		$iNewProfileId = intval($_REQUEST['OPTIONS']['PROFILE']);
		$iNewDriverId = trim($_REQUEST['OPTIONS']['DRIVER']);
		$this->strBackupPath = $_REQUEST['OPTIONS']['PATH'];
		$arResultBackup = false;
		if ($iNewProfileId > 0) {
			$this->doActionChangeProfile();
			$_SESSION['LIST_BACKUPS'] = $this->oCurrentDriver->GetListAllRemoteBackup();
		} elseif (isset($this->arAllDrivers[$iNewDriverId]) && strlen($this->strBackupPath) > 0) {
			$this->iSelectDriverId = $iNewDriverId;
			$this->oCurrentDriver = new $this->arAllDrivers[$this->iSelectDriverId]['CLASS_NAME']($_REQUEST['DRIVER_SETTINGS']);
			$_SESSION['LIST_BACKUPS'] = $this->oCurrentDriver->GetListAllRemoteBackup();
		} else {
			$this->arErrors[] = "Выберите профайл или драйвер (и укажите настройки драйвера), а также проверьте правильность пути к каталогу бэкапов";
			unset($_SESSION['LIST_BACKUPS']);
		}
	}

	public function doActionStartDownload()
	{
		$iNewProfileId = intval($_REQUEST['OPTIONS']['PROFILE']);
		$iNewDriverId = trim($_REQUEST['OPTIONS']['DRIVER']);
		$this->strBackupPath = $_REQUEST['OPTIONS']['PATH'];
		$this->strBackupFile = $_REQUEST['BACKUP_FILE'];
		if ($iNewProfileId > 0) {
			$this->doActionChangeProfile();
		} elseif (isset($this->arAllDrivers[$iNewDriverId]) && strlen($this->strBackupPath) > 0) {
			$this->iSelectDriverId = $iNewDriverId;
			$this->oCurrentDriver = new $this->arAllDrivers[$this->iSelectDriverId]['CLASS_NAME']($_REQUEST['DRIVER_SETTINGS']);
		}
		if (function_exists("pcntl_fork")) {
			$pid = pcntl_fork();
			if ($pid == -1) {
				$this->doProcessStartDownload();
			} else {
				if ($pid) {
				} else {
					session_write_close();
					$this->doProcessStartDownload();
				}
			}
		} else {
			$this->doProcessStartDownload();
		}
	}

	public function doActionCheckDownload()
	{
		$iNewProfileId = intval($_REQUEST['OPTIONS']['PROFILE']);
		$iNewDriverId = trim($_REQUEST['OPTIONS']['DRIVER']);
		$this->strBackupPath = $_REQUEST['OPTIONS']['PATH'];
		$this->strBackupFile = $_REQUEST['BACKUP_FILE'];
		if ($iNewProfileId > 0) {
			$this->doActionChangeProfile();
		} elseif (isset($this->arAllDrivers[$iNewDriverId]) && strlen($this->strBackupPath) > 0) {
			$this->iSelectDriverId = $iNewDriverId;
			$this->oCurrentDriver = new $this->arAllDrivers[$this->iSelectDriverId]['CLASS_NAME']($_REQUEST['DRIVER_SETTINGS']);
		}
	}

	public function doActionGoRestore()
	{
		if (!file_exists($_SERVER['DOCUMENT_ROOT'] . "/restore.php")) {
			$client = new HttpClient(
				array(
					'redirect' => true,
					'redirectMax' => 10,
					'socketTimeout' => 60,
					'streamTimeout' => 60,
					'disableSslVerification' => true
				)
			);
			$client->setHeader("Accept", '*/*');
			$rFile = fopen($_SERVER['DOCUMENT_ROOT'] . "/restore.php", "w+");
			if ($rFile) {
				$client->setOutputStream($rFile);
			}
			if ($client->query("GET", "https://www.1c-bitrix.ru/download/scripts/restore.php")) {
				$status = intval($client->getStatus());
				$strAnswer = trim($client->getResult());
			}
			if ($rFile) {
				fclose($rFile);
			}
		}
		if (file_exists($_SERVER['DOCUMENT_ROOT'] . "/restore.php")) {
			@unlink(__FILE__);
			$_SESSION = array();
			header("Location: /restore.php");
			ob_end_clean();
			die();
		}
	}

	public function doProcessStartDownload()
	{
		@set_time_limit(0);
		@ignore_user_abort(true);
		$arListForDownload = $this->getStatusDownload();
		if ($arListForDownload['STATUS'] != "OK") {
			$oDriver = $this->getDriverObject();
			foreach ($arListForDownload['FILES'] as $strFile => $arFile) {
				if ($arFile['STATUS'] != "OK") {
					$strLocalTmpFile = $_SERVER['DOCUMENT_ROOT'] . "/" . $arFile['FILE_NAME'] . ".tmp";
					if (file_exists($strLocalTmpFile)) {
						continue;
					}
					if ($oDriver->GetRemoteFile($arFile['FULL_NAME'], $strLocalTmpFile)) {
						if (filesize($strLocalTmpFile) == $arFile["SIZE"]) {
							rename($strLocalTmpFile, $_SERVER['DOCUMENT_ROOT'] . "/" . $arFile['FILE_NAME']);
							$arListForDownload['FILES'][$strFile]['STATUS'] = "OK";
						} else {
							@unlink($strLocalTmpFile);
							$arListForDownload['FILES'][$strFile]['STATUS'] = "ERROR";
						}
					}
				}
			}
		}
	}

	public function getSelectProfile()
	{
		if ($this->iSelectProgileId > 0) {
			return $this->arAllProfiles[$this->iSelectProgileId];
		}
		return false;
	}

	public function getSelectProfileId()
	{
		return $this->iSelectProgileId;
	}

	public function getAllProfiles()
	{
		return $this->arAllProfiles;
	}

	public function getSelectDriver()
	{
		if ($this->iSelectDriverId !== false) {
			return $this->arAllDrivers[$this->iSelectDriverId];
		}
		return false;
	}

	public function getSelectDriverId()
	{
		return $this->iSelectDriverId;
	}

	public function getAllDrivers()
	{
		return $this->arAllDrivers;
	}

	/**
	 * @return AmminaBackupDriverBase
	 */
	public function getDriverObject()
	{
		return $this->oCurrentDriver;
	}

	public function getBackupPath()
	{
		return $this->strBackupPath;
	}

	public function getBackupFile()
	{
		return $this->strBackupFile;
	}

	public function getErrors()
	{
		return $this->arErrors;
	}

	public function getNotes()
	{
		return $this->arNotes;
	}

	public function getModeDownload()
	{
		return $this->bIsModeDownload;
	}

	public function getStatusDownload()
	{
		$arResult = $_SESSION['LIST_BACKUPS'][$this->strBackupFile];
		$arResult['TOTAL_DOWNLOAD'] = 0;
		foreach ($arResult['FILES'] as $strFile => $arFile) {
			if (file_exists($_SERVER['DOCUMENT_ROOT'] . "/" . $arFile['FILE_NAME'])) {
				$arResult['TOTAL_DOWNLOAD'] += filesize($_SERVER['DOCUMENT_ROOT'] . "/" . $arFile['FILE_NAME']);
				$arResult['FILES'][$strFile]['TOTAL_DOWNLOAD'] = filesize($_SERVER['DOCUMENT_ROOT'] . "/" . $arFile['FILE_NAME']);
				$arResult['FILES'][$strFile]['STATUS'] = "OK";
				if (filesize($_SERVER['DOCUMENT_ROOT'] . "/" . $arFile['FILE_NAME']) != $arFile['SIZE']) {
					$arResult['FILES'][$strFile]['STATUS'] = "DOWNLOAD_ERROR";
				}
			} elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . "/" . $arFile['FILE_NAME'] . ".tmp")) {
				$arResult['TOTAL_DOWNLOAD'] += filesize($_SERVER['DOCUMENT_ROOT'] . "/" . $arFile['FILE_NAME'] . ".tmp");
				$arResult['FILES'][$strFile]['TOTAL_DOWNLOAD'] = filesize($_SERVER['DOCUMENT_ROOT'] . "/" . $arFile['FILE_NAME'] . ".tmp");
				$arResult['FILES'][$strFile]['STATUS'] = "DOWNLOAD";
				$arResult['FILES'][$strFile]['PERCENT'] = intval($arResult['FILES'][$strFile]['TOTAL_DOWNLOAD'] / $arResult['FILES'][$strFile]['SIZE'] * 100);
			}
		}
		$arResult['TOTAL_PERCENT'] = intval($arResult['TOTAL_DOWNLOAD'] / $arResult['TOTAL_SIZE'] * 100);
		if ($arResult['TOTAL_DOWNLOAD'] == $arResult['TOTAL_SIZE']) {
			$arResult['STATUS'] = "OK";
		} else {
			$arResult['STATUS'] = "PROCESS";
		}
		return $arResult;
	}
}

global $APPLICATION;
$APPLICATION = new AmminaBackupApplication(isset($arBackupConfigs) ? $arBackupConfigs : array());
$APPLICATION->doActionCheck();

?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
	<title>Ammina.Backup - загрузка резервной копии</title>
	<style>
        body {
            background-color: #f5f5f5;
            padding: 40px 0;
        }
	</style>
</head>
<body>
<?
global $APPLICATION;
$iCurrentProfile = $APPLICATION->getSelectProfileId();
$iCurrentDriver = $APPLICATION->getSelectDriverId();
if ($iCurrentDriver !== false) {
	$oDriver = $APPLICATION->getDriverObject();
}
?>
<div class="container">
	<div class="row">
		<div class="col-12<?= ($APPLICATION->getModeDownload() ? '' : ' col-md-8 offset-md-2') ?>">
			<div class="card">
				<div class="card-header">
					Ammina.Backup - загрузка резервной копии
				</div>
				<div class="card-body">
					<?
					if (!empty($APPLICATION->getErrors())) {
						?>
						<div class="alert alert-danger" role="alert">
							<?= implode("<br>", $APPLICATION->getErrors()) ?>
						</div>
						<?
					}
					if (!empty($APPLICATION->getNotes())) {
						?>
						<div class="alert alert-success" role="alert">
							<?= implode("<br>", $APPLICATION->getNotes()) ?>
						</div>
						<?
					}
					?>
					<form id="abm-settings" method="post" enctype="multipart/form-data">
						<?
						if ($APPLICATION->getModeDownload()) {
							?>
							<input type="hidden" name="action" value="checkDownload"/>
							<?
							if (isset($_REQUEST['OPTIONS'])) {
								foreach ($_REQUEST['OPTIONS'] as $k => $v) {
									?>
									<input type="hidden" name="OPTIONS[<?= htmlspecialchars($k) ?>]" value="<?= htmlspecialchars($v) ?>"/>
									<?
								}
							}
							if (isset($_REQUEST['DRIVER_SETTINGS'])) {
								foreach ($_REQUEST['DRIVER_SETTINGS'] as $k => $v) {
									?>
									<input type="hidden" name="DRIVER_SETTINGS[<?= htmlspecialchars($k) ?>]" value="<?= htmlspecialchars($v) ?>"/>
									<?
								}
							}
							if (isset($_REQUEST['BACKUP_FILE'])) {
								?>
								<input type="hidden" name="BACKUP_FILE" value="<?= htmlspecialchars($_REQUEST['BACKUP_FILE']) ?>"/>
								<?
							}
							$arCurrentBackup = $APPLICATION->getStatusDownload();
							?>
							<div class="form-group">
								<label for="OPTIONS_PROFILE">Общий статус загрузки</label>
								<div class="progress">
									<div class="progress-bar<?= ($arCurrentBackup['TOTAL_PERCENT'] < 100 ? ' progress-bar-striped progress-bar-animated' : '') ?> bg-success" role="progressbar" aria-valuenow="<?= $arCurrentBackup['TOTAL_PERCENT'] ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?= $arCurrentBackup['TOTAL_PERCENT'] ?>%"><?= $arCurrentBackup['TOTAL_PERCENT'] ?>
										%
									</div>
								</div>
							</div>
							<div class="table-responsive">
								<table class="table table-sm">
									<thead>
									<tr>
										<th scope="col">Файл</th>
										<th scope="col">Размер</th>
										<th scope="col">Загружено</th>
										<th scope="col">Статус</th>
									</tr>
									</thead>
									<tbody>
									<?
									foreach ($arCurrentBackup['FILES'] as $strFile => $arFile) {
										$strClass = '';
										if ($arFile['STATUS'] == "OK") {
											$strClass = 'table-success';
										} elseif ($arFile['STATUS'] == "DOWNLOAD") {
											$strClass = 'table-info';
										} elseif ($arFile['STATUS'] == "DOWNLOAD_ERROR") {
											$strClass = 'table-danger';
										}
										?>
										<tr class="<?= $strClass ?>">
											<th scope="row"><?= $arFile['FILE_NAME'] ?></th>
											<td><?= AmminaBackupFormatSize($arFile['SIZE']) ?></td>
											<td>
												<?
												if ($arFile['STATUS'] == "DOWNLOAD" || $arFile['STATUS'] == "OK") {
													echo AmminaBackupFormatSize($arFile['TOTAL_DOWNLOAD']);
												}
												?>
											</td>
											<td>
												<?
												if ($arFile['STATUS'] == "DOWNLOAD") {
													?>
													<div class="progress">
														<div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" aria-valuenow="<?= $arCurrentBackup['TOTAL_PERCENT'] ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?= $arFile['PERCENT'] ?>%;"><?= $arFile['PERCENT'] ?>
															%
														</div>
													</div>
													<?
												} elseif ($arFile['STATUS'] == "OK") {
													echo "Успешно";
												} elseif ($arFile['STATUS'] == "DOWNLOAD_ERROR") {
													echo "Ошибка загрузки";
												}
												?>
											</td>
										</tr>
										<?
									}
									?>
									</tbody>
								</table>
							</div>
							<div class="form-group">
								<a href="javascript:void(0)" class="btn btn-secondary" data-toggle="tooltip" data-placement="top" title="Вернуться к настройкам загрузки резервной копии" id="amb-back">Вернуться</a>
								<a href="javascript:void(0)" class="btn btn-primary float-right<?= ($arCurrentBackup['TOTAL_PERCENT'] != 100 ? ' disabled' : '') ?>" data-toggle="tooltip" data-placement="top" title="Удалить скрипт загрузки и перейти к восстановлению резервной копии" id="amb-gorestore"<?= ($arCurrentBackup['TOTAL_PERCENT'] != 100 ? ' disabled="disabled"' : '') ?>>Перейти
									к восстановлению</a>
							</div>
							<?
						} else {
							?>
							<input type="hidden" name="action" value=""/>
							<div class="form-group">
								<label for="OPTIONS_PROFILE">Сохраненная настройка</label>
								<select class="form-control" id="OPTIONS_PROFILE" name="OPTIONS[PROFILE]">
									<option value="0"<?= ($iCurrentProfile <= 0 ? ' selected="selected"' : "") ?>>
										(указать вручную)
									</option>
									<?
									foreach ($APPLICATION->getAllProfiles() as $id => $arConfig) {
										?>
										<option value="<?= $id ?>"<?= ($iCurrentProfile == $id ? ' selected="selected"' : "") ?>><?= $arConfig['NAME'] ?></option>
										<?
									}
									?>
								</select>
							</div>
							<div class="form-group">
								<label for="OPTIONS_DRIVER">Драйвер</label>
								<select class="form-control" id="OPTIONS_DRIVER" name="OPTIONS[DRIVER]"<?= ($iCurrentProfile > 0 ? ' disabled="disabled"' : "") ?>>
									<option value="">(выберите драйвер)</option>
									<?
									foreach ($APPLICATION->getAllDrivers() as $driver => $arDriver) {
										?>
										<option value="<?= $driver ?>"<?= ($iCurrentDriver == $driver ? ' selected="selected"' : "") ?>><?= $arDriver['NAME'] ?></option>
										<?
									}
									?>
								</select>
							</div>
							<?
							if ($iCurrentDriver !== false) {
								?>
								<hr/>
								<h5 class="card-title">Параметры подключения драйвера</h5>
								<?= $oDriver->doRenderSettings($iCurrentProfile <= 0); ?>
								<hr/>
								<div class="form-group">
									<label for="OPTIONS_PATH">Путь хранения бэкапов</label>
									<input type="text" class="form-control" id="OPTIONS_PATH" name="OPTIONS[PATH]" value="<?= htmlspecialchars($APPLICATION->getBackupPath()) ?>"<?= ($iCurrentProfile > 0 ? ' disabled="disabled"' : "") ?>>
								</div>
								<div class="text-right">
									<a href="javascript:void(0)" class="btn btn-primary" data-toggle="tooltip" data-placement="top" title="Проверить подключение и получить список резервных копий" id="amb-checksettings">Проверить
										подключение</a>
								</div>
								<?
								if (isset($_SESSION['LIST_BACKUPS']) && !empty($_SESSION['LIST_BACKUPS'])) {
									?>
									<div class="form-group">
										<label for="BACKUP_FILE">Резервная копия</label>
										<select class="form-control" id="BACKUP_FILE" name="BACKUP_FILE">
											<option value=""<?= ($iCurrentProfile <= 0 ? ' selected="selected"' : "") ?>>
												(выберите резервную копию)
											</option>
											<?
											foreach ($_SESSION['LIST_BACKUPS'] as $strName => $arBackup) {
												?>
												<option value="<?= $strName ?>"<?= ($APPLICATION->getBackupFile() == $strName ? ' selected="selected"' : "") ?>><?= $arBackup['BACKUP_NAME'] ?>
													от <?= date("d.m.Y H:i:s", $arBackup['BACKUP_TIME']) ?>,
													файлов: <?= count($arBackup['FILES']) ?>,
													размер: <?= AmminaBackupFormatSize($arBackup['TOTAL_SIZE']) ?></option>
												<?
											}
											?>
										</select>
									</div>
									<div class="text-right">
										<a href="javascript:void(0)" class="btn btn-primary disabled" data-toggle="tooltip" data-placement="top" title="Начать загрузку выбранной резервной копии" id="amb-download" disabled="disabled">Загрузить</a>
									</div>
									<?
								} else {
									?>
									<div class="alert alert-danger mt-3" role="alert">
										Резервные копии не найдены или не указаны (неправильно указаны) настройки
										подключения, либо не проверены настройки подключения
									</div>
									<?
								}
							}
						}
						?>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>

<script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>
<script>
    $(document).ready(function () {
        $('[data-toggle="tooltip"]').tooltip();
        var mainForm = $("#abm-settings");
        mainForm.find("#OPTIONS_PROFILE").change(function () {
            mainForm.find("[name='action']").val("changeProfile");
            mainForm.submit();
        });
        mainForm.find("#OPTIONS_DRIVER").change(function () {
            mainForm.find("[name='action']").val("changeDriver");
            mainForm.submit();
        });
        mainForm.find("#amb-checksettings").click(function () {
            mainForm.find("[name='action']").val("checkSettings");
            mainForm.submit();
        });
        mainForm.find("#BACKUP_FILE").change(function () {
            if ($(this).val() != "") {
                mainForm.find("#amb-download").removeClass("disabled");
                mainForm.find("#amb-download").removeAttr("disabled");
            } else {
                mainForm.find("#amb-download").addClass("disabled");
                mainForm.find("#amb-download").attr("disabled", "disabled");
            }
        });
        mainForm.find("#amb-download").click(function () {
            if ($(this).attr("disabled") != "disabled") {
                mainForm.find("[name='action']").val("startDownload");
                mainForm.submit();
            }
        });
        mainForm.find("#amb-back").click(function () {
            mainForm.find("[name='action']").val("checkSettings");
            mainForm.submit();
        });
        mainForm.find("#amb-gorestore").click(function () {
            if ($(this).attr("disabled") != "disabled") {
                mainForm.find("[name='action']").val("goRestore");
                mainForm.submit();
            }
        });
		<?
		if ($APPLICATION->getModeDownload() && $arCurrentBackup['STATUS'] != "OK") {
		?>
        window.setTimeout(function () {
            $("#abm-settings").submit();
        }, 5000);
		<?
		}
		?>
    });
</script>
</body>
</html>

<?

ob_end_flush();
