<?php
error_reporting(E_ALL);
if(isset($_GET['action']) && $_GET['action'] == 'dl'){
	if(isset($_GET['filename'])){
		if(file_exists('pm/backup/full/' . $_GET['filename'] . '.zip')){
			header('Content-type: application/zip');
			header('Content-Disposition: attachment; filename="backup.zip"');
			readfile('pm/backup/full/' . $_GET['filename'] . '.zip');
		}
	}
}
require_once("pm/include/pm.class.php");
$pm = new pm();
if(isset($_GET['p']))
	if(!$pm->open_index($_GET['p']))
		$pm->show_error('404', 404);
//$pm->install_pack('adodb', 'adodb.zip', false);
//$pm->backup(false, 'Spaßbackup');
//$pm->remove_pack('hello_world', true, true);
//$pm->revert_changes(1);
echo $pm->get_debug_code();