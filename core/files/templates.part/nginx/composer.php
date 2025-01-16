<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>
{% if $PLATFORM == default %}

{% if $PHPCOMPOSER == on %}

include {% $NGINX_MODULE_PHPCOMPOSER_PATH %};

{% endif %}

{% endif %}
