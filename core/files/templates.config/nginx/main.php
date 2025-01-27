<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>

	charset <?= $this->param('|NV|CHARSET') ?>;
<? if (!empty($this->param('|NV|DIRINDEX'))) { ?>
	index <?= $this->param('|NV|DIRINDEX') ?>;
<? } ?>
<?= $this->includePart('nginx/symlinks.php'); ?>
	include <?= $this->param('|NV|NGINX_VHOST_INCLUDES') ?>;
	include <?= $this->param('|NV|INCLUDE_RESOURCE_PATH') ?>;

<?= $this->includePart('nginx/pagespeed.php'); ?>
<?= $this->includePart('nginx/composer.php'); ?>
<?= $this->includePart('nginx/logs.php'); ?>

<? if ($this->param('|NV|SSI') === 'on') { ?>
	ssi on;
<? } ?>

<?= $this->includePart('nginx/seo.php'); ?>
<?= $this->includePart('nginx/root.php'); ?>

<? if (!empty($this->param('|NV|USER_RESOURCES'))) { ?>
	include <?= $this->param('|NV|USER_NGINX_RESOURCES_PATH') ?>;
<? } ?>

<?= $this->includePart('nginx/optimization.php'); ?>
<? if ($this->param('platform') === 'default') { ?>
	<?= $this->includePart('nginx/rules_default.php'); ?>
	<?
}
if ($this->param('platform') === 'bitrix') {
	?>
	<?= $this->includePart('nginx/rules_bitrix.php'); ?>
	<?
}
if ($this->param('platform') === 'laravel') {
	?>
	<?= $this->includePart('nginx/rules_laravel.php'); ?>
	<?
}
