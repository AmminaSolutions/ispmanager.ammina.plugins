<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
<? if (!empty($this->param('|AD|LOCATION_PATH'))) { ?>
	<Directory <?= $this->param('|AD|LOCATION_PATH') ?>>
		<? if ($this->param('|AD|SSI') === 'on') { ?>
			Options +Includes
		<? }
		if ($this->param('|AD|CGI') === 'on') {
			?>
			Options +ExecCGI
			<? if (!empty($this->param('|AD|CGI_EXT'))) { ?>
				AddHandler cgi-script <?= $this->param('|AD|CGI_EXT', true) ?>
				<?
			}
		} elseif ($this->param('|AD|PHP_MODE') === 'php_mode_fcgi_apache') { ?>
			Options +ExecCGI
		<? } else { ?>
			Options -ExecCGI
			<?
		}
		if ($this->param('|AD|PHP_MODE') === 'php_mode_mod') {
			if (!empty($this->param('|AD|ISP_CUT'))) {
				?>
				php_admin_flag engine on
			<? } ?>
			<IfModule php5_module>
				php_admin_flag engine on
			</IfModule>
			<IfModule php7_module>
				php_admin_flag engine on
			</IfModule>
			<IfModule php_module>
				php_admin_flag engine on
			</IfModule>
		<? } elseif ($this->param('|AD|PHP_MODE') === 'php_mode_lsapi') {
			?>
			<IfModule lsapi_module>
				php_admin_flag engine on
			</IfModule>
			<?
		} ?>
	</Directory>
	<?
}
if (!empty($this->param('|ADS|STATDIR_LOCATION'))) {
	?>
	<Directory <?= $this->param('|ADS|STATDIR_LOCATION') ?>>
		DirectoryIndex index.html
		AddDefaultCharset <?= $this->param('|ADS|WEBSTAT_ENCODING', true) ?>
	</Directory>
	<?
}
