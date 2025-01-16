<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>

{% if $PLATFORM == default %}

{% if $PAGESPEED == on %}

include {% $NGINX_MODULE_PAGESPEED_PATH %};

{% endif %}

{% endif %}
