<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>
{% if $PLATFORM == "default" %}
{% if $PAGESPEED == on %}
<IfModule pagespeed_module>
	ModPagespeed on
</IfModule>
{% endif %}
{% endif %}
