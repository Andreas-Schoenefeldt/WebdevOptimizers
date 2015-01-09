#!/usr/local/bin/php -q
<?php
	date_default_timezone_set("Europe/Berlin");
	include ('C:\Dokumente und Einstellungen\Andreas\Eigene Dateien\Dropbox\My Dropbox\Programming/lib_php/CmdIO.php');
	
	$io = new CmdIO();
	
	$baseDirectory = $argv[1];
	$pathCrumbs = explode('\\',str_replace('"', '', $baseDirectory));
	
	$reducedName = str_replace('_crushed', '', $pathCrumbs[count($pathCrumbs) - 1]);
	
	$target = str_replace('.png', '_crushed.png', $argv[1]);
	
	echo 'renaming '.$target.' to '.$reducedName;
	exec("ren \"$target\" \"$reducedName\"");
	
?>