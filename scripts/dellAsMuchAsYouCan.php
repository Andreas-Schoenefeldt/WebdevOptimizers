#!/usr/local/bin/php -q
<?php

	include ('C:\Dokumente und Einstellungen\Andreas\Eigene Dateien\Dropbox\My Dropbox\Programming/lib_php/CmdIO.php');

	date_default_timezone_set("Europe/Berlin");
	
	$io = new CmdIO();
	
	$baseDirectory = $argv[1];
	$io->cmd_print('Starting to delete as much as possible of '.$baseDirectory);
	$io->cmd_print();
	
	if(!$baseDirectory){
		printUsage();
	} else {
		
		tryDelete($baseDirectory);
	
	}	
	
	function tryDelete($filepath){
		$io = new CmdIO();
	
		if(is_dir($filepath)){
			$subDir = opendir($filepath); 
			$io->cmd_print('> found dir ' . $filepath);
			while($entryName = readdir($subDir)) {
				if($entryName != '.' && $entryName != '..') {
					$ndir = $filepath.'\\'.$entryName;
					tryDelete($ndir);
					throwAway($ndir);
				}
			}
			closedir($subDir);
		} else {
			throwAway($filepath);
		}
	}
	
	function throwAway($filepath){
		$io = new CmdIO();
		try{
			if (is_dir($filepath)){
				rmdir($filepath);
			} else {
				$res = unlink($filepath);
				if (!unlink){
					$io->cmd_print(pressbutton: );
					$io->readStdInn();
				}
			}
		} catch (Exception $e) {
			$io->cmd_print('some error with file: '.$filepath);
		}
	}
	
	
	function printUsage(){
		$io = new CmdIO();
		$io->cmd_print("A script to delete as much of a filestructure as possible:\n\n   1. parameter: the rootfolder, that is meant to be deleted.\n", true, 1);
		
	}
	
?>