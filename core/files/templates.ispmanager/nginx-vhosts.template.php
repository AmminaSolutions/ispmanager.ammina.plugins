<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>
server {
server_name {% $NAME %} [% $ALIASES %];
listen {% $NGINX_LISTEN_ON %} [% $LISTEN_FLAGS %];

{% if $PLATFORM == default and $REDIRECT_HTTP == on %}

return 301 https://$host:{% $SSL_PORT %}$request_uri;

{% elif $PLATFORM != default and $SEO_SETTINGS_HTTPS == on %}

return 301 https://$host$request_uri;

{% else %}

<? $this->includePart('nginx/main.php'); ?>

{% endif %}

}

{% if $SSL == on %}

{% import etc/templates/nginx-vhosts-ssl.template %}

{% endif %}
