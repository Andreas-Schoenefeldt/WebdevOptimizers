#!/usr/local/bin/php -q
<?php
	date_default_timezone_set("Europe/Berlin");
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/CmdIO.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Filehandler/staticFunctions.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/ComandLineTools/CmdParameterReader.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Mail/Mail.php');

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
			'd' => array(
				'name' => 'date',
				'datatype' => 'String',
				'description' => 'use this to put a direct date for the log processing use 6.7.2012'
			),
			'ts' => array(
				'name' => 'timeframeStart',
				'datatype' => 'String',
				'default' => '00:00',
				'description' => 'a time before this the logs are ignored. Write as h:mm in 24h format'
			),
			'te' => array(
				'name' => 'timeframeEnd',
				'datatype' => 'String',
				'default' => '24:00',
				'description' => 'a time after this the logs are ignored. Write as h:mm in 24h format'
			)
		),
		'A script to parse a logfile and return the results in a readable form.'
	);
	
	
	$files = $params->getFiles();
	
	if(! $params->getVal('r') && count($files) == 0 && ! $params->getVal('l')){
		$params->print_usage();
	} else {
		
		$env = $params->getVal('e');
		$class = capitalise($env) .'LogAnalyser';
		
		require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Filehandler/' . $env . '/' . $class . '.php');
		
		// this is a remote connection, start the remote process
		if ($params->getVal('r') || $params->getVal('l')) {
		
		$configBaseDir = (str_replace('//','/',dirname(__FILE__).'/') .'AnalyseLogfiles/');
		
		forEachFile($configBaseDir, '.*config.*\.php', true, 'processLogFiles');
			
		} else {
			$analyser = new $class($files, 'error');
			$analyser->printResults();
		}
	}

	function processLogFiles($configFile) {
		global $params, $io, $class;
		
		$io->out('> Config File: '.$configFile);
		
		require_once($configFile);
		
		set_error_handler('custom_error_handler', E_ALL);

		try {

			$download = ! $params->getVal('l');	
			
			if ($download) { // Variable to test the html generation quikly
				$io->out('> Preparing ' . $targetWorkingFolder);
				
				if (! file_exists($targetWorkingFolder)) mkdir($targetWorkingFolder, 0744, true);
				if ($clearWorkingFolderOnStart) emptyFolder($targetWorkingFolder, array('/\.sdb$/'));
				
				if (! file_exists($targetWorkingFolder)) mkdir($targetWorkingFolder, 0744, true);
			}
			
			$io->out('> Coppying Files...');
			$results = array();
				
			foreach ($logConfiguration as $layout => $config) {
				
				$splits = ($params->getVal('d')) ? explode('.', $params->getVal('d')) : array();
				
				$day = (count($splits) == 3) ?   $splits[0] : date("d");
				$month = (count($splits) == 3) ? $splits[1] : date("m");
				$year = (count($splits) == 3) ?  $splits[2] : date("Y");
				
				$timestamp = strtotime("$year-$month-$day 00:00:00.000 GMT");
				if (! $params->getVal('d')) $timestamp += $config['dayoffset'] * 86400; // change the date by the number of days
				
				
				$time = date($config['timestampformat'], $timestamp);
				
				for ($i = 0; $i < count($config['fileBodys']); $i++){
					$file = str_replace('${timestamp}', $time, $config['fileBodys'][$i]) . '.' . $config['extension'];
					$target = $targetWorkingFolder . '/' . $file;
					if ($download) download($webdavUser, $webdavPswd, $webdavUrl, $file, $targetWorkingFolder, $alertConfiguration);
					$results[$layout][] = $target;
				}
			}
			
			/*
			if ($download) {
				$io->out('> Unmounting ' . $mountpoint);
				$command = 'umount -f ' . $mountpoint;
				$lastline = system($command, $retval);
			}
			*/
			
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
			
			$settings = array(
				  'from' => $params->getVal('ts')
				, 'to' => $params->getVal('te')
				, 'timestamp' => $timestamp
				, 'timezoneOffset' => 0
			);
			
			foreach ($results as $layout => $files) {
				$analyser = new $class($files, $layout, $settings, $alertConfiguration);
				
				Mail::sendDWAlertMails($analyser->alertMails, $targetWorkingFolder, $alertConfiguration, $layout);
				
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
		
		} catch (Exception $e) {
			$io->error("Error occurred during processing log files. Maybe invalid config file ($configFile) provided: ".$e->getMessage());
		}
	}
	
	function download($webdavUser, $webdavPswd, $webdavUrl, $filename, $localWorkingDir, $alertConfiguration) {
		global $io;
		
		// check if the file exists before
		$commandBody = "curl -k -I -L --user \"$webdavUser:$webdavPswd\" ";
		$command = $commandBody . '"' . $webdavUrl . '/' . $filename . '"';
		$output = shell_exec($command);
		$lines = explode("\n", trim($output));
		
		// check the response header status code
		for ($i = 0; $i < count($lines); $i++) {
			if (startsWith($lines[$i], 'HTTP')) {
				$codes = explode(' ', trim($lines[$i]));
				$statuscode = $codes[1];
				break;
			}
		}
		
		if (isset($statuscode) && $statuscode == '200') {
		
			$io->out('> ----------------------------------');
			$io->out('> Downloading ' . $filename);
			$commandBody = "curl -k --user \"$webdavUser:$webdavPswd\" ";
			$command =  $commandBody . '"' . $webdavUrl . '/' . $filename . '" -o "' . $localWorkingDir . '/' . $filename . '"' ;
			
			$lastline = system($command, $retval);
			// retry logic
			if($retval > 0) {
				$io->error('Failed download ' . $filename);
				return false;
			}
			
			return true;
		} else {
			$errorMessage = "File $filename could not be downloaded from the server. Message: $output. Http Status Code: " . (isset($statuscode) ? $statuscode : 'undefined');
			$io->error($errorMessage);
			if (!isset($statuscode) || $statuscode != "404") {
				Mail::sendDWAlertMails(array('Server Alert' => array('Connection failed' => array('subject' => "Failed to connect to $webdavUrl.", 'message' => "Alert: Failed to connect to $webdavUrl. $errorMessage"))), $localWorkingDir, $alertConfiguration, '');
			}
		}
		
		return false;
	}
	
	
	function upload($filename) {
		global $webdavUser, $webdavPswd, $htmlWorkingDir, $webdavUrl, $io;
		
		$commandBody = "curl -k --user \"$webdavUser:$webdavPswd\" ";
		$command =  $commandBody . '-T "' . $htmlWorkingDir . '/' . $filename . '" "' . $webdavUrl . '/html/' . $filename . '"';
		
		$io->out('> ----------------------------------');
		$io->out('> Uploading ' . $filename); 
		$lastline = system($command, $retval);
		// retry logic
		if($retval > 0) {
			$io->error('Failed upload ' . $filename);
			return false;
		}
		
		return true;
	}
	
	/**
	 * Calls a function for every file in a folder.
	 *
	 * @param string $dir The directory to traverse.
	 * @param string $pattern The file pattern to call the function for. Leave as NULL to match all pattern.
	 * @param bool $recursive Whether to list subfolders as well.
	 * @param string $callback The function to call. It must accept one argument that is a relative filepath of the file.
	 */
	function forEachFile($dir, $pattern = null, $recursive = false, $callback) {
		if ($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false) {
				if ($file === '.' || $file === '..') {
					continue;
				}
				if (is_file($dir . $file)) {
					if ($pattern) {
						if (!preg_match("/$pattern/", $file)) {
							continue;
						}
					}
					$callback($dir . $file);
				}elseif($recursive && is_dir($dir . $file)) {
					forEachFile($dir . $file . DIRECTORY_SEPARATOR, $pattern, $recursive, $callback);
				}
			}
			closedir($dh);
		}
	}
	
	function custom_error_handler($errno, $errstr, $errfile, $errline, $errcontext)
	{
		$constants = get_defined_constants(1);

		$eName = 'Unknown error type';
		foreach ($constants['Core'] as $key => $value) {
			if (substr($key, 0, 2) == 'E_' && $errno == $value) {
				$eName = $key;
				break;
			}
		}

		$msg = $eName . ': ' . $errstr . ' in ' . $errfile . ', line ' . $errline;

		throw new Exception($msg);
	}
	
?>
