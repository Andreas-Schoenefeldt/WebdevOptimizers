<?php
	date_default_timezone_set("Europe/Berlin");
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/CmdIO.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Debug/libDebug.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/ComandLineTools/CmdParameterReader.php');
	
	$io = new CmdIO();
	
	$params = new CmdParameterReader(
		$argv,
		array(
			'c' => array(
				'name' => 'clear',
				'datatype' => 'Boolean',
				
				'description' => 'Clear. If set, the outputfolder will we cleared before the file creation'
			),
			'o' => array(
				'name' => 'output',
				'datatype' => 'Enum',
				'default' => 'xml',
				'values' => array(
					  'xml' => array('name' => 'xml')
					, 'html' => array('name' => 'html')
				),
				
				'description' => 'Defines if we generate html or a xml library'
				
			),
		),
		'A Script to create a demandware content import xml file in order to create multilingual sitespecific content assets.'
	);
	
	$configFileName = $params->getFileName();
	
	if(! file_exists($configFileName)) {
		$params->print_usage();
		$io->fatal("The config file " . $configFileName . " does not exist.", 'CreateSeleniumTestSuite');
	
	} else {
		
		// get the configuration
		try {
			include($configFileName);
		} catch (Exception $e) {
			$io->fatal($e->getMessage());
		}
		
		if (! isset($siteAssets) || ! count($siteAssets)) {
			$io->fatal('The given config file is empty or invalid: ' . $configFileName, 'CreateSeleniumTestSuite');
		}
		
		switch ($params->getVal('o')) {
			case 'html':
				break;
			case 'xml':
				break;
		}
		
		
	
		// now write the file
		
		$outputFile = $workingDir . $fileName;
		$opFP = fopen($outputFile, 'w');
		
		fwrite($opFP, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
		fwrite($opFP, '<library xmlns="http://www.demandware.com/xml/impex/library/2006-10-31">' . "\n");
		
		foreach($siteAssets as $name => $vars) {
			
			fwrite($opFP, '<content content-id="' . $name . '">'. "\n");
			fwrite($opFP, '<display-name xml:lang="x-default">' . $vars['displayName'] . '</display-name>'. "\n");
			fwrite($opFP, '<online-flag>true</online-flag>'. "\n");
			fwrite($opFP, '<searchable-flag>true</searchable-flag>' . "\n");
			fwrite($opFP, '<page-attributes/>' . "\n");
			fwrite($opFP, '<custom-attributes>' . "\n");
			
			$templateFileName = $templateDir . $vars['templateFile'];
			$tfFp = fopen($templateFileName, 'r');
			$localisedContents = array();
			
			while ($line = fgets($tfFp, 2048)){
				
				$loopEntrys = (array_key_exists('entrys', $vars)) ? $vars['entrys'] : $entrys;
				
				// now do all the locals
				for ($i = 0; $i < count($loopEntrys); $i++) {
					$entry = $loopEntrys[$i];
					$locale = getLanguage($entry);
					$site = getSite($entry);
					
					if (! array_key_exists($entry, $localisedContents)) $localisedContents[$entry] = '<custom-attribute attribute-id="body" xml:lang="' . $entry . '">';
					
					$localisedContents[$entry] .=  str_replace(array("&", "<", ">", "\"", "'"), array("&amp;", "&lt;", "&gt;", "&quot;", "&apos;"), processTemplate($line, $locale, $site, $vars));
					
				}
			}
			
			// write the localised bodys
			foreach($localisedContents as $entry => $text) {
				$text .= '</custom-attribute>' . "\n";	
				fwrite($opFP, $text);
			}
			
			fwrite($opFP, '</custom-attributes>' . "\n");
			fwrite($opFP, '<folder-links>' . "\n");
			fwrite($opFP, "\t" . '<classification-link folder-id="EMAILS"/>' . "\n");
			fwrite($opFP, '</folder-links>' . "\n");
			fwrite($opFP, '</content>' . "\n\n");
		}
		
		fwrite($opFP, '</library>');
	}
	
	
	/// Functions -----------------------
	
	
	function getLanguage($entry) {
		$splits = explode('-', $entry);
		return ($splits[0] == 'x') ? 'en' : $splits[0];
	}
	
	function getSite($entry) {
		$splits = explode('-', $entry);
		
		if (count($splits) == 1) return strtoupper($splits[0]);
		return ($splits[1] == 'default') ? 'GB' : strtoupper($splits[1]);
	}
	
	
	function processTemplate($string, $locale, $site, $vars) {
		
		preg_match_all('/\${[ ]*?_t\([ ]*?["\'][ ]*?(?P<key>.*?)[ ]*?["\'][ ]*?\)[ ]*?}/', $string, $matches, PREG_PATTERN_ORDER);
		for ($i = 0; $i < count($matches['key']); $i++){
			$key = $matches['key'][$i];
			$string = str_replace($matches[0][$i], translate($key, $locale), $string);
		}
		
		preg_match_all('/\${[ ]*?_var\([ ]*?["\'][ ]*?(?P<var>.*?)[ ]*?["\'][ ]*?\)[ ]*?}/', $string, $matches, PREG_PATTERN_ORDER);
		for ($i = 0; $i < count($matches['var']); $i++){
			$key = $matches['var'][$i];
			$string = str_replace($matches[0][$i], $vars['vars'][$key][$site], $string);
		}
		
		preg_match_all('/\${[ ]*?_css\([ ]*?["\'][ ]*?(?P<css>.*?)[ ]*?["\'][ ]*?\)[ ]*?}/', $string, $matches, PREG_PATTERN_ORDER);
		for ($i = 0; $i < count($matches['css']); $i++){
			$key = $matches['css'][$i];
			$string = str_replace($matches[0][$i], $vars['CSS'][$key], $string);
		}
		
		preg_match_all('/\${[ ]*?locale[ ]*?}/', $string, $matches, PREG_PATTERN_ORDER);
		for ($i = 0; $i < count($matches[0]); $i++){
			$string = str_replace($matches[0][$i], $locale, $string);
		}
		
		return $string;
		
	}
	
	function translate($key, $locale) {
		global $io, $translationMap;
		
		if (array_key_exists($key, $translationMap)) {
			if (array_key_exists($locale, $translationMap[$key])) {
				return $translationMap[$key][$locale];
			}
			
			foreach ($translationMap[$key] as $loc => $value) {
				$io->error('Locale ' . $locale . ' not found in translationMap for key ' .$key .'. Fallback to locale ' . $loc . ': "' . $value. '"' , 'translate()');
				return $value;
			}
		}
		$io->error($key . ' not found in translationMap.');
		return ':' . $key . ':';
	}
	
	
	


?>