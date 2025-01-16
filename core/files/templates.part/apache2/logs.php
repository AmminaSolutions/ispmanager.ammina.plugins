<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>
{% if $LOG_ACCESS == on %}
CustomLog {% $ACCESS_LOG_PATH %} combined
{% else %}
CustomLog /dev/null combined
{% endif %}
{% if $LOG_ERROR == on %}
ErrorLog {% $ERROR_LOG_PATH %}
{% else %}
ErrorLog /dev/null
{% endif %}
