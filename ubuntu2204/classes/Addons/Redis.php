<?php

namespace AmminaISP\Ubuntu2204\Addons;

use AmminaISP\Core\Addons\AbstractRedis;

class Redis extends AbstractRedis
{
	protected function checkSystemSettings(): void
	{
		$content = file_get_contents('/etc/rc.local');
		if (!str_contains($content, '/proc/sys/net/core/somaxconn')) {
			$content .= "\necho 511 > /proc/sys/net/core/somaxconn\n";
			file_put_contents("/etc/rc.local", $content);
		}
		$value = (int)trim(exec("cat /proc/sys/net/core/somaxconn"));
		if ($value !== 511) {
			exec("echo 511 > /proc/sys/net/core/somaxconn");
		}

		if (!str_contains($content, '/sys/kernel/mm/transparent_hugepage/enabled')) {
			$content .= "\necho never > /sys/kernel/mm/transparent_hugepage/enabled\n";
			file_put_contents("/etc/rc.local", $content);
		}
		$value = exec("cat /sys/kernel/mm/transparent_hugepage/enabled");
		if (!str_contains($value, '[never]')) {
			exec("echo never > /sys/kernel/mm/transparent_hugepage/enabled");
		}


		$content = file_get_contents('/etc/sysctl.conf');
		if (!str_contains($content, 'vm.overcommit_memory')) {
			$content .= "\nvm.overcommit_memory = 1\n";
			file_put_contents("/etc/sysctl.conf", $content);
		}
		$value = exec("sysctl vm.overcommit_memory");
		if (!str_contains($value, '1')) {
			exec("sysctl vm.overcommit_memory=1");
		}


	}
}