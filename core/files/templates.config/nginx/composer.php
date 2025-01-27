<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
<? if ($this->param('platform') === 'default' && $this->param('|NV|PHPCOMPOSER') === 'on') { ?>
	include <?= $this->param('|NV|NGINX_MODULE_PHPCOMPOSER_PATH') ?>;
	<?
}