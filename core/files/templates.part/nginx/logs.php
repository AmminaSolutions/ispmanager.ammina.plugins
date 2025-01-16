<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>
{% if $LOG_ACCESS == on %}

access_log [% $ACCESS_LOG_PATH %];

{% elif $NO_TRAFF_COUNT == on %}

access_log off;

{% endif %}

{% if $LOG_ERROR == on %}

error_log {% $ERROR_LOG_PATH %} notice;

{% else %}

error_log /dev/null crit;

{% endif %}
