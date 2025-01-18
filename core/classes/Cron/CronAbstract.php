<?php

namespace AmminaISP\Core\Cron;

/**
 * Cron операции
 */
abstract class CronAbstract
{
	protected static ?CronAbstract $instance = null;

	protected string $pathApacheVhosts = '/etc/apache2/vhosts';
	protected string $pathNginxVhosts = '/etc/nginx/vhosts';
	protected string $apache2ConfigClass = '';

	public static function getInstance(): static
	{
		if (is_null(static::$instance)) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	public function run(): void
	{
		$this->runCycle();
		return;
		while (true) {
			$this->runCycle();
			if ((int)date('s') >= 50) {
				break;
			}
			sleep(5);
		}
	}

	protected function runCycle(): void
	{
		$this->checkWebConfigApache();
		//$this->checkWebConfigNginx();
	}

	/**
	 * @param string $path
	 * @param string $ext
	 * @return \SplFileInfo[]
	 */
	protected function findConfFiles(string $path, string $ext = 'conf'): array
	{
		$result = [];
		$directory = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO);
		$iterator = new \RecursiveIteratorIterator($directory);
		/**
		 * @var \SplFileInfo $file
		 */
		foreach ($iterator as $file) {
			if (!$file->isFile()) {
				continue;
			}
			if ($file->getExtension() !== $ext) {
				continue;
			}
			if (($file->getCTime() + 5) < time()) {
				$result[] = $file;
			}
		}
		return $result;
	}

	protected function checkWebConfigApache(): void
	{
		foreach ($this->findConfFiles($this->pathApacheVhosts, 'conf') as $fileInfo) {
			$checker = new $this->apache2ConfigClass($fileInfo->getRealPath());
			$checker->run();
		}
	}

	protected function checkWebConfigNginx(): void
	{
		foreach ($this->findConfFiles($this->pathNginxVhosts, 'conf') as $fileInfo) {

		}
	}
}