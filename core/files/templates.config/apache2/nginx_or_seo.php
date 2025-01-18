<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
<? if ($this->param('|AV|NGINX_FRONTEND') == 'on') { ?>
	SetEnvIf X-Forwarded-Proto https HTTPS=on
<? }