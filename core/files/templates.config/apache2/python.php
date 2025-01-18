<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
<? if ($this->param('platform') === 'default') {
	if ($this->param('|AV|PYTHON') === 'on') {
		?>
		IncludeOptional <?= $this->param('|AV|APACHE_MODULE_PYTHON_PATH', true) ?>
		<?
	}
	if ($this->param('|AV|PYTHON_MODE') === 'python_mode_cgi') {
		?>
		ScriptAlias /python-bin/ <?= $this->param('|AV|PYTHON_BIN_DIR', true) ?>
		AddHandler application/x-httpd-python .py
		Action application/x-httpd-python /python-bin/python
		<FilesMatch "\.py$">
		SetHandler application/x-httpd-python
		</FilesMatch>
		<?
	}
}
