<?
error_reporting(E_ALL & ~E_NOTICE);
header("Content-type: text/html; charset=utf-8");

if (isset($_REQUEST['getambrestore']) && $_REQUEST['getambrestore'] == "Y") {
	if (file_exists($_SERVER['DOCUMENT_ROOT'] . "/bitrix/php_interface/ambrestore.php")) {
		copy($_SERVER['DOCUMENT_ROOT'] . "/bitrix/php_interface/ambrestore.php", $_SERVER['DOCUMENT_ROOT'] . "/ambrestore.php");
		@unlink($_SERVER['DOCUMENT_ROOT'] . "/bitrix/php_interface/ambrestore.php");
	}
	header("Location: /ambrestore.php");
	die();
}

if (@preg_match('#ru#i', $_SERVER['HTTP_ACCEPT_LANGUAGE']))
	$lang = 'ru';
elseif (@preg_match('#de#i', $_SERVER['HTTP_ACCEPT_LANGUAGE']))
	$lang = 'de';

if ($_REQUEST['lang'])
	$lang = $_REQUEST['lang'];

if (!in_array($lang, array('ru', 'en', 'de')))
	$lang = 'en';

define("LANG", $lang);

if (LANG == 'ru') {
	$msg['hello'] = "Добро пожаловать!";
	$msg['desc'] = "
		Добро пожаловать в виртуальную машину Ammina ISPManager!<br>
		<br>
		<br>
		Система оптимально сконфигурирована и готова к использованию \"1С-Битрикс: Управление сайтом\" и \"1С-Битрикс: Корпоративный портал\". Система построена на базе панели ISPManager с плагинами Ammina для простой настройки www-хостов<br>
		<br>
		Пожалуйста, выберите один из вариантов:<br>";
} elseif (LANG == 'de') {
	$msg['hello'] = "Willkommen!";
	$msg['desc'] = "
		Herzlich willkommen bei der virtuellen Maschine Ammina ISPManager!<br>
 		<br>
		<br>
		Das System ist für die Verwendung von \"Bitrix Intranet Portal \" und \"Bitrix24 \" optimal konfiguriert und einsatzbereit\".<br>
 		<br>
		Bitte wählen Sie eine der folgenden Möglichkeiten:<br>";
} else {
	$msg['hello'] = "Welcome!";
	$msg['desc'] = "
		Welcome to Ammina ISPManager!<br>
		<br>
		<br>
		System is optimally configured and is ready to be used with \"Bitrix Site Manager\" and \"Bitrix24\".<br>
		<br>
		Please select one option below:<br>";
}
?>
<html>
<head>
	<title><?= $msg['hello'] ?></title>
</head>
<body style="background:#4A507B">
<style>
	td {
		font-family: Verdana;
	}
</style>
<table width=100% height=100%>
	<tr>
		<td align=center valign=middle>
			<table align="center" cellspacing=0 cellpadding=0 border=0 style="width:851px;height:387px">
				<tr>
					<td width=11><img src="images/corner_top_left.gif"></td>
					<td height=57 bgcolor="#FFFFFF" valign="middle">
						<table cellpadding=0 cellspacing=0 border=0 width=100%>
							<tr>
								<td align=left
									style="font-size:14pt;color:#E11537;padding-left:25px"><?= $msg['hello'] ?></td>
								<td align=right style="font-size:10pt">
									<?
									$arLang = array();
									foreach (array('en', 'de') as $l)
										$arLang[] = LANG == $l ? "<span style='color:grey'>$l</span>" : "<a href='?lang=$l' style='color:black'>$l</a>";
									echo implode(' | ', $arLang);
									?>
								</td>
							</tr>
						</table>

					</td>
					<td width=11><img src="images/corner_top_right.gif"></td>
				</tr>
				<tr>
					<td bgcolor="#FFFFFF">&nbsp;</td>
					<td height=1 bgcolor="#FFFFFF">
						<hr size="1px" color="#D6D6D6">
					</td>
					<td bgcolor="#FFFFFF">&nbsp;</td>
				</tr>
				<tr>
					<td bgcolor="#FFFFFF">&nbsp;</td>
					<td bgcolor="#FFFFFF" style="padding-left:25px;font-size:10pt" valign="topmiddle">
						<?= $msg['desc'] ?>
						<br>
						<br>
						<br>
						<a href="bitrixsetup.php?lang=<?= LANG ?>"><img src="images/button_create_<?= LANG ?>.gif"
																		border=0></a>
						<a href="restore.php?lang=<?= $lang ?>"><img src="images/button_restore_<?= LANG ?>.gif"
																	 border=0></a>
						<a href="?getambrestore=Y"><img
									src="images/button_ambrestore_<?= LANG ?>.gif" border=0></a>
					</td>
					<td bgcolor="#FFFFFF">&nbsp;</td>
				</tr>
				<tr>
					<td><img src="images/corner_bottom_left.gif"></td>
					<td height=23 bgcolor="#FFFFFF" background="images/bottom_fill.gif"></td>
					<td><img src="images/corner_bottom_right.gif"></td>
				</tr>
			</table>
			<img src='images/logo_installer_<?= LANG ?>.gif'>
		</td>
	</tr>
</table>
</body>
</html>
