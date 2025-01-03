<?php

namespace AmminaISP\Core;

class Settings
{

	protected static ?Settings $instance = null;

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