<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
include fastcgi_params;
fastcgi_pass    $php_sock;
<?= $this->includePart('nginx/fastcgi_php.php'); ?>
