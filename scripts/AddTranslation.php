#!/usr/local/bin/php -q
<?php
	date_default_timezone_set("Europe/Berlin");
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/CmdIO.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Filehandler/Fileparser.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Filehandler/FileTranslator.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Filehandler/ResourceFileHandler.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Filehandler/staticFunctions.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/ComandLineTools/CmdParameterReader.php');
	
	
	$params = new CmdParameterReader(
		$argv,
		array(
			'm' => array(
				'name' => 'mode',
				'datatype' => 'Enum',
				'default' => 'find',
				'values' => array(
					'find' => array('description' => 'Searches the templates for untranslated strings and puts a resource bundle instead.'),
					'keys' => array('description' => 'Searches for resource keys, and will ask you for a translation of every key.'),
					'new_only' => array('name' => 'new', 'description' => 'Searches for resource keys, and checks, if they are translated or not.'),
					'project_optimize' => array('name' => 'optimize', 'description' => 'Will parse the whole project structure and search for untranslated strings, missing resource keys and css optimization posibilitys. To use this feature, you will have to create a .translation.config file in your project root folder.')
				),
				
				'description' => 'Use this parameter to define the parse mode for your project.'
			),
			'e' => array(
				'name' => 'environment',
				'datatype' => 'Enum',
				'default' => 'demandware',
				'values' => array(
					'demandware' => array('name' => 'dw'),
					'grails' => array('name' => 'g'),
				),
				
				'description' => 'Defines the software environment of your project.'
			),
			
			'root' => array(
				'name' => 'r',
				'datatype' => 'String',
				
				'description' => 'If the Root Folder of the Project differ from the environments default, you can add this here.'
			),
		),
		'A script to parse a jsp file and provide an easy possibility to add the translations.'
	);
	
	$fileToTranslate = $params->getFileName();
	
	if(!$fileToTranslate){
		$params->print_usage();
	} else {
		
		/** --------------------------------------------------------------
		 * Here the structure how the parser Initialisation is build up
		 *
		 * array(
		 *		"parsingRegex" => array( , , ),					Array of Regular expressions for finding translateble parts in the file
		 *		"forcedSingleNodes" => array(, , , ),			Nodes which will be handled as single nodes, no matter a / was found or not
		 *		"textNodes" => array( , , ), 					Nodes which content will be inserted as text. No further parsing is going on
		 *		"ignoreAttributeNodes" => array( , , ),  		Nodes where never an attribute will be translated
		 *		"commentClosingNodes" => array(, , ,),
		 *		"allowedNodeInNodes" => array(,, ,),
		 *		"translatebleAttributesList" => array(,,,), 	list of ranslatable attributes
		 *		"translatebleInputTypeList" => array(,,,), 		list of inputs where the value attribute is also taken for translation
		 * )
		 ** --------------------------------------------------------------- */
		
		$htmlSingleNodes = array('br', 'input', 'link', 'meta', 'img', 'hr');
		$htmlTextNodes = array('script', 'style', '!--', '!DOCTYPE', '?xml');
		$htmlInlineNodes = array('b', 'u', 'i', 'a', 'strong', 'emph');
		$htmlIgnoreAttributeNodes = array('!--', 'script', '!DOCTYPE', '?xml');
		$htmlCommentClosingNodes = array('!--' => '-->', '!DOCTYPE' => '>', '?xml' => '?>'); // mapping of tag name to full closing String
		
		$htmlTranslatebleAttributesList = array('title', 'alt', 'value'); // html attributes, which will be checked for translation
		$htmlTranslatebleInputTypeList = array('button', 'submit'); // types of inputs, which are translated anyway
		
		switch ($params->getVal('e')){
			default:
				$io->fatal('The environment ' . $params->getVal('e') . ' is not implemented.' );
				break;
			case 'grails':
				
				$rootfolder = ($params->getVal('root')) ? $params->getVal('root') : 'grails-app';
				
				$textNodes = array_merge($htmlTextNodes, array('%--'));
				$inlineNodes = array_merge($htmlInlineNodes, array());
				$forcedSingleNodes = array_merge($htmlSingleNodes, array('g:message'));
				
				$forcedOpenNodes = array();
				
				$logicalForkNodes = array();
				
				$ignoreAttributeNodes = array_merge($htmlIgnoreAttributeNodes);
				
				$commentClosingNodes = array_merge($htmlCommentClosingNodes, array('%--' => '--%>'));
				
				$translatebleAttributesList = array_merge($htmlTranslatebleAttributesList, array());
				
				
				$parserRegexArray = array();
				$parserRegexArray[Fileparser::$EXTRACT_MODES['KEYS']] = array('/<g:message.*?code[ ]*?=[ ]*?["\'](?P<key>.*?)["\'].*?>/', '/\${[ ]*?message[ ]*?\([ ]*?code[ ]*?:[ ]*?["\'](?P<key>.*?)["\'][ ]*?\)[ ]*?}/');
				$parserRegexArray[Fileparser::$EXTRACT_MODES['VALUES']] = array();
				
				break;
			case 'demandware':
				// add the demandware specific classes
				require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Filehandler/demandware/DemandwareFileTranslator.php');
				
				// config
				$rootfolder = ($params->getVal('root')) ? $params->getVal('root') : 'cartridges';
				
				$forcedSingleNodes = array_merge(
					$htmlSingleNodes,
					// isml single nodes
					array('iselse' , 'isaddressform', 'isprint', 'isinclude', 'iscontentasset' , 'isinputfield', 'isbminputfield', 'ispaymentline', 'iselseif', 'isset', 'isbreak', 'isreplace', 'islineitemprice', 'isstatus', 'iscontent', 'isbreadcrumb', 'ismodule', 'isslot')
				);
				
				$forcedOpenNodes = array('isif', 'isdecorate');
				
				$logicalForkNodes = array('iselseif', 'isif');
				
				$textNodes = array_merge($htmlTextNodes, array('iscomment', '!---', 'isscript'));
				$inlineNodes = array_merge($htmlInlineNodes, array('isprint'));
				$commentClosingNodes = array_merge($htmlCommentClosingNodes, array('!---' => '--->', '?demandware-pipeline' => '?>'));
				
				$translatebleAttributesList = array_merge($htmlTranslatebleAttributesList, array('bctext1', 'bctext2', 'bctext3', 'p_totallabel'));
				
				$allowedNodeInNodes = array('isif', 'iselse', 'iselseif', 'iscomment', 'isprint');
				$noIndentNodes = array('iselse', 'iselseif');
				
				$ignoreAttributeNodes = array_merge($htmlIgnoreAttributeNodes, array('iscomment', '!---', 'isscript', 'isloop', 'isif', '?demandware-pipeline'));
				
				$parserRegexArray = array();
				$parserRegexArray[Fileparser::$SEARCH_MODES['KEYS']] = array(
					'/[rR]esource(\.msg[f]?|Msgf)[ ]*?\([ ]*?["\'][ ]*?(?P<key>[a-zA-Z0-9.-_]*?)[ ]*?["\'][ ]*?,[ ]*?["\'][ ]*?(?P<namespace>.*?)[ ]*?["\']/',
					'/new.*?Status[ ]*?\(.*?,[ ]*?["\'][ ]*?(?P<key>[a-zA-Z0-9.-_]*?)[ ]*?["\'][ ]*?\)/'
				); 
				$parserRegexArray[Fileparser::$SEARCH_MODES['KEYS_WITH_VARIABLES']] = array(
					'/[rR]esource(\.msg[f]?|Msgf)[ ]*?\([ ]*?["\'][ ]*?(?P<key>[a-zA-Z0-9.-_]*?)[ ]*?["\'][ ]*?\+.*?,[ ]*?["\'][ ]*?(?P<namespace>.*?)[ ]*?["\']/',
					'/new.*?Status[ ]*?\(.*?,[ ]*?["\'][ ]*?(?P<key>[a-zA-Z0-9.-_]*?)[ ]*?["\'][ ]*?\+.*?\)/'
				);
				$parserRegexArray[Fileparser::$SEARCH_MODES['VALUES']] = array('/(?P<before>.*?)\${(?P<var>.*?)}(?P<after>[^$]*)/'); // called by preg_match_all 
				$parserRegexArray[Fileparser::$SEARCH_MODES['SCRIPTS']] = array('/importScript[ ]*?\([ ]*?["\'][ ]*?(?P<path>.*?)[ ]*?["\'][ ]*?\)/');
				
		}
		
		/** ----------------------------------------------------------------
		 * initialise the resource file handler
		 */
		$resourceFileHandler = new ResourceFileHandler($params->getVal('m'), $params->getVal('e'));
		
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
		
		/** ----------------------------------------------------------------
		 * parse the file(s)
		 */
		
		$structureInit = array(
			"parsingRegex" => $parserRegexArray,
			'logicalForkNodes' => $logicalForkNodes,
			"resourceFileHandler" => $resourceFileHandler,
			"forcedSingleNodes" => $forcedSingleNodes,
			'forcedOpenNodes' => $forcedOpenNodes,
			"textNodes" => $textNodes,
			'inlineNodes' => $inlineNodes,
			"noIndentNodes" => $noIndentNodes,
			"ignoreAttributeNodes" => $ignoreAttributeNodes,
			"commentClosingNodes" => $commentClosingNodes,
			"allowedNodeInNodes" => $allowedNodeInNodes,
			"translatebleAttributesList" => $translatebleAttributesList,
			"translatebleInputTypeList" => $htmlTranslatebleInputTypeList
		);
		
		switch ($params->getVal('e')){
			default:
				$io->fatal('The environment ' . $params->getVal('e') . ' is not implemented.' );
				$translator = new FileTranslator($appRootPath, $fileToTranslate, $structureInit, $params->getVal('m'), $params->getVal('e'));
				break;
			case 'demandware':
				$translator = new DemandwareFileTranslator($appRootPath, $fileToTranslate, $structureInit, $params->getVal('m'), $params->getVal('e'));
				break;
		}
		
		die('end for now'. "\n");
		
		// parse the textfile and get the keys
		$codesource = new Fileparser($fileToTranslate, $structureInit, $params->getVal('m'), $params->getVal('e'));
		
		// $codesource->print_results();
		
		/** ----------------------------------------------------------------
		 * Start the translation
		 */
		
		$io->cmd_print("Start of Translation:", true, 1);
		
		switch ($params->getVal('m')){
			default:
				foreach ($codesource->fileMap as $key => $stats) {
					
					switch ($params->getVal('m')){
						case 'normal':	
							$io->cmd_print('');
							$io->cmd_print(" [TRANSLATE] $key in line(s) ".implode(", ", $stats['lines'])." (Press enter to keep the old value)", true, 2);
							break;
						case 'new_only':
							$io->cmd_print(" [TRANSLATE] $key in line(s) ".implode(", ", $stats['lines']));
							break;
					}
					
					// get the default translation
					$old = $codesource->getTranslationKey($stats['assumed_namespace'], $key);
					
					switch ($old['status']){
						default:
							$locals = $codesource->getLocals($old['namespace']);
							for( $i = 0; $i < count($locals); $i++) {
								$local = $locals[$i];
								$value = $codesource->getTranslationKey($old['namespace'], $key, $local);
								
								switch ($value['status']){
									case 1:
										$v = '"'.$value['value'].'"';
										break;
									case -1:
										$v = "Delete key in line(s) ".implode(", ", $stats['lines']).".";
										//
										break;
									case 0:
										$v = "[Translation not defined jet]";
										break;
									
								}
								
								$spacer = ' ';
								for ($k = 0; $k < 7 - strlen($local); $k++){
									$spacer .= ' ';
								}
								
								// switch the readMode
								switch ($params->getVal('m')){
									default:
										$readLine = true;
										break;
									case 'new_only':
										$readLine = ($value['status'] == 0 || $value['value'] == '') ? true : false;
										break;
								}
								
								// read only, if activated
								if ($readLine) {
									$line = $io->readStdInn("   >".$spacer."[$local] ".$v);
									if ($line != '') {
										$codesource->changeTranslatedLine($old['namespace'], $key, $line, $local);
									}
								}
							}
							break;
						case -1:
							// $io->readStdInn('Press Enter to continue');
							break;
						
					}
					
				}
				break;
			case 'find':
				
				$filename = explode('/', $fileToTranslate);
				$filename = explode('.', $filename[count($filename) - 1]);
				$filename = $filename[0];
				$defaultFile = false;
				
				// which file?
				if ($defaultFile === false){
					
					$rStr = '';
					foreach ($codesource->resourceFileHandler->localisationFiles as $int => $path){
						if ($rStr != '' && $rStr != $path['rootfolder']) $io->out();
						$io->cmd_print("  [$int] - ".$path['rootfolder'] . ': ' . $path['filename']);
						$rStr = $path['rootfolder'];
					}
					
					$io->out();
					
					$index = intval($io->readStdInn("Where should the translations be added?"));
					$defaultFile = $codesource->resourceFileHandler->localisationFiles[$index];
					
					// import the selected file
					$codesource->resourceFileHandler->importResourceFile($defaultFile['namespace'], $defaultFile['rootfolder']);
					$io->cmd_print("");
				}
				$keyBase = $defaultFile['namespace'].'.'.$filename.'.';
				
				$somethingToTranslate = false;
				
				foreach ($codesource->fileValueMap as $value => $stats) {
					if ($stats['translate']) {
						
						$io->cmd_print("\n[TRANSLATE] ".$stats['value']." in line(s) ".implode(", ", $stats['lines']));
						
						$val = str_replace(array(':', '.', '-', '&', ';', "'" , '>', '<' , '*'), '', $stats['value']);
						$keyParts = explode(' ',$val);
						$suggestedKey = (count($keyParts) > 1)? $keyParts[0].$keyParts[1] : $val;
						
						// try to get an existing translation
						$key = $codesource->getTranslationKeyForValue($stats['value']);
						if ($key === false){
							
							$addkey = completeKey($keyBase, $suggestedKey);
							
							$ns = $defaultFile['namespace'];
							$rootfolder = $defaultFile['rootfolder'];
							
						} else {
							
							$addkey = '';
							
							for ($i = 0; $i < count($key); $i++){
								if ($key[$i]['namespace'] == $defaultFile['namespace']) {
									$addkey = $key[$i]['key'];
									$keyindex = $i;
									$ns = $defaultFile['namespace'];
									$rootfolder = $defaultFile['rootfolder'];
									
									$io->cmd_print("   > Taking ".$key[$i]['key'].' from '.str_replace($appRootPath,'.', $key[$i]['path']));
									break;
								}
							}
							
							if (! $addkey) {
							
								if (count($key) > 1){
									for ($i = 0; $i < count($key); $i++){
										$io->cmd_print("  [$i] - ".$key[$i]['key'].' - '.str_replace($appRootPath,'.', $key[$i]['path']));
									}
									
									$keyindex = $io->readStdInn("Take one of those or enter 'n' to add a new key");
									
								} else {
									$keyindex = 0;
									$io->cmd_print('   > found key '.$key[$keyindex]['key'].' in file: '.str_replace($appRootPath,'.', $key[$keyindex]['path']));	
									$input = $io->readStdInn("   > Would you like to choose this key (Y/n)?");
									$keyindex = ($input == 'n' || $input == 'N') ? 'n' : 0;
									
								}
								
								if ($keyindex === 'n' || $keyindex === 'N') {
									$addkey = completeKey($keyBase, $suggestedKey);
									$ns = $defaultFile['namespace'];
									$rootfolder = $defaultFile['rootfolder'];
								} else {
									$addkey = $key[$keyindex]['key'];
									$ns = $key[$keyindex]['namespace'];
									$rootfolder = $key[$keyindex]['rootfolder'];
									
									$codesource->resourceFileHandler->importResourceFile($key[$keyindex]['namespace'], $rootfolder);
								}
							}
						}
						
						// could be, that the user desided to ignore the key
						
						if ($addkey) {
							$codesource->changeTranslatedLine($ns, $addkey, $stats['value'], $rootfolder);
							$codesource->addValueKey($value, $addkey, $ns);
							$somethingToTranslate = true;
						} else {
							// do not translate this key
							$codesource->setValueKeyTranslationStatus($value, false);
						}
					}
				}
				
				break;
		}
		
		$io->cmd_print("\n");

		$codesource->printChangedResourceFiles(true);
		if ($somethingToTranslate && $io->readStdInn("Would you like to print the file now (y/N)?") == 'y') { 
		
			// print the resource files
			$codesource->printChangedResourceFiles();
		} else {
			$io->cmd_print("\n\nNo need to print something.");
		}
		
		// do something	
		$io->cmd_print("\n");
	
	}
	
	

?>