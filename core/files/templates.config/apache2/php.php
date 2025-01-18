<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
<? if ($this->param('|AV|PHP') === 'on' && $this->param('|AV|FILES_MATCH') === 'on') { ?>
	<FilesMatch "\.ph(p[3-5]?|tml)$">
	SetHandler <?= $this->param('|AV|PHP_HANDLER', true) ?>
	<? if ($this->param('|AV|APACHE_FCGID') === 'on' && $this->param('|AV|PHP_MODE') === 'php_mode_fcgi_apache') {
		if ($this->param('platform') === 'default') { ?>
			FCGIWrapper <?= $this->param('|AV|PHP_BIN_WRAPPER', true) ?>
		<? } else { ?>
			FCGIWrapper <?= $this->param('php_bin_wrapper_ammina', true) ?>
			<?
		}
	}
	?>
	</FilesMatch>
<? }
if ($this->param('|AV|PHP_MODE') === 'php_mode_mod') {
	?>
	<FilesMatch "\.phps$">
	SetHandler application/x-httpd-php-source
	</FilesMatch>
	<? if (!empty($this->param('|AV|ISP_CUT'))) {
		?>
		<?= $this->includePart('apache2/php_values.php') ?>
	<? } ?>
	<IfModule php5_module>
		<?= $this->includePart('apache2/php_user_conf.php') ?>
		<?= $this->includePart('apache2/php_values.php') ?>
	</IfModule>
	<IfModule php7_module>
		<?= $this->includePart('apache2/php_user_conf.php') ?>
		<?= $this->includePart('apache2/php_values.php') ?>
	</IfModule>
	<IfModule php_module>
		<?= $this->includePart('apache2/php_user_conf.php') ?>
		<?= $this->includePart('apache2/php_values.php') ?>
	</IfModule>
<? } elseif ($this->param('|AV|PHP_MODE') === 'php_mode_lsapi') { ?>
	<FilesMatch "\.phps$">
	SetHandler application/x-httpd-php-source
	</FilesMatch>
	<? if (!empty($this->param('|AV|ISP_CUT'))) { ?>
		<?= $this->includePart('apache2/php_values.php') ?>
	<? } ?>
	<IfModule lsapi_module>
		<?= $this->includePart('apache2/php_values.php') ?>
	</IfModule>
<? } elseif ($this->param('|AV|PHP_MODE') === 'php_mode_cgi') { ?>
	ScriptAlias /php-bin/ <?= $this->param('|AV|PHP_BIN_DIR', true) ?>
	AddHandler application/x-httpd-php5 .php .php3 .php4 .php5 .phtml
	Action application/x-httpd-php5 /php-bin/php
<? } elseif ($this->param('|AV|PHP_MODE') === 'php_mode_fcgi_apache' && $this->param('|AV|APACHE_FCGID') !== 'on') { ?>
	AddType application/x-httpd-fastphp .php .php3 .php4 .php5 .phtml
	Alias /php-fcgi/ <?= $this->param('|AV|PHP_BIN_DIR', true) ?>
	<?
}
