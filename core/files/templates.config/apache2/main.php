<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
	ServerName <?= $this->param('|AV|NAME', true) ?>
<? if (!empty($this->param('|AV|ALIASES'))) { ?>
	ServerAlias <?= $this->param('|AV|ALIASES', true) ?>
	<?
}
if (!empty($this->param('|AV|NGINX_FOREGROUND'))) { ?>
	ServerAlias <?= $this->param('|AV|IPADDRS', true) ?>
<? } ?>
	DocumentRoot <?= $this->param('|AV|DOCROOT', true) ?>
	ServerAdmin <?= $this->param('|AV|EMAIL', true) ?>

<? if (!empty($this->param('|AV|DIRINDEX'))) { ?>
	DirectoryIndex <?= $this->param('|AV|DIRINDEX', true) ?>
<? } ?>
	AddDefaultCharset <?= $this->param('|AV|CHARSET', true) ?>
<? if ($this->param('|AV|APACHEITK') == 'on') { ?>
	AssignUserID <?= $this->param('|AV|OWNER') ?> <?= $this->param('|AV|OWNER_GROUP', true) ?>
<? } else { ?>
	SuexecUserGroup <?= $this->param('|AV|OWNER') ?> <?= $this->param('|AV|OWNER_GROUP', true) ?>
<? } ?>
<?= $this->includePart('apache2/logs.php') ?>
<? if ($this->param('|AV|CGI') == 'on') { ?>
	ScriptAlias /cgi-bin/ <?= $this->param('|AV|CGI_EXT_PATH', true) ?>
<? } ?>
	<Directory <?= $this->param('|AV|DOCROOT') ?>>
		Options -Indexes
	</Directory>
<?= $this->includePart('apache2/pagespeed.php') ?>
<?= $this->includePart('apache2/composer.php') ?>
<?= $this->includePart('apache2/python.php') ?>
<?= $this->includePart('apache2/php.php') ?>
<?= $this->includePart('apache2/nginx_or_seo.php') ?>
<? if (!empty($this->param('|AV|INCLUDE'))) { ?>
	Include <?= $this->param('|AV|INCLUDE', true) ?>
	<?
}
if ($this->param('|AV|USE_RESOURCES') == 'on') { ?>
	Include <?= $this->param('|AV|INCLUDE_PHP', true) ?>
	<?
}
if ($this->param('|AV|VIRTUAL_DOCROOT') == 'on') { ?>
	VirtualDocumentRoot <?= $this->param('|AV|VIRTUAL_DOCROOT_PATH', true) ?>
	<?
}