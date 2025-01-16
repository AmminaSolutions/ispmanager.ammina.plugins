<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>

fastcgi_pass    $php_sock;
fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
include fastcgi_params;
