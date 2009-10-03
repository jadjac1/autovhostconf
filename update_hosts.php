<?php
require_once 'commons.php';
require_once 'config.php';

function update_hosts($data) 
{
	$HOSTS_STOP_MARKER="#\\vhosts\n";
	$HOSTS_START_MARKER=<<<STR
#/vhosts
# Please. Dont modify this lines directrly
# Use configuration tools instead!
STR;
	
	$hosts_path = "/etc/hosts";
	$hosts = array();
	$hosts[] = preg_replace(
		'/\s*#\/vhosts.*?#\\\vhosts/s', 
		"", 
		file_get_contents($hosts_path)
	);
	$hosts[] = $HOSTS_START_MARKER;
	$hosts[] = $data;
	$hosts[] = $HOSTS_STOP_MARKER;
	
	file_put_contents(
		$hosts_path, 
		join("\n", $hosts)
	);
}

function update_httpd_conf($vhosts)
{
	$conf_file = '/etc/apache2/vhosts/auto_generated.conf';
	
	if (ALLOW_PRECONFIGURE_HTTPD) {
		if (!file_exists('/etc/apache2/vhosts')) {
			mkdir('/etc/apache2/vhosts');
			file_put_contents(
				'/etc/apache2/httpd.conf',
				"\n\nInclude /etc/apache2/other/*.conf",
				FILE_APPEND
			);
		}		
	}
	
	@`touch $conf_file`;
	if (file_put_contents($conf_file, $vhosts)) {
		`httpd -k graceful`;
	}
}

if (!check_admin_privileges()) {
	fprintf(STDERR, "You don't have admin rights");
	exit(1);
}

$service_dir = dirname(__FILE__);
update_hosts(file_get_contents("$service_dir/hosts.conf"));
update_httpd_conf(file_get_contents("$service_dir/vhosts.conf"));