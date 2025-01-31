<?
########## Proxy config ######
$proxyAddr = "";
$proxyPort = "";
$proxyUserName = "";
$proxyPassword = "";
##############################

header("Content-type: text/html; charset=utf-8");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Expires: 0");
header("Pragma: public");

error_reporting(E_ALL &~ E_NOTICE);
if(version_compare(phpversion(), '5.3.0') == -1)
	die('PHP 5.3.0 or higher is required!');

if(!function_exists('gzopen'))
	die('GZIP module is not installed!');

ob_implicit_flush(true);
set_time_limit(1800);
define('TIMEOUT',15);

if (@preg_match('#ru#i',$_SERVER['HTTP_ACCEPT_LANGUAGE']))
	$lang = 'ru';
elseif (@preg_match('#de#i',$_SERVER['HTTP_ACCEPT_LANGUAGE']))
	$lang = 'de';

if ($_REQUEST['lang'])
	$lang = $_REQUEST['lang'];

if (!in_array($lang,array('ru','en','de')))
	$lang = 'en';

define("LANG", $lang);

$this_script_name = basename(__FILE__);

umask(0);
if (file_exists($file = $_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/dbconn.php'))
	include($file);

if (!defined("BX_DIR_PERMISSIONS"))
	define("BX_DIR_PERMISSIONS", 0755);

if (!defined("BX_FILE_PERMISSIONS"))
	define("BX_FILE_PERMISSIONS", 0644);

$arEditions = array(
	'ru' => array(
		array(
			'NAME' => 'Управление сайтом',
			'LIST' => array(
				"big_business"=>"Бизнес веб-кластер",
				"portal"=>"Веб-кластер",
				"business"=>"Бизнес",
				"expert"=>"Эксперт",
				"small_business"=>"Малый бизнес",
				"standard"=>"Стандарт",
				"start"=>"Старт",
			),
		),
		array(
			'NAME' => 'Корпоративный портал',
			'LIST' => array(
	//			'intranet_company' => 'Компания',
				'intranet_team' => 'Совместная работа',
				'intranet_business' => 'Бизнес-процессы',
				'intranet_holding' => 'Холдинг',
			)
		),
		array(
			'NAME' => 'Решения для государственных организаций',
			'LIST' => array(
				'gov/gossite' => 'Официальный сайт государственной организации',
				'gov/gossite_ext' => 'Официальный сайт государственной организации (расширенный)',
				'gov/gosportal' => 'Внутренний портал государственной организации',
				'gossite_ua' => 'Офіційний сайт державної організації для України',
			),
		),
		array(
			'NAME' => 'Решения для образования',
			'LIST' => array(
				'edu/eduportal' => 'Внутренний портал учебного заведения',
				'edu/edusite' => 'Сайт учебного заведения',
				'school' => 'Сайт школы',
				'school_base' => 'Сайт школы (базовый)',
				'ppk' => 'Портал повышения квалификации',
			)
		),
		array(
			'NAME' => 'Решения для 1С:Франчайзи',
			'LIST' => array(
				'franchise_std' => 'Сайт 1С-Франчайзи',
				'franchise_biz' => 'Интернет-магазин 1С:Франчайзи',
			)
		),
		array(
			'NAME' => 'Решения для здравоохранения',
			'LIST' => array(
				'medsite_base' => 'Сайт медицинской организации (Базовый)',
				'medsite' => 'Сайт медицинской организации (Расширенный)',
				'medportal' => 'Портал органа управления здравоохранением',
			)
		),
		array(
			'NAME' => 'Сайт конференций',
			'LIST' => 'conf/conference',
		),
	),
	'en' => array(
		array(
			'NAME' => 'Bitrix Site Manager',
			'LIST' => array(
				"enterprise" => "Enterprise",
				"professional" => "Professional",
				"smb" => "Small Business",
				"std" => "Standard",
				"str" => "Start",
				"premium" => "Premium",
				"ultimate" => "Ultimate",
			)
		),
		array(
			'NAME' => 'Bitrix24',
			'LIST' => array(
				'portal/intranet_teamspace' => 'TeamPace',
				'portal/intranet_bizspace' => 'BizPace',
				'portal/en_intranet_holding' => 'BizPace Enterprise'
			),
		)
	),
	'de' => array(
		array(
			'NAME' => 'Bitrix Site Manager',
			'LIST' => array(
				"de/de_enterprise" => "Enterprise",
				"de/de_professional" => "Professional",
				"de/de_smb" => "Small Business",
				"de/de_std" => "Standard",
				"de/de_str" => "Start",
				"de/de_premium" => "Premium",
				"de/de_ultimate" => "Ultimate",
			),
		),
		array(
			'NAME' => 'Bitrix24',
			'LIST' => array(
				'de/de_intranet_teamspace' => 'TeamPace',
				'de/de_intranet_bizspace' => 'BizPace',
				'de/de_intranet_holding' => 'BizPace Enterprise'
			),
		)
	)
);

####### MESSAGES ########
$MESS = array();
$ar = array();
if (LANG == "ru")
{
	$MESS["NO_PERMS"] = "Нет прав на запись в файл ";
	$MESS["LOADER_LICENSE_KEY"] = "Лицензионный ключ";
	$MESS["INTRANET"] = "Корпоративный портал";
	$MESS["LOADER_TITLE"] = "Загрузка продуктов \"1С-Битрикс\"";
	$MESS["UPDATE_SUCCESS"] = "Обновлено успешно. <a href='?'>Открыть</a>.";
	$MESS["LOADER_NEW_STEPS"] = "Загрузка продуктов \"1С-Битрикс\"";
	$MESS["LOADER_SUBTITLE1"] = "Загрузка продукта";
	$MESS["LOADER_SUBTITLE2"] = "1С-Битрикс";
	$MESS["LOADER_MENU_LIST"] = "Выбор дистрибутива";
	$MESS["LOADER_MENU_LOAD"] = "Загрузка дистрибутива с сервера";
	$MESS["LOADER_MENU_UNPACK"] = "Распаковка дистрибутива";
	$MESS["LOADER_TECHSUPPORT"] = "";
	$MESS["LOADER_TITLE_LIST"] = "Выбор дистрибутива";
	$MESS["LOADER_TITLE_LOAD"] = "Загрузка дистрибутива на сайт";
	$MESS["LOADER_TITLE_UNPACK"] = "Распаковка дистрибутива";
	$MESS["LOADER_TITLE_LOG"] = "Отчет по загрузке";
	$MESS["LOADER_SAFE_MODE_ERR"] = "<font color=\"#FF0000\"><b>Внимание!</b></font> PHP на вашем сайте работает в Safe Mode. Установка продукта в автоматическом режиме невозможна. Пожалуйста, обратитесь в службу технической поддержки для получения дополнительной информации.";
	$MESS["LOADER_NO_PERMS_ERR"] = "<font color=\"#FF0000\"><b>Внимание!</b></font> PHP не имеет прав на запись в корневую папку #DIR# вашего сайта. Загрузка продукта может оказаться невозможной. Пожалуйста, установите необходимые права на корневую папку вашего сайта или обратитесь к администраторам вашего хостинга.";
	$MESS["LOADER_EXISTS_ERR"] = "";
	$MESS["LOADER_IS_DISTR"] = "На сайте найдены загруженые дистрибутивы. Нажмите на название любого из дистрибутивов для его распаковки:";
	$MESS["LOADER_OVERWRITE"] = "<b>Внимание!</b> Существующие на сайте файлы могут быть перезаписаны файлами из дистрибутива.";
	$MESS["LOADER_IS_DISTR_PART"] = "На сайте найдены недогруженные дистрибутивы. Нажмите на название любого из недогруженных дистрибутивов для полной загрузки:";
	$MESS["LOADER_NEW_LOAD_TITLE"] = "Загрузка дистрибутива с сайта <a href=\"http://www.1c-bitrix.ru\" target=\"_blank\">http://www.1c-bitrix.ru</a>";
	$MESS["LOADER_NEW_ED"] = "Выбор дистрибутива";
	$MESS["LOADER_NEW_VERSION"] = "Доступна новая версия скрипта установки, но загрузить её не удалось";
	$MESS["LOADER_NEW_AUTO"] = "Автоматически запустить распаковку после загрузки";
	$MESS["LOADER_NEW_STEPS"] = "Загружать по шагам с шагом";
	$MESS["LOADER_NEW_STEPS0"] = "неограниченно долгим";
	$MESS["LOADER_NEW_STEPS30"] = "не более 30 секунд";
	$MESS["LOADER_NEW_STEPS60"] = "не более 60 секунд";
	$MESS["LOADER_NEW_STEPS120"] = "не более 120 секунд";
	$MESS["LOADER_NEW_STEPS180"] = "не более 180 секунд";
	$MESS["LOADER_NEW_STEPS240"] = "не более 240 секунд";
	$MESS["LOADER_NEW_LOAD"] = "Загрузить";
	$MESS["LOADER_DESCR"] = "Этот скрипт предназначен для загрузки дистрибутивов \"1С-Битрикс\" с сайта <a href=\"http://www.1c-bitrix.ru/download/index.php\" target=\"_blank\">www.1c-bitrix.ru</a> непосредственно на ваш сайт, а так же для распаковки дистрибутива на вашем сайте.<br><br> Загрузите этот скрипт в корневую папку вашего сайта и откройте его в браузере (введите в адресной строке браузера <nobr>http://&lt;ваш сайт&gt;/".$this_script_name."</nobr>).";
	$MESS["LOADER_BACK_2LIST"] = "Вернуться в список дистрибутивов";
	$MESS["LOADER_BACK"] = "Назад";
	$MESS["LOADER_LOG_ERRORS"] = "Произошли следующие ошибки:";
	$MESS["LOADER_NO_LOG"] = "Log-файл не найден";
	$MESS["LOADER_BOTTOM_NOTE1"] = "<b><font color=\"#FF0000\">Внимание!</font></b> По окончании установки продукта <b>обязательно</b> удалите скрипт <nobr>/".$this_script_name."</nobr> с вашего сайта. Доступ постороннего человека к этому скрипту может повлечь за собой нарушение работы вашего сайта.";
	$MESS["LOADER_KB"] = "кб";
	$MESS["LOADER_LOAD_QUERY_SERVER"] = "Запрашиваю сервер...";
	$MESS["LOADER_LOAD_QUERY_DISTR"] = "Запрашиваю дистрибутив #DISTR#";
	$MESS["LOADER_LOAD_CONN2HOST"] = "Открываю соединение к #HOST#...";
	$MESS["LOADER_LOAD_NO_CONN2HOST"] = "Не могу соединиться с #HOST#:";
	$MESS["LOADER_LOAD_QUERY_FILE"] = "Запрашиваю файл...";
	$MESS["LOADER_LOAD_WAIT"] = "Ожидаю ответ...";
	$MESS["LOADER_LOAD_SERVER_ANSWER"] = "Ошибка загрузки. Ответа сервера:<br><br> #ANS#";
	$MESS["LOADER_LOAD_SERVER_ANSWER1"] = "Ответ сервера: 403 Доступ запрещён.<br>Проверьте правильность ввода ключа.";
	$MESS["LOADER_LOAD_NEED_RELOAD"] = "Докачка дистрибутива невозможна. Начинаю качать заново.";
	$MESS["LOADER_LOAD_NO_WRITE2FILE"] = "Не могу открыть файл #FILE# на запись";
	$MESS["LOADER_LOAD_LOAD_DISTR"] = "Загружаю дистрибутив #DISTR#";
	$MESS["LOADER_LOAD_ERR_SIZE"] = "Ошибка размера файла";
	$MESS["LOADER_LOAD_ERR_RENAME"] = "Не могу переименовать файл #FILE1# в файл #FILE2#";
	$MESS["LOADER_LOAD_CANT_OPEN_WRITE"] = "Не могу открыть файл #FILE# на запись";
	$MESS["LOADER_LOAD_CANT_OPEN_READ"] = "Не могу открыть файл #FILE# на чтение";
	$MESS["LOADER_LOAD_LOADING"] = "Загружаю файл... дождитесь окончания загрузки...";
	$MESS["LOADER_LOAD_FILE_SAVED"] = "Файл сохранен: #FILE# [#SIZE# байт]";
	$MESS["LOADER_UNPACK_ACTION"] = "Распаковываю дистрибутив... дождитесь окончания распаковки...";
	$MESS["LOADER_UNPACK_UNKNOWN"] = "Неизвестная ошибка. Повторите процесс еще раз или обратитесь в службу технической поддержки";
	$MESS["LOADER_UNPACK_DELETE"] = "Ошибка удаления временных файлов. Удалите файлы вручную.";
	$MESS["LOADER_UNPACK_SUCCESS"] = "Дистрибутив успешно распакован";
	$MESS["LOADER_UNPACK_ERRORS"] = "Дистрибутив распакован с ошибками";
	$MESS["LOADER_KEY_DEMO"] = "Демонстрационная версия";
	$MESS["LOADER_KEY_COMM"] = "Коммерческая версия";
	$MESS["LOADER_KEY_TITLE"] = "Введите лицензионный ключ";
}
elseif (LANG == "de")
{
	$MESS["NO_PERMS"] = "Nicht genunugend Rechte um die Datei zu beschreiben";
        $MESS["LOADER_LICENSE_KEY"] = "Lizenzschlussel";
        $MESS["INTRANET"] = "Intranet Portal ";
        $MESS["LOADER_TITLE"] = "Download der \"Bitrix\" Software";
        $MESS["UPDATE_SUCCESS"] = "Aktualisierung erfolgreich durchgefuhrt. <a href='?'>Offnen?</a>.";
        $MESS["LOADER_NEW_STEPS"] = "Download der \"Bitrix\" Software";
        $MESS["LOADER_SUBTITLE1"] = "Softwaredownload";
        $MESS["LOADER_SUBTITLE2"] = "Bitrix ";
        $MESS["LOADER_MENU_LIST"] = "Auswahl des Installationspacks";
        $MESS["LOADER_MENU_LOAD"] = "Download des Installationspacks von dem Server";
        $MESS["LOADER_MENU_UNPACK"] = "Auspacken des Installationspacks";
        $MESS["LOADER_TECHSUPPORT"] = "";
        $MESS["LOADER_TITLE_LIST"] = "Auswahl des Installationspacks";
        $MESS["LOADER_TITLE_LOAD"] = "Upload des Installationspacks auf die Website";
        $MESS["LOADER_TITLE_UNPACK"] = "Auspacken des Installationspacks";
        $MESS["LOADER_TITLE_LOG"] = "Uploadbericht";
        $MESS["LOADER_SAFE_MODE_ERR"] = "<font color=\"#FF0000\"><b>Achtung!</b></font> PHP auf Ihrer Website arbeitet im Safe Mode. Die automatische Installation der Software ist nicht moglich. Bitte wenden Sie sich fur weitere Informationen an den technischen Support.";
        $MESS["LOADER_NO_PERMS_ERR"] = "<font
color=\"#FF0000\"><b>Achtung!</b></font> PHP hat nicht genugend Rechte um das Hautverzeichnis #DIR# Ihrer Website zu uberschreiben. Es konnen Fehler bei der Installation auftreten. Bitte stellen Sie alle erforderlichen Rechte ein, oder wenden Sie sich an Ihren Hosting-Anbieter.";
        $MESS["LOADER_EXISTS_ERR"] = "";
        $MESS["LOADER_IS_DISTR"] = "Hochgeladene Installationspacks wurden gefunden. Klicken Sie auf den Namen des erforderlichen Installationspacks um mit dem Auspacken zu beginnen:";
        $MESS["LOADER_OVERWRITE"] = "<b>Achtung!</b> Die existierenden Dateien konnen durch die Dateien aus dem Installationspack uberschrieben werden.";
        $MESS["LOADER_IS_DISTR_PART"] = "Auf der Website wurden nicht vollstandig geladenen Installationspacks hochgeladen. Klicken Sie auf den Namen des erforderlichen Installationspacks um mit Upload fortzufuhren:";
        $MESS["LOADER_NEW_LOAD_TITLE"] = "Download des Installationspacks von der Site <a href=\"http://www.bitrix.de\" target=\"_blank\">http://www.bitrix.de</a>";
        $MESS["LOADER_NEW_ED"] = "Auswahl des Installationspacks";
        $MESS["LOADER_NEW_VERSION"] = "Neue Version des Installationsskripts ins verfugbar!";
        $MESS["LOADER_NEW_AUTO"] = "Auspacken automatisch nach dem Upload starten";
        $MESS["LOADER_NEW_STEPS"] = "Hochladen in folgenden Schritten";
        $MESS["LOADER_NEW_STEPS0"] = "uneingeschrankt ";
        $MESS["LOADER_NEW_STEPS30"] = "max. 30 Sekunden";
        $MESS["LOADER_NEW_STEPS60"] = " max. 60 Sekunden";
        $MESS["LOADER_NEW_STEPS120"] = "max. 120 Sekunden";
        $MESS["LOADER_NEW_STEPS180"] = "max. 180 Sekunden";
        $MESS["LOADER_NEW_STEPS240"] = "max. 240 Sekunden";
        $MESS["LOADER_NEW_LOAD"] = "Hochladen";
        $MESS["LOADER_DESCR"] = "Dieses Skript ist dient dem Download der \"Bitrix\"-Installationspacks von der Website <a
href=\"http://www.bitrix.de/download/index.php\" target=\"_blank\">www.bitrix.de</a> direkt auf Ihre Website, sowie dem Auspacken des Installationspacks auf Ihrer Website.<br><br> 
Laden Sie dieses Skript in das Hauptverzeichnis, und offnen Sie es in Ihrem Internet-Browser. Geben Sie dafur in der Adresszeile <nobr>http://&amp;lt;IhreWebsite&amp;gt;/".$this_script_name."</nobr>).";
        $MESS["LOADER_BACK_2LIST"] = "Zur der Installationspack-Liste zuruckkehren";
        $MESS["LOADER_BACK"] = "Zuruck";
        $MESS["LOADER_LOG_ERRORS"] = "Folgende Fehler sind aufgetreten:";
        $MESS["LOADER_NO_LOG"] = "Log-Datei nicht gefunden";
        $MESS["LOADER_BOTTOM_NOTE1"] = "<b><font color=\"#FF0000\">Achtung!</font></b> Nach der Installation des Produkts loschen Sie <b>unbedingt</b> das Skript<nobr>/".$this_script_name."</nobr> von Ihrer Website. Der Fremdzugriff zu diesem Skript kann fehlerhafte Funktion Ihrer Website mit sich fuhren.";
        $MESS["LOADER_KB"] = "кб";
        $MESS["LOADER_LOAD_QUERY_SERVER"] = "Anfrage an den Server...";
        $MESS["LOADER_LOAD_QUERY_DISTR"] = "Anfrage fur das Installationspack #DISTR#";
        $MESS["LOADER_LOAD_CONN2HOST"] = "Aufbau der Verbindung mit #HOST#...";
        $MESS["LOADER_LOAD_NO_CONN2HOST"] = "Keine Verbindung mit #HOST#:";
        $MESS["LOADER_LOAD_QUERY_FILE"] = "Anfrage fur die Datei...";
        $MESS["LOADER_LOAD_WAIT"] = "Abwarten der Ruckmeldung...";
        $MESS["LOADER_LOAD_SERVER_ANSWER"] = "Upload-Fehler. Serverruckmeldung:<br><br> #ANS#";
        $MESS["LOADER_LOAD_SERVER_ANSWER1"] = " Serverruckmeldung: 403 Zugriff verweigert.<br>Uberprufen Sie die Richtigkeit des Codes.";
        $MESS["LOADER_LOAD_NEED_RELOAD"] = "Fortfuhrung des Downloadvorgangs nicht moglich. Der Downloadvorgang wird erneut ausgefuhrt.";
        $MESS["LOADER_LOAD_NO_WRITE2FILE"] = "Die #FILE# kann nicht bearbeitet werden";
        $MESS["LOADER_LOAD_LOAD_DISTR"] = "Installationspack #DISTR# wird hochgeladen";
        $MESS["LOADER_LOAD_ERR_SIZE"] = "Fehler bei der Dateigro?e";
        $MESS["LOADER_LOAD_ERR_RENAME"] = "Die Datei #FILE1# kann nicht in #FILE2# umbenannt warden";
        $MESS["LOADER_LOAD_CANT_OPEN_WRITE"] = "Die Datei #FILE# kann nicht bearbeitet werden";
        $MESS["LOADER_LOAD_CANT_OPEN_READ"] = "Die Datei #FILE# kann nicht gelesen werden";
        $MESS["LOADER_LOAD_LOADING"] = "Upload-Vorgang lauft... Bitte warten Sie bis der Vorgang beendet wird.";
        $MESS["LOADER_LOAD_FILE_SAVED"] = "Datei gespeichert: #FILE# [#SIZE# Byte]";
        $MESS["LOADER_UNPACK_ACTION"] = " Das Installationspack wird ausgepackt... Bitte warten Sie bis der Vorgang beendet wird.";
        $MESS["LOADER_UNPACK_UNKNOWN"] = "Unbekannter Fehler. Fuhren Sie den Vorgang nochmal aus, oder wenden Sie sich an den technischen Support";
        $MESS["LOADER_UNPACK_DELETE"] = "Fehler beim Loschen der temporaren Dateien. Loschen Sie bitte die Dateien manuell.";
        $MESS["LOADER_UNPACK_SUCCESS"] = "Das Installationspack wurde erfolgreich ausgepackt";
        $MESS["LOADER_UNPACK_ERRORS"] = " Das Installationspack wurde mit Fehlern ausgepackt ";
        $MESS["LOADER_KEY_DEMO"] = "Testversion";
        $MESS["LOADER_KEY_COMM"] = "Vollversion";
        $MESS["LOADER_KEY_TITLE"] = "Lizenzcode eingeben";
}
else
{
	$MESS["NO_PERMS"] = "No permissions to write the file ";
	$MESS["LOADER_LICENSE_KEY"] = "Your license key";
	$MESS["INTRANET"] = "Bitrix Intranet Portal";
	$MESS["LOADER_TITLE"] = "Loading Product \"Bitrix Site Manager\" or \"Bitrix Intranet Portal\"";
	$MESS["UPDATE_SUCCESS"] = "Successful update. <a href='?'>Open</a>.";
	$MESS["LOADER_SUBTITLE1"] = "Loading";
	$MESS["LOADER_SUBTITLE2"] = "Bitrix Site Manager or Bitrix Intranet Portal";
	$MESS["LOADER_MENU_LIST"] = "Choose a package";
	$MESS["LOADER_MENU_LOAD"] = "Download installation package from server";
	$MESS["LOADER_MENU_UNPACK"] = "Unpacking the Installation Package";
	$MESS["LOADER_TECHSUPPORT"] = "";
	$MESS["LOADER_TITLE_LIST"] = "Select installation package";
	$MESS["LOADER_TITLE_LOAD"] = "Uploading installation package to the site";
	$MESS["LOADER_TITLE_UNPACK"] = "Unpacking the Installation Package";
	$MESS["LOADER_TITLE_LOG"] = "Upload report";
	$MESS["LOADER_SAFE_MODE_ERR"] = "<font color=\"#FF0000\"><b>Attention!</b></font> Your PHP functions in Safe Mode. The Setup cannot proceed in automatic mode. Please consult the technical support service for additional instructions.";
	$MESS["LOADER_NO_PERMS_ERR"] = "<font color=\"#FF0000\"><b>Attention!</b></font> PHP has not enough permissions to write to the root directory #DIR# of your site. Loading is likely to fail. Please set the required access permissions to the root directory of your site or consult administrators of your hosting service.";
	$MESS["LOADER_EXISTS_ERR"] = "";
	$MESS["LOADER_IS_DISTR"] = "Uploaded installation packages were found on the site. Click the name of any package to start installation:";
	$MESS["LOADER_OVERWRITE"] = "<b>Attention!</b> Files currently present on your site will possibly be overwritten with files from the package.";
	$MESS["LOADER_IS_DISTR_PART"] = "Incompletely uploaded installation packages were found on the site. Click the name of any package to finish loading:";
	$MESS["LOADER_NEW_LOAD_TITLE"] = "Download new installation package from <a href=\"http://www.bitrixsoft.com\" target=\"_blank\">http://www.bitrixsoft.com</a>";
	$MESS["LOADER_NEW_VERSION"] = "New version of bitrixsetup script is available!";
	$MESS["LOADER_NEW_ED"] = "Choose a package";
	$MESS["LOADER_NEW_AUTO"] = "automatically start unpacking after loading";
	$MESS["LOADER_NEW_STEPS"] = "load gradually with interval:";
	$MESS["LOADER_NEW_STEPS0"] = "unlimited";
	$MESS["LOADER_NEW_STEPS30"] = "less than 30 seconds";
	$MESS["LOADER_NEW_STEPS60"] = "less than 60 seconds";
	$MESS["LOADER_NEW_STEPS120"] = "less than 120 seconds";
	$MESS["LOADER_NEW_STEPS180"] = "less than 180 seconds";
	$MESS["LOADER_NEW_STEPS240"] = "less than 240 seconds";
	$MESS["LOADER_NEW_LOAD"] = "Download";
	$MESS["LOADER_DESCR"] = "";
	$MESS["LOADER_BACK_2LIST"] = "Back to packages list";
	$MESS["LOADER_BACK"] = "Back";
	$MESS["LOADER_LOG_ERRORS"] = "The following errors occured:";
	$MESS["LOADER_NO_LOG"] = "Log file not found";
	$MESS["LOADER_BOTTOM_NOTE1"] = "<b><font color=\"#FF0000\">Attention!</font></b> After you have finished installing, <b>please be sure</b> to delete the script <nobr>/".$this_script_name."</nobr> from your site. Otherwise, unauthorized persons may access this script and damage your site.";
	$MESS["LOADER_KB"] = "kb";
	$MESS["LOADER_LOAD_QUERY_SERVER"] = "Connecting server...";
	$MESS["LOADER_LOAD_QUERY_DISTR"] = "Requesting package #DISTR#";
	$MESS["LOADER_LOAD_CONN2HOST"] = "Establishing connection to #HOST#...";
	$MESS["LOADER_LOAD_NO_CONN2HOST"] = "Cannot connect to #HOST#:";
	$MESS["LOADER_LOAD_QUERY_FILE"] = "Requesting file...";
	$MESS["LOADER_LOAD_WAIT"] = "Waiting for response...";
	$MESS["LOADER_LOAD_SERVER_ANSWER"] = "Error while downloading. Server reply was: #ANS#";
	$MESS["LOADER_LOAD_SERVER_ANSWER1"] = "Server reply: 403 Forbidden.<br>Please check your licence key.";
	$MESS["LOADER_LOAD_NEED_RELOAD"] = "Cannot resume download. Starting new download.";
	$MESS["LOADER_LOAD_NO_WRITE2FILE"] = "Cannot open file #FILE# for writing";
	$MESS["LOADER_LOAD_LOAD_DISTR"] = "Downloading package #DISTR#";
	$MESS["LOADER_LOAD_ERR_SIZE"] = "File size error";
	$MESS["LOADER_LOAD_ERR_RENAME"] = "Cannot rename file #FILE1# to #FILE2#";
	$MESS["LOADER_LOAD_CANT_OPEN_WRITE"] = "Cannot open file #FILE# for writing";
	$MESS["LOADER_LOAD_CANT_OPEN_READ"] = "Cannot open file #FILE# for reading";
	$MESS["LOADER_LOAD_LOADING"] = "Download in progress. Please wait...";
	$MESS["LOADER_LOAD_FILE_SAVED"] = "File saved: #FILE# [#SIZE# bytes]";
	$MESS["LOADER_UNPACK_ACTION"] = "Unpacking the package. Please wait...";
	$MESS["LOADER_UNPACK_UNKNOWN"] = "Unknown error occured. Please try again or consult the technical support service";
	$MESS["LOADER_UNPACK_DELETE"] = "Errors occured while deleting temporary files";
	$MESS["LOADER_UNPACK_SUCCESS"] = "The installation package successfully unpacked";
	$MESS["LOADER_UNPACK_ERRORS"] = "Errors occured while unpacking the installation package";
	$MESS["LOADER_KEY_DEMO"] = "Demo version";
	$MESS["LOADER_KEY_COMM"] = "Commercial version";
	$MESS["LOADER_KEY_TITLE"] = "Specify license key";
}
####### /MESSAGES ########

function LoaderGetMessage($name)
{
	global $MESS;
	return $MESS[$name];
}

$strAction = $_REQUEST["action"];

$bx_host = 'www.1c-bitrix.ru';
$bx_url = '/download/files/scripts/'.$this_script_name;
$form = '';


$strError = '';
if ($strAction!="LOAD" && $strAction!="UNPACK" && $strAction!="LOG")
{
	$strAction = "LIST";

	if (!$proxyAddr)
	{
		// Check for updates
		$res = @fsockopen($bx_host, 80, $errno, $errstr, 3);

		if($res) 
		{
			$strRequest = "HEAD ".$bx_url." HTTP/1.1\r\n";
			$strRequest.= "Host: ".$bx_host."\r\n";
			$strRequest.= "\r\n";

			fputs($res, $strRequest);

			while ($line = fgets($res, 4096))
			{
				if (@preg_match("/Content-Length: *([0-9]+)/i", $line, $regs))
				{
					if (filesize(__FILE__) != trim($regs[1]))
					{
						$tmp_name = $this_script_name.'.tmp';
						if (LoadFile('http://'.$bx_host.$bx_url, $tmp_name, 0))
						{
							if (rename($_SERVER['DOCUMENT_ROOT'].'/'.$tmp_name,__FILE__))
							{
								bx_accelerator_reset();
								echo '<script>document.location="?lang='.LANG.'";</script>'.LoaderGetMessage('UPDATE_SUCCESS');
								die();
							}
							else
								$strError = str_replace("#FILE#", $this_script_name, LoaderGetMessage("LOADER_LOAD_CANT_OPEN_WRITE"));
						}
						else
							$strError = LoaderGetMessage('LOADER_NEW_VERSION');
					}
					break;
				}
			}
			fclose($res);
		}
	}
}

if ($strAction=="UNPACK" && (!isset($_REQUEST["filename"]) || strlen($_REQUEST["filename"])<=0))
	$strAction = "LIST";


$script = '';
if ($strAction=="LIST")
{
	$txt = '';
	if ($strError)
		$txt = ShowError($strError);

	/*************************************************/
	if (ini_get("safe_mode") == "1")
		$txt .= LoaderGetMessage("LOADER_SAFE_MODE_ERR") . '<br>';

	if (!is_writable($_SERVER["DOCUMENT_ROOT"]))
		$txt .= str_replace("#DIR#", $_SERVER["DOCUMENT_ROOT"], LoaderGetMessage("LOADER_NO_PERMS_ERR")) . '<br>';

	if (file_exists($_SERVER["DOCUMENT_ROOT"]."/bitrix")
		&& is_dir($_SERVER["DOCUMENT_ROOT"]."/bitrix"))
		$txt .= LoaderGetMessage("LOADER_EXISTS_ERR") . '<br>';

	$arLocalDistribs = array();
	$arLocalDistribs_tmp = array();

	$handle = opendir($_SERVER["DOCUMENT_ROOT"]);
	if ($handle)
	{
		while (false !== ($ffile = readdir($handle)))
		{
			if (!is_file($_SERVER["DOCUMENT_ROOT"]."/".$ffile))
				continue;

			if (strtolower(substr($ffile, -7))==".tar.gz")
				$arLocalDistribs[] = $ffile;
			elseif (strtolower(substr($ffile, -11))==".tar.gz.tmp")
				$arLocalDistribs_tmp[] = $ffile;
			elseif (strtolower(substr($ffile, -11))==".tar.gz.log")
				$arLocalDistribs_tmp[] = $ffile;
		}
		closedir($handle);
	}

	if (count($arLocalDistribs)>0)
	{
		$txt .= LoaderGetMessage("LOADER_IS_DISTR").'<br>';
		for ($i = 0; $i < count($arLocalDistribs); $i++)
			$txt .= '<a href="'.$this_script_name.'?action=UNPACK&filename='.urlencode($arLocalDistribs[$i]).'&by_step=Y">'.$arLocalDistribs[$i].'</a><br><br>';
		$txt .= LoaderGetMessage("LOADER_OVERWRITE") . '<br><br>';
	}


	if (count($arLocalDistribs_tmp)>0 && $_REQUEST['action']=='LIST')
	{
//		$txt .= '<br>'.LoaderGetMessage("LOADER_IS_DISTR_PART") . '<br>';
		foreach($arLocalDistribs_tmp as $distr)
			@unlink($_SERVER['DOCUMENT_ROOT'].'/'.$distr);
//			$txt .= '<a href="'.$this_script_name.'?action=LOAD&url='.urlencode(substr($arLocalDistribs_tmp[$i], 0, strlen($arLocalDistribs_tmp[$i])-4)).'">'.$arLocalDistribs_tmp[$i].'</a><br>';
	}


	$txt .= '<script>
		function show_select(k)
		{
			document.getElementById("download_button").disabled = false;
	';
	foreach($arEditions[LANG] as $k => $ED)
		$txt .= 'if (ob = document.getElementById("select_'.$k.'"))
				ob.disabled = "disabled";
			';
	$txt .= '
			if (ob = document.getElementById("select_" + k))
				ob.disabled = "";
		}
		</script>
	';

	$form = '
	<form method="post" action="'.$this_script_name.'?action=LOAD" onsubmit="document.getElementById(\'download_button\').disabled=true">
	<input type=hidden name=lang value="'.LANG.'">
	';

	$txt .= '<table cellspacing=0 cellpadding=2 border=0>';
	$txt .= '<tr><td colspan=2>'.LoaderGetMessage("LOADER_NEW_ED").':</td></tr>';

	foreach($arEditions[LANG] as $k => $ED)
	{
		$txt.=' 
		<tr><td style="padding-left:10px;"><input name=edition type=radio value="'.$k.'" id="radio_'.$k.'" onclick="show_select('.$k.')"> <label for="radio_'.$k.'">'.$ED['NAME'].'</label></td>';

		$txt .= '<td>';
		if (is_array($ED['LIST']))
		{
			$txt .= '
				<select name="url" disabled id="select_'.$k.'">';
					foreach ($ED['LIST'] as $key => $value)
						$txt .= '<option value="'.$key.'">'.$value.'</option>';
				$txt .= '
				</select>';
		}
		$txt .= '</td>';
	}


	$txt.='
		<tr>
			<td colspan=2 height=15></td>
		</tr>
		<tr>
			<td colspan=2 style="padding-left:10px"><input type="radio" name="licence_type" value="demo" checked id="licence_type_1" onclick="document.getElementById(\'license_key\').disabled=\'disabled\'"><label for="licence_type_1">'.LoaderGetMessage("LOADER_KEY_DEMO").'</label></td>
		</tr>
		<tr>
			<td colspan=2 style="padding-left:10px"><input type="radio" name="licence_type" value="src" id="licence_type_2" onclick="document.getElementById(\'license_key\').disabled=\'\'"><label for="licence_type_2">'.LoaderGetMessage("LOADER_KEY_COMM").'</label></td>
		</tr>
		<tr>
			<td style="padding-left:10px;" align=right>'.LoaderGetMessage("LOADER_LICENSE_KEY").': </td>
			<td><input type="text" disabled value="DEMO" id="license_key" name="LICENSE_KEY" size="20" title="'.LoaderGetMessage("LOADER_KEY_TITLE").'"></td>
		</tr>				
				
	</table>';

		$ar = array(
			'FORM' => $form,
			'TITLE' => LoaderGetMessage("LOADER_TITLE"),
			'HEAD' => LoaderGetMessage("LOADER_MENU_LIST"),
			'TEXT' => $txt,
			'TEXT_ALIGN' => 'top',
			'BOTTOM' => (file_exists($_SERVER['DOCUMENT_ROOT'].'/index.php')?'<input type="button" value="&nbsp;&nbsp;&nbsp;'.LoaderGetMessage("LOADER_BACK").'&nbsp;&nbsp;&nbsp;" onclick="document.location=\'/index.php?lang='.LANG.'\'">&nbsp;':'').
					'<input type="submit" value="&nbsp;&nbsp;&nbsp;'.LoaderGetMessage("LOADER_NEW_LOAD").'&nbsp;&nbsp;&nbsp;" id="download_button" disabled>'
		);

	/*************************************************/
}
elseif ($strAction=="LOAD")
{
	/*********************************************************************/

	if(LANG == "ru")
		$site = "http://www.1c-bitrix.ru/";
	else
		$site = "http://www.bitrixsoft.com/";

	if($_REQUEST['licence_type'] == 'src' || $_REQUEST['LICENSE_KEY'])
	{
		$path = 'private/download/';
		$suffix = '_source.tar.gz';
		@mkdir($_SERVER['DOCUMENT_ROOT'].'/bitrix');
		$fres = @fopen($_SERVER['DOCUMENT_ROOT'].'/bitrix/license_key.php','wb');
		@fwrite($fres,'<'.'? $LICENSE_KEY = "'.EscapePHPString($_REQUEST['LICENSE_KEY']).'"; ?'.'>') && fclose($fres);
	}
	else
	{
		@unlink($_SERVER['DOCUMENT_ROOT'].'/bitrix/license_key.php');
		$path = 'download/';
		if(version_compare(phpversion(), '5.0.0') == -1)
			$suffix = '_encode_php4.tar.gz';
		else
			$suffix = '_encode_php5.tar.gz';
	}

	$ED = $arEditions[LANG][$_REQUEST['edition']];
	if (is_array($ED['LIST']))
		$url = $_REQUEST['url'];
	else
		$url = $ED['LIST'];

	if ($_REQUEST['LICENSE_KEY'] && false !== $p = strpos($url, '/'))
		$url = substr($url,$p+1);

	$strRequestedUrl = $site.$path.$url.$suffix;

	$iTimeOut = TIMEOUT;

	$strUserAgent = "BitrixSiteLoader";
	$strFilename = $_SERVER["DOCUMENT_ROOT"]."/".basename($strRequestedUrl);

	$strLog = '';
	$status = '';
	$res = LoadFile($strRequestedUrl.($_REQUEST["LICENSE_KEY"]<>''? "?lp=".md5($_REQUEST["LICENSE_KEY"]):''), $strFilename, $iTimeOut);
	if (!$res)
		$txt = nl2br($strLog);
	elseif ($res==2) // частичная закачка
	{
		$txt = $status;
		$script = "<script>GoToPage(\"".$this_script_name."?action=LOAD&edition=".urlencode($_REQUEST['edition'])."&url=".urlencode($url)."&lang=".urlencode(LANG)."&LICENSE_KEY=".urlencode($_REQUEST["LICENSE_KEY"])."&action_next=".urlencode($_REQUEST["action_next"])."&xz=".rand(0, 32000)."\");</script>\n";
	}
	else
	{
		$txt = $status;
		$script = "<script>GoToPage(\"".$this_script_name."?action=UNPACK&by_step=Y&filename=".urlencode(basename($strRequestedUrl))."&lang=".urlencode(LANG)."&xz=".rand(0, 32000)."\");</script>\n";
	}

	$ar = array(
		'FORM' => $form,
		'TITLE' => LoaderGetMessage("LOADER_MENU_LOAD"),
		'HEAD' => LoaderGetMessage("LOADER_MENU_LOAD"),
		'TEXT' => $txt,
		'BOTTOM' => '<input type="button" value="&nbsp;&nbsp;&nbsp;'.LoaderGetMessage("LOADER_BACK").'&nbsp;&nbsp;&nbsp;" onclick="document.location=\''.$this_script_name.'?action=LIST&lang='.LANG.'\'">'
	);

	/*********************************************************************/
}
elseif ($strAction=="UNPACK")
{
	/*********************************************************************/
//	$iNumDistrFiles = 8000;

	SetCurrentStatus(LoaderGetMessage("LOADER_UNPACK_ACTION"));
	$oArchiver = new CArchiver($_SERVER["DOCUMENT_ROOT"]."/".$_REQUEST["filename"], true);
	$tres = $oArchiver->extractFiles($_SERVER["DOCUMENT_ROOT"]);

	SetCurrentProgress($oArchiver->iCurPos, $oArchiver->iArchSize, False);
	$txt = $status;
	if ($tres)
	{
		if (!$oArchiver->bFinish)
			$script = "<script>GoToPage(\"".$this_script_name."?action=UNPACK&filename=".urlencode(basename($oArchiver->_strArchiveName))."&by_step=Y&seek=".$oArchiver->iCurPos."\");</script>\n";
		else // finish
		{
			$res = unlink($_SERVER["DOCUMENT_ROOT"]."/".$_REQUEST["filename"]) && unlink(__FILE__);
			@unlink($_SERVER["DOCUMENT_ROOT"]."/".$_REQUEST["filename"].'.log');
			@unlink($_SERVER["DOCUMENT_ROOT"]."/".$_REQUEST["filename"].'.tmp');

			@unlink($_SERVER['DOCUMENT_ROOT'].'/restore.php');
			$strInstFile = "index.php";

			if (!$res)
				SetCurrentStatus(LoaderGetMessage("LOADER_UNPACK_DELETE"));
			elseif (!file_exists($_SERVER["DOCUMENT_ROOT"]."/".$strInstFile))
				SetCurrentStatus(LoaderGetMessage("LOADER_UNPACK_UNKNOWN"));
			else
			{
				bx_accelerator_reset();
				SetCurrentStatus(LoaderGetMessage("LOADER_UNPACK_SUCCESS"));
				$script = "<script>document.location = \"/".$strInstFile."\";</script>";
			}
		}
	}
	else
	{
		SetCurrentStatus(LoaderGetMessage("LOADER_UNPACK_ERRORS"));
		$arErrors = &$oArchiver->GetErrors();
		if (count($arErrors)>0)
		{
			if ($ft = fopen($_SERVER["DOCUMENT_ROOT"]."/".$this_script_name.".log", "wb"))
			{
				foreach ($arErrors as $value)
				{
					$str = "[".$value[0]."] ".$value[1]."\n";
					fwrite($ft, $str);
					$txt .= $str . '<br>';
				}
				fclose($ft);
			}
		}
	}

	$ar = array(
		'FORM' => $form,
		'TITLE' => LoaderGetMessage("LOADER_MENU_UNPACK"),
		'HEAD' => LoaderGetMessage("LOADER_MENU_UNPACK"),
		'TEXT' => $txt,
		'BOTTOM' => '<input type="button" value="&nbsp;&nbsp;&nbsp;'.LoaderGetMessage("LOADER_BACK").'&nbsp;&nbsp;&nbsp;" onclick="document.location=\''.$this_script_name.'?action=LIST\'">'
		);

	/*********************************************************************/
}

########### HTML #########
	html($ar);
	echo $script;
##########################


function LoadFile($strRequestedUrl, $strFilename, $iTimeOut)
{
	global $proxyAddr, $proxyPort, $proxyUserName, $proxyPassword, $strUserAgent, $strRequestedSize;

	$iTimeOut = IntVal($iTimeOut);
	if ($iTimeOut>0)
		$start_time = getmicrotime();

	$strRealUrl = $strRequestedUrl;
	$iStartSize = 0;
	$iRealSize = 0;

	$bCanContinueDownload = False;

	// ИНИЦИАЛИЗИРУЕМ, ЕСЛИ ДОКАЧКА
	$strRealUrl_tmp = "";
	$iRealSize_tmp = 0;
	if (file_exists($strFilename.".tmp") && file_exists($strFilename.".log") && filesize($strFilename.".log")>0)
	{
		$fh = fopen($strFilename.".log", "rb");
		$file_contents_tmp = fread($fh, filesize($strFilename.".log"));
		fclose($fh);

		list($strRealUrl_tmp, $iRealSize_tmp) = explode("\n", $file_contents_tmp);
		$strRealUrl_tmp = Trim($strRealUrl_tmp);
		$iRealSize_tmp = Trim($iRealSize_tmp);
	}
	if ($iRealSize_tmp<=0 || strlen($strRealUrl_tmp)<=0)
	{
		$strRealUrl_tmp = "";
		$iRealSize_tmp = 0;

		if (file_exists($strFilename.".tmp"))
			@unlink($strFilename.".tmp");

		if (file_exists($strFilename.".log"))
			@unlink($strFilename.".log");
	}
	else
	{
		$strRealUrl = $strRealUrl_tmp;
		$iRealSize = $iRealSize_tmp;
		$iStartSize = filesize($strFilename.".tmp");
	}
	// КОНЕЦ: ИНИЦИАЛИЗИРУЕМ, ЕСЛИ ДОКАЧКА

//	SetCurrentStatus(LoaderGetMessage("LOADER_LOAD_QUERY_SERVER"));

	// ИЩЕМ ФАЙЛ И ЗАПРАШИВАЕМ ИНФО
	do
	{
//		SetCurrentStatus(str_replace("#DISTR#", $strRealUrl, LoaderGetMessage("LOADER_LOAD_QUERY_DISTR")));

		$lasturl = $strRealUrl;
		$redirection = "";

		$parsedurl = parse_url($strRealUrl);
		$useproxy = (($proxyAddr != "") && ($proxyPort != ""));

		if (!$useproxy)
		{
			$host = $parsedurl["host"];
			$port = $parsedurl["port"];
			$hostname = $host;
		}
		else
		{
			$host = $proxyAddr;
			$port = $proxyPort;
			$hostname = $parsedurl["host"];
		}

		$port = $port ? $port : "80";

//		SetCurrentStatus(str_replace("#HOST#", $host, LoaderGetMessage("LOADER_LOAD_CONN2HOST")));
		$sockethandle = fsockopen($host, $port, $error_id, $error_msg, 30);
		if (!$sockethandle)
		{
//			SetCurrentStatus(str_replace("#HOST#", $host, LoaderGetMessage("LOADER_LOAD_NO_CONN2HOST"))." [".$error_id."] ".$error_msg);
			return false;
		}
		else
		{
			if (!$parsedurl["path"])
				$parsedurl["path"] = "/";

//			SetCurrentStatus(LoaderGetMessage("LOADER_LOAD_QUERY_FILE"));
			$request = "";
			if (!$useproxy)
			{
				$request .= "HEAD ".$parsedurl["path"].($parsedurl["query"] ? '?'.$parsedurl["query"] : '')." HTTP/1.0\r\n";
				$request .= "Host: $hostname\r\n";
			}
			else
			{
				$request .= "HEAD ".$strRealUrl." HTTP/1.0\r\n";
				$request .= "Host: $hostname\r\n";
				if ($proxyUserName)
					$request .= "Proxy-Authorization: Basic ".base64_encode($proxyUserName.":".$proxyPassword)."\r\n";
			}

			if ($strUserAgent != "")
				$request .= "User-Agent: $strUserAgent\r\n";

			$request .= "\r\n";

			fwrite($sockethandle, $request);

			$result = "";
//			SetCurrentStatus(LoaderGetMessage("LOADER_LOAD_WAIT"));

			$replyheader = "";
			while (($result = fgets($sockethandle, 4024)) && $result!="\r\n")
			{
				$replyheader .= $result;
			}
			fclose($sockethandle);

			$ar_replyheader = explode("\r\n", $replyheader);

			$replyproto = "";
			$replyversion = "";
			$replycode = 0;
			$replymsg = "";
			if (preg_match("#([A-Z]{4})/([0-9.]{3}) ([0-9]{3})#", $ar_replyheader[0], $regs))
			{
				$replyproto = $regs[1];
				$replyversion = $regs[2];
				$replycode = IntVal($regs[3]);
				$replymsg = substr($ar_replyheader[0], strpos($ar_replyheader[0], $replycode) + strlen($replycode) + 1, strlen($ar_replyheader[0]) - strpos($ar_replyheader[0], $replycode) + 1);
			}

			if ($replycode!=200 && $replycode!=302)
			{
				if ($replycode==403)
					SetCurrentStatus(LoaderGetMessage("LOADER_LOAD_SERVER_ANSWER1"));
				else
					SetCurrentStatus(str_replace("#ANS#", $replycode." - ".$replymsg, LoaderGetMessage("LOADER_LOAD_SERVER_ANSWER")).'<br>'.htmlspecialchars($strRequestedUrl));
				return false;
			}

			$strLocationUrl = "";
			$iNewRealSize = 0;
			$strAcceptRanges = "";
			for ($i = 1; $i < count($ar_replyheader); $i++)
			{
				if (strpos($ar_replyheader[$i], "Location") !== false)
					$strLocationUrl = trim(substr($ar_replyheader[$i], strpos($ar_replyheader[$i], ":") + 1, strlen($ar_replyheader[$i]) - strpos($ar_replyheader[$i], ":") + 1));
				elseif (strpos($ar_replyheader[$i], "Content-Length") !== false)
					$iNewRealSize = IntVal(Trim(substr($ar_replyheader[$i], strpos($ar_replyheader[$i], ":") + 1, strlen($ar_replyheader[$i]) - strpos($ar_replyheader[$i], ":") + 1)));
				elseif (strpos($ar_replyheader[$i], "Accept-Ranges") !== false)
					$strAcceptRanges = Trim(substr($ar_replyheader[$i], strpos($ar_replyheader[$i], ":") + 1, strlen($ar_replyheader[$i]) - strpos($ar_replyheader[$i], ":") + 1));
			}

			if (strlen($strLocationUrl)>0)
			{
				$redirection = $strLocationUrl;
				$redirected = true;
				if ((strpos($redirection, "http://")===false))
					$strRealUrl = dirname($lasturl)."/".$redirection;
				else
					$strRealUrl = $redirection;
			}

			if (strlen($strLocationUrl)<=0)
				break;
		}
	}
	while (true);
	// КОНЕЦ: ИЩЕМ ФАЙЛ И ЗАПРАШИВАЕМ ИНФО

	$bCanContinueDownload = ($strAcceptRanges == "bytes");

/*
	// ЕСЛИ НЕЛЬЗЯ ДОКАЧИВАТЬ
	if (!$bCanContinueDownload
		|| ($iRealSize>0 && $iNewRealSize != $iRealSize))
	{
	//	SetCurrentStatus(LoaderGetMessage("LOADER_LOAD_NEED_RELOAD"));
	//	$iStartSize = 0;
		die(LoaderGetMessage("LOADER_LOAD_NEED_RELOAD"));
	}
	// КОНЕЦ: ЕСЛИ НЕЛЬЗЯ ДОКАЧИВАТЬ
*/

	// ЕСЛИ МОЖНО ДОКАЧИВАТЬ
	if ($bCanContinueDownload)
	{
		$fh = fopen($strFilename.".log", "wb");
		if (!$fh)
		{
			SetCurrentStatus(str_replace("#FILE#", $strFilename.".log", LoaderGetMessage("LOADER_LOAD_NO_WRITE2FILE")));
			return false;
		}
		fwrite($fh, $strRealUrl."\n");
		fwrite($fh, $iNewRealSize."\n");
		fclose($fh);
	}
	// КОНЕЦ: ЕСЛИ МОЖНО ДОКАЧИВАТЬ

//	SetCurrentStatus(str_replace("#DISTR#", $strRealUrl, LoaderGetMessage("LOADER_LOAD_LOAD_DISTR")));
	$strRequestedSize = $iNewRealSize;

	// КАЧАЕМ ФАЙЛ
	$parsedurl = parse_url($strRealUrl);
	$useproxy = (($proxyAddr != "") && ($proxyPort != ""));

	if (!$useproxy)
	{
		$host = $parsedurl["host"];
		$port = $parsedurl["port"];
		$hostname = $host;
	}
	else
	{
		$host = $proxyAddr;
		$port = $proxyPort;
		$hostname = $parsedurl["host"];
	}

	$port = $port ? $port : "80";

	SetCurrentStatus(str_replace("#HOST#", $host, LoaderGetMessage("LOADER_LOAD_CONN2HOST")));
	$sockethandle = fsockopen($host, $port, $error_id, $error_msg, 30);
	if (!$sockethandle)
	{
		SetCurrentStatus(str_replace("#HOST#", $host, LoaderGetMessage("LOADER_LOAD_NO_CONN2HOST"))." [".$error_id."] ".$error_msg);
		return false;
	}
	else
	{
		if (!$parsedurl["path"])
			$parsedurl["path"] = "/";

		SetCurrentStatus(LoaderGetMessage("LOADER_LOAD_QUERY_FILE"));

		$request = "";
		if (!$useproxy)
		{
			$request .= "GET ".$parsedurl["path"].($parsedurl["query"] ? '?'.$parsedurl["query"] : '')." HTTP/1.0\r\n";
			$request .= "Host: $hostname\r\n";
		}
		else
		{
			$request .= "GET ".$strRealUrl." HTTP/1.0\r\n";
			$request .= "Host: $hostname\r\n";
			if ($proxyUserName)
				$request .= "Proxy-Authorization: Basic ".base64_encode($proxyUserName.":".$proxyPassword)."\r\n";
		}

		if ($strUserAgent != "")
			$request .= "User-Agent: $strUserAgent\r\n";

		if ($bCanContinueDownload && $iStartSize>0)
			$request .= "Range: bytes=".$iStartSize."-\r\n";

		$request .= "\r\n";

		fwrite($sockethandle, $request);

		$result = "";
		SetCurrentStatus(LoaderGetMessage("LOADER_LOAD_WAIT"));

		$replyheader = "";
		while (($result = fgets($sockethandle, 4096)) && $result!="\r\n")
			$replyheader .= $result;

		$ar_replyheader = explode("\r\n", $replyheader);

		$replyproto = "";
		$replyversion = "";
		$replycode = 0;
		$replymsg = "";
		if (preg_match("#([A-Z]{4})/([0-9.]{3}) ([0-9]{3})#", $ar_replyheader[0], $regs))
		{
			$replyproto = $regs[1];
			$replyversion = $regs[2];
			$replycode = IntVal($regs[3]);
			$replymsg = substr($ar_replyheader[0], strpos($ar_replyheader[0], $replycode) + strlen($replycode) + 1, strlen($ar_replyheader[0]) - strpos($ar_replyheader[0], $replycode) + 1);
		}

		if ($replycode!=200 && $replycode!=302 && $replycode!=206)
		{
			SetCurrentStatus(str_replace("#ANS#", $replycode." - ".$replymsg, LoaderGetMessage("LOADER_LOAD_SERVER_ANSWER")));
			return false;
		}

		$strContentRange = "";
		$iContentLength = 0;
		$strAcceptRanges = "";
		for ($i = 1; $i < count($ar_replyheader); $i++)
		{
			if (strpos($ar_replyheader[$i], "Content-Range") !== false)
				$strContentRange = trim(substr($ar_replyheader[$i], strpos($ar_replyheader[$i], ":") + 1, strlen($ar_replyheader[$i]) - strpos($ar_replyheader[$i], ":") + 1));
			elseif (strpos($ar_replyheader[$i], "Content-Length") !== false)
				$iContentLength = doubleval(Trim(substr($ar_replyheader[$i], strpos($ar_replyheader[$i], ":") + 1, strlen($ar_replyheader[$i]) - strpos($ar_replyheader[$i], ":") + 1)));
			elseif (strpos($ar_replyheader[$i], "Accept-Ranges") !== false)
				$strAcceptRanges = Trim(substr($ar_replyheader[$i], strpos($ar_replyheader[$i], ":") + 1, strlen($ar_replyheader[$i]) - strpos($ar_replyheader[$i], ":") + 1));
		}

		$bReloadFile = True;
		if (strlen($strContentRange)>0)
		{
			if (preg_match("# *bytes +([0-9]*) *- *([0-9]*) */ *([0-9]*)#i", $strContentRange, $regs))
			{
				$iStartBytes_tmp = doubleval($regs[1]);
				$iEndBytes_tmp = doubleval($regs[2]);
				$iSizeBytes_tmp = doubleval($regs[3]);

				if ($iStartBytes_tmp==$iStartSize
					&& $iEndBytes_tmp==($iNewRealSize-1)
					&& $iSizeBytes_tmp==$iNewRealSize)
				{
					$bReloadFile = False;
				}
			}
		}

		if ($bReloadFile)
		{
			@unlink($strFilename.".tmp");
			$iStartSize = 0;
		}

		if (($iContentLength+$iStartSize)!=$iNewRealSize)
		{
			SetCurrentStatus(LoaderGetMessage("LOADER_LOAD_ERR_SIZE"));
			return false;
		}

		$fh = fopen($strFilename.".tmp", "ab");
		if (!$fh)
		{
			SetCurrentStatus(str_replace("#FILE#", $strFilename.".tmp", LoaderGetMessage("LOADER_LOAD_CANT_OPEN_WRITE")));
			return false;
		}

		$bFinished = True;
		$downloadsize = (double) $iStartSize;
		SetCurrentStatus(LoaderGetMessage("LOADER_LOAD_LOADING"));
		while (!feof($sockethandle))
		{
			if ($iTimeOut>0 && (getmicrotime()-$start_time)>$iTimeOut)
			{
				$bFinished = False;
				break;
			}

			$result = fread($sockethandle, 256 * 1024);
			$downloadsize += strlen($result);
			if ($result=="")
				break;

			fwrite($fh, $result);
		}
		SetCurrentProgress($downloadsize,$iNewRealSize);

		fclose($fh);
		fclose($sockethandle);

		if ($bFinished)
		{
			@unlink($strFilename);
			if (!@rename($strFilename.".tmp", $strFilename))
			{
				SetCurrentStatus(str_replace("#FILE2#", $strFilename, str_replace("#FILE1#", $strFilename.".tmp", LoaderGetMessage("LOADER_LOAD_ERR_RENAME"))));
				return false;
			}
			@unlink($strFilename.".tmp");
		}
		else
			return 2;

		SetCurrentStatus(str_replace("#SIZE#", $downloadsize, str_replace("#FILE#", $strFilename, LoaderGetMessage("LOADER_LOAD_FILE_SAVED"))));
		@unlink($strFilename.".log");
		return 1;
	}
	// КОНЕЦ: КАЧАЕМ ФАЙЛ
}
function html($ar)
{
?>
	<html>
	<head>
	<title><?=$ar['TITLE']?></title>
	</head>
	<body style="background:#4A507B">
	<script>
		function GoToPage(url)
		{
			window.setTimeout('document.location="' + url + '"', 500);
		}
	</script>
	<style>
		td {font-family:Verdana;font-size:9pt}
	</style>
	<?=$ar['FORM']?>
	<table width=100% height=100%><tr><td align=center valign=middle>
	<table align="center" cellspacing=0 cellpadding=0 border=0 style="width:801px;height:387px">
		<tr>
			<td width=11><img src="<?=img('corner_top_left.gif')?>" width=11 height=57></td>
			<td height=57 bgcolor="#FFFFFF" valign="middle">
				<table cellpadding=0 cellspacing=0 border=0 width=100%><tr>
					<td align=left style="font-size:14pt;color:#E11537;padding-left:25px"><?=$ar['HEAD']?></td>
					<td align=right>
						<?
						$arLang = array();
						foreach(array('en','de','ru') as $l)
							$arLang[] = LANG == $l ? "<span style='color:grey'>$l</span>" : "<a href='?lang=$l' style='color:black'>$l</a>";
						echo implode(' | ',$arLang);
						?>
					</td>
				</tr></table>

			</td>
			<td width=11><img src="<?=img('corner_top_right.gif')?>" width=11 height=57></td>
		</tr>
		<tr>
			<td bgcolor="#FFFFFF">&nbsp;</td>
			<td height=1 bgcolor="#FFFFFF"><hr size="1px" color="#D6D6D6"></td>
			<td bgcolor="#FFFFFF">&nbsp;</td>
		</tr>
		<tr>
			<td bgcolor="#FFFFFF">&nbsp;</td>
			<td bgcolor="#FFFFFF" style="padding:20px;font-size:10pt" valign="<?=$ar['TEXT_ALIGN']?$ar['TEXT_ALIGN']:'middle'?>"><?=$ar['TEXT']?></td>
			<td bgcolor="#FFFFFF">&nbsp;</td>
		</tr>
		<tr>
			<td bgcolor="#FFFFFF">&nbsp;</td>
			<td bgcolor="#FFFFFF" style="padding:20x;font-size:10pt" valign="middle" align="right" height="40px"><?=$ar['BOTTOM']?></td>
			<td bgcolor="#FFFFFF">&nbsp;</td>
		</tr>
		<tr>
			<td><img src="<?=img('corner_bottom_left.gif')?>" width=11 height=23></td>
			<td height=23 bgcolor="#FFFFFF" background="<?=img('bottom_fill.gif')?>"></td>
			<td><img src="<?=img('corner_bottom_right.gif')?>" width=11 height=23></td>
		</tr>
	</table>
	<img src='<?=img('logo_'.($GLOBALS['lang']=='ru'?'':'en_').'installer.gif')?>' width=95 height=34>
	</td></tr></table>
	</form>
	</body>
	</html>
<?
}

function SetCurrentStatus($str)
{
	global $strLog;
	$strLog .= $str."\n";
}

function SetCurrentProgress($cur,$total=0,$red=true)
{
	global $status;
	if (!$total)
	{
		$total=100;
		$cur=0;
	}
	$val = intval($cur/$total*100);
	if ($val > 99)
		$val = 99;

	$status = '
	<div align=center style="padding:10px;font-size:18px">'.$val.'%</div>
	<table width=100% cellspacing=0 cellpadding=0 border=0 style="border:1px solid #D8D8D8">
	<tr>
		<td style="width:'.$val.'%;height:13px" bgcolor="'.($red?'#FF5647':'#54B4FF').'" background="'.img(($red?'red':'blue').'_progress.gif').'"></td>
		<td style="width:'.(100-$val).'%"></td>
	</tr>
	</table>';
}

function getmicrotime()
{
	list($usec, $sec) = explode(" ", microtime());
	return ((float)$usec + (float)$sec);
}

class CArchiver
{
	var $_strArchiveName = "";
	var $_bCompress = false;
	var $_strSeparator = " ";
	var $_dFile = 0;

	var $_arErrors = array();
	var $iArchSize = 0;
	var $iCurPos = 0;
	var $bFinish = false;

	function CArchiver($strArchiveName, $bCompress = false)
	{
		$this->_bCompress = false;
		if (!$bCompress)
		{
			if (file_exists($strArchiveName))
			{
				if ($fp = fopen($strArchiveName, "rb"))
				{
					$data = fread($fp, 2);
					fclose($fp);
					if ($data == "\37\213")
					{
						$this->_bCompress = True;
					}
				}
			}
			else
			{
				if (substr($strArchiveName, -2) == 'gz')
				{
					$this->_bCompress = True;
				}
			}
		}
		else
		{
			$this->_bCompress = True;
		}

		$this->_strArchiveName = $strArchiveName;
		$this->_arErrors = array();
	}

	function extractFiles($strPath, $vFileList = false)
	{
		$this->_arErrors = array();

		$v_result = true;
		$v_list_detail = array();

		$strExtrType = "complete";
		$arFileList = 0;
		if ($vFileList!==false)
		{
			$arFileList = &$this->_parseFileParams($vFileList);
			$strExtrType = "partial";
		}

		if ($v_result = $this->_openRead())
		{
			$v_result = $this->_extractList($strPath, $v_list_detail, $strExtrType, $arFileList, '');
			$this->_close();
		}

		return $v_result;
	}

	function &GetErrors()
	{
		return $this->_arErrors;
	}

	function _extractList($p_path, &$p_list_detail, $p_mode, $p_file_list, $p_remove_path)
	{
		global $iNumDistrFiles;

		$v_result = true;
		$v_nb = 0;
		$v_extract_all = true;
		$v_listing = false;

		$p_path = str_replace("\\", "/", $p_path);

		if ($p_path == ''
			|| (substr($p_path, 0, 1) != '/'
				&& substr($p_path, 0, 3) != "../"
				&& !strpos($p_path, ':')))
		{
			$p_path = "./".$p_path;
		}

		$p_remove_path = str_replace("\\", "/", $p_remove_path);
		if (($p_remove_path != '') && (substr($p_remove_path, -1) != '/'))
			$p_remove_path .= '/';

		$p_remove_path_size = strlen($p_remove_path);

		switch ($p_mode)
		{
			case "complete" :
				$v_extract_all = TRUE;
				$v_listing = FALSE;
				break;
			case "partial" :
				$v_extract_all = FALSE;
				$v_listing = FALSE;
				break;
			case "list" :
				$v_extract_all = FALSE;
				$v_listing = TRUE;
				break;
			default :
				$this->_arErrors[] = array("ERR_PARAM", "Invalid extract mode (".$p_mode.")");
				return false;
		}

		clearstatcache();

		$tm=time();
		while((extension_loaded("mbstring")? mb_strlen($v_binary_data = $this->_readBlock(), "latin1") : strlen($v_binary_data = $this->_readBlock())) != 0)
		{
			$v_extract_file = FALSE;
			$v_extraction_stopped = 0;

			if (!$this->_readHeader($v_binary_data, $v_header))
				return false;

			if ($v_header['filename'] == '')
				continue;

			// ----- Look for long filename
				if ($v_header['typeflag'] == 'L')
			{
				if (!$this->_readLongHeader($v_header))
					return false;
			}


			if ((!$v_extract_all) && (is_array($p_file_list)))
			{
				// ----- By default no unzip if the file is not found
				$v_extract_file = false;

				for ($i = 0; $i < count($p_file_list); $i++)
				{
					// ----- Look if it is a directory
					if (substr($p_file_list[$i], -1) == '/')
					{
						// ----- Look if the directory is in the filename path
						if ((strlen($v_header['filename']) > strlen($p_file_list[$i]))
							&& (substr($v_header['filename'], 0, strlen($p_file_list[$i])) == $p_file_list[$i]))
						{
							$v_extract_file = TRUE;
							break;
						}
					}
					elseif ($p_file_list[$i] == $v_header['filename'])
					{
						// ----- It is a file, so compare the file names
						$v_extract_file = TRUE;
						break;
					}
				}
			}
			else
			{
			  $v_extract_file = TRUE;
			}

			// ----- Look if this file need to be extracted
			if (($v_extract_file) && (!$v_listing))
			{
				if (($p_remove_path != '')
					&& (substr($v_header['filename'], 0, $p_remove_path_size) == $p_remove_path))
				{
					$v_header['filename'] = substr($v_header['filename'], $p_remove_path_size);
				}
				if (($p_path != './') && ($p_path != '/'))
				{
					while (substr($p_path, -1) == '/')
						$p_path = substr($p_path, 0, strlen($p_path)-1);

					if (substr($v_header['filename'], 0, 1) == '/')
						$v_header['filename'] = $p_path.$v_header['filename'];
					else
						$v_header['filename'] = $p_path.'/'.$v_header['filename'];
				}
				if (file_exists($v_header['filename']))
				{
					if ((@is_dir($v_header['filename'])) && ($v_header['typeflag'] == ''))
					{
						$this->_arErrors[] = array("DIR_EXISTS", "File '".$v_header['filename']."' already exists as a directory");
						return false;
					}
					if ((is_file($v_header['filename'])) && ($v_header['typeflag'] == "5"))
					{
						$this->_arErrors[] = array("FILE_EXISTS", "Directory '".$v_header['filename']."' already exists as a file");
						return false;
					}
					if (!is_writeable($v_header['filename']))
					{
						$this->_arErrors[] = array("FILE_PERMS", "File '".$v_header['filename']."' already exists and is write protected");
						return false;
					}
				}
				elseif (($v_result = $this->_dirCheck(($v_header['typeflag'] == "5" ? $v_header['filename'] : dirname($v_header['filename'])))) != 1)
				{
					$this->_arErrors[] = array("NO_DIR", "Unable to create path for '".$v_header['filename']."'");
					return false;
				}

				if ($v_extract_file)
				{
					if ($v_header['typeflag'] == "5")
					{
						if (!@file_exists($v_header['filename']))
						{
							if (!@mkdir($v_header['filename'], BX_DIR_PERMISSIONS))
							{
								$this->_arErrors[] = array("ERR_CREATE_DIR", "Unable to create directory '".$v_header['filename']."'");
								return false;
							}
						}
					}
					else
					{
						if (($v_dest_file = fopen($v_header['filename'], "wb")) == 0)
						{
							$this->_arErrors[] = array("ERR_CREATE_FILE", LoaderGetMessage('NO_PERMS') .' '. $v_header['filename']);
							return false;
						}
						else
						{
							$n = floor($v_header['size']/512);
							for ($i = 0; $i < $n; $i++)
							{
								$v_content = $this->_readBlock();
								fwrite($v_dest_file, $v_content, 512);
							}
							if (($v_header['size'] % 512) != 0)
							{
								$v_content = $this->_readBlock();
								fwrite($v_dest_file, $v_content, ($v_header['size'] % 512));
							}

							@fclose($v_dest_file);

							@chmod($v_header['filename'], BX_FILE_PERMISSIONS);
							@touch($v_header['filename'], $v_header['mtime']);
						}

						clearstatcache();
						if (filesize($v_header['filename']) != $v_header['size'])
						{
							$this->_arErrors[] = array("ERR_SIZE_CHECK", "Extracted file '".$v_header['filename']."' have incorrect file size '".filesize($v_filename)."' (".$v_header['size']." expected). Archive may be corrupted");
							return false;
						}
					}
				}
				else
				{
					$this->_jumpBlock(ceil(($v_header['size']/512)));
				}
			}
			else
			{
				$this->_jumpBlock(ceil(($v_header['size']/512)));
			}

			if ($v_listing || $v_extract_file || $v_extraction_stopped)
			{
				if (($v_file_dir = dirname($v_header['filename'])) == $v_header['filename'])
					$v_file_dir = '';
				if ((substr($v_header['filename'], 0, 1) == '/') && ($v_file_dir == ''))
					$v_file_dir = '/';

				$p_list_detail[$v_nb++] = $v_header;

				if ($v_nb % 100 == 0)
					SetCurrentProgress($this->iCurPos, $this->iArchSize, False);
			}

			if ($_REQUEST['by_step'] && (time()-$tm) > TIMEOUT) 
			{
				SetCurrentProgress($this->iCurPos, $this->iArchSize, False);
				return true;
			}
		}
		$this->bFinish = true;
		return true;
	}

	function _readBlock()
	{
		$v_block = "";
		if (is_resource($this->_dFile))
		{
			if (isset($_REQUEST['seek']))
			{
				if ($this->_bCompress)
					gzseek($this->_dFile, intval($_REQUEST['seek']));
				else
					fseek($this->_dFile, intval($_REQUEST['seek']));

				$this->iCurPos = IntVal($_REQUEST['seek']);

				unset($_REQUEST['seek']);
			}
			if ($this->_bCompress)
				$v_block = gzread($this->_dFile, 512);
			else
				$v_block = fread($this->_dFile, 512);

			$this->iCurPos +=  (extension_loaded("mbstring")? mb_strlen($v_block, "latin1") : strlen($v_block));
		}
		return $v_block;
	}

	function _readHeader($v_binary_data, &$v_header)
	{
		if ((extension_loaded("mbstring")? mb_strlen($v_binary_data, "latin1") : strlen($v_binary_data)) ==0)
		{
			$v_header['filename'] = '';
			return true;
		}

		if ((extension_loaded("mbstring")? mb_strlen($v_binary_data, "latin1") : strlen($v_binary_data)) != 512)
		{
			$v_header['filename'] = '';
			$this->_arErrors[] = array("INV_BLOCK_SIZE", "Invalid block size : ".strlen($v_binary_data)."");
			return false;
		}

		$v_checksum = 0;
		for ($i = 0; $i < 148; $i++)
			$v_checksum+=ord(substr($v_binary_data, $i, 1));
		for ($i = 148; $i < 156; $i++)
			$v_checksum += ord(' ');
		for ($i = 156; $i < 512; $i++)
			$v_checksum+=ord(substr($v_binary_data, $i, 1));

		$v_data = unpack("a100filename/a8mode/a8uid/a8gid/a12size/a12mtime/a8checksum/a1typeflag/a100link/a6magic/a2version/a32uname/a32gname/a8devmajor/a8devminor/a155prefix/a12temp", $v_binary_data);

		$v_header['checksum'] = OctDec(trim($v_data['checksum']));
		if ($v_header['checksum'] != $v_checksum)
		{
			$v_header['filename'] = '';

			if (($v_checksum == 256) && ($v_header['checksum'] == 0))
				return true;

			$this->_arErrors[] = array("INV_BLOCK_CHECK", "Invalid checksum for file '".$v_data['filename']."' : ".$v_checksum." calculated, ".$v_header['checksum']." expected");
			return false;
		}

		// ----- Extract the properties
		$v_header['filename'] = trim($v_data['prefix'], "\x00")."/".trim($v_data['filename'], "\x00");
		$v_header['mode'] = OctDec(trim($v_data['mode']));
		$v_header['uid'] = OctDec(trim($v_data['uid']));
		$v_header['gid'] = OctDec(trim($v_data['gid']));
		$v_header['size'] = OctDec(trim($v_data['size']));
		$v_header['mtime'] = OctDec(trim($v_data['mtime']));
		if (($v_header['typeflag'] = $v_data['typeflag']) == "5")
			$v_header['size'] = 0;

		return true;
	}

	function _readLongHeader(&$v_header)
	{
		$v_filename = '';
		$n = floor($v_header['size']/512);
		for ($i = 0; $i < $n; $i++)
		{
			$v_content = $this->_readBlock();
			$v_filename .= $v_content;
		}
		if (($v_header['size'] % 512) != 0)
		{
			$v_content = $this->_readBlock();
			$v_filename .= $v_content;
		}

		$v_binary_data = $this->_readBlock();

		if (!$this->_readHeader($v_binary_data, $v_header))
			return false;

		$v_header['filename'] = trim($v_filename, "\x00");

		return true;
	}

	function _jumpBlock($p_len = false)
	{
		if (is_resource($this->_dFile))
		{
			if ($p_len === false)
				$p_len = 1;

			if ($this->_bCompress)
				gzseek($this->_dFile, gztell($this->_dFile)+($p_len*512));
			else
				fseek($this->_dFile, ftell($this->_dFile)+($p_len*512));
		}
		return true;
	}

	function &_parseFileParams(&$vFileList)
	{
		if (isset($vFileList) && is_array($vFileList))
			return $vFileList;
		elseif (isset($vFileList) && strlen($vFileList)>0)
			return explode($this->_strSeparator, $vFileList);
		else
			return array();
	}

	function _openRead()
	{

		if ($this->_bCompress)
		{
			$this->_dFile = gzopen($this->_strArchiveName, "rb");
			$this->iArchSize = filesize($this->_strArchiveName) * 3;
		}
		else
		{
			$this->_dFile = fopen($this->_strArchiveName, "rb");
			$this->iArchSize = filesize($this->_strArchiveName);
		}

		if (!$this->_dFile)
		{
			$this->_arErrors[] = array("ERR_OPEN", "Unable to open '".$this->_strArchiveName."' in read mode");
			return false;
		}

		return true;
	}

	function _close()
	{
		if (is_resource($this->_dFile))
		{
			if ($this->_bCompress)
				gzclose($this->_dFile);
			else
				fclose($this->_dFile);

			$this->_dFile = 0;
		}

		return true;
	}

	function _dirCheck($p_dir)
	{
		if ((is_dir($p_dir)) || ($p_dir == ''))
			return true;

		$p_parent_dir = dirname($p_dir);

		if (($p_parent_dir != $p_dir) &&
			($p_parent_dir != '') &&
			(!$this->_dirCheck($p_parent_dir)))
			return false;

		if (!is_dir($p_dir) && !mkdir($p_dir, BX_DIR_PERMISSIONS))
		{
			$this->_arErrors[] = array("CANT_CREATE_PATH", "Unable to create directory '".$p_dir."'");
			return false;
		}

		return true;
	}

}

function img($name)
{
	if (file_exists($_SERVER['DOCUMENT_ROOT'].'/images/'.$name))
		return '/images/'.$name;
	return 'http://www.1c-bitrix.ru/images/bitrix_setup/'.$name;
}

function ShowError($str)
{
	return	'<div style="color:red;text-align:center;border:1px solid red;padding:5px;">'.$str.'</div>';
}

function bx_accelerator_reset()
{
        if(function_exists("accelerator_reset"))
                accelerator_reset();
        elseif(function_exists("wincache_refresh_if_changed"))
                wincache_refresh_if_changed();
}

function EscapePHPString($str)
{
	$str = str_replace("\\", "\\\\", $str);
	$str = str_replace("\$", "\\\$", $str);
	$str = str_replace("\"", "\\"."\"", $str);
	return $str;
}
?>
