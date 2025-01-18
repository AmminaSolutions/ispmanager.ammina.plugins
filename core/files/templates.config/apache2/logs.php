<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
<? if ($this->param('|AV|LOG_ACCESS') === 'on') { ?>
	CustomLog <?= $this->param('|AV|ACCESS_LOG_PATH') ?> combined
<? } else { ?>
	CustomLog /dev/null combined
	<?
}
if ($this->param('|AV|LOG_ERROR') === 'on') {
	?>
	ErrorLog <?= $this->param('|AV|ERROR_LOG_PATH', true) ?>
<? } else { ?>
	ErrorLog /dev/null
	<?
}