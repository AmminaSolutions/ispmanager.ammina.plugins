<?php
/**
 * @var \AmminaISP\Core\TemplateSynchronizer $this
 */
?>

{% if $PLATFORM == default %}

location / {

	{% if $PHP == on %}

		{% if $SEMANTIC_URL == on and $PHP_MODE == php_mode_fcgi_nginxfpm %}

			try_files $uri $uri/ /index.php?$args;

		{% endif %}

		location ~ [^/]\.ph(p\d*|tml)$ {

			{% if $PHP_MODE == php_mode_fcgi_nginxfpm %}

				try_files /does_not_exists @php;

			{% else %}

				try_files /does_not_exists @fallback;

			{% endif %}

		}
	{% endif %}

	location ~* ^.+\.({% $NGINX_STATIC %})$ {

		{% if $SRV_CACHE == on %}

			expires [% $EXPIRES_VALUE %];

		{% endif %}

		{% if $REDIRECT_TO_APACHE == on %}

			try_files $uri $uri/ @fallback;

		{% endif %}

	}

	{% if $REDIRECT_TO_APACHE == on %}

		location / {
			try_files /does_not_exists @fallback;
		}

	{% endif %}

}

{% if $ANALYZER != off and $ANALYZER != "" %}

	location {% $WEBSTAT_LOCATION %} {
		charset [% $WEBSTAT_ENCODING %];
		index index.html;

		{% if $PHP == on %}

			location ~ [^/]\.ph(p\d*|tml)$ {

				{% if $PHP_MODE == php_mode_fcgi_nginxfpm %}

					try_files /does_not_exists @php;

				{% else %}

					try_files /does_not_exists @fallback;

				{% endif %}

			}

		{% endif %}

		{% if $REDIRECT_TO_APACHE == on %}

			location ~* ^.+\.({% $NGINX_STATIC %})$ {

				{% if $SRV_CACHE == on %}

					expires [% $EXPIRES_VALUE %];

				{% endif %}

				try_files $uri $uri/ @fallback;
			}
			location {% $WEBSTAT_LOCATION %} {
				try_files /does_not_exists @fallback;
			}

		{% endif %}

	}

{% endif %}

{% if $REDIRECT_TO_APACHE == on %}

	location @fallback {
		include {% $INCLUDE_DYNAMIC_RESOURCE_PATH %};
		proxy_pass {% $BACKEND_BIND_URI %};
		proxy_redirect {% $BACKEND_BIND_URI %} /;
		proxy_set_header Host $host;
		proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
		proxy_set_header X-Forwarded-Proto $scheme;
		proxy_set_header X-Forwarded-Port $server_port;

		{% if $NO_TRAFF_COUNT == on %}

			access_log off;

		{% endif %}

	}

{% endif %}

{% if $REDIRECT_TO_PHPFPM == on %}

	location @php {
		include {% $INCLUDE_DYNAMIC_RESOURCE_PATH %};
		fastcgi_index index.php;
		fastcgi_param PHP_ADMIN_VALUE "sendmail_path = /usr/sbin/sendmail -t -i -f {% $EMAIL %}";
		fastcgi_pass {% $PHPFPM_USER_SOCKET_PATH %};
		fastcgi_split_path_info ^((?U).+\.ph(?:p\d*|tml))(/?.+)$;
		try_files $uri =404;
		include fastcgi_params;
	}

{% endif %}


{% endif %}
