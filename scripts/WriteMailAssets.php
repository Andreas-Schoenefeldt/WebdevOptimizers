<?php
	
	date_default_timezone_set("Europe/Berlin");
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/CmdIO.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Debug/libDebug.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/ComandLineTools/CmdParameterReader.php');
	
	$io = new CmdIO();
	
	$params = new CmdParameterReader(
		$argv,
		array(
			'e' => array(
				'name' => 'environment',
				'datatype' => 'Enum',
				'default' => 'demandware',
				'values' => array(
					'demandware' => array('name' => 'dw'),
					'grails' => array('name' => 'g'),
					'openCMS' => array('name' => 'ocms'),
					'magento' => array('name' => 'm')
				),
				
				'description' => 'Defines the software environment of your project.'
			),
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
			$io->fatal('The given config file is empty or invalid: ' . $configFileName, 'WriteDWMailAssets');
		}
		
		$outputMode = $params->getVal('o');
		
		if (! file_exists($workingDir)) {
			$io->warn('Creating the outputfolder ' . $workingDir);
			mkdir($workingDir, 0777, true);
		}
		
		switch ($outputMode) {
			case 'html':
				
				if (isset($htmlAssets) && count($htmlAssets)) {
					
					foreach($htmlAssets as $name => $vars) {
						$folder = $workingDir . 'html/';
						if (! file_exists($folder)) mkdir($folder, 0777, true);
						
						$loopEntrys = (array_key_exists('entrys', $vars)) ? $vars['entrys'] : $entrys;
						
						for ($i = 0; $i < count($loopEntrys); $i++) {
							$entry = $loopEntrys[$i];
							$locale = getLanguage($entry);
							
							$outputFile = $folder . $name . '_' . $entry . '.html';
							
							$template = new Template($outputFile, $entry, $locale, $vars, $templateDir, $outputMode);
						}
					}
				} else {
					$io->fatal('The given config file is missing a $htmlAssets array: ' . $configFileName, 'WriteDWMailAssets');
				}
				
				break;
			case 'xml':
				
				$outputFile = $workingDir . $fileName;
				$opFP = fopen($outputFile, 'w');
				
				// now write the file
				fwrite($opFP, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
				fwrite($opFP, '<library xmlns="http://www.demandware.com/xml/impex/library/2006-10-31">' . "\n");
				
				foreach($siteAssets as $name => $vars) {
					
					$io->out("\t".'> Writing ' . $name);
					
					fwrite($opFP, '<content content-id="' . $name . '">'. "\n");
					fwrite($opFP, '<display-name xml:lang="x-default">' . $vars['displayName'] . '</display-name>'. "\n");
					fwrite($opFP, '<online-flag>true</online-flag>'. "\n");
					fwrite($opFP, '<searchable-flag>true</searchable-flag>' . "\n");
					fwrite($opFP, '<page-attributes/>' . "\n");
					fwrite($opFP, '<custom-attributes>' . "\n");
					
					$loopEntrys = (array_key_exists('entrys', $vars)) ? $vars['entrys'] : $entrys;
					
					for ($i = 0; $i < count($loopEntrys); $i++) {
							$entry = $loopEntrys[$i];
							$locale = getLanguage($entry);
							
							$result = getProcessedTemplate($entry, $locale, $vars, $templateDir, $outputMode);
							
							fwrite($opFP, '<custom-attribute attribute-id="body" xml:lang="' . $entry . '">'. "\n");
							fwrite($opFP, str_replace(array("&", "<", ">", "\"", "'"), array("&amp;", "&lt;", "&gt;", "&quot;", "&apos;"), $result));
							fwrite($opFP, '</custom-attribute>' . "\n");	   
					}
					
					fwrite($opFP, '</custom-attributes>' . "\n");
					fwrite($opFP, '<folder-links>' . "\n");
					fwrite($opFP, "\t" . '<classification-link folder-id="EMAILS"/>' . "\n");
					fwrite($opFP, '</folder-links>' . "\n");
					fwrite($opFP, '</content>' . "\n\n");
				}
				
				fwrite($opFP, '</library>');
				fclose($opFP);
				break;
		}
		
	}
	
	
	class Template {
		
		var $entry;
		var $locale;
		var $vars;
		var $templateDir;
		var $outputMode;
		
		var $decoratorStack = array();
		var $templateIdCounter = 0;
		
		/*
		 *
		 */
		function __construct($outputFile, $entry, $locale, $vars, $templateDir, $outputMode){
			$this->entry = $entry;
			$this->locale = $locale;
			$this->vars = $vars;
			$this->templateDir = $templateDir;
			$this->outputMode = $outputMode;
			
			$opFP = fopen($outputFile, 'w');
			fwrite($opFP, $this->getProcessedTemplate($this->vars));
			fclose($opFP);
		}
		
		function getProcessedTemplate($vars, $forReplace = ''){
			global $io;
			
			$tmplateId = $this->getNextTemplateId();
			$templateFileName = $this->templateDir . $vars['templateFile'];
			if (! file_exists($templateFileName)) $io->fatal('The required template file does not exist: ' . $templateFileName);
			
			$tfFp = fopen($templateFileName, 'r');
			
			$site = $this->getSite();
			
			$result = '';
			while ($line = fgets($tfFp, 2048)){
				$result .= $this->processTemplate($line, $site, $vars, $forReplace, $tmplateId);
			}
			fclose($tfFp);
			
			return $this->decorate($result, $tmplateId); 
		}
		
		function getNextTemplateId() {return 'id' . $this->templateIdCounter++; }
		
		function decorate($toDecorate, $tmplateId){
			global $decoratorAssets, $io;
			
			if ( array_key_exists($tmplateId, $this->decoratorStack)) {
				$decorator = $this->decoratorStack[$tmplateId];
				if (array_key_exists($decorator, $decoratorAssets)) {
					return $this->getProcessedTemplate($decoratorAssets[$decorator], $toDecorate);
				} else {
					$io->error('No Template Decorator Definition found for key: ' . $decorator, '$decoratorAssets');
				}
			} 
				
			// no decorator
			return $toDecorate;
		}
		
		
		function getSite(){
			$splits = explode('-', $this->entry);
		
			if (count($splits) == 1) return strtoupper($splits[0]);
			return ($splits[1] == 'default') ? 'GB' : strtoupper($splits[1]);
		}
		
		
		function processTemplate($string, $site, $vars, $forReplace, $tmplateId){
			global $siteAssets, $io, $brand, $hostBase;
			
			preg_match_all('/\${[ ]*?_decorate\([ ]*?["\'][ ]*?(?P<decorator>.*?)[ ]*?["\'][ ]*?\)[ ]*?}/', $string, $matches, PREG_PATTERN_ORDER);
			for ($i = 0; $i < count($matches['decorator']); $i++){
				$this->decoratorStack[$tmplateId] = $matches['decorator'][$i];
				$string = trim(str_replace($matches[0][$i], '', $string));
			}
			
			// the replace
			preg_match_all('/\${[ ]*?_replace\([ ]*?\)[ ]*?}/', $string, $matches, PREG_PATTERN_ORDER);
			for ($i = 0; $i < count($matches[0]); $i++){
				$string = str_replace($matches[0][$i], $forReplace, $string);
			}
			
			preg_match_all('/\${[ ]*?_t\([ ]*?["\'][ ]*?(?P<key>.*?)[ ]*?["\'][ ]*?\)[ ]*?}/', $string, $matches, PREG_PATTERN_ORDER);
			for ($i = 0; $i < count($matches['key']); $i++){
				$key = $matches['key'][$i];
				$string = str_replace($matches[0][$i], $this->translate($key, $this->getPathLocale($this->locale)), $string);
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
				$string = str_replace($matches[0][$i], $this->getPathLocale($this->locale), $string);
			}
			
			// take care of the includes
			preg_match_all('/\${[ ]*?_include\([ ]*?["\'][ ]*?(?P<include>.*?)[ ]*?["\'][ ]*?\)[ ]*?}/', $string, $matches, PREG_PATTERN_ORDER);
			for ($i = 0; $i < count($matches['include']); $i++){
				$key = $matches['include'][$i];
				
				if (array_key_exists($key, $siteAssets)) {
					$text = $this->getProcessedTemplate($siteAssets[$key]);
				} else {
					$io->error('No Template Definition found for key: ' . $key, '$siteAssets');
					$text = '';
				}
				
				$string = str_replace($matches[0][$i], $text, $string); // replace the include with the template
			}
			
			// now only html replacements
			if ($this->outputMode == 'html') {
				// $url('Search-Show','cgid','snow_snowshop')$
				// http://dev11.store.napali.demandware.net/on/demandware.store/Sites-RX-FR-Site/fr_FR/Search-Show?cgid=swim_bikinis
				
				preg_match_all('/\$url\([ ]*?["\'][ ]*?(?P<pipeline>.*?)[ ]*?["\'][ ]*?,[ ]*?["\'][ ]*?(?P<param>.*?)[ ]*?["\'],[ ]*?["\'][ ]*?(?P<value>.*?)[ ]*?["\'][ ]*?\)[ ]*?\$/', $string, $matches, PREG_PATTERN_ORDER);
				
				for ($i = 0; $i < count($matches[0]); $i++){
					$url = 'http://' . $hostBase[$site] . '/on/demandware.store/Sites-' . $brand . '-' . $site .'-Site/' . $this->locale . '_' . $site . '/' . $matches['pipeline'][$i] . '?' . $matches['param'][$i] . '=' .  $matches['value'][$i];
					$string = str_replace($matches[0][$i], $url, $string);
				}
				
				preg_match_all('/\$url\([ ]*?["\'][ ]*?(?P<pipeline>.*?)[ ]*?["\'][ ]*?\)[ ]*?\$/', $string, $matches, PREG_PATTERN_ORDER);
				
				for ($i = 0; $i < count($matches[0]); $i++){
					$url = 'http://' . $hostBase[$site] . '/on/demandware.store/Sites-' . $brand . '-' . $site .'-Site/' . $this->locale . '_' . $site . '/' . $matches['pipeline'][$i];
					$string = str_replace($matches[0][$i], $url, $string);
				}
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
					$io->warn('Locale ' . $locale . ' not found in translationMap for key ' .$key .'. Fallback to locale ' . $loc . ': "' . $value. '"' , 'translate()');
					return $value;
				}
			}
			$io->error($key . ' not found in translationMap.');
			return ':' . $key . ':';
		}
		
		function getPathLocale($loc) {
			global $locals;
			return (in_array($loc, $locals)) ? $loc : $locals[0];
		}
		
	
	} 
	
	
	
	/// Functions -----------------------
	
	
	function getLanguage($entry) {
		global $locals;
		if (in_array($entry, $locals)) {
			return $entry;
		}
		$splits = explode('-', $entry);
		return ($splits[0] == 'x') ? 'en' : $splits[0];
	}
	
	// will return the real language for this site
	function getPathLocale($loc) {
		global $locals;
		return (in_array($loc, $locals)) ? $loc : $locals[0];
	}
	
	function getSite($entry) {
		$splits = explode('-', $entry);
		
		if (count($splits) == 1) return strtoupper($splits[0]);
		return ($splits[1] == 'default') ? 'GB' : strtoupper($splits[1]);
	}
	
	
	function getProcessedTemplate($entry, $locale, $vars, $templateDir, $outputMode){
		global $io;
		
		$templateFileName = $templateDir . $vars['templateFile'];
		if (! file_exists($templateFileName)) $io->fatal('The required template file does not exist: ' . $templateFileName);
		$tfFp = fopen($templateFileName, 'r');
		
		$site = getSite($entry);
		
		$result = '';
		
		while ($line = fgets($tfFp, 2048)){
			
			$result .= processTemplate($line, $locale, $site, $vars, $entry, $templateDir, $outputMode);
			
		}
		
		fclose($tfFp);
		
		return $result; 
	}
	
	
	function processTemplate($string, $locale, $site, $vars, $entry, $templateDir, $outputMode) {
		global $siteAssets, $io, $brand, $hostBase;
		
		preg_match_all('/\${[ ]*?_t\([ ]*?["\'][ ]*?(?P<key>.*?)[ ]*?["\'][ ]*?\)[ ]*?}/', $string, $matches, PREG_PATTERN_ORDER);
		for ($i = 0; $i < count($matches['key']); $i++){
			$key = $matches['key'][$i];
			$string = str_replace($matches[0][$i], translate($key, getPathLocale($locale)), $string);
		}
		
		preg_match_all('/\${[ ]*?_var\([ ]*?["\'][ ]*?(?P<var>.*?)[ ]*?["\'][ ]*?\)[ ]*?}/', $string, $matches, PREG_PATTERN_ORDER);
		for ($i = 0; $i < count($matches['var']); $i++){
			$key = $matches['var'][$i];
			$string = str_replace($matches[0][$i], $vars['vars'][$key][$site], $string);
		}
		
		preg_match_all('/\${[ ]*?_css\([ ]*?["\'][ ]*?(?P<css>.*?)[ ]*?["\'][ ]*?\)[ ]*?}/', $string, $matches, PREG_PATTERN_ORDER);
		for ($i = 0; $i < count($matches['css']); $i++){
			$key = $matches['css'][$i];
			
			if (! array_key_exists('CSS', $vars)) {
				$io->error('no CSS array found for context ' . $vars('displayName') . '. ', 'general config.');
			} else {
				$string = str_replace($matches[0][$i], $vars['CSS'][$key], $string);
			}
		}
		
		preg_match_all('/\${[ ]*?locale[ ]*?}/', $string, $matches, PREG_PATTERN_ORDER);
		for ($i = 0; $i < count($matches[0]); $i++){
			$string = str_replace($matches[0][$i], getPathLocale($locale), $string);
		}
		
		// take care of the includes
		preg_match_all('/\${[ ]*?_include\([ ]*?["\'][ ]*?(?P<include>.*?)[ ]*?["\'][ ]*?\)[ ]*?}/', $string, $matches, PREG_PATTERN_ORDER);
		for ($i = 0; $i < count($matches['include']); $i++){
			$key = $matches['include'][$i];
			
			if (array_key_exists($key, $siteAssets)) {
				
				$text = getProcessedTemplate($entry, $locale, $siteAssets[$key], $templateDir, $outputMode);
				
			} else {
				$io->error('No Template Definition found for key: ' . $key, '$siteAssets');
				$text = '';
			}
			
			$string = str_replace($matches[0][$i], $text, $string); // replace the include with the template
		}
		
		// now only html replacements
		if ($outputMode == 'html') {
			// $url('Search-Show','cgid','snow_snowshop')$
			// http://dev11.store.napali.demandware.net/on/demandware.store/Sites-RX-FR-Site/fr_FR/Search-Show?cgid=swim_bikinis
			
			preg_match_all('/\$url\([ ]*?["\'][ ]*?(?P<pipeline>.*?)[ ]*?["\'][ ]*?,[ ]*?["\'][ ]*?(?P<param>.*?)[ ]*?["\'],[ ]*?["\'][ ]*?(?P<value>.*?)[ ]*?["\'][ ]*?\)[ ]*?\$/', $string, $matches, PREG_PATTERN_ORDER);
			
			for ($i = 0; $i < count($matches[0]); $i++){
				$url = 'http://' . $hostBase[$site] . '/on/demandware.store/Sites-' . $brand . '-' . $site .'-Site/' . $locale . '_' . $site . '/' . $matches['pipeline'][$i] . '?' . $matches['param'][$i] . '=' .  $matches['value'][$i];
				$string = str_replace($matches[0][$i], $url, $string);
			}
			
			preg_match_all('/\$url\([ ]*?["\'][ ]*?(?P<pipeline>.*?)[ ]*?["\'][ ]*?\)[ ]*?\$/', $string, $matches, PREG_PATTERN_ORDER);
			
			for ($i = 0; $i < count($matches[0]); $i++){
				$url = 'http://' . $hostBase[$site] . '/on/demandware.store/Sites-' . $brand . '-' . $site .'-Site/' . $locale . '_' . $site . '/' . $matches['pipeline'][$i];
				$string = str_replace($matches[0][$i], $url, $string);
			}
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
				$io->warn('Locale ' . $locale . ' not found in translationMap for key ' .$key .'. Fallback to locale ' . $loc . ': "' . $value. '"' , 'translate()');
				return $value;
			}
		}
		$io->error($key . ' not found in translationMap.');
		return ':' . $key . ':';
	}
	
	
	


?>