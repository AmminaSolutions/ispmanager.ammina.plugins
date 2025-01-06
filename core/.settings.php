<?php
return [
	'main' => [
		'make_charsets' => [
			'make' => true,
			'list' => [
				'WINDOWS-1251',
				'UTF-8',
			],
		],
	],
	'ispmanager' => [
		'path nginx-gzip-types' => 'text/plain text/css application/json application/x-javascript text/xml application/xml application/xml+rss text/javascript application/javascript image/svg+xml',
		'DomainTTL' => '300',
	],
	'features' => [
		'web' => [
			'apache' => true,
			'nginx' => true,
			'logrotate' => true,
			'awstats' => false,
			'php' => false,
			'php_fpm' => true,
			'pagespeed' => false,
			'phpcomposer' => false,
		],
		'mail' => [
			'mta' => true,
			'dovecot' => true,
			'greylisting' => true,
			'opendkim' => true,
			'spamassassin' => true,
			'clamav' => true,
			'sieve' => true,
			'roundcube' => true,
		],
		'dns' => [
			'dns' => true,
		],
		'ftp' => [
			'ftp' => true,
		],
		'mysql' => [
			'mysql' => true,
		],
		'phpmyadmin' => [
			'phpmyadmin' => true,
		],
		'postgresql' => [
			'postgresql' => false,
		],
		'phppgadmin' => [
			'phppgadmin' => false,
		],
		'quota' => [
			'quota' => true,
		],
		'fail2ban' => [
			'fail2ban' => true,
		],
		'ansible' => [
			'ansible' => false,
		],
		'docker' => [
			'docker' => false,
		],
		'nodejs' => [
			'nodejs' => false,
		],
		'python' => [
			'altpythongr' => false,
			'isppython38' => false,
			'isppython39' => false,
			'isppython310' => false,
			'isppython311' => false,
			'isppython312' => false,
		],
		'wireguard' => [
			'wireguard' => false,
		],
		'redis' => [
			'redis' => true,
			'options' => [
				'memorylimit' => 128,
				'databases' => 16,
				'issocket' => true,
			],
		],
		'memcached' => [
			'memcached' => true,
			'options' => [
				'cachesize' => 64,
				'maxconn' => 1024,
				'issocket' => true,
			],
		],
		'php' => [
			'52' => [
				'install' => false,
				'mod_apache' => false,
				'fpm' => false,
			],
			'53' => [
				'install' => false,
				'mod_apache' => false,
				'fpm' => false,
			],
			'54' => [
				'install' => false,
				'mod_apache' => false,
				'fpm' => false,
			],
			'55' => [
				'install' => false,
				'mod_apache' => false,
				'fpm' => false,
			],
			'56' => [
				'install' => false,
				'mod_apache' => false,
				'fpm' => false,
			],
			'70' => [
				'install' => false,
				'mod_apache' => false,
				'fpm' => false,
			],
			'71' => [
				'install' => false,
				'mod_apache' => false,
				'fpm' => false,
			],
			'72' => [
				'install' => false,
				'mod_apache' => false,
				'fpm' => false,
			],
			'73' => [
				'install' => false,
				'mod_apache' => false,
				'fpm' => false,
			],
			'74' => [
				'install' => true,
				'mod_apache' => false,
				'fpm' => true,
			],
			'80' => [
				'install' => false,
				'mod_apache' => false,
				'fpm' => false,
			],
			'81' => [
				'install' => true,
				'mod_apache' => false,
				'fpm' => true,
			],
			'82' => [
				'install' => true,
				'mod_apache' => false,
				'fpm' => true,
			],
			'83' => [
				'install' => true,
				'mod_apache' => false,
				'fpm' => true,
			],
		],
	],
	'modules' => [
		'bitninja' => false,
		'ddosguard' => false,
		'siteprobuilder' => false,
		'cloudflare' => false,
		'softaculous' => false,
		'kernelcare' => false,
		'revisium' => false,
	],
	'php_extensions_install' => [
		'default_options' => [
			'lzf' => [
				'better-compression' => false,
			],
			'redis' => [
				'igbinary' => true,
				'lzf' => true,
				'zstd' => true,
				'msgpack' => true,
				'lz4' => true,
			],
			'swoole' => [
				'sockets' => true,
				'openssl' => true,
				'http2' => true,
				'mysqlnd' => true,
				'json' => true,
				'curl' => true,
				'cares' => true,
				'pgsql' => false,
				'brotli' => true,
				'zstd' => true,
				'odbc' => false,
				'oracle' => false,
				'sqlite' => true,
				'thread' => false,
				'iouring' => false,
			],
			'openswoole' => [
				'sockets' => true,
				'openssl' => true,
				'http2' => true,
				'mysqlnd' => true,
				'hook-curl' => true,
				'cares' => true,
				'postgres' => false,
			],
		],
		'default' => [
			'zstd' => true,
			'lzf' => true,
			'igbinary' => true,
			'msgpack' => true,
			'brotli' => true,
			'redis' => true,
			'swoole' => true,
			'openswoole' => true,
		],
		'php' => [
			'52' => [],
			'53' => [],
			'54' => [],
			'55' => [],
			'56' => [],
			'70' => [],
			'71' => [],
			'72' => [],
			'73' => [],
			'74' => [],
			'80' => [],
			'81' => [],
			'82' => [],
			'83' => [],
		],
	],
	'php_extensions' => [
		'all' => [
			'bcmath' => true,
			'bz2' => true,
			'calendar' => true,
			'cgi-fcgi' => true,
			'ctype' => true,
			'curl' => true,
			'date' => true,
			'dba' => false,
			'dom' => true,
			'exif' => true,
			'filter' => true,
			'ftp' => true,
			'gd' => true,
			'gettext' => true,
			'gmp' => true,
			'hash' => true,
			'htscanner' => false,
			'iconv' => true,
			'imagick' => true,
			'imap' => false,
			'intl' => true,
			'ioncube' => false,
			'json' => true,
			'ldap' => false,
			'libxml' => true,
			'mbstring' => true,
			'mcrypt' => true,
			'memcache' => true,
			'memcached' => false,
			'mhash' => true,
			'mysql' => true,
			'mysqli' => true,
			'odbc' => false,
			'opcache' => true,
			'openssl' => true,
			'pcntl' => true,
			'pcre' => true,
			'pdo' => true,
			'pdo_dblib' => false,
			'pdo_mysql' => true,
			'pdo_odbc' => false,
			'pdo_pgsql' => false,
			'pdo_sqlite' => false,
			'pgsql' => false,
			'posix' => false,
			'pspell' => false,
			'readline' => true,
			'recode' => false,
			'reflection' => true,
			'session' => true,
			'shmop' => true,
			'simplexml' => true,
			'snmp' => false,
			'soap' => true,
			'sockets' => true,
			'spl' => true,
			'ssh2' => true,
			'standard' => true,
			'sybase_ct' => false,
			'sysvmsg' => false,
			'sysvsem' => false,
			'sysvshm' => false,
			'tidy' => false,
			'timezonedb' => false,
			'tokenizer' => true,
			'wddx' => false,
			'xml' => true,
			'xmlreader' => true,
			'xmlrpc' => true,
			'xmlwriter' => true,
			'xsl' => false,
			'zendoptimizer' => false,
			'zip' => true,
			'zlib' => true,
			'core' => true,
			'ereg' => true,
			'fileinfo' => true,
			'mysqlnd' => true,
			'phar' => true,
			'sqlite3' => true,
			'zendguardloader' => false,
			'igbinary' => true,
			'lzf' => true,
			'zstd' => true,
			'msgpack' => true,
			'redis' => true,
			'swoole' => true,
		],
		'isp-php52' => [],
		'isp-php53' => [],
		'isp-php54' => [],
		'isp-php55' => [],
		'isp-php56' => [],
		'isp-php70' => [],
		'isp-php71' => [],
		'isp-php72' => [],
		'isp-php73' => [],
		'isp-php74' => [],
		'isp-php80' => [],
		'isp-php81' => [],
		'isp-php82' => [],
		'isp-php83' => [],
	],
	'php-path' => [
		'isp-php52' => '/opt/php52',
		'isp-php53' => '/opt/php53',
		'isp-php54' => '/opt/php54',
		'isp-php55' => '/opt/php55',
		'isp-php56' => '/opt/php56',
		'isp-php70' => '/opt/php70',
		'isp-php71' => '/opt/php71',
		'isp-php72' => '/opt/php72',
		'isp-php73' => '/opt/php73',
		'isp-php74' => '/opt/php74',
		'isp-php80' => '/opt/php80',
		'isp-php81' => '/opt/php81',
		'isp-php82' => '/opt/php82',
		'isp-php83' => '/opt/php83',
	],
	'php_settings_show_user' => [
		'display_errors',
		'memory_limit',
		'mbstring.func_overload',
		'mbstring.internal_encoding',
	],
	'php_settings' => [
		'all' => [
			'display_errors' => 'On',
			'enable_dl' => 'On',
			'short_open_tag' => 'On',
			'allow_url_fopen' => 'On',
			'pcre.backtrack_limit' => '1000000',
			'pcre.recursion_limit' => '100000',
			'session.entropy_length' => '128',
			'session.entropy_file' => '/dev/urandom',
			'session.cookie_httponly' => 'On',
			'max_execution_time' => '300',
			'max_file_uploads' => '1000',
			'max_input_time' => '10000',
			'max_input_vars' => '10000',
			'memory_limit' => '512M',
			'post_max_size' => '1024M',
			'realpath_cache_size' => '8192K',
			'realpath_cache_ttl' => '300',
			'upload_max_filesize' => '1024M',
			'opcache.enable' => 'On',
			'opcache.enable_cli' => 'On',
			'opcache.blacklist_filename' => '#PHP_DIR#/etc/opcache*.blacklist',
			'opcache.force_restart_timeout' => '60',
			'opcache.interned_strings_buffer' => '512',
			'opcache.max_accelerated_files' => '1000000',
			'opcache.max_wasted_percentage' => '1',
			'opcache.memory_consumption' => '2048',
			'opcache.huge_code_pages' => '0',
			'opcache.revalidate_freq' => '0',
			'opcache.validate_timestamps' => '1',
			'opcache.fast_shutdown' => '1',
			'opcache.save_comments' => '1',
			'opcache.load_comments' => '1',
			'mbstring.internal_encoding' => 'UTF-8',
			'mail.add_x_header' => 'Off',
			'disable_functions' => '',
		],
		'isp-php52' => [
			'mbstring.func_overload' => '2',
		],
		'isp-php53' => [
			'mbstring.func_overload' => '2',
		],
		'isp-php54' => [
			'mbstring.func_overload' => '2',
		],
		'isp-php55' => [
			'mbstring.func_overload' => '2',
		],
		'isp-php56' => [
			'mbstring.func_overload' => '2',
		],
		'isp-php70' => [
			'mbstring.func_overload' => '2',
		],
		'isp-php71' => [
			'mbstring.func_overload' => '2',
		],
		'isp-php72' => [
			'mbstring.func_overload' => '2',
		],
		'isp-php73' => [
			'mbstring.func_overload' => '2',
		],
		'isp-php74' => [
			'mbstring.func_overload' => '2',
		],
		'isp-php80' => [
			'opcache.jit_buffer_size' => '512M',
		],
		'isp-php81' => [
			'opcache.jit_buffer_size' => '512M',
		],
		'isp-php82' => [
			'opcache.jit_buffer_size' => '512M',
		],
		'isp-php83' => [
			'opcache.jit_buffer_size' => '512M',
		],
	],
	'memory_params_coefficient' => [
		'mysql|query-cache-size' => 'M',
		'mysql|query-cache-limit' => 'M',
		'mysql|innodb-buffer-pool-size' => 'M',
		'mysql|max-heap-table-size' => 'M',
		'mysql|tmp-table-size,tmp-memory-table-size' => 'M',
		'mysql|key-buffer-size' => 'M',
		'mysql|join-buffer-size' => 'M',
		'mysql|sort-buffer-size' => 'M',
		'mysql|bulk-insert-buffer-size' => 'M',
		'mysql|myisam-sort-buffer-size' => 'M',
	],
	'memory_params' => [
		'PHP_threads' => [
			1 => 4,
			2 => 6,
			3 => 8,
			4 => 10,
			5 => 14,
			6 => 18,
			7 => 22,
			8 => 24,
			9 => 30,
			10 => 60,
			11 => 80,
			12 => 100,
			13 => 180,
			14 => 220,
			15 => 290,
		],
		'mysql|query-cache-size' => [
			1 => 16,
			2 => 48,
			3 => 96,
			4 => 128,
			5 => 128,
			6 => 128,
			7 => 128,
			8 => 128,
			9 => 128,
			10 => 128,
			11 => 128,
			12 => 128,
			13 => 128,
			14 => 128,
			15 => 128,
		],
		'mysql|query-cache-limit' => [
			1 => 2,
			2 => 4,
			3 => 4,
			4 => 8,
			5 => 16,
			6 => 16,
			7 => 16,
			8 => 16,
			9 => 16,
			10 => 16,
			11 => 16,
			12 => 16,
			13 => 16,
			14 => 16,
			15 => 16,
		],
		'mysql|table-open-cache,table-cache' => [
			1 => 2048,
			2 => 4096,
			3 => 8192,
			4 => 8192,
			5 => 8192,
			6 => 8192,
			7 => 8192,
			8 => 10240,
			9 => 12288,
			10 => 14336,
			11 => 14336,
			12 => 14336,
			13 => 18432,
			14 => 18432,
			15 => 18432,
		],
		'mysql|thread-cache-size' => [
			1 => 32,
			2 => 32,
			3 => 64,
			4 => 96,
			5 => 96,
			6 => 96,
			7 => 128,
			8 => 128,
			9 => 128,
			10 => 128,
			11 => 128,
			12 => 128,
			13 => 512,
			14 => 512,
			15 => 1024,
		],
		'mysql|max-heap-table-size' => [
			1 => 16,
			2 => 48,
			3 => 96,
			4 => 96,
			5 => 128,
			6 => 128,
			7 => 128,
			8 => 128,
			9 => 128,
			10 => 128,
			11 => 128,
			12 => 128,
			13 => 128,
			14 => 128,
			15 => 128,
		],
		'mysql|tmp-table-size,tmp-memory-table-size' => [
			1 => 16,
			2 => 48,
			3 => 96,
			4 => 96,
			5 => 128,
			6 => 128,
			7 => 128,
			8 => 128,
			9 => 128,
			10 => 128,
			11 => 128,
			12 => 128,
			13 => 128,
			14 => 128,
			15 => 128,
		],
		'mysql|key-buffer-size' => [
			1 => 8,
			2 => 24,
			3 => 24,
			4 => 24,
			5 => 32,
			6 => 48,
			7 => 64,
			8 => 64,
			9 => 96,
			10 => 96,
			11 => 196,
			12 => 196,
			13 => 256,
			14 => 256,
			15 => 512,
		],
		'mysql|join-buffer-size' => [
			1 => 1,
			2 => 4,
			3 => 4,
			4 => 4,
			5 => 8,
			6 => 8,
			7 => 12,
			8 => 14,
			9 => 14,
			10 => 18,
			11 => 24,
			12 => 24,
			13 => 32,
			14 => 32,
			15 => 64,
		],
		'mysql|sort-buffer-size' => [
			1 => 1,
			2 => 4,
			3 => 4,
			4 => 4,
			5 => 8,
			6 => 8,
			7 => 12,
			8 => 14,
			9 => 14,
			10 => 18,
			11 => 24,
			12 => 24,
			13 => 32,
			14 => 32,
			15 => 64,
		],
		'mysql|bulk-insert-buffer-size' => [
			1 => 1,
			2 => 1,
			3 => 2,
			4 => 2,
			5 => 2,
			6 => 2,
			7 => 2,
			8 => 2,
			9 => 2,
			10 => 2,
			11 => 2,
			12 => 2,
			13 => 2,
			14 => 2,
			15 => 2,
		],
		'mysql|myisam-sort-buffer-size' => [
			1 => 1,
			2 => 4,
			3 => 4,
			4 => 4,
			5 => 8,
			6 => 8,
			7 => 12,
			8 => 14,
			9 => 14,
			10 => 18,
			11 => 24,
			12 => 24,
			13 => 32,
			14 => 32,
			15 => 32,
		],
		'mysql|innodb-buffer-pool-size' => [
			1 => 64,
			2 => 128,
			3 => 256,
			4 => 384,
			5 => 768,
			6 => 1024,
			7 => 1536,
			8 => 2048,
			9 => 3072,
			10 => 6144,
			11 => 10240,
			12 => 14336,
			13 => 18432,
			14 => 24576,
			15 => 32768,
		],
	],
	'memory_params_independent' => [],
	'mysql_settings' => [
		'back-log' => '70',
		'character-set-server' => 'utf8',
		'collation-server' => 'utf8_unicode_ci',
		'expire-logs-days' => '10',
		'explicit-defaults-for-timestamp' => 'TRUE',
		'host-cache-size' => '228',
		'init-connect' => 'SET NAMES utf8 COLLATE utf8_unicode_ci',
		'innodb-flush-log-at-trx-commit' => '2',
		'innodb-flush-method' => 'O_DIRECT',
		'innodb-log-file-size' => '67108864',
		'innodb-strict-mode' => 'FALSE',
		'local-infile' => 'FALSE',
		'max-allowed-packet' => '134217728',
		'max-binlog-size' => '104857600',
		'max-long-data-size' => '134217728',
		'max-relay-log-size' => '104857600',
		'myisam-recover-options' => 'BACKUP',
		'open-files-limit' => '20619',
		'query-cache-type' => 'ON',
		'skip-name-resolve' => 'TRUE',
		'sql-mode' => '',
		'thread-stack' => '131072',
		'transaction-isolation' => 'READ-COMMITTED',
		'innodb-file-per-table' => 'TRUE',
		'default-storage-engine' => 'InnoDB',
		'sync-binlog' => '0',
	],
];