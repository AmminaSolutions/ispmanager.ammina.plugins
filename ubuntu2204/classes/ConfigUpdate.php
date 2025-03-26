<?php

namespace AmminaISP\Ubuntu2204;

class ConfigUpdate extends Installer
{
	public function __construct()
	{
		$this->resetInstallerTaskMixed();
		parent::__construct();
	}

	protected function resetInstallerTaskMixed(): void
	{
		$this->loadTaskList();
		if (empty($this->taskList)) {
			return;
		}
		$this->taskList['php_extensions'] = false;
		$this->taskList['php_settings'] = false;
		$this->saveTaskList();
	}
}