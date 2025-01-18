<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
<? if (!empty($this->param('|AV|USER_PHP_CONF'))) { ?>
	Include <?= $this->param('|AV|USER_PHP_CONF', true) ?>
<? }
if (!empty($this->param('|AV|SITE_PHP_CONF'))) { ?>
	Include <?= $this->param('|AV|SITE_PHP_CONF', true) ?>
<? }
