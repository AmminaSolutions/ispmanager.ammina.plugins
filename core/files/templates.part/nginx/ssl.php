<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>

ssl_certificate [% $SSL_CRT_BUNDLE_PATH %];
ssl_certificate_key [% $SSL_KEY_PATH %];
ssl_ciphers EECDH:+AES256:-3DES:RSA+AES:!NULL:!RC4;
ssl_prefer_server_ciphers on;
ssl_protocols [% $SSL_SECURE_PROTOCOLS %];

{% if $HSTS == on %}

add_header Strict-Transport-Security "max-age=31536000;";

{% endif %}

{% if $SSL_DHPARAM == 2048 %}

ssl_dhparam /etc/ssl/certs/dhparam2048.pem;

{% elif $SSL_DHPARAM == 4096 %}

ssl_dhparam /etc/ssl/certs/dhparam4096.pem;

{% endif %}
