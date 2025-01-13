<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>
SSLEngine on
SSLCertificateFile [% $SSL_CRT %]
SSLCertificateKeyFile [% $SSL_KEY %]
{% if $SSL_BUNDLE != "" %}
SSLCertificateChainFile [% $SSL_BUNDLE %]
{% endif %}
SSLHonorCipherOrder on
SSLProtocol [% $SSL_SECURE_PROTOCOLS %]
SSLCipherSuite EECDH:+AES256:-3DES:RSA+AES:!NULL:!RC4
{% if $HSTS == on %}
<IfModule headers_module>
	Header always set Strict-Transport-Security "max-age=31536000; preload"
</IfModule>
{% endif %}
