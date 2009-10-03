#!/usr/bin/php
<?php
# Created by vectoroc

require_once 'config.php';
require_once 'commons.php';

function install($sites_dir)
{
	$service_name = SERVICE_NAME;	
	$src_dir = dirname(__FILE__);
	$home_dir = $_ENV['HOME'];
	$dst_dir = "$home_dir/.$service_name";
	$conf = <<<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>Label</key>
	<string>$service_name</string>
	<key>ProgramArguments</key>
	<array>
		<string>php</string>
		<string>$dst_dir/agent.php</string>
		<string>$sites_dir</string>
	</array>
	<key>WatchPaths</key>
	<array>
		<string>$sites_dir</string>
	</array>
</dict>
</plist>
PLIST;
	
	@mkdir($dst_dir);
	`cp -R -f $src_dir/* $dst_dir`;
	
	file_put_contents(
		"$home_dir/Library/LaunchAgents/$service_name.plist", 
		$conf
	);
}

function update_hosts($hosts, $vhosts)
{
	$service_dir = dirname(__FILE__);
	$command = "php $service_dir/update_hosts.php";
	
	file_put_contents($service_dir.'/hosts.conf', join("\n", $hosts));
	file_put_contents($service_dir.'/vhosts.conf', join("\n", $vhosts));
	
	system(
		"osascript -e 'do shell script \"$command\" with administrator privileges'", 
		$updated_failed
	);
	
	return !$updated_failed;
}

function generate_host_entry($site_name)
{
	return "127.0.0.1\t\t$site_name";
}

function generate_vhost_config($site_root, $site_name, $port = 80)
{
	static $shared_port = 8765;
	$conf = <<<VHOST
<VirtualHost *:$port>
   DocumentRoot $site_root/$site_name
   ServerName $site_name
   <Directory $site_root/$site_name>
      AllowOverride All
      Order Allow,deny
      Allow from All
   </Directory>
</VirtualHost>
<IfModule bonjour_module>
   Listen $shared_port
   <VirtualHost *:$shared_port>
      DocumentRoot $site_root/$site_name
   </VirtualHost>
   RegisterResource "$site_name" "/" $shared_port
</IfModule>
VHOST;

	$shared_port--;
	if ($port != 80) {
		$conf = "Listen $port\nNameVirtualHost *:$port\n" . $conf;
	}

	return $conf;
}

function update_if_modified_sites_list($sites)
{
	$sites_file = dirname(__FILE__).'/sites.txt';
	$sites_data = join("\n", $sites);
	if (@file_get_contents($sites_file) != $sites_data) {
		file_put_contents($sites_file, $sites_data);
		return true;
	}
	return false;
}

function run()
{
	global $argv;
	
	if ($argv[1] == '--install') {
		$install_mode = true;
		$sites_dir = $argv[2];
		array_shift($argv);
	}
	else {
		$install_mode = false;
		$sites_dir = $argv[1];
	}

	if (empty($sites_dir)) {
		$sites_dir = $_ENV['HOME'] . '/Sites';
	}
	
	if (!is_dir($sites_dir)) {
		error("'$sites_dir' is invalid dir. You must specify path with sites folder");
		exit(1);
	}
	
	$sites_dir = dirname($sites_dir).'/'.basename($sites_dir);	
	
	if ($install_mode) {
		install($sites_dir);
		message(
			"Vhost configurator agent installed. \n"
			. "Run agent.php maunally or change something in sites root folder\n",
			'install'
		);
		echo `launchctl load ~/Library/LaunchAgents`;
		exit(0);
	}
	
	$sites = array_filter(explode(
		"\n", `ls -lt $sites_dir | grep ^d | awk '{print $9}'`
	));
	$domain_re = '/([a-z][a-z0-9_-]*(?:\.[a-z][a-z0-9_-]*)*):?(\d+)?/i';
	$hosts = array();
	$vhosts = array();
	$valid_sites = array();
	$invalid_sites = array();
	foreach ($sites as $site) {
		if (preg_match($domain_re, $site, $matches)) {
			$site_name = $matches[1];
			$port = isset($matches[2]) ? $matches[2] : 80;
			if ($site_name == 'localhost' && $port == 80) {
				error("Can`nt create vhost 'localhost[:80]'");
				continue;
			}
			
			$valid_sites[] = $site;
			$hosts[] = generate_host_entry($site_name);
			$vhosts[] = generate_vhost_config($sites_dir, $site_name, $port);
		}
		else {
			$invalid_sites[] = $site;
		}
	}
	
	if (count($invalid_sites)) {
		message("'".join("', '", $invalid_sites)."' is invalid domain names.");
	}
	
	if (!update_if_modified_sites_list($valid_sites)) {
		# nothing changes. exit
		exit(0);
	}

	if (update_hosts($hosts, $vhosts)) {
		message("Apache have been succefully reconfigured", 'success');
		exit(0);
	}
	else {
		error("Something is wrong.\nCheck log in Console.app");
		exit(1);
	}
}

run();