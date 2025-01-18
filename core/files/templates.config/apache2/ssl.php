<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
	SSLEngine on
	SSLCertificateFile <?= $this->param('|AV|SSL_CRT', true) ?>
	SSLCertificateKeyFile <?= $this->param('|AV|SSL_KEY', true) ?>
<? if (!empty($this->param('|AV|SSL_BUNDLE'))) { ?>
	SSLCertificateChainFile <?= $this->param('|AV|SSL_BUNDLE', true) ?>
<? } ?>
	SSLHonorCipherOrder on
	SSLProtocol <?= $this->param('|AV|SSL_SECURE_PROTOCOLS', true) ?>
	SSLCipherSuite EECDH:+AES256:-3DES:RSA+AES:!NULL:!RC4
<? if ($this->param('|AV|HSTS') === 'on') { ?>
	<IfModule headers_module>
		Header always set Strict-Transport-Security "max-age=31536000; preload"
	</IfModule>
	<?
}

