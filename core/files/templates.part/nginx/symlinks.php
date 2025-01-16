<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>

{% if $PLATFORM == default %}

disable_symlinks if_not_owner from=$root_path;

{% else %}

disable_symlinks off;

{% endif %}
