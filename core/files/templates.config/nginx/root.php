<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>

	set $root_path <?= $this->param('|NV|VIRTUAL_DOCROOT') ?>;
<? if ($this->param('platform') === 'default') {
	if ($this->param('|NV|AUTOSUBDOMAIN') !== 'off' && !empty($this->param('|NV|AUTOSUBDOMAIN'))) {
		if ($this->param('|NV|AUTOSUBDOMAIN') === 'autosubdomain_subdir') {
			?>
			set $subdomain "";
			<?
		} else {
			?>
			set $subdomain <?= $this->param('|NV|AUTOSUBDOMAIN_SUBDOMAIN_PART') ?>;
			<?
		}
		?>
		if ($host ~* ^((.*).<?= $this->param('|NV|NAME') ?>)$) {
		<? if ($this->param('|NV|AUTOSUBDOMAIN') === 'autosubdomain_dir') {
			?>
			set $subdomain $1;
		<? } elseif ($this->param('|NV|AUTOSUBDOMAIN') === 'autosubdomain_subdir') {
			?>
			set $subdomain $2;
		<? } ?>
		}
		root $root_path/$subdomain;
		<?
	} else {
		?>
		root $root_path;
		<?
	}
	?>

	<?
} else {
	?>
	root $root_path;
	<?
}
