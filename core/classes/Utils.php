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
		$time = explode(' ', microtime());
		$time = $time[1] . '.' . substr($time[0], 2);
		file_put_contents($strPath . $time . "." . rand(10, 10000000) . ".command", serialize($arData));
	}

	public static function getAllJob(): array
	{
		$result = [];
		$basePath = $_SERVER['DOCUMENT_ROOT'] . "/.local/cronjob/";
		$directory = new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO);
		$iterator = new \RecursiveIteratorIterator($directory);
		/**
		 * @var \SplFileInfo $file
		 */
		foreach ($iterator as $file) {
			if (!$file->isFile()) {
				continue;
			}
			if ($file->getExtension() !== 'command') {
				continue;
			}
			if (($file->getCTime() + 15) < time()) {
				$result[$file->getFilename()] = $file->getPathname();
			}
		}
		ksort($result, SORT_NATURAL);
		return $result;
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

	public static function randString($length = 10, $extendsChars = ''): string
	{
		$allChars = "abcdefghijklnmopqrstuvwxyzABCDEFGHIJKLNMOPQRSTUVWXYZ0123456789" . $extendsChars;
		$result = "";
		for ($i = 0; $i < $length; $i++) {
			$result .= $allChars[rand(0, strlen($allChars) - 1)];
		}
		return $result;
	}

	public static function translitString(string $value): string
	{
		$translitFrom = "а,б,в,г,д,е,ё,ж,з,и,й,к,л,м,н,о,п,р,с,т,у,ф,х,ц,ч,ш,щ,ъ,ы,ь,э,ю,я,А,Б,В,Г,Д,Е,Ё,Ж,З,И,Й,К,Л,М,Н,О,П,Р,С,Т,У,Ф,Х,Ц,Ч,Ш,Щ,Ъ,Ы,Ь,Э,Ю,Я,і,І,ї,Ї,ґ,Ґ";
		$translitTo = "a,b,v,g,d,e,ye,zh,z,i,y,k,l,m,n,o,p,r,s,t,u,f,kh,ts,ch,sh,shch,,y,,e,yu,ya,A,B,V,G,D,E,YE,ZH,Z,I,Y,K,L,M,N,O,P,R,S,T,U,F,KH,TS,CH,SH,SHCH,,Y,,E,YU,YA,i,I,i,I,g,G";
		$from = explode(",", $translitFrom);
		$to = explode(",", $translitTo);
		$replace = [];
		foreach ($from as $k => $v) {
			$replace[$v] = $to[$k];
		}
		$result = '';
		for ($i = 0, $iMax = strlen($value); $i < $iMax; $i++) {
			$char = substr($value, $i, 1);
			if (isset($replace[$char])) {
				$result .= $replace[$char];
			} else {
				if (preg_match("/[a-zA-Z0-9]/u", $char)) {
					$result .= $char;
				}
			}
		}
		return $result;
	}

	public static function getUserHomeDir(string $user): ?string
	{
		$result = execShellCommand("getent passwd {$user} | cut -d: -f6");
		return (empty($result) ? null : $result);
	}

	public static function execShellCommand(string|array $command, &$output = null, &$result_code = null, ?string $scope = null): false|string
	{
		if (is_null($scope)) {
			if (defined('SHELL_SCOPE')) {
				$scope = SHELL_SCOPE;
			} else {
				$scope = 'panel';
			}
		}
		if (!is_array($command)) {
			$command = [$command];
		}
		$path = '';
		if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/.local/shell_path')) {
			$path = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/.local/shell_path');
		}
		if (!str_contains('/usr/local/sbin', $path) || !str_contains('/usr/local/bin', $path)) {
			$path = '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';
		}

		$script = [
			'#!/bin/sh',
			'',
			'export PATH="' . $path . '"',
			'',
			...$command,
			'',
			'exit $?',
			'',
		];
		$fileName = $_SERVER['DOCUMENT_ROOT'] . '/.local/.tmp.' . $scope . '.sh';
		file_put_contents($fileName, implode("\n", $script));
		$result = exec('sh ' . $fileName, $output, $result_code);
		@unlink($fileName);
		return $result;
	}
}
