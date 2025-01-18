<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
php_admin_value sendmail_path "/usr/sbin/sendmail -t -i -f <?= $this->param('|AV|EMAIL') ?>"
php_admin_value upload_tmp_dir "<?= $this->param('|AV|MOD_TMP_PATH') ?>"
php_admin_value session.save_path "<?= $this->param('|AV|MOD_TMP_PATH') ?>"
php_admin_value open_basedir "<?= $this->param('|AV|BASEDIR_PATH') ?>"
