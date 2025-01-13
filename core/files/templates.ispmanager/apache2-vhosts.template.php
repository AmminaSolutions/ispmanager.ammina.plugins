<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>
<VirtualHost {% $LISTEN_ON %}>
	<? $this->includePart('apache2/main.php') ?>
</VirtualHost>
{% if $SSL == on %}
{% import etc/templates/apache2-vhosts-ssl.template %}
{% endif %}
