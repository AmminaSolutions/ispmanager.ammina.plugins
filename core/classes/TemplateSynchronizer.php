<?php

namespace AmminaISP\Core;

/**
 * Генерация и синхронизация шаблонов из каталогов AmminaISP в каталоги ISPManager
 */
class TemplateSynchronizer
{
	protected static ?TemplateSynchronizer $instance = null;
	protected array $rules = [];
	protected array $templateParts = [];
	protected array $afterCommands = [];
	protected array $fileContents = [];
	protected array $partContents = [];

	public static function getInstance(): static
	{
		if (is_null(static::$instance)) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	public function __construct()
	{
		$this->setDefaultRules();
		$this->setDefaultTemplateParts();
	}

	/**
	 * Очищаем все правила синхронизации
	 *
	 * @return $this
	 */
	public function clearRules(): static
	{
		$this->rules = [];
		return $this;
	}

	/**
	 * Создать правила по умолчанию
	 * @return $this
	 */
	public function setDefaultRules(): static
	{
		$this->clearRules();
		$this->addRule('templates.ispmanager', '/usr/local/mgr5/etc/templates');
		return $this;
	}

	/**
	 * Добавить правило
	 *
	 * @param string $from
	 * @param string $to
	 * @return $this
	 */
	public function addRule(string $from, string $to): static
	{
		$from = [
			joinPaths($_SERVER['DOCUMENT_ROOT'], 'core/files', $from),
			joinPaths($_SERVER['OS_ROOT'], 'files', $from),
			joinPaths($_SERVER['DOCUMENT_ROOT'], '.local/files', $from),
		];
		$this->rules[] = [
			'from' => $from,
			'to' => $to,
		];
		return $this;
	}

	/**
	 * Установить пути к частям шаблонов по умолчанию
	 * @return $this
	 */
	public function setDefaultTemplateParts(): static
	{
		$this->clearTemplateParts();
		$this->addTemplatePart('templates.part');
		return $this;
	}

	/**
	 * Очистить пути к частям шаблонов
	 * @return $this
	 */
	public function clearTemplateParts(): static
	{
		$this->templateParts = [];
		return $this;
	}

	/**
	 * Добавить путь к частям шаблонов
	 * @param string $path
	 * @return $this
	 */
	public function addTemplatePart(string $path): static
	{
		$paths = [
			joinPaths($_SERVER['DOCUMENT_ROOT'], 'core/files', $path),
			joinPaths($_SERVER['OS_ROOT'], 'files', $path),
			joinPaths($_SERVER['DOCUMENT_ROOT'], '.local/files', $path),
		];
		$this->templateParts[] = [
			'path' => $paths,
		];
		return $this;
	}

	/**
	 * Выполнение генерации и синхронизации шаблонов
	 *
	 * @param bool $showMessages
	 * @return TemplateSynchronizer
	 */
	public function run(bool $showMessages = false): static
	{
		$this->afterCommands = [];
		foreach ($this->rules as $rule) {
			$this->runRule($rule, $showMessages);
		}
		$this->runAfterCommands($showMessages);
		if ($showMessages) {
			Console::success("Генерация и синхронизация шаблонов выполнена");
		}
		return $this;
	}

	/**
	 * Ищем команды, которые должны быть выполнены при изменении указанного файла
	 *
	 * @param string $file
	 * @param array $commands
	 * @return void
	 */
	protected function findAfterCommands(string $file, array $commands): void
	{
		foreach ($commands as $path => $pathCommands) {
			if (str_starts_with($file, $path)) {
				foreach ($pathCommands as $command) {
					$this->afterCommands[$command] = $command;
				}
			}
		}
	}

	/**
	 * Выполняем команды
	 * @param bool $showMessages
	 * @return void
	 */
	protected function runAfterCommands(bool $showMessages = false): void
	{
		$this->afterCommands = array_values($this->afterCommands);
		foreach ($this->afterCommands as $command) {
			if ($showMessages) {
				Console::notice("Выполняем команду: {$command}");
				system($command);
			}
		}
	}

	/**
	 * Синхронизируем файлы по 1 правилу
	 *
	 * @param array $rule
	 * @param bool $showMessages
	 * @return void
	 */
	protected function runRule(array $rule, bool $showMessages = false): void
	{
		[$files, $commands] = $this->findFilesFromRule($rule);
		foreach ($files as $file) {
			$content = $this->makeFileContent($file['from']);
			if (!file_exists($file['to']) || file_get_contents($file['to']) !== $content) {
				checkDirPath($file['to']);
				file_put_contents($file['to'], $content);
				$this->findAfterCommands($file['rel'], $commands);
				if ($showMessages) {
					Console::notice("Файл изменен: {$file['from']} -> {$file['to']}");
				}
			}
		}
	}

	/**
	 * Поиск файлов и команд для копирования в соответствии с правилом
	 * @param array $rule
	 * @return array[]
	 */
	protected function findFilesFromRule(array $rule): array
	{
		$files = [];
		$commands = [];
		foreach ($rule['from'] as $from) {
			if (!file_exists($from)) {
				continue;
			}
			$directory = new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO);
			$iterator = new \RecursiveIteratorIterator($directory);
			/**
			 * @var \SplFileInfo $file
			 */
			foreach ($iterator as $file) {
				if (!$file->isFile()) {
					continue;
				}
				$relPath = removePathRoot($file->getPathname(), $from);
				if (!str_starts_with($relPath, '/')) {
					$relPath = '/' . $relPath;
				}
				if ($file->getFilename() === '.service.command') {
					$commands[dirname($relPath)] = [];
					foreach (explode("\n", file_get_contents($file->getPathname())) as $lineCommand) {
						$lineCommand = trim($lineCommand);
						if ($lineCommand == '') {
							continue;
						}

						$commands[dirname($relPath)][] = $lineCommand;
					};
				} elseif ($file->getExtension() === 'php') {
					$resultRelPath = substr($relPath, 0, -4);
					$files[$relPath] = [
						'rel' => $relPath,
						'from' => $file->getPathname(),
						'to' => joinPaths($rule['to'], $resultRelPath),
					];
				}
			}
		}
		return [$files, $commands];
	}

	protected function makeFileContent(string $filePath): string
	{
		if (!array_key_exists($filePath, $this->fileContents)) {
			ob_start();
			include $filePath;
			$this->fileContents[$filePath] = ob_get_clean();
		}
		return $this->fileContents[$filePath];
	}

	public function includePart(string $partPath): void
	{
		if (!array_key_exists($partPath, $this->partContents)) {
			$fileName = null;
			foreach ($this->templateParts as $rule) {
				foreach ($rule['path'] as $part) {
					$fullName = joinPaths($part, $partPath);
					if (file_exists($fullName)) {
						$fileName = $fullName;
					}
				}
			}
			if (!is_null($fileName)) {
				ob_start();
				include $fileName;
				$this->partContents[$partPath] = ob_get_clean();
			}
		}
		echo $this->partContents[$partPath];
	}
}