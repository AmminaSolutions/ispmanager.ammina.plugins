<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
<? if ($this->param('platform') === 'default' && $this->param('|AV|PHPCOMPOSER') === 'on') { ?>
	IncludeOptional <?= $this->param('|AV|APACHE_MODULE_PHPCOMPOSER_PATH', true) ?>{% $ %}
	<?
}
