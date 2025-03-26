<?php

namespace AmminaISP\Ubuntu2404\Cron;

use AmminaISP\Core\Cron\CronAbstract;

class Cron extends CronAbstract
{
	public function __construct()
	{
		$this->apache2ConfigClass = Apache2Config::class;
		$this->nginxConfigClass = NginxConfig::class;
		$this->redisServiceName = 'redis-server';
		$this->redisServicePid = '/var/run/redis/redis-server.pid';
		$this->memcachedServiceName = 'memcached';
		$this->memcachedServicePid = '/var/run/memcached/memcached.pid';
	}
}