<?php

namespace AmminaISP\Core;

class Utils
{
	public static function checkDirPath($path, $permission = 0700): bool
	{
		$path = str_replace("//", "/", $path);
		if (!str_ends_with($path, "/")) {
			$path = substr($path, 0, strrpos($path, "/"));
		}
		$path = rtrim($path, "/");
		if ($path === "") {
			return true;
		}
		if (!file_exists($path)) {
			return mkdir($path, $permission, true);
		}
		return is_dir($path);
	}

	public static function addJob($strCommand, $arOptions): void
	{
		$strPath = $_SERVER['DOCUMENT_ROOT'] . "/.local/cronjob/";
		self::checkDirPath($strPath);
		$arData = [
			"COMMAND" => $strCommand,
			"OPTIONS" => $arOptions,
		];
		file_put_contents($strPath . microtime(true) . "." . rand(10, 10000000) . ".command", serialize($arData));
	}

	public static function boolToFlag(bool $value): string
	{
		return $value ? "on" : "off";
	}

	public static function isOn(string $value): bool
	{
		return strtolower($value) === "on";
	}

	public static function checkOnOff(string $value): string
	{
		$value = strtolower($value);
		if (!in_array($value, ["on", "off"])) {
			return "off";
		}
		return $value;
	}

	public static function joinPaths(string $basePath, string ...$paths): string
	{
		foreach ($paths as $index => $path) {
			if (empty($path) && $path !== '0') {
				unset($paths[$index]);
			} else {
				$paths[$index] = DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
			}
		}

		return $basePath . implode('', $paths);
	}

	public static function removePathRoot(string $path, ?string $root = null): string
	{
		if (is_null($root)) {
			$root = $_SERVER['DOCUMENT_ROOT'];
		}
		$path = rtrim($path, DIRECTORY_SEPARATOR);
		if (str_starts_with($path, $root)) {
			$path = substr($path, strlen($root));
		}
		return ltrim($path, DIRECTORY_SEPARATOR);
	}

	public static function idn_to_ascii(string $value): string
	{
		if (function_exists("idn_to_ascii")) {
			$value = idn_to_ascii($value);
		}
		return $value;
	}

	/**
	 * Поиск файла в одном из каталогов файлов
	 * @param string $path
	 * @return string|null
	 */
	public static function findFile(string $path): ?string
	{
		$from = [
			joinPaths($_SERVER['DOCUMENT_ROOT'], 'core/files', $path),
			joinPaths($_SERVER['OS_ROOT'], 'files', $path),
			joinPaths($_SERVER['DOCUMENT_ROOT'], '.local/files', $path),
		];
		$result = null;
		foreach ($from as $checkPath) {
			if (file_exists($checkPath)) {
				$result = $checkPath;
			}
		}
		return $result;
	}
}
