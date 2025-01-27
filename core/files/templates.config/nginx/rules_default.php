<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>
	location / {
<? if ($this->param('|NV|PHP') === 'on') {
	if ($this->param('|NV|SEMANTIC_URL') === 'on' && $this->param('|NV|PHP_MODE') === 'php_mode_fcgi_nginxfpm') {
		?>
		try_files $uri $uri/ /index.php?$args;
	<? } ?>
	location ~ [^/]\.ph(p\d*|tml)$ {
	<? if ($this->param('|NV|PHP_MODE') === 'php_mode_fcgi_nginxfpm') { ?>
		try_files /does_not_exists @php;
	<? } else { ?>
		try_files /does_not_exists @fallback;
	<? } ?>
	}
<? } ?>
	location ~* ^.+\.(<?= $this->param('|NV|NGINX_STATIC') ?>)$ {
<? if ($this->param('|NV|SRV_CACHE') === 'on') { ?>
	expires <?= $this->param('|NV|EXPIRES_VALUE') ?>;
	<?
}
if ($this->param('|NV|REDIRECT_TO_APACHE') === 'on') {
	?>
	try_files $uri $uri/ @fallback;
<? } ?>
	}
<? if ($this->param('|NV|REDIRECT_TO_APACHE') === 'on') { ?>
	location / {
	try_files /does_not_exists @fallback;
	}
<? } ?>
	}
<? if ($this->param('|NV|ANALYZER') !== 'off' && !empty($this->param('|NV|ANALYZER'))) { ?>
	location <?= $this->param('|NV|WEBSTAT_LOCATION') ?> {
	charset <?= $this->param('|NV|WEBSTAT_ENCODING') ?>;
	index index.html;
	<? if ($this->param('|NV|PHP') === 'on') { ?>
		location ~ [^/]\.ph(p\d*|tml)$ {
		<? if ($this->param('|NV|PHP_MODE') === 'php_mode_fcgi_nginxfpm') { ?>
			try_files /does_not_exists @php;
		<? } else { ?>
			try_files /does_not_exists @fallback;
			<?
		}
		?>
		}
		<?
	}
	if ($this->param('|NV|REDIRECT_TO_APACHE') === 'on') {
		?>
		location ~* ^.+\.(<?= $this->param('|NV|NGINX_STATIC') ?>)$ {
		<? if ($this->param('|NV|SRV_CACHE') === 'on') {
			?>
			expires <?= $this->param('|NV|EXPIRES_VALUE') ?>;
			<?
		} ?>
		try_files $uri $uri/ @fallback;
		}
		location <?= $this->param('|NV|WEBSTAT_LOCATION') ?> {
		try_files /does_not_exists @fallback;
		}
		<?
	} ?>
	}

	<?
}
if ($this->param('|NV|REDIRECT_TO_APACHE') === 'on') {
	?>
	location @fallback {
	include <?= $this->param('|NV|INCLUDE_DYNAMIC_RESOURCE_PATH') ?>;
	proxy_pass <?= $this->param('|NV|BACKEND_BIND_URI') ?>;
	proxy_redirect <?= $this->param('|NV|BACKEND_BIND_URI') ?> /;
	proxy_set_header Host $host;
	proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
	proxy_set_header X-Forwarded-Proto $scheme;
	proxy_set_header X-Forwarded-Port $server_port;
	<? if ($this->param('|NV|NO_TRAFF_COUNT') === 'on') {
		?>
		access_log off;
		<?
	} ?>
	}

	<?
}
if ($this->param('|NV|REDIRECT_TO_PHPFPM') === 'on') {
	?>
	location @php {
	include <?= $this->param('|NV|INCLUDE_DYNAMIC_RESOURCE_PATH') ?>;
	fastcgi_index index.php;
	fastcgi_param PHP_ADMIN_VALUE "sendmail_path = /usr/sbin/sendmail -t -i -f <?= $this->param('|NV|EMAIL') ?>";
	fastcgi_pass <?= $this->param('|NV|PHPFPM_USER_SOCKET_PATH') ?>;
	fastcgi_split_path_info ^((?U).+\.ph(?:p\d*|tml))(/?.+)$;
	try_files $uri =404;
	include fastcgi_params;
	}

	<?
}