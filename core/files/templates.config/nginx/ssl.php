<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>

	ssl_certificate <?= $this->param('|NV|SSL_CRT_BUNDLE_PATH') ?>;
	ssl_certificate_key <?= $this->param('|NV|SSL_KEY_PATH') ?>;
	ssl_ciphers EECDH:+AES256:-3DES:RSA+AES:!NULL:!RC4;
	ssl_prefer_server_ciphers on;
	ssl_protocols <?= $this->param('|NV|SSL_SECURE_PROTOCOLS') ?>;
<? if ($this->param('|NV|HSTS') === 'on') { ?>
	add_header Strict-Transport-Security "max-age=31536000;";
<? }
if ((int)$this->param('|NV|') === 2048) {
	?>
	ssl_dhparam /etc/ssl/certs/dhparam2048.pem;
<? } elseif ((int)$this->param('|NV|SSL_DHPARAM') === 4096) { ?>
	ssl_dhparam /etc/ssl/certs/dhparam4096.pem;
<? }