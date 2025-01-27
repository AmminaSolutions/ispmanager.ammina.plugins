<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
<? if ($this->param('|NV|SRV_GZIP') === 'on') { ?>
	gzip on;
	gzip_comp_level <?= $this->param('|NV|GZIP_LEVEL') ?>;
	gzip_disable "msie6";
	gzip_types <?= $this->param('|NV|GZIP_TYPES') ?>;
<? }