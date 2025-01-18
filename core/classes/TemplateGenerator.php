<?php

namespace AmminaISP\Core;

/**
 * Генерация шаблонов из каталогов AmminaISP для сервисов ОС
 */
class TemplateGenerator
{
	protected static ?TemplateGenerator $instance = null;
	protected array $params = [];
	public array $templateDirs = [];

	public static function getInstance(): static
	{
		if (is_null(static::$instance)) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	public function __construct()
	{
		$this->templateDirs = [
			joinPaths($_SERVER['DOCUMENT_ROOT'], 'core/files', 'templates.config'),
			joinPaths($_SERVER['OS_ROOT'], 'files', 'templates.config'),
			joinPaths($_SERVER['DOCUMENT_ROOT'], '.local/files', 'templates.config'),
		];
	}

	/**
	 * Генерируем контент по шаблону
	 *
	 * @param string $template
	 * @param array|null $params
	 * @return string|null
	 */
	public function run(string $template, ?array $params = null): ?string
	{
		if (!is_null($params)) {
			$this->params = $params;
		}
		return trim($this->includePart($template));
	}

	/**
	 * Ищем файл шаблона
	 * @param string $path
	 * @return string|null
	 */
	protected function findFile(string $path): ?string
	{
		$result = null;
		foreach ($this->templateDirs as $dir) {
			$fileName = joinPaths($dir, $path);
			if (file_exists($fileName)) {
				$result = $fileName;
			}
		}
		return $result;
	}

	/**
	 * Вставляем часть шаблона
	 * @param string $partPath
	 * @return string
	 */
	public function includePart(string $partPath): string
	{
		$result = '';
		$fileName = $this->findFile($partPath);
		if (!is_null($fileName)) {
			ob_start();
			include $fileName;
			$result = ob_get_clean();
		}
		return $result;
	}

	/**
	 * Вернуть параметр. Для вложенных данных - через |
	 * @param string $ident
	 * @param bool $includeBreakLine
	 * @return mixed
	 */
	public function param(string $ident, bool $includeBreakLine = false): mixed
	{
		$arIdent = explode('|', $ident);
		if ($arIdent[0] == '') {
			$arIdent[0] = '__';
		}

		$link =& $this->params;
		foreach ($arIdent as $ident) {
			if (array_key_exists($ident, $link)) {
				$link = &$link[$ident];
			} else {
				return null;
			}
		}
		return $link . ($includeBreakLine ? "\n" : '');
	}
}