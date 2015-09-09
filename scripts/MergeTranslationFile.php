#!/usr/local/bin/php -q
<?php
	date_default_timezone_set("Europe/Berlin");
	
	$pathToPHPShellHelpers = str_replace('//','/',dirname(__FILE__).'/') .'../../PHP-Shell-Helpers/';
	
	require_once($pathToPHPShellHelpers . 'CmdIO.php');
	require_once($pathToPHPShellHelpers . 'Filehandler/staticFunctions.php');
	require_once($pathToPHPShellHelpers . 'ComandLineTools/CmdParameterReader.php');
	require_once($pathToPHPShellHelpers . 'Filehandler/ResourceFileHandler.php');

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
					'zend' => array('name' => 'z')
				),
				
				'description' => 'Defines the software environment of your project.'
			),
			
			'f' => array(
				'name' => 'fileToMerge',
				'datatype' => 'String',
				'description' => 'The csv file to merge or to export, if the export parameter is given. If export all is given, there should be a export folder be defined at this place',
				'required' => true
			),
			
			'x' => array(
				'name' => 'export',
				'datatype' => 'String',
				'description' => 'add the properties file you want to export as csv',
				'required' => false
			),
			
			'xa' => array(
				'name' => 'exportAll',
				'datatype' => 'Boolean',
				'description' => 'export all the propertie files as <resource namespace>.csv',
				'required' => false
			),
			
			'root' => array(
				'name' => 'r',
				'datatype' => 'String',
				
				'description' => 'If the Root Folder of the Project differ from the environments default, you can add this here.'
			),
		),
		'A script to parse a IT project and inject new i18n keys into the appropriate resource files. The cells of the csv have to be separated with , and you need the headlines in the file: default, fr, es, de for example.'
	);
	
	$fileToTranslate = $params->getFileName();
	
	if(!$fileToTranslate){
		$params->print_usage();
	} else {
		
		$resourceFileSheme = "Java";
		
		switch ($params->getVal('e')){
			default:
				$io->fatal('The environment ' . $params->getVal('e') . ' is not implemented.' );
				break;
			case 'demandware':
				// config
				$rootfolder = ($params->getVal('root')) ? $params->getVal('root') : 'cartridges';
				break;
			case 'openCMS':
				$rootfolder = ($params->getVal('root')) ? $params->getVal('root') : 'modules'; // for now we just support the merge of one module
				break;
			case 'zend':
				$resourceFileSheme = "zend";
				$rootfolder = ($params->getVal('root')) ? $params->getVal('root') : 'application'; // for now we just support the merge of one module
				break;
		}
		
		/** ----------------------------------------------------------------
		 * initialise the resource file handler
		 */
		switch ($resourceFileSheme){
			default:
				$class = 'ResourceFileHandler';
				break;
			case 'zend':
				$class = capitalise($resourceFileSheme) .'ResourceFileHandler';
				// dynamically including the Zend parser class
				require_once($pathToPHPShellHelpers . 'Filehandler/' . $resourceFileSheme . '/' . $class . '.php');
				break;
		}
		
		$resourceFileHandler = new $class('both', $params->getVal('e'));
		
		// now, cut the path until the root folder, getcwd() to get the full path of the file
		$fullPath = getcwd() . '/' . $fileToTranslate;
		
		preg_match('/.*?\/' . $rootfolder . '/', $fullPath, $matches);
		
		if (count($matches) == 0) {
			$io->fatal("No rootfolder '" . $rootfolder . "' found. Has your project the standard " . $params->getVal('e') . " filestructure? If not, try to add the project rootfolder name with the -root parameter. We tested against $fullPath", 'AddTranslation');
		} else {
			$appRootPath = $matches[0];
		}
		
		// read the projects configuration file, if existend
		$configFileName = $appRootPath . '/.translation.config';
		$config = array();
		if (file_exists($configFileName)){
			$io->out("\n> Using config file " . $configFileName);
			$config = readConfig($configFileName);
		} else {
			$io->error("No config file found at " . $configFileName);
		}
		
		// parse all the keyfiles and build up the translation map and localisationFiles
		recurseIntoFolderContent($appRootPath, $appRootPath, function($filepath, $baseDirectory){
			global $resourceFileHandler, $config, $appRootPath;
			
			$parts = explode('/', str_replace($appRootPath, '', $filepath));
			
			if (! array_key_exists('cartridgepath', $config) || (count($parts > 2) && in_array($parts[1], $config['cartridgepath']))) {
				$resourceFileHandler->addResourceFile($filepath, $baseDirectory);
			}
		}, true);
		
		if (array_key_exists('prefered_propertie_locations', $config)) {
			$resourceFileHandler->setPreferedPropertieLocations($config['prefered_propertie_locations']);
		}
		
		$exportAll = $params->getVal('xa');
		
		if ($exportAll || $params->getVal('x')) {
			
			$namespaces = $exportAll ? array_keys($resourceFileHandler->localisationMap) : array($params->getVal('x'));
			
			// set up the global export file
			if ($exportAll){
				$exportDirectory = is_dir($params->getVal('f')) ? $params->getVal('f') : dirname($params->getVal('f'));
				$exportAllFileName = is_dir($params->getVal('f')) ? $exportDirectory . '/ALL-KEYS-EXPORT.csv' : $params->getVal('f');	
			}
			
			$allKeys = array('key' => array());
			
			// go through all the namespaces
			for ($i = 0; $i < count($namespaces); $i++) {
				
				$namespace = $namespaces[$i];
				$fileName = $exportAll ? $exportDirectory . '/' . $namespace . '.csv' : $params->getVal('f');
			
				if (array_key_exists($namespace, $resourceFileHandler->localisationMap)) {
					
					if (array_key_exists('cartridgepath', $config)){
						$cartridgepath = $config['cartridgepath'];
						
					} else {
						// we have no cartridge path, but need one, lets make a proposal
						$cartridgepath = array();
						$cartridgeKeys = $resourceFileHandler->getPreferedLocalisationMap($namespace, $cartridgepath); // default with all cartridges
						foreach ($cartridgeKeys as $cartridge => $stats) {
							$cartridgepath[] = $cartridge;
						}
						
						// ask the user
						$keyAdd = $io->readStdInn("> No cartridge path is defined, would you like to take " . implode(',' , $cartridgepath) . " (enter), or enter the one you would prefer (seperate multiple values with, and without space)");
						if ($keyAdd != '') {
							$cartridgepath = explode(',', $keyAdd);
						}
						
						$config['cartridgepath'] = $cartridgepath;
						
						writeConfig($configFileName, $config);
					}
					
					$cartridgeKeys = $resourceFileHandler->getPreferedLocalisationMap($namespace, $cartridgepath);
					
					// first we get the header
					$keys = array('key' => array());
					for ($c = 0; $c < count($cartridgepath); $c++) {
						if (array_key_exists( $cartridgepath[$c] , $cartridgeKeys)) {
							$locals = $cartridgeKeys[$cartridgepath[$c]];
							foreach ($locals as $locale => $stats) {
								if (! array_key_exists($locale, $keys)) 
									$keys[$locale] = array();
									
								if (! array_key_exists($locale, $allKeys)) {
									// a new key emerged, we set all that happened before to ''
									$allKeys[$locale] = count($allKeys['key']) > 0 ? array_fill(0, count($allKeys['key']), '') : array();
								}
							}
						}
					}
					
					// the we go through all the keys
					for ($c = 0; $c < count($cartridgepath); $c++) {
						if (array_key_exists( $cartridgepath[$c] , $cartridgeKeys)) {
							$locals = $cartridgeKeys[$cartridgepath[$c]];
							
							// write the file
							foreach ($locals['default']['keys'] as $key => $translation) {
							
								// if we have the key already, we move on
								if (in_array($key, $keys)) break;
							
								$keys['key'][] = $key;
								if ($exportAll) $allKeys['key'][] = $key;
								
								// we make sure, that we miss no existing locale defined in the project
								foreach ($allKeys as $locale => $stats) {
									if($locale != 'key') {
										switch($params->getVal('e')) {
											default:
												$value = array_key_exists($locale, $locals) && array_key_exists($key, $locals[$locale]['keys']) ? $locals[$locale]['keys'][$key] : '';
												break;
											case 'openCMS':
												$value = array_key_exists($locale, $locals) && array_key_exists($key, $locals[$locale]['keys']) ? unicode_conv($locals[$locale]['keys'][$key]) : '';
												break;
										}
										
										$keys[$locale][] = $value;
										if ($exportAll) $allKeys[$locale][] = $value;
									}
								}
							}
						}
					}
					
					// write the single file 
					$io->out("> exporting namespace $namespace to file " . $fileName);
					$mergefile = fopen($fileName, 'w');
					
					// first the header
					foreach ($keys as $locale => $stats) {
						$header[] = $locale;
					}
					
					fputcsv($mergefile, $header);
					
					// then the keys
					for ($k = 0; $k < count($keys['key']); $k++){
						$line = array();
						for($h = 0; $h < count($header); $h++){
							$line[] = $keys[$header[$h]][$k];
						} 
						fputcsv($mergefile, $line);
					}

					fclose($mergefile);
					
				} else {
					$io->error("The namespace $namespace was not found among the paresed resource files.");
				}
			}
			
			// writing the global file
			$io->out("> exporting all keys also to combined file " . $exportAllFileName);
			$mergefile = fopen($exportAllFileName, 'w');
			
			// first the header
			$header = array();
			foreach ($allKeys as $locale => $stats) {
				$header[] = $locale;
			}
			fputcsv($mergefile, $header);
			
			// then the keys
			for ($k = 0; $k < count($allKeys['key']); $k++){
				$line = array();
				for($h = 0; $h < count($header); $h++){
					$line[] = $allKeys[$header[$h]][$k];
				} 
				fputcsv($mergefile, $line);
			}

			fclose($mergefile);
			
		} else {
		
			$mergeKeys = array();
			$indexes = array();
			
			$io->out('> Opening the new translation csv file ' . $params->getVal('f'));
			// read the mergefile
			$mergefile = fopen($params->getVal('f'), 'r');
			
			$headers = fgetcsv($mergefile);
			
			if (count($headers) < 2) {
				$io->fatal('Is your file valid? The cells have to be seperated with , and you need the hadlines in the file: default, fr, es, de for example.');
				d($headers);
			} 
			
			// build the keymap
			for ($i = 1; $i < count($headers); $i++) {
				$header = trim($headers[$i]);
				if (substr($header, 0, 1) != '#' ) {
					$mergeKeys[$header] = array('keys' => array());
					$indexes[$i] = $header;
				}
			}
			
			$lineNumber = 1;
			while ($parts = fgetcsv($mergefile)) {
				$lineNumber++;
				$key = trim($parts[0]);
				for ($i = 1; $i < count($parts); $i++) {
					if ( array_key_exists($i, $indexes) && $indexes[$i]){ // only take valid headers
						$value = trim($parts[$i]);
						
						if ($key && $value) {
							$mergeKeys[$indexes[$i]]['keys'][$key] = trim($value);
						} else {
							$io->warn( $key . ' - no value for ' . $indexes[$i], 'line ' . $lineNumber);
						}
					}
				}
				
			}
			
			fclose($mergefile);
			$resourceFileHandler->mergeKeyFileExtract($mergeKeys);
			
			if ($resourceFileHandler->printChangedResourceFiles(true)){	
				if ($io->readStdInn("Would you like to print the updated resource files (y/N)?") == 'y') { 
					// print the resource files
					$resourceFileHandler->printChangedResourceFiles();
				}
			}
		}
	}
	
	function writeConfig($configFile, $config) {
		global $io;
		
		$io->out('> Updating config file ' . $configFile);
		
		$fp = fopen($configFile, 'w');
		
		foreach ($config as $key => $values) {
			fwrite($fp, $key . ':' . implode(',', $values) . "\n");
		}
		
		fclose($fp);
	}
	
	// adds a project config file
	function readConfig($configFile) {
		$fp = fopen($configFile, 'r');
		$config = array();
		
		$ignoredValues = array();
		$mode = 'list';
		
		while ($line = fgets($fp, 2048)) {
			$parts = explode('//', $line, 2); // remove simple comments
			$parts = explode(':', $parts[0], 2);
			$name = trim($parts[0]);
			
			switch ($name) {
				case 'IGNORED_VALUES':
					$mode = 'IGNORED_VALUES';
					break;
			}
			
			if (count($parts) > 1 && trim($parts[1])) {
				switch ($mode){
					default:
						$values = explode(',', $parts[1]);
						for ($i = 0; $i < count($values); $i++) {
							$values[$i] = trim($values[$i]);
						}
						
						$config[$name] = $values;
					
						break;
					case 'IGNORED_VALUES':
						if (! array_key_exists($name, $ignoredValues)) $ignoredValues[$name] = array();
						$ignoredValues[$name][] = trim($parts[1]);
						break;
				}
			}
			
		}
		fclose($fp);
		
		return $config;
	}
	
	
	function unicode_conv($originalString) {
		global $io;
		
		return json_decode('"' . html_entity_decode($originalString) .'"');
	}
	
	

?>
