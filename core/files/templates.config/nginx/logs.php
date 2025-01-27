<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
<? if ($this->param('|NV|LOG_ACCESS') === 'on') { ?>
	access_log <?= $this->param('|NV|ACCESS_LOG_PATH') ?>;
	<?
} elseif ($this->param('|NV|NO_TRAFF_COUNT') === 'on') {
	?>
	access_log off;
	<?
}
if ($this->param('|NV|LOG_ERROR') === 'on') { ?>
	error_log <?= $this->param('|NV|ERROR_LOG_PATH') ?> notice;
	<?
} else {
	?>
	error_log /dev/null crit;
	<?
}
