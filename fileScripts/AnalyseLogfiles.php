#!/usr/local/bin/php -q
<?php
	date_default_timezone_set("Europe/Berlin");
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/CmdIO.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Filehandler/staticFunctions.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/ComandLineTools/CmdParameterReader.php');

	$params = new CmdParameterReader(
		$argv,
		array(
			'e' => array(
				'name' => 'environment',
				'datatype' => 'Enum',
				'default' => 'demandware',
				'values' => array(
					'demandware' => array('name' => 'dw')
				),
				
				'description' => 'Defines the software environment of the logfile.'
			),
			'r' => array(
				'name' => 'remote',
				'datatype' => 'Boolean',
				'description' => 'if set, the files will be grabbed from a remote location'
			),
			'l' => array(
				'name' => 'local',
				'datatype' => 'Boolean',
				'description' => 'process localy only',
			),
		),
		'A script to parse a logfile and return the results in a readable form.'
	);
	
	
	$files = $params->getFiles();
	
	if(! $params->getVal('r') && count($files) == 0){
		$params->print_usage();
	} else {
		
		$env = $params->getVal('e');
		$class = capitalise($env) .'LogAnalyser';
		
		require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Filehandler/' . $env . '/' . $class . '.php');
		
		// this is a remote connection, start the remote process
		if ($params->getVal('r')) {
			
			require_once(str_replace('//','/',dirname(__FILE__).'/') .'AnalyseLogfiles/config.php');
			
			$download = ! $params->getVal('l');
			
			
			if ($download) { // Variable to test the html generation quikly
				$io->out('> Preparing ' . $targetWorkingFolder);
				
				if (! file_exists($targetWorkingFolder)) mkdir($targetWorkingFolder, 0744, true);
				if ($clearWorkingFolderOnStart) deleteFiles($targetWorkingFolder);
				
				
				$mountpoint = $webdavMountRoot . '/' . $webdavparts[2];
				$io->out('> Mounting ' . $webdavUrl . ' to ' . $mountpoint);
				if (! file_exists($mountpoint)) mkdir($mountpoint);
				if (! file_exists($targetWorkingFolder)) mkdir($targetWorkingFolder);
				
				
				$secure = substr($webdavparts[0], 0, -1) == 'https';
				
				$command = 'mount_webdav ';
				if ($secure) $command .= '-s ';
				$command .= $webdavUrl . ' ' . $mountpoint;
				
				// d($command);
				
				$lastline = system($command, $retval);
				
				// retry logic
				if($retval > 0) {
					echo ("> Retry ");
					$trys = 1;
					while ($retval > 0 && $trys < 10) {
						echo ($trys . " ");
						$lastline = system($command, $retval);
						$trys++;
					}
					
					if ($retval > 0) {
						throw new Exception('Something went wrong with the webdav connection: ' . $lastline);
					} else {
						echo "Success\n";
					}
				}
				
				$io->out('> Coppying Files...');
				$results = array();
			}
			
			foreach ($logConfiguration as $layout => $config) {
				$timestamp = mktime(0, 0, 0, date("m")  , date("d") + $config['dayoffset'], date("Y"));
				$time = date($config['timestampformat'], $timestamp);
				
				$results[$layout] = array();
				
				for ($i = 0; $i < count($config['fileBodys']); $i++){
					$file = str_replace('${timestamp}', $time, $config['fileBodys'][$i]) . '.' . $config['extension'];
					$target = $targetWorkingFolder . '/' . $file;
					if ($download) copy($mountpoint . '/' . $file, $target);
					$results[$layout][] = $target;
				}
			}
			
			
			if ($download) {
				$io->out('> Unmounting ' . $mountpoint);
				$command = 'umount -f ' . $mountpoint;
				$lastline = system($command, $retval);
			}
			
			$htmlWorkingDir = $targetWorkingFolder . '/html';
			if (! file_exists($htmlWorkingDir)) mkdir($htmlWorkingDir);
			
			// now lets print a index.html
			$io->out('> Writing index.html');
			
			$filepath = $htmlWorkingDir . '/index.html';
			$file = fopen($filepath, 'w');
			
			$title = "Logfiles - " . date('d.m.Y', $timestamp);
			
			fwrite($file, '<!DOCTYPE html><html><head><meta content="text/html; charset=utf-8" http-equiv="Content-Type"><title>'.$title.'</title>
				<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js" type="text/javascript"></script><script src="app.js" type="text/javascript"></script><link href="style.css" type="text/css" rel="stylesheet">
				</head><body><div class="page"><h1>'.$title.'</h1>');
			
			foreach ($results as $layout => $files) {
				$analyser = new $class($files, $layout);
				
				$analyser->setWorkingDir($htmlWorkingDir);
				$analyser->setTime($timestamp);
				
				$io->out('> Writing result for ' . $layout . ' files.');
				$filename = $analyser->printResults('html');
				fwrite($file, '<p><a href="'. $filename .'"><strong>'. $analyser->layout . ' logs</strong> ('.$analyser->getErrorCount().' different errors, '. $analyser->getAllErrorCount() .' total)</a></p>');
				
				if ($download) upload($filename);
				
				
			}
			fwrite($file, '</div></body></html>');
			fclose($file);
			
			if ($download) {
				upload('index.html');
				upload('app.js');
				upload('style.css');
			}
			
		} else {
			$analyser = new $class($files, 'error');
				$analyser->printResults();
		}
	}
	
	function upload($filename) {
		global $webdavUser, $webdavPswd, $htmlWorkingDir, $webdavUrl, $io;
		
		$commandBody = "curl -k --user \"$webdavUser:$webdavPswd\" ";
		$command =  $commandBody . '-T "' . $htmlWorkingDir . '/' . $filename . '" "' . $webdavUrl . '/html/' . $filename . '"';
		
		$io->out('> Uploading ' . $filename); 
		$lastline = system($command, $retval);
		// retry logic
		if($retval > 0) {
			$io->error('Failed upload ' . $filename);
			return false;
		}
		
		return true;
	}
	
?>
