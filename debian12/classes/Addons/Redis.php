<?php

namespace AmminaISP\Debian12\Addons;

use AmminaISP\Core\Addons\AbstractRedis;

class Redis extends AbstractRedis
{
	protected function checkSystemSettings(): void
	{
		$value = (int)trim(exec("cat /proc/sys/net/core/somaxconn"));
		if ($value !== 511) {
			file_put_contents("/etc/rc.local", file_get_contents("/etc/rc.local") . "\necho 511 > /proc/sys/net/core/somaxconn\n");
			exec("echo 511 > /proc/sys/net/core/somaxconn");
		}
		$value = exec("sysctl vm.overcommit_memory");
		if (!str_contains($value, '1')) {
			file_put_contents("/etc/sysctl.conf", file_get_contents("/etc/sysctl.conf") . "\nvm.overcommit_memory = 1\n");
			exec("sysctl vm.overcommit_memory=1");
		}
		$value = exec("cat /sys/kernel/mm/transparent_hugepage/enabled");
		if (!str_contains($value, '[never]')) {
			file_put_contents("/etc/rc.local", file_get_contents("/etc/rc.local") . "\necho never > /sys/kernel/mm/transparent_hugepage/enabled\n");
			exec("echo never > /sys/kernel/mm/transparent_hugepage/enabled");
		}

	}
}