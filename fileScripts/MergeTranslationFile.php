#!/usr/local/bin/php -q
<?php
	
	date_default_timezone_set("Europe/Berlin");
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/CmdIO.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Filehandler/staticFunctions.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/ComandLineTools/CmdParameterReader.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Filehandler/ResourceFileHandler.php');

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
					'openCMS' => array('name' => 'ocms')
				),
				
				'description' => 'Defines the software environment of your project.'
			),
			
			'f' => array(
				'name' => 'fileToMerge',
				'datatype' => 'String',
				'description' => 'The csv file to merge or to export, if the export parameter is given.',
				'required' => true
			),
			
			'x' => array(
				'name' => 'export',
				'datatype' => 'String',
				'description' => 'add the properties file you want to export as csv',
				'required' => false
			),
			
			'root' => array(
				'name' => 'r',
				'datatype' => 'String',
				
				'description' => 'If the Root Folder of the Project differ from the environments default, you can add this here.'
			),
		),
		'A script to parse a IT project and incect new i18n keys into the appropriate resource files. The cells of the csv have to be seperated with , and you need the hadlines in the file: default, fr, es, de for example.'
	);
	
	$fileToTranslate = $params->getFileName();
	
	if(!$fileToTranslate){
		$params->print_usage();
	} else {
		
		switch ($params->getVal('e')){
			default:
				$io->fatal('The environment ' . $params->getVal('e') . ' is not implemented.' );
				break;
			case 'demandware':
				// config
				$rootfolder = ($params->getVal('root')) ? $params->getVal('root') : 'cartridges';
				break;
			case 'openCMS':
				$rootfolder = ($params->getVal('root')) ? $params->getVal('root') : 'modules';
				break;
		}
		
		/** ----------------------------------------------------------------
		 * initialise the resource file handler
		 */
		$resourceFileHandler = new ResourceFileHandler('both', $params->getVal('e'));
		
		// now, cut the path until the root folder, getcwd() to get the full path of the file
		$fullPath = getcwd() . '/' . $fileToTranslate;
		
		preg_match('/.*?\/' . $rootfolder . '/', $fullPath, $matches);
		
		if (count($matches) == 0) {
			$io->fatal("No rootfolder '" . $rootfolder . "' found. Has your project the standard " . $params->getVal('e') . " filestructure? If not, try to add the project rootfolder name with the -root parameter.", 'AddTranslation');
		} else {
			$appRootPath = $matches[0];
		}
		
		// parse all the keyfiles and build up the translation map and localisationFiles
		recurseIntoFolderContent($appRootPath, $appRootPath, function($filepath, $baseDirectory){
			global $resourceFileHandler;
			$resourceFileHandler->addResourceFile($filepath, $baseDirectory);
		}, true);
		
		
		// read the projects configuration file, if existend
		$configFileName = $appRootPath . '/.translation.config';
		$config = array();
		if (file_exists($configFileName)){
			$io->out("\n> found a config file " . $configFileName);
			$config = readConfig($configFileName);
		} else {
			$io->error("No Config file found in " . $configFileName);
		}
		
		if (array_key_exists('prefered_propertie_locations', $config)) {
			$resourceFileHandler->setPreferedPropertieLocations($config['prefered_propertie_locations']);
		}
		
		if ($params->getVal('x')) {
			
			$namespace = $params->getVal('x');
			
			if (array_key_exists($namespace, $resourceFileHandler->localisationMap)) {
				$io->out("> exporting namespace $namespace to file " . $params->getVal('f'));
				$mergefile = fopen($params->getVal('f'), 'w');
				$locals = $resourceFileHandler->getPreferedLocalisationMap($namespace);
				
				// write the header
				$header = array('keys');
				foreach ($locals as $locale => $stats) {
					$header[] = $locale;
				}
				fputcsv($mergefile, $header);
				
				// write the file
				
				foreach ($locals['default']['keys'] as $key => $translation) {
					$line = array($key);
					foreach ($locals as $locale => $stats) {
						switch($params->getVal('e')) {
							default:
								$line[] = array_key_exists($key, $locals[$locale]['keys']) ? $locals[$locale]['keys'][$key] : '';
								break;
							case 'openCMS':
								$line[] = array_key_exists($key, $locals[$locale]['keys']) ? unicode_conv($locals[$locale]['keys'][$key]) : '';
								break;
						}
					}
					fputcsv($mergefile, $line);
				}
				
				fclose($mergefile);
			} else {
				$io->fatal("The namespace $namespace was not found among the paresed resource files.");
			}
			
		} else {
		
			$mergeKeys = array();
			$indexes = array();
			
			$io->out('> Opening the new translation csv file ' . $params->getVal('f'));
			// read the mergefile
			$mergefile = fopen($params->getVal('f'), 'r');
			
			$parts = fgetcsv($mergefile);
			
			if (count($parts) < 2) {
				$io->fatal('Is your file valid? The cells have to be seperated with , and you need the hadlines in the file: default, fr, es, de for example.');
				d($parts);
			} 
			
			// build the keymap
			for ($i = 1; $i < count($parts); $i++) {
				$mergeKeys[trim($parts[$i])] = array('keys' => array());
				$indexes[$i] = trim($parts[$i]);
			}
			
			$lineNumber = 1;
			while ($parts = fgetcsv($mergefile)) {
				$lineNumber++;
				$key = trim($parts[0]);
				for ($i = 1; $i < count($parts); $i++) {
					/* // old file syntax
					$keyVal = explode('=', $parts[$i], 2);
					
					if (trim($keyVal[0]) && count($keyVal) > 1) {
						$mergeKeys[$indexes[$i]]['keys'][trim($keyVal[0])] = trim($keyVal[1]);
					} else {
						$io->error($parts[$i], 'line ' . $lineNumber . ' of the merge file');
					}
					
					*/
					$value = trim($parts[$i]);
					
					if ($key && $value) {
						$mergeKeys[$indexes[$i]]['keys'][$key] = trim($value);
					} else {
						$io->error($line, 'line ' . $lineNumber . ' of the merge file');
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
