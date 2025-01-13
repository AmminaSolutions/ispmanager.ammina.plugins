<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>
{% if $PLATFORM == "default" %}
{% if $PHPCOMPOSER == on %}
IncludeOptional {% $APACHE_MODULE_PHPCOMPOSER_PATH %}
{% endif %}
{% endif %}
