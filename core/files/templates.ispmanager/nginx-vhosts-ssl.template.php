<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>
server {
server_name {% $NAME %} [% $ALIASES %];
listen {% $NGINX_SSL_LISTEN_ON %} [% $LISTEN_SSL_FLAGS %];

<? $this->includePart('nginx/ssl.php'); ?>
<? $this->includePart('nginx/main.php'); ?>

}
