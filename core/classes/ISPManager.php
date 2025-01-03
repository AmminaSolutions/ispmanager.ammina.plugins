<?php

namespace AmminaISP\Core;

class ISPManager
{
	protected static ?ISPManager $instance = null;

	public static function getInstance(): static
	{
		if (is_null(static::$instance)) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	public function __construct()
	{
	}
}