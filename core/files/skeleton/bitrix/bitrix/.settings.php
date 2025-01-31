<?php
return [
	'utf_mode' =>
		[
			'value' => true,
			'readonly' => true,
		],
	'cache_flags' =>
		[
			'value' =>
				[
					'config_options' => 3600,
					'site_domain' => 3600,
				],
			'readonly' => false,
		],
	'cookies' =>
		[
			'value' =>
				[
					'secure' => false,
					'http_only' => true,
				],
			'readonly' => false,
		],
	'exception_handling' =>
		[
			'value' =>
				[
					'debug' => false,
					'handled_errors_types' => 4437,
					'exception_errors_types' => 4437,
					'ignore_silence' => false,
					'assertion_throws_exception' => true,
					'assertion_error_type' => 256,
					'log' => [
						'settings' =>
							[
								'file' => '/var/log/phpexceptions.log',
								'log_size' => 1000000,
							],
					],
				],
			'readonly' => false,
		],
	'crypto' =>
		[
			'value' =>
				[
					'crypto_key' => $this->randString(32),
				],
			'readonly' => true,
		],
	'connections' =>
		[
			'value' =>
				[
					'default' =>
						[
							'className' => '\\Bitrix\\Main\\DB\\MysqliConnection',
							'host' => 'localhost',
							'database' => '',
							'login' => '',
							'password' => '',
							'options' => 2,
						],
				],
			'readonly' => true,
		],
];