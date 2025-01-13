<?php

namespace AmminaISP\Ubuntu2204;

use AmminaISP\Core\AbstractInstaller;
use AmminaISP\Core\Console;
use AmminaISP\Core\Exceptions\ISPManagerFeatureException;
use AmminaISP\Core\ISPManager;

class Installer extends AbstractInstaller
{

	/**
	 * Установка features mysql
	 * @return void
	 * @throws ISPManagerFeatureException
	 */
	public function installFeatureMySql(): void
	{
		if ($this->isTaskComplete('feature_mysql')) {
			return;
		}
		$this->setTaskStart('feature_mysql');
		$package = 'package_mariadb';
		if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/local/installmysql')) {
			$content = trim(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/local/installmysql'));
			if ($content === 'mysql') {
				$package = 'package_mysql';
			}
		}
		ISPManager::getInstance()->commandFeatureMySql(null, $package);
		$this->setTaskComplete('feature_mysql');
	}
}