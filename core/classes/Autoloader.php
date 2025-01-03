<?php

namespace AmminaISP\Core;

class Autoloader
{

	protected static array $namespaces = [];

	public static function autoload($class): void
	{
		if (str_starts_with($class, '\\')) {
			$class = substr($class, 1);
		}
		foreach (static::$namespaces as $namespace => $path) {
			if (str_starts_with($class, $namespace)) {
				$subclass = substr($class, strlen($namespace));
				$fullPath = $path . str_replace('\\', '/', $subclass) . '.php';
				if (file_exists($fullPath)) {
					include_once($fullPath);
				}
			}
		}
	}

	public static function registerNamespace(string $namespace, string $directory): void
	{
		if (!str_ends_with($namespace, '\\')) {
			$namespace .= '\\';
		}
		if (!str_ends_with($directory, '/')) {
			$directory .= '/';
		}
		self::$namespaces[$namespace] = $directory;
	}
}

spl_autoload_register(Autoloader::class . '::autoload');
