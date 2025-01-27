<?php
/**
 * @var \AmminaISP\Core\TemplateGenerator $this
 */
?>

add_header "X-Content-Type-Options" "nosniff";
set $setSameOrigin SAMEORIGIN;
if ($request_uri ~ "^/(pub/|online/|services/telephony/info_receiver.php|/bitrix/tools/voximplant/)") {
set $setSameOrigin '';
}
add_header X-Frame-Options $setSameOrigin;
<? if ($this->param('|NV|REDIRECT_TO_PHPFPM') === 'on') { ?>
	set $php_sock <?= $this->param('|NV|PHPFPM_USER_SOCKET_PATH') ?>;
<? } ?>

location ~* /\.ht  { deny all; }
location ~* /\.(svn|hg|git) { deny all; }
location ~* ^/bitrix/(modules|local_cache|stack_cache|managed_cache|php_interface) {
deny all;
}
location ~* ^/local/(modules|php_interface) {
deny all;
}
location ~* ^/upload/1c_[^/]+/ { deny all; }
location ~* /\.\./ { deny all; }
location ~* ^/bitrix/html_pages/\.config\.php { deny all; }
location ~* ^/bitrix/html_pages/\.enabled { deny all; }
location ^~ /upload/support/not_image   { internal; }

error_page 403 /403.html;
error_page 404 = @ammina404;
error_page 500 /500.html;
error_page 502 /502.html;
error_page 503 /503.html;
error_page 504 /504.html;

