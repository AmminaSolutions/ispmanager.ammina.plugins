<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
	server {
	server_name <?= $this->param('|NV|NAME') ?> <?= $this->param('|NV|ALIASES') ?>;
	listen <?= $this->param('|NV|NGINX_LISTEN_ON') ?> <?= $this->param('|NV|LISTEN_FLAGS') ?>;
<? if ($this->param('|NV|SSL') === 'on' && $this->param('seo_settings_https') === 'on') { ?>
	return 301 https://$host$request_uri;
<? } else { ?>
	<?= $this->includePart('nginx/main.php'); ?>
	<?
}
?>
	}
<? if ($this->param('|NV|SSL') === 'on') {
	$flags = $this->param('|NV|LISTEN_SSL_FLAGS');
	if (strpos($flags, 'http2') !== false) {
		$flags = str_replace('http2', '', $flags);
	}
	?>
	server {
	server_name <?= $this->param('|NV|NAME') ?> <?= $this->param('|NV|ALIASES') ?>;
	listen <?= $this->param('|NV|NGINX_SSL_LISTEN_ON') ?> <?= $flags ?>;
	<?= $this->includePart('nginx/ssl.php'); ?>
	<?= $this->includePart('nginx/main.php'); ?>
	}
<? }
