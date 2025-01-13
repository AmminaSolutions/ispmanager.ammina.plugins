<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>

{% if $PHP == on and $FILES_MATCH == on %}
<FilesMatch "\.ph(p[3-5]?|tml)$">
SetHandler [% $PHP_HANDLER %]
{% if $APACHE_FCGID == on and $PHP_MODE == php_mode_fcgi_apache %}
{% if $PLATFORM == default %}
FCGIWrapper [% $PHP_BIN_WRAPPER %]
{% else %}
FCGIWrapper [% $PHP_BIN_WRAPPER_AMMINA %]
{% endif %}
{% endif %}
</FilesMatch>
{% endif %}
{% if $PHP_MODE == php_mode_mod %}
<FilesMatch "\.phps$">
SetHandler application/x-httpd-php-source
</FilesMatch>
{% if $ISP_CUT %}
php_admin_value sendmail_path "/usr/sbin/sendmail -t -i -f {% $EMAIL %}"
php_admin_value upload_tmp_dir "{% $MOD_TMP_PATH %}"
php_admin_value session.save_path "{% $MOD_TMP_PATH %}"
php_admin_value open_basedir "{% $BASEDIR_PATH %}"
{% endif %}
<IfModule php5_module>
	{% if $USER_PHP_CONF %}
	Include {% $USER_PHP_CONF %}
	{% endif %}
	{% if $SITE_PHP_CONF %}
	Include {% $SITE_PHP_CONF %}
	{% endif %}
	php_admin_value sendmail_path "/usr/sbin/sendmail -t -i -f {% $EMAIL %}"
	php_admin_value upload_tmp_dir "{% $MOD_TMP_PATH %}"
	php_admin_value session.save_path "{% $MOD_TMP_PATH %}"
	php_admin_value open_basedir "{% $BASEDIR_PATH %}"
</IfModule>
<IfModule php7_module>
	{% if $USER_PHP_CONF %}
	Include {% $USER_PHP_CONF %}
	{% endif %}
	{% if $SITE_PHP_CONF %}
	Include {% $SITE_PHP_CONF %}
	{% endif %}
	php_admin_value sendmail_path "/usr/sbin/sendmail -t -i -f {% $EMAIL %}"
	php_admin_value upload_tmp_dir "{% $MOD_TMP_PATH %}"
	php_admin_value session.save_path "{% $MOD_TMP_PATH %}"
	php_admin_value open_basedir "{% $BASEDIR_PATH %}"
</IfModule>
<IfModule php_module>
	{% if $USER_PHP_CONF %}
	Include {% $USER_PHP_CONF %}
	{% endif %}
	{% if $SITE_PHP_CONF %}
	Include {% $SITE_PHP_CONF %}
	{% endif %}
	php_admin_value sendmail_path "/usr/sbin/sendmail -t -i -f {% $EMAIL %}"
	php_admin_value upload_tmp_dir "{% $MOD_TMP_PATH %}"
	php_admin_value session.save_path "{% $MOD_TMP_PATH %}"
	php_admin_value open_basedir "{% $BASEDIR_PATH %}"
</IfModule>
{% elif $PHP_MODE == php_mode_lsapi %}
<FilesMatch "\.phps$">
SetHandler application/x-httpd-php-source
</FilesMatch>
{% if $ISP_CUT %}
php_admin_value sendmail_path "/usr/sbin/sendmail -t -i -f {% $EMAIL %}"
php_admin_value upload_tmp_dir "{% $MOD_TMP_PATH %}"
php_admin_value session.save_path "{% $MOD_TMP_PATH %}"
php_admin_value open_basedir "{% $BASEDIR_PATH %}"
{% endif %}
<IfModule lsapi_module>
	php_admin_value sendmail_path "/usr/sbin/sendmail -t -i -f {% $EMAIL %}"
	php_admin_value upload_tmp_dir "{% $MOD_TMP_PATH %}"
	php_admin_value session.save_path "{% $MOD_TMP_PATH %}"
	php_admin_value open_basedir "{% $BASEDIR_PATH %}"
</IfModule>
{% elif $PHP_MODE == php_mode_cgi %}
ScriptAlias /php-bin/ [% $PHP_BIN_DIR %]
AddHandler application/x-httpd-php5 .php .php3 .php4 .php5 .phtml
Action application/x-httpd-php5 /php-bin/php
{% elif $PHP_MODE == php_mode_fcgi_apache and $APACHE_FCGID != on %}
AddType application/x-httpd-fastphp .php .php3 .php4 .php5 .phtml
Alias /php-fcgi/ [% $PHP_BIN_DIR %]
{% endif %}
