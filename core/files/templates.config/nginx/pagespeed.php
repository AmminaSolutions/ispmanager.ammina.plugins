<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
<? if ($this->param('platform') === 'default' && $this->param('|NV|PAGESPEED') === 'on') { ?>
	include <?= $this->param('|NV|NGINX_MODULE_PAGESPEED_PATH') ?>;
	<?
}