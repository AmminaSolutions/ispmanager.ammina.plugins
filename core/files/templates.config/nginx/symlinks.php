<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
<? if ($this->param('platform') === 'default') { ?>
	disable_symlinks if_not_owner from=$root_path;
<? } else { ?>
	disable_symlinks off;
<? }
