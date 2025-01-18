<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
<VirtualHost <?= $this->param('|AV|LISTEN_ON') ?>>
	<?= $this->includePart('apache2/main.php') ?>

</VirtualHost>
<? if ($this->param('|AV|SSL') === 'on') { ?>
	<VirtualHost <?= $this->param('|AV|LISTEN_ON_SSL') ?>>
		<?= $this->includePart('apache2/ssl.php') ?>
		<?= $this->includePart('apache2/main.php') ?>
	</VirtualHost>
<? } ?>
<?= $this->includePart('apache2/directory.php') ?>