<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
<? if ($this->param('platform') === 'default' && $this->param('|AV|PAGESPEED') === 'on') { ?>
	<IfModule pagespeed_module>
		ModPagespeed on
	</IfModule>
	<?
}
