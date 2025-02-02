<?php

namespace AmminaISP\Core;

/**
 * Синхронизация файлов из каталогов AmminaISP в каталоги ISPManager
 */
class FilesSynchronizer
{
	protected static ?FilesSynchronizer $instance = null;
	protected array $rules = [];
	protected array $afterCommands = [];

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
		$this->addRule('ispmanager', '/usr/local/mgr5');
		$this->addRule('server/etc', '/etc');
		$this->addRule('server/usr', '/usr');
		$this->addRule('server/var', '/var');
		return $this;
	}

	/**
	 * Создать правила синхронизации только файлов ISPManager
	 * @return $this
	 */
	public function setOnlyIspManagerRules(): static
	{
		$this->clearRules();
		$this->addRule('ispmanager', '/usr/local/mgr5');
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
	 * Выполнение синхронизации файлов
	 *
	 * @param bool $showMessages
	 * @return FilesSynchronizer
	 */
	public function run(bool $showMessages = false): static
	{
		$this->afterCommands = [];
		foreach ($this->rules as $rule) {
			$this->runRule($rule, $showMessages);
		}
		$this->runAfterCommands($showMessages);
		if ($showMessages) {
			Console::success("Синхронизация выполнена");
		}
		return $this;
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
			$content = trim(file_get_contents($file['from']));
			if (!file_exists($file['to']) || trim(file_get_contents($file['to'])) !== $content) {
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
					foreach (explode("\n", file_get_contents($file->getPathname())) as $lineCommand) {
						$lineCommand = trim($lineCommand);
						if ($lineCommand == '') {
							continue;
						}

						$commands[dirname($relPath)][] = $lineCommand;
					};
				} else {
					$files[$relPath] = [
						'rel' => $relPath,
						'from' => $file->getPathname(),
						'to' => joinPaths($rule['to'], $relPath),
					];
				}
			}
		}
		return [$files, $commands];
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
			}
			@exec($command);
		}
	}
}