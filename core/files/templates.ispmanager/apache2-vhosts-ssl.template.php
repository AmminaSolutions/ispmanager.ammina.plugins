<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>
<VirtualHost {% $LISTEN_ON_SSL %}>

	<? $this->includePart('apache2/ssl.php') ?>
	<? $this->includePart('apache2/main.php') ?>

</VirtualHost>
