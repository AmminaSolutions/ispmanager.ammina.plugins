<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>
ServerName {% $NAME %}
{% if $ALIASES != "" %}
	ServerAlias [% $ALIASES %]
{% endif %}
{% if $NGINX_FOREGROUND %}
	ServerAlias {% $IPADDRS %}
{% endif %}
DocumentRoot [% $DOCROOT %]
ServerAdmin [% $EMAIL %]
{% if $DIRINDEX != "" %}
	DirectoryIndex [% $DIRINDEX %]
{% endif %}
AddDefaultCharset [% $CHARSET %]
{% if $APACHEITK == on %}
	AssignUserID {% $OWNER %} {% $OWNER_GROUP %}
{% else %}
	SuexecUserGroup {% $OWNER %} {% $OWNER_GROUP %}
{% endif %}
{% if $LOG_ACCESS == on %}
	CustomLog {% $ACCESS_LOG_PATH %} combined
{% else %}
	CustomLog /dev/null combined
{% endif %}
{% if $LOG_ERROR == on %}
	ErrorLog {% $ERROR_LOG_PATH %}
{% else %}
	ErrorLog /dev/null
{% endif %}
{% if $CGI == on %}
	ScriptAlias /cgi-bin/ {% $CGI_EXT_PATH %}
{% endif %}
<Directory [% $DOCROOT %]>
	Options -Indexes
</Directory>
<? $this->includePart('apache2/pagespeed.php') ?>
<? $this->includePart('apache2/composer.php') ?>
<? $this->includePart('apache2/python.php') ?>
<? $this->includePart('apache2/php.php') ?>
<? $this->includePart('apache2/nginx_or_seo.php') ?>
{% if $INCLUDE %}
	Include {% $INCLUDE %}
{% endif %}
{% if $USE_RESOURCES == on %}
	Include {% $INCLUDE_PHP %}
{% endif %}
{% if $VIRTUAL_DOCROOT == on %}
	VirtualDocumentRoot [% $VIRTUAL_DOCROOT_PATH %]
{% endif %}
