#!/usr/local/bin/php -q
<?php
	date_default_timezone_set("Europe/Berlin");
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/CmdIO.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Debug/libDebug.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Filehandler/staticFunctions.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/ComandLineTools/CmdParameterReader.php');
	
	
	$io = new CmdIO();
	
	$params = new CmdParameterReader(
		$argv,
		array(
			'o' => array(
				'name' => 'outputfolder',
				'datatype' => 'String',
				
				'description' => 'Folder to put the testsuite to. If omitted "tests_" + the name of the config file  is used.'
			),
			'n' => array(
				'name' => 'name',
				'datatype' => 'String',
				
				'description' => 'Name of the testsuite and the namespace of the files. If ommitted, it will take the config file name as namespace.'
			),
			
			'c' => array(
				'name' => 'clear',
				'datatype' => 'Boolean',
				
				'description' => 'Clear. If set, the outputfolder will we cleared before the file creation'
			),
		),
		'A Script to create a selenium testsuite, based on a configuration file as last parameter.'
	);
	
	$configFileName = $params->getFileName();
	
	if(! file_exists($configFileName)) {
		$params->print_usage();
		$io->fatal("The config file " . $configFileName . " does not exist.", 'CreateSeleniumTestSuite');
	
	} else {
		
		$nameBase = $params->getVal('n');
		if (! $nameBase) {
			$info = pathinfo($configFileName);
			$nameBase = $info['filename'];
		}
		
		// get the configuration
		try {
			include($configFileName);
		} catch (Exception $e) {
			$io->fatal($e->getMessage());
		}
		
		if (! isset($config) || ! count($config)) {
			$io->fatal('The given config file is empty or invalid: ' . $configFileName, 'CreateSeleniumTestSuite');
		}
		
		
		
		// create or use the output Dir, make it always new
		
		if ($params->getVal('o')) {
			$folder = $params->getVal('o');
		} else {
			if (isset($defaults) && array_key_exists('targetfolder', $defaults)) {
				$folder = $defaults['targetfolder'];
				$io->out('> [INFO] Using preconfigured targetfolder from configuration file: ' . $folder);
			} else {
				$folder = 'tests_' . $nameBase;
				$io->out('> [INFO] Using default folder name for this config file: ' . $folder);
			}
		}
		
		// create the output Dir, make it always new
		if ($params->getVal('c') && file_exists($folder)) {
			deleteFiles($folder);
		} 
		
		if (! file_exists($folder)) {
			mkdir($folder, 0777, true);
		}
		
		if(! is_dir($folder)) {
			$io->fatal($folder . ' is no folder.', 'CreateSeleniumTestSuite');
		}
		
		
		if (! isset($servers)) {
			$io->fatal('There is no $servers array set in the config file. Please add your http host.', 'CreateSeleniumTestSuite');
		}
		
		// now we write the config files
		// TODO avoid deadlock loops?
		$cp = new ConfigurationProcessor($config, $folder, $nameBase, $translationMap);
		$cp->setServers($servers);
		if (isset($smoketests))  $cp->setSmoketests($smoketests);
		$io->out('> Starting with writing the files.');
		$cp->writeFiles();
		
		$io->out('> ' . $cp->fileNameCount . ' testcase(s) generated. (total '. ($cp->fileNameCount * count($servers)) .' for ' . count($servers) . ' server(s))');
		
	}
	
	
	class ConfigurationProcessor {
		
		var $pathConf = array();
		var $blocks = array(); // this are the blocks, divided by forks
		
		var $translationMap = array();
		
		var $namebase;
		var $folder;
		var $suiteFile;
		var $servers = array(); // this holds the different environments
		var $smoketests = array(); // the smoketestindexes
		
		var $fileNameCount = 0;
		
		function __construct($config, $folder, $namebase, $translationMap = array()){
			$this->folder = $folder;
			$this->namebase = $namebase;
			$this->setTranslationMap($translationMap);
			// enter the first fork and process the file
			$this->buildConfigPaths($config, $namebase);
		}
		
		function setSmoketests($smoketests) {
			$this->smoketests = $smoketests;
		}
		
		function withSmoketests(){
			return count($this->smoketests) > 0;
		}
		
		function setServers($servers){
			$this->servers = $servers;
		}
		
		function getFilename(){
			$name = $this->namebase . $this->fileNameCount . '.test';
			$this->fileNameCount++;
			return $name;
		}
		
		
		function writeFiles(){
			
			print '> .';
			
			// set up the locations for the servers
			
			$locations = array();
			for ($s = 0; $s < count($this->servers); $s++) {
				$location = array();
				$location['environment'] = $this->servers[$s];
				$location['folder'] = $this->folder . '/' . $location['environment'] . '/';
				
				if (! file_exists($location['folder'])) {
					mkdir($location['folder']);
				}
				
				$location['suiteFiles'] = array( array('name' => $this->namebase . '.testsuite'));
				$location['suiteFiles'][0]['fileHandler'] = fopen($location['folder'] . $location['suiteFiles'][0]['name'], 'w');
				
				if ($this->withSmoketests()) {
					$location['suiteFiles'][] = array('name' => $this->namebase . '-smoketest.testsuite');
					$location['suiteFiles'][1]['fileHandler'] = fopen($location['folder'] . $location['suiteFiles'][1]['name'], 'w');
				}
				
				$locations[] = $location;
			}
			
			// open all the suit files
			for ($s = 0; $s < count($locations); $s++) {
				$location = $locations[$s];
				
				// initialise suitefiles
				for ($f = 0; $f < count($location['suiteFiles']); $f++) {
					$suiteFile = $location['suiteFiles'][$f];
					fwrite($suiteFile['fileHandler'], '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
					fwrite($suiteFile['fileHandler'], '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'. "\n");
					fwrite($suiteFile['fileHandler'], '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">'. "\n");
					fwrite($suiteFile['fileHandler'], '<head>'. "\n");
					fwrite($suiteFile['fileHandler'], '	<meta content="text/html; charset=UTF-8" http-equiv="content-type" />'. "\n");
					fwrite($suiteFile['fileHandler'], '	<title>' . $suiteFile['name'] . '</title>'. "\n");
					fwrite($suiteFile['fileHandler'], '</head>'. "\n");
					fwrite($suiteFile['fileHandler'], '<body>'. "\n");
					fwrite($suiteFile['fileHandler'], '	<table id="suiteTable" cellpadding="1" cellspacing="1" border="1" class="selenium"><tbody>'. "\n");
					fwrite($suiteFile['fileHandler'], '		<tr><td><b>' . $suiteFile['name'] .'</b></td></tr>'. "\n");
				}
			}
			
			
			$count = count($this->pathConf);
			for ($i = 0; $i < $count; $i++) {
				
				if (! $this->withSmoketests() || in_array($i, $this->smoketests)) {
				
					if ($i % 200 == 0) print '.';
					
					$conf = $this->pathConf[$i];
					$fileName = $this->getFilename();
					
					$c = $count . ''; $add = '';
					while (strlen($add) < strlen($c) - strlen($i . '')) {
						$add .= '0';
					}
					
					for ($s = 0; $s < count($locations); $s++) {
						
						$location = $locations[$s];
						
						// write the file to every server
						$filePath = $location['folder'] . $fileName;
						$file = fopen($filePath, 'w');
						
						fwrite($file, '<?xml version="1.0" encoding="UTF-8"?>'. "\n");
						fwrite($file, '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'. "\n");
						fwrite($file, '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">'. "\n");
						fwrite($file, '<head profile="http://selenium-ide.openqa.org/profiles/test-case">'. "\n");
						fwrite($file, '	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'. "\n");
						fwrite($file, '	<link rel="selenium.base" href="https://' . $location['environment'] . '/" />'. "\n");
						fwrite($file, '	<title>' . $conf['name'] . '</title>'. "\n");
						fwrite($file, '</head>'. "\n");
						fwrite($file, '<body>'. "\n");
						fwrite($file, '	<table cellpadding="1" cellspacing="1" border="1">'. "\n");
						fwrite($file, '		<thead>'. "\n");
						fwrite($file, '			<tr><td rowspan="1" colspan="3">' .$conf['name'] . '</td></tr>'. "\n");
						fwrite($file, '		</thead><tbody>'. "\n");
						
						for ($k = 0; $k < count($conf['blocks']); $k++) {
							$block = $this->blocks[$conf['blocks'][$k]];
							for($v = 0; $v < count($block); $v++) {
								$line = $block[$v];
								
								if (array_key_exists('comment', $line)) { fwrite($file, "\n<!-- " . $line['comment'] . " -->"); }
								fwrite($file, "\n<tr><td>" .$line['command'] . "</td><td>" .$line['target'] . "</td><td>" .$line['value'] . "</td></tr>");
							}
						}
						
						fwrite($file, '		</tbody></table></body></html>');
						fclose($file);
						
						$line = "\n".'<tr><td><a href="' . $fileName . '">' . $add . $i . $conf['name'] . '</a></td></tr>';
						fwrite($location['suiteFiles'][0]['fileHandler'], $line);
						if (in_array($i, $this->smoketests)) {
							fwrite($location['suiteFiles'][1]['fileHandler'], $line);
						}
					}
				}
			}
			
			// close all the suitfiles
			for ($s = 0; $s < count($this->servers); $s++) {
				$location = $locations[$s];
				for ($f = 0; $f < count($location['suiteFiles']); $f++) {
					$suiteFile = $location['suiteFiles'][$f];
					
					fwrite($suiteFile['fileHandler'], '	</tbody></table>');
					fwrite($suiteFile['fileHandler'], '</body></html>');
					
					fclose($suiteFile['fileHandler']);
				}
			}
			
			print "\n";
			
		}
		
		function buildConfigPaths($sequence, $namebase){
			
			$this->pathConf = $this->processSequence($sequence, array(
				array(
					'name' => '',
					'blocks' => array(),
					'config' => array('locale' => 'en') // default locale config for english
				)
			));
			
		}
		
		/**
		 *
		 *@param array $fork - the splitted possibilitys
		 */
		function processFork($fork, $pathConfig){
			
			$returnConf = array();
			
			/*
			
			d('BEFORE');
			d($pathConfig);
			d($fork);
			
			*/
			
			// fork for every path config in every fork
			for ($k = 0 ; $k < count($pathConfig); $k++) {
				$subPathConfig = array();
				$subPathConfig[] = $pathConfig[$k];
				
				$add = false;
				for ($i = 0; $i < count($fork); $i++){
					$seq = $fork[$i];
					$forkConf = $subPathConfig;
					
					// check the conditions
					if(! array_key_exists('condition', $seq) || $this->conditionsFullfilled($forkConf[0]['config'], $seq['condition'])) {
						
						$name = (array_key_exists('name', $seq) && $this->cleanName($seq['name'])) ? $seq['name'] : '';
						$forkConf[0]['name'] = $forkConf[0]['name'] . ($name ? '_' . $this->cleanName($name) :  $name);
						// dive into the fork
						if (array_key_exists('sequence', $seq)) {
							$subConf = $this->processSequence($seq['sequence'], $forkConf, $name);
							
							if ($subConf === false) {
								$subConf = array();
							}
							
						} else {
							d($seq);
							throw new Exception('Sequence not found in fork node. Check your Syntax in the node printed above.');
						}
						
						for ($v = 0; $v < count($subConf); $v++) {
							$returnConf[] = $subConf[$v];
							$add = true;
						}
					} 
				}
				
				if (!$add){
					// make sure, at least one path is transmitted per path
					$returnConf[] = $pathConfig[$k];
				}
			}
			
			/*
			d('AFTER');
			d($returnConf);
			
			global $io;
			$io->readStdInn();
			*/
			return $returnConf;
		}
		
		// function, that is putting together the 
		function processSequence($sequence, $pathConfig, $blockName = '') {
			global $io;
			// create the new block
			
			$currentblockId = count($this->blocks);
			$this->blocks[] = array();
			
			$blockN = $blockName;
			
			if (count($sequence)) { // check first, if the sequenc is full, to avoid the add of an empty block
			
				$pathConfig[0]['blocks'][] = $currentblockId;
				
				// walk through all the lines
				$i = 0;
				while ($i < count($sequence)) {
					
					$line = $sequence[$i];
					
					// check if we have a normal command line or a fork
					if (array_key_exists('command', $line)) {
						
						// check if we have a function call or a direct command - make new blocks for the whole sequence
						if (substr(trim($line['command']), 0, 2) == '${') {
							
							if (count($pathConfig) > 1) {
								// we have a config after a split. Because of the dynamic nature of the function line we have to evaluate every branch sepperately
								
								$newSeq = array();
								$newSeq[] = $line; // slice the command as sepperate sequence
								
								for ($b = 0; $b < count($pathConfig); $b++) {
									$newPathConfig = array();
									$newPathConfig[] = $pathConfig[$b];
									
									$newPathConfig = $this->processSequence($newSeq, $newPathConfig);
									array_splice($pathConfig, $b, 1, $newPathConfig); // replacing the old element with all elements of the returned config
								}
								
								// we are back, now we continue with a new block
								$currentblockId = count($this->blocks);
								$this->blocks[] = array();
								
								for ($k = 0; $k < count($pathConfig); $k++) {
									$pathConfig[$k]['blocks'][] = $currentblockId;
								}	
								
							} else {
							
								$line['command'] = str_replace('$conf[', '$pathConfig[0]["config"][', $line['command']);
								
								try {
									$evalcode = '$container = ' . substr(trim($line['command']), 2, -1) . ';';
									@eval($evalcode);
								} catch (Exception $e) {
									$io->error($e->getMessage());
									$io->fatal('Probably wrong syntax of line: ' . $evalcode, 'ConfigurationProcessor');
								}
								
								if ($container) {
									array_splice($sequence, $i + 1, 0, $container);
								} else {
									// this is a invalid branch, because of missing data
									$io->error("Missing Data", $line['command']);
									d($pathConfig[0]["config"]);
									return false;
								}
							}
						} else {
						
							// translate target and value
							$line['target'] = $this->replaceTranslations($line['target'], $pathConfig[0]['config']['locale']);
							$line['value'] = $this->replaceTranslations($line['value'], $pathConfig[0]['config']['locale']);
							
							// add conditions to this branch
							if (array_key_exists('setConditions', $line)) {
								
								foreach ($line['setConditions'] as $key => $conditionValue) {
									for ($k = 0; $k < count($pathConfig); $k++) {
										$pathConfig[$k]['config'][$key] = $conditionValue;
									}
								}
								
								// add a comment
								$line['comment'] = _d($pathConfig[0]['config']);
								
							}
							
							if ($blockN) {
								if (array_key_exists('comment', $line)) {
									$line['comment'] = $blockN . ': ' . $line['comment'];
								} else {
									$line['comment'] = $blockN . ': ';
								}
								// remove the blockname
								$blockN = '';
							}
							
							// add line to current command block
							$this->blocks[$currentblockId][] = $line;	
						}
					} else {
						// this is a fork
						$pathConfig = $this->processFork($line, $pathConfig);
						
						// now we create a new Block - this is the block after we are back from the fork
						// attention! If we are moving to a dynamic function, we have to split again!
						$currentblockId = count($this->blocks);
						$this->blocks[] = array();
						
						for ($k = 0; $k < count($pathConfig); $k++) {
							$pathConfig[$k]['blocks'][] = $currentblockId;
						}	
					}
					
					// next line
					$i++;
				}
			}
			
			return $pathConfig;
			
		}
		
		function conditionsFullfilled($conf = array(), $forkConfig = 'true') {
			global $io;
			
			$forkEscaped = str_replace(array('unlink', '{'. '}', 'include', 'require'), '', $forkConfig);
			
			if ($forkEscaped != $forkConfig) {
				global $io;
				$io->fatal('Possible maliciouse code: ' . $forkConfig, 'ConfigurationProcessor');
			}
			
			// Warning! this one is quite a security risk. Quite some code can be inserted here, i tryed to ovoid this be omitting some functional signs.
			$evalcode = '$fullfilled = (' . $forkEscaped . ');';
			try {
				@eval($evalcode);
			} catch (Exception $e) {
				$io->error($e->getMessage());
				$io->fatal('Probably wrong syntax of condition: ' . $evalcode, 'ConfigurationProcessor');
			}
			
			if (!isset($fullfilled)) {
				d($conf);
				$io->fatal('Wrong syntax: ' . $evalcode, 'ConfigurationProcessor');
			}
			
			return $fullfilled;
			
		}
		
		function setTranslationMap($translationMap) {
			$this->translationMap = $translationMap;
		}
		
		function translate($key, $locale) {
			global $io;
			
			if (array_key_exists($key, $this->translationMap)) {
				if (array_key_exists($locale, $this->translationMap[$key])) {
					return $this->translationMap[$key][$locale];
				}
				
				foreach ($this->translationMap[$key] as $loc => $value) {
					$io->error('Locale ' . $locale . ' not found in translationMap for key ' .$key .'. Fallback to locale ' . $loc . ': "' . $value. '"' , 'translate()');
					return $value;
				}
			}
			$io->error($key . ' not found in translationMap.');
			return ':' . $key . ':';
		}
		
		
		function replaceTranslations($string, $locale) {
			
			preg_match_all('/\${[ ]*?_t\([ ]*?["\'][ ]*?(?P<key>.*?)[ ]*?["\'][ ]*?\)}/', $string, $matches, PREG_PATTERN_ORDER);
						
			for ($i = 0; $i < count($matches['key']); $i++){
				
				$key = $matches['key'][$i];
				
				$string = str_replace($matches[0][$i], $this->translate($key, $locale), $string);
			}
			
			return $string;
		}
		
		
		function cleanName($name) {
			return str_replace(' ', '', $name);
		}
		
	}

?>