#!/usr/local/bin/php -q
<?php
	date_default_timezone_set("Europe/Berlin");
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/CmdIO.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Debug/libDebug.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Filehandler/staticFunctions.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/ComandLineTools/CmdParameterReader.php');
	
	
	$io = new CmdIO();
	
	$params = new CmdParameterReader($argv, array(
		'outputfolder' => array('default' => '/Users/Andreas/Desktop/Roman_MC_Converted/', 'description' => 'The Folder to put the translation to.'),
		'httpRoot' => array('default' => 'http://www.hydro-consult.de', 'description' => 'The http root URI to the website. Will be used for the canonical tag in the changed html files.'),
		'ws' => array('default' => 0, 'description' => 'Without Statics. If set to 1, only the html and php files will be exportet.', 0 => 1)
	));
	
	$fileToTranslate = $params->getFileName();
	
	if(!$fileToTranslate){
		$params->print_usage("A Script to convert html files to php files. Will also add a canonical Tag  to the old html file.");
	} else {
		
		// create the output Dir, make it always new
		if (file_exists($params->getVal('outputfolder'))) {
			deleteFiles($params->getVal('outputfolder'));
		} 
		
		mkdir($params->getVal('outputfolder'));
		
		if (!is_dir($fileToTranslate)) {
			$fileToTranslate = dirname($fileToTranslate);
		}
		
		
		
		recurseOverCompleteStructure($fileToTranslate, $fileToTranslate, function($file, $base){
			global $params, $io;
			
			$relpath = str_replace($base, '', $file);
			
			
			d($base);
			
			$newFile = $params->getVal('outputfolder') . $relpath;
			
			if (is_dir($file) && !file_exists($newFile)) {
				mkdir($newFile);
			}
			
			if (!is_dir($file)) {
				
				$name = basename($file);
				$parts = explode('.', $name);
				
				if ($parts[1] == 'html') {
					$dir = dirname($newFile);
					$relDir = dirname($relpath);
					
					$read = fopen($file, 'r');
					$writeHtml = fopen($dir . '/' . $parts[0] . '.html', 'w');
					$writePhp = fopen($dir . '/' . $parts[0] . '.php', 'w');
					
					$write_PHP = true;
					
					/*
					fwrite($writePhp, '<?php date_default_timezone_set("Europe/Berlin"); ?>' . "\r\n");
					// leads to a sctrange error in Romans website
					*/
					
					while($line = fgets($read, 4096)){
						
						$line = str_replace('.html', '.php', $line);
						if (strstr($line, '<?xml version="1.0" encoding="UTF-8"?>') > -1){ // this one makes errors in the html
							fwrite($writeHtml, $line);
						} else if (strstr($line, '<head>') > -1) {
							fwrite($writeHtml, $line);
							if ($write_PHP) {
								fwrite($writePhp, $line);
								fwrite($writePhp, "\t\t".'<?php include ("' . getRootIncludeString($relpath, 'includes_menu/head.php') . '");' . " " . '?>'. "\r\n");
							}
							fwrite($writeHtml, "\t\t" .'<link rel="canonical" href="' . $params->getVal('httpRoot') . $relDir . $parts[0] . '.php" />' . "\r\n");
							
						} else if (strstr($line, '<div id="head">') > -1) { // start case menu
							fwrite($writeHtml, $line);
							$write_PHP = false;
							
							fwrite($writePhp, "\t\t".'<?php include ("' . getRootIncludeString($relpath, 'includes_menu/menu.php') . '");' . " " . '?>'. "\r\n");
							
						} else if (strstr($line, '<div id="content">') > -1) { // close case
							fwrite($writeHtml, $line);
							$write_PHP = true;
							fwrite($writePhp, $line);
						} else {
							fwrite($writeHtml, $line);
							if ($write_PHP) fwrite($writePhp, $line);
						}
					}
					
				} else if ($parts[1] == 'php' && file_exists($newFile)) {
					// avoid override - do nothing
				} else if ($params->getVal('ws') == 0){
					copy($file, $newFile);
				}
			}
			
		}, true);
		
		
		
		
	}

?>