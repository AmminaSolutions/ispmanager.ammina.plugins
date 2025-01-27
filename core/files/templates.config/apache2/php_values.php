<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
	php_admin_value sendmail_path "/usr/sbin/sendmail -t -i -f <?= $this->param('|AV|EMAIL') ?>"
	php_admin_value upload_tmp_dir "<?= $this->param('|AV|MOD_TMP_PATH') ?>"
	php_admin_value session.save_path "<?= $this->param('|AV|MOD_TMP_PATH') ?>"
	php_admin_value open_basedir "<?= $this->param('|AV|BASEDIR_PATH') ?>"
<?
if ($this->param('platform') === 'bitrix') {
	if ((int)$this->param('php_version') < 80) {
		if ($this->param('charset') == 'UTF-8' && $this->param('bitrix_settings_20100') == 'on') {
			?>
			php_admin_value mbstring.func_overload 2
			<?
		} else {
			?>
			php_admin_value mbstring.func_overload 0
			<?
		}
	}
	?>
	php_admin_value mbstring.internal_encoding "<?= $this->param('charset') ?>"
	<?

}
