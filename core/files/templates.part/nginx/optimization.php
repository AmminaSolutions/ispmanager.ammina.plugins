<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>

{% if $SRV_GZIP == on %}

gzip on;
gzip_comp_level [% $GZIP_LEVEL %];
gzip_disable "msie6";
gzip_types [% $GZIP_TYPES %];

{% endif %}
