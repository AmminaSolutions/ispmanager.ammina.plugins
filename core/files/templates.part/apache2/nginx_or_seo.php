<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>
{%if $NGINX_FRONTEND == on %}
SetEnvIf X-Forwarded-Proto https HTTPS=on
{% elif $PLATFORM == "default" %}
{% if $REDIRECT_WWW == to_www %}
RewriteEngine on
RewriteCond %{HTTP_HOST} "={% $NAME %}" [NC]
RewriteRule ^(.*)$ http://www.{% $NAME %}%{REQUEST_URI} [R=301,L]
{% elif $REDIRECT_WWW == from_www %}
RewriteEngine on
RewriteCond %{HTTP_HOST} "=www.{% $NAME %}" [NC]
RewriteRule ^(.*)$ http://{% $NAME %}%{REQUEST_URI} [R=301,L]
{% endif %}
{% else %}

{% endif %}