location ^~ /500.html    { root /var/www/amminaenv_error; }
location ^~ /502.html    { root /var/www/amminaenv_error; }
location ^~ /503.html    { root /var/www/amminaenv_error; }
location ^~ /504.html    { root /var/www/amminaenv_error; }
location ^~ /403.html    { root /var/www/amminaenv_error; }
location ^~ /404.html    { root /var/www/amminaenv_error; }
location @ammina404    {
access_log off;
error_page     404 405 412 502 504 = @bitrix;
try_files       $uri $uri/ @bitrix;
}
<? if ($this->param('bitrix_settings_pushserver') === 'on') { ?>
	location ~* ^/bitrix/subws/ {
	access_log off;
	proxy_pass http://nodejs_sub;
	proxy_max_temp_file_size 0;
	proxy_read_timeout  43800;
	proxy_http_version 1.1;
	proxy_set_header Upgrade $replace_upgrade;
	proxy_set_header Connection $connection_upgrade;
	}

	location ~* ^/bitrix/sub/ {
	access_log off;
	rewrite ^/bitrix/sub/(.*)$ /bitrix/subws/$1 break;
	proxy_pass http://nodejs_sub;
	proxy_max_temp_file_size 0;
	proxy_read_timeout  43800;
	}

	location ~* ^/bitrix/rest/ {
	access_log off;
	proxy_pass http://nodejs_pub;
	proxy_max_temp_file_size 0;
	proxy_read_timeout  43800;
	}
	<?
}
if ($this->param('bitrix_settings_composite') === 'on') {
	?>
	include amminaisp/conf/ammina_composite.conf;

	set $composite_cache    "bitrix/html_pages/${host}${composite_key}/index@${args}.html";
	set $composite_file     "${root_path}/${composite_cache}";
	set $composite_enabled  "${root_path}/bitrix/html_pages/.enabled";
	set $use_composite_cache "";
	if ($is_global_composite  = 1) {set $use_composite_cache "A";}
	if ($is_site_composite_<?= $this->param('bitrix_composite_var_suffix') ?> = 1) {set $use_composite_cache "${use_composite_cache}B";}

	if (-f $composite_enabled)     { set $use_composite_cache "${use_composite_cache}C"; }
	<? if ($this->param('bitrix_composite_memcached') === 'on') { ?>
		set $use_composite_cache "${use_composite_cache}D";
		memcached_connect_timeout 1s;
		memcached_read_timeout 1s;
		memcached_send_timeout 1s;
		memcached_gzip_flag 65536;
	<? } else { ?>
		if (-f $composite_file)  { set $use_composite_cache "${use_composite_cache}D"; }
		<?
	}
}
if ($this->param('bitrix_composite_memcached') === 'on') {
	if ($this->param('|NV|REDIRECT_TO_PHPFPM') === 'on') {
		?>
		location / {
		error_page     404 405 412 502 504 = @bitrix2;
		try_files       $uri $uri/ @bitrix2;
		}
	<? } else { ?>
		location / {
		error_page     404 405 412 502 504 = @bitrix;
		default_type text/html;
		<? if ($this->param('bitrix_settings_composite') === 'on') { ?>

			if ($use_composite_cache = "ABCD") {
			add_header X-Bitrix-Composite "Nginx (memcached)";
			set $memcached_key "/${host}${composite_key}/index@${args}.html";
			memcached_pass <?= $this->param('bitrix_composite_memcached_pass') ?>;
			}
			<?
		} ?>

		try_files /does_not_exists @bitrix;
		}
		<?
	}
} else { ?>
	location / {
	<? if ($this->param('|NV|REDIRECT_TO_APACHE') === 'on') { ?>
		location ~* ^.+\.(jpg|jpeg|gif|png|svg|js|css|mp3|ogg|mpe?g|avi|zip|gz|bz2?|rar|swf|webp|woff2|woff|ttf|otf|eot|ico|webp|mp4|webm)$ {
		<? if ($this->param('|NV|SRV_CACHE') === 'on') { ?>
			expires <?= $this->param('|NV|EXPIRES_VALUE') ?>;
			<?
		} ?>
		try_files $uri $uri/ @bitrix;
		}
		location / {
		<? if ($this->param('bitrix_settings_composite') === 'on') { ?>
			if ($use_composite_cache = "ABCD") {
			add_header X-Bitrix-Composite "Nginx (file)";
			rewrite .* /$composite_cache last;
			}
			<?
		} ?>
		try_files       /does_not_exists @bitrix;
		}
		<?
	} else { ?>
		error_page     404 405 412 502 504 = @bitrix;
		try_files       $uri $uri/ @bitrix;
		<?
	} ?>
	}
	<? if ($this->param('|NV|REDIRECT_TO_APACHE') === 'on') { ?>
		location ~ [^/]\.ph(p\d*|tml)$ {
		try_files /does_not_exists @bitrix;
		}
		<?
	}
}
?>
location ~* /upload/.*\.(php|php3|php4|php5|php6|phtml|pl|asp|aspx|cgi|dll|exe|shtm|shtml|fcg|fcgi|fpl|asmx|pht|py|psp|rb|var)$ {
types {
text/plain text/plain php php3 php4 php5 php6 phtml pl asp aspx cgi dll exe ico shtm shtml fcg fcgi fpl asmx pht py psp rb var;
}
}
<? if ($this->param('|NV|REDIRECT_TO_APACHE') === 'on') { ?>
	location @bitrix{
	proxy_pass <?= $this->param('|NV|BACKEND_BIND_URI') ?>;
	proxy_redirect <?= $this->param('|NV|BACKEND_BIND_URI') ?> /;
	proxy_set_header Host $host;
	proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
	proxy_set_header X-Forwarded-Proto $scheme;
	proxy_set_header X-Forwarded-Port $server_port;
	access_log off;
	}
	<?
}
if ($this->param('|NV|REDIRECT_TO_PHPFPM') === 'on') {
	if ($this->param('bitrix_composite_memcached') === 'on') { ?>
		location ~ \.php$ {
		error_page     404 405 412 502 504 = @bitrix;
		default_type text/html;
		if ($use_composite_cache = "ABCD") {
		add_header X-Bitrix-Composite "Nginx (memcached)";
		set $memcached_key "/${host}${composite_key}/index@${args}.html";
		memcached_pass <?= $this->param('bitrix_composite_memcached_pass') ?>;
		}
		try_files       $uri @bitrix2;
		<?= $this->includePart('nginx/fastcgi.php'); ?>
		fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
		}
		location @bitrix{
		try_files       $uri @bitrix2;
		<?= $this->includePart('nginx/fastcgi.php'); ?>
		fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
		}
		location @bitrix2 {
		error_page     404 405 412 502 504 = @bitrix3;
		default_type text/html;
		if ($use_composite_cache = "ABCD") {
		add_header X-Bitrix-Composite "Nginx (memcached)";
		set $memcached_key "/${host}${composite_key}/index@${args}.html";
		memcached_pass <?= $this->param('bitrix_composite_memcached_pass') ?>;
		}
		<?= $this->includePart('nginx/fastcgi.php'); ?>
		fastcgi_param SCRIPT_FILENAME $document_root/bitrix/urlrewrite.php;
		}
		location @bitrix3 {
		<?= $this->includePart('nginx/fastcgi.php'); ?>
		fastcgi_param SCRIPT_FILENAME $document_root/bitrix/urlrewrite.php;
		}
		location ~* /bitrix/admin.+\.php$ {
		error_page     404 405 412 502 504 = @bitrixadm;
		default_type text/html;
		try_files       $uri @bitrixadm;
		<?= $this->includePart('nginx/fastcgi.php'); ?>
		fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
		}
		location @bitrixadm {
		default_type text/html;
		<?= $this->includePart('nginx/fastcgi.php'); ?>
		fastcgi_param SCRIPT_FILENAME $document_root/bitrix/admin/404.php;
		}
	<? } else { ?>
		location ~ \.php$ {
		error_page     404 405 412 502 504 = @bitrix;
		try_files       $uri @bitrix;
		<?= $this->includePart('nginx/fastcgi.php'); ?>
		fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
		}
		location @bitrix {
		<?= $this->includePart('nginx/fastcgi.php'); ?>
		fastcgi_param SCRIPT_FILENAME $document_root/bitrix/urlrewrite.php;
		}
		location ~* /bitrix/admin.+\.php$ {
		error_page     404 405 412 502 504 = @bitrixadm;
		default_type text/html;
		try_files       $uri @bitrixadm;
		<?= $this->includePart('nginx/fastcgi.php'); ?>
		fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
		}
		location @bitrixadm {
		default_type text/html;
		<?= $this->includePart('nginx/fastcgi.php'); ?>
		fastcgi_param SCRIPT_FILENAME $document_root/bitrix/admin/404.php;
		}
		<?
	}
}
?>
location = /favicon.ico {
log_not_found off;
access_log off;
<? if ($this->param('|NV|SRV_CACHE') === 'on') { ?>
	expires <?= $this->param('|NV|EXPIRES_VALUE') ?>;
<? } ?>
}
<? if ($this->param('seo_settings_deny_robots') === 'on') { ?>
	location = /robots.txt {
	add_header Content-Type text/plain;
	return 200 "User-agent: *\nDisallow: /\n";
	}
	if ($http_user_agent ~ WordPress|SemrushBot|SputnikBot|Crowsnest|PaperLiBot|peerindex|ia_archiver|Slurp|Aport|NING|JSKit|rogerbot|BLEXBot|MJ12bot|Twiceler|Baiduspider|Java|CommentReader|Yeti|discobot|BTWebClient|Tagoobot|Ezooms|igdeSpyder|AhrefsBot|Teleport|Offline|DISCo|netvampire|Copier|HTTrack|WebCopier|GrapeshotCrawler|coccocbotweb|HybridBot|magpiecrawlerHostTracker|Riddler|SentiBot|HostTracker|YandexAccessibilityBot|YandexAdNet|YandexBlogs|YandexBot|YandexCalendar|YandexDirect|YandexFavicons|YaDirectFetcher|YandexForDomain|YandexImages|YandexImageResizer|YandexMarket|YandexMedia|YandexMetrika|APIs-Google|Mediapartners-Google|AdsBot-Google-Mobile|AdsBot-Google-Mobile|AdsBot-Google|Googlebot|AdsBot-Google-Mobile-Apps|FeedFetcher-Google|Google-Read-Aloud|DuplexWeb-Google|Favicon) {
	return 403;
	}
<? } else { ?>
	location = /robots.txt {
	<? if ($this->param('bitrix_modules_ammina_regions') === 'on') {
		if ($this->param('|NV|REDIRECT_TO_APACHE') === 'on') {
			?>
			proxy_pass <?= $this->param('|NV|BACKEND_BIND_URI') ?>/bitrix/tools/ammina.robots.php;
			proxy_redirect <?= $this->param('|NV|BACKEND_BIND_URI') ?> /;
			proxy_set_header Host $host;
			proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
			proxy_set_header X-Forwarded-Proto $scheme;
			proxy_set_header X-Forwarded-Port $server_port;
			access_log off;
			<?
		}
		if ($this->param('|NV|REDIRECT_TO_PHPFPM') === 'on') { ?>
			<?= $this->includePart('nginx/fastcgi.php'); ?>
			fastcgi_param SCRIPT_FILENAME $document_root/bitrix/tools/ammina.robots.php;
			<?
		}
	} ?>
	allow all;
	log_not_found off;
	access_log off;
	}
<? } ?>
location ~* @.*\.html$ {
internal;
expires -1y;
add_header X-Bitrix-Composite "Nginx (file)";
}
location ~* ^/bitrix/components/bitrix/player/mediaplayer/player$ {
add_header Access-Control-Allow-Origin *;
}
location ~* ^/bitrix/cache/(css/.+\.css|js/.+\.js)$ {
<? if ($this->param('|NV|SRV_CACHE') === 'on') { ?>
	expires <?= $this->param('|NV|EXPIRES_VALUE') ?>;
<? } ?>
error_page 404 /404.html;
}
location ^~ /bitrix/ammina.cache/ {
<? if ($this->param('|NV|SRV_CACHE') === 'on') { ?>
	expires <?= $this->param('|NV|EXPIRES_VALUE') ?>;
<? } ?>
error_page 404 /404.html;
}
location ~* ^/bitrix/cache { deny all; }

location ^~ /upload/bx_cloud_upload/ {
location ~ ^/upload/bx_cloud_upload/(http[s]?)\.([^/:]+)\.(s3|s3-us-west-1|s3-eu-west-1|s3-ap-southeast-1|s3-ap-northeast-1)\.amazonaws\.com/(.+)$ {
internal;
resolver 8.8.8.8;
proxy_method GET;
proxy_set_header    X-Real-IP               $remote_addr;
proxy_set_header    X-Forwarded-For         $proxy_add_x_forwarded_for;
proxy_set_header    X-Forwarded-Server      $host;
#proxy_max_temp_file_size 0;
proxy_pass $1://$2.$3.amazonaws.com/$4;
}
location ~* .*$       { deny all; }
}

location ~* ^.+\.(jpg|jpeg|gif|png|svg|js|css|mp3|ogg|mpe?g|avi|zip|gz|bz2?|rar|swf|webp|woff2|woff|ttf|otf|eot|ico|webp|mp4|webm)$ {
error_page 404 /404.html;
<? if ($this->param('|NV|SRV_CACHE') === 'on') { ?>
	expires <?= $this->param('|NV|EXPIRES_VALUE') ?>;
<? } ?>
}
location = /404.php {
access_log off ;
}
