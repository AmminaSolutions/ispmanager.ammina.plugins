<?php

namespace AmminaISP\Core;

class Settings
{

	protected static ?Settings $instance = null;
	protected array $settings = [];
	protected array $mergeReplace = [
		"main.make_charsets.list",
	];

	public static function getInstance(): static
	{
		if (is_null(static::$instance)) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	public function __construct()
	{
		$this->loadSettings();
	}

	/**
	 * Загружаем настройки из файлов .settings.php
	 *
	 * @return void
	 */
	public function loadSettings(): void
	{
		$this->settings = [];
		$from = [
			joinPaths($_SERVER['DOCUMENT_ROOT'], 'core/.settings.php'),
			joinPaths($_SERVER['OS_ROOT'], '.settings.php'),
			joinPaths($_SERVER['DOCUMENT_ROOT'], '.local/.settings.php'),
		];
		foreach ($from as $file) {
			if (!file_exists($file)) {
				continue;
			}
			$data = include($file);
			if (!is_array($data)) {
				continue;
			}
			$this->settings = array_replace_recursive($this->settings, $data);
			foreach ($this->mergeReplace as $rule) {
				$ruleList = explode('.', str_replace('\.', '~', $rule));
				$check = $data;
				$to =& $this->settings;
				foreach ($ruleList as $field) {
					$field = str_replace('~', '.', $field);
					if (!array_key_exists($field, $check)) {
						continue(2);
					}
					$to = &$to[$field];
					$check = $check[$field];
				}
				$to = $check;
			}
		}
	}

	/**
	 * Возвращает параметр настроек. Разделитель - точка. Например: main.make_charsets.make
	 *
	 * @param string $option
	 * @return mixed
	 */
	public function get(string $option): mixed
	{
		$chain = explode('.', str_replace('\.', '~', $option));
		$result =& $this->settings;
		foreach ($chain as $item) {
			$item = str_replace('~', '.', $item);
			if (!array_key_exists($item, $result)) {
				return null;
			}
			$result = &$result[$item];
		}
		return $result;
	}
}