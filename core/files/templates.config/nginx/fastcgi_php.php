<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */

$rules = [
'sendmail_path=\\"/usr/sbin/sendmail -t -i -f ' . $this->param('|NV|EMAIL') . '\\"',
'upload_tmp_dir=\\"' . $this->param('|NV|MOD_TMP_PATH') . '\\"',
'session.save_path=\\"' . $this->param('|NV|MOD_TMP_PATH') . '\\"',
'open_basedir=\\"' . $this->param('|NV|MOD_TMP_PATH') . '\\"',
];
if ($this->param('platform') === 'bitrix') {
	if ((int)$this->param('php_version') < 80) {
		if ($this->param('charset') == 'UTF-8' && $this->param('bitrix_settings_20100') == 'on') {
			$rules[] = 'mbstring.func_overload=2';
		} else {
			$rules[] = 'mbstring.func_overload=0';
		}
	}
	$rules[] = 'mbstring.internal_encoding=\\"' . $this->param('charset') . '\\"';
}
?>
fastcgi_param PHP_ADMIN_VALUE "<?= implode("\\n", $rules) ?>";
