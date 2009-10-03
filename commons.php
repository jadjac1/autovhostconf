<?php 

function message($msg, $type = 'info')
{
	$app_name = SERVICE_CAPTION;
	$icon_dir = dirname(__FILE__).'/resources';
	$icon = 'MESSAGE_'.strtoupper($type).'_ICON';
	$image = '';
	if (defined($icon)) {
		
		$image = "--image $icon_dir/" . constant($icon);
	}
	
	$file = ($type == 'error')
		  ? STDERR
		  : STDOUT
	;	
	
	fprintf($file, $msg . "\n");
	@`which -s growlnotify && growlnotify -n "$app_name" -m "$msg" $image`;
}

function error($msg)
{
	message($msg, 'error');
}

function check_admin_privileges()
{
	return is_writable('/etc');
}