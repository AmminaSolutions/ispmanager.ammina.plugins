<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>

charset [% $CHARSET %];

{% if $DIRINDEX != "" %}

index [% $DIRINDEX %];

{% endif %}

<? $this->includePart('nginx/symlinks.php'); ?>

include {% $NGINX_VHOST_INCLUDES %};
include {% $INCLUDE_RESOURCE_PATH %};

<? $this->includePart('nginx/pagespeed.php'); ?>

<? $this->includePart('nginx/composer.php'); ?>

<? $this->includePart('nginx/logs.php'); ?>

{% if $SSI == on %}

ssi on;

{% endif %}

<? $this->includePart('nginx/seo.php'); ?>

<? $this->includePart('nginx/root.php'); ?>

{% if $USER_RESOURCES %}

include {% $USER_NGINX_RESOURCES_PATH %};

{% endif %}

<? $this->includePart('nginx/optimization.php'); ?>

<? $this->includePart('nginx/rules_default.php'); ?>

<? $this->includePart('nginx/rules_bitrix.php'); ?>

<? $this->includePart('nginx/rules_laravel.php'); ?>
