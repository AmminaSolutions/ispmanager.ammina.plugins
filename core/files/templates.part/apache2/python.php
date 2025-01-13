<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>
{% if $PLATFORM == "default" %}
{% if $PYTHON == on %}
IncludeOptional {% $APACHE_MODULE_PYTHON_PATH %}
{% endif %}
{% if $PYTHON_MODE == python_mode_cgi %}
ScriptAlias /python-bin/ [% $PYTHON_BIN_DIR %]
AddHandler application/x-httpd-python .py
Action application/x-httpd-python /python-bin/python
<FilesMatch "\.py$">
SetHandler application/x-httpd-python
</FilesMatch>
{% endif %}
{% endif %}
