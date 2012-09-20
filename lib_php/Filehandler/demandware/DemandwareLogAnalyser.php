<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../FileAnalyser.php');


class DemandwareLogAnalyser extends FileAnalyser {
	
	var $cartridgePath = array(); // the cartridgepath in order of inclusion
	
	function __construct($file, $layout, $settings)  {
		parent::__construct($file, 'demandware', $layout, $settings);
	}
	
	function analyse($fileIdent){
		
		$alyStatus = array('timestamp' => $this->settings['timestamp'], 'errorType' => '-', 'enter' => true, 'stacktrace' => '', 'lineNumber' => 1, 'fileIdent' => $fileIdent, 'add' => false);
		
		while ($line = fgets($this->filePointer, 4096)) {
		
		
			switch($this->layout) {
				default:
					throw new Exception('Don\'t know how to handel ' . $this->layout . ' files.');
					break;
				case 'error':
					$alyStatus = $this->analyse_error_line($alyStatus, $line);
					break;
				case 'customwarn':
				case 'customerror':
					$alyStatus = $this->analyse_customerror_line($alyStatus, $line);
					break;
				case 'quota':
					$alyStatus = $this->analyse_quota_line($alyStatus, $line);
					break;
			}
			
			if ($alyStatus['add']) {
				$this->addEntry($alyStatus['timestamp'], $alyStatus['errorType'], $alyStatus['entry'], $alyStatus['entryNumber'], $alyStatus['fileIdent'], $alyStatus['data'], $alyStatus['stacktrace']);
			
				$alyStatus['enter'] = true;
				$alyStatus['stacktrace']  = '';
				$alyStatus['add'] = false;
			}
			
			$alyStatus['lineNumber']++;
			
		}
	}
	
	function analyse_quota_line($alyStatus, $line) {
		if ($alyStatus['enter']) { // every line is a error
			
			$alyStatus['entryNumber'] = $alyStatus['lineNumber'];
			$alyStatus['data'] = array('sites' => array(), 'dates' => array(), 'GMT timestamps' => array(), 'max actual' => array(), 'pipeline' => array());
			$alyStatus['stacktrace'] .= $line;
			$alyStatus['add'] = true;
			
			if (substr($line, 0, 1) == '[' && substr($line, 25, 4) == 'GMT]') {
				$errorLineLayout = 'extended';
				$parts = explode(']', substr($line, 29), 2);
				$alyStatus['data']['dates'][substr($line, 1, 10)] = true;
				$alyStatus['timestamp'] = strtotime(substr($line, 1, 27));
				$alyStatus['data']['GMT timestamps'][substr($line, 11, 9)] = true;
			} else {
				$errorLineLayout = 'core_extract';
				$parts = explode(':', $line, 2);
			}
			
			if (count($parts) > 1) {
				
				$alyStatus['entry'] = trim($parts[1]);
				
				$messageParts = explode('|', trim(substr($parts[0], 2))); // 0 => basic description, 1 => timestamp?, 2 => Site, 3 => Pipeline, 4 => another description? 5 => Cryptic stuff
				
				$description = explode(':', $alyStatus['entry'], 2);
				
				if (count($description) > 1) {
					$errorType = explode(' ', trim($description[0]), 2); // remove the quota or what else
					
					if (count($errorType) > 1) {
						$alyStatus['errorType'] = trim($errorType[1]);
					} else {
						$errorType = explode(':', trim($description[1]), 2);
						$alyStatus['errorType'] = trim($errorType[0]);
						$description[1] = $errorType[1];
					}
				} else {
					d($alyStatus['entry']);
				}
				
				switch($alyStatus['errorType']){
					default:
					
						preg_match('/(, max actual was [0-9]*?),/', $description[1], $matches);
						
						$message = $description[1];
						if (count($matches) > 1) {
							$message = str_replace($matches[1], '', $message);
							$maxExceeds = explode(' ', $matches[1]);
							$alyStatus['data']['max actual'][$maxExceeds[count($maxExceeds) - 1]] = true;
						} 
					
						$alyStatus['entry'] = $alyStatus['errorType'] . ': ' . $message;
						if ($errorLineLayout == 'extended' && count($messageParts) > 2) {
							$alyStatus['data']['sites'][$this->extractSiteID(trim($messageParts[2]))] = true;
						} else {
							$type = $messageParts[0];
							if (startsWith($type, 'MulticastListener')) $type = 'MulticastListener';
							
							$alyStatus['entry'] = $type . ': ' . $alyStatus['entry'];
						}
						break;
				}
				
			} else {
				d($parts);
				d('SOMETHING STRANGE: ' . $line);
			}
			
			
		}
		
		return $alyStatus;
	}
	
	function analyse_customerror_line($alyStatus, $line) {
		
		if ($alyStatus['enter']) { // every line is a error
			
			$alyStatus['entryNumber'] = $alyStatus['lineNumber'];
			$alyStatus['data'] = array('sites' => array(), 'order numbers' => array(), 'dates' => array(), 'GMT timestamps' => array());
			$alyStatus['stacktrace'] .= $line;
			$alyStatus['add'] = true;
			
			if (substr($line, 0, 1) == '[' && substr($line, 25, 4) == 'GMT]') {
				$errorLineLayout = 'extended';
				$parts = explode('== custom', $line, 2);
				
				$parts = (count($parts) > 1) ? $parts : explode(' custom  ', $line); // this is a message comming form Logger.error
				
				$alyStatus['timestamp'] = strtotime(substr($line, 1, 27));
				$alyStatus['data']['dates'][substr($line, 1, 10)] = true;
				$alyStatus['data']['GMT timestamps'][substr($line, 11, 9)] = true;
			} else {
				$errorLineLayout = 'core_extract';
				$parts = explode(':', $line, 2);
			}
			
			if (count($parts) > 1) {
				
				$alyStatus['entry'] = trim($parts[1]);
				$messageParts = explode('|', trim(substr($parts[0], 29))); // 0 => basic description, 1 => timestamp?, 2 => Site, 3 => Pipeline, 4 => another description? 5 => Cryptic stuff
				
				$alyStatus = $this->extractMeaningfullCustomData($alyStatus);
				
				switch($alyStatus['errorType']){
					default:
						$alyStatus['entry'] = $alyStatus['entry'];
						break;
					case 'SendOgoneDeleteAuthorization.ds':
					case 'SendOgoneAuthorization.ds':
					case 'SendOgoneCapture.ds':
					case 'SendOgoneRefund.ds':
						
						$params = explode(' OrderNo:', $alyStatus['entry'], 2);
						
						// d($alyStatus['errorType']);
						// d($params);
						if (count($params) > 1) {
							$alyStatus['entry'] = substr($params[0], 0, -1);
							$params = explode(',', $params[1]);
							
							// d($params);
							
							$alyStatus['data']['order numbers'][trim($params[0])] = true;
							
							for ($i = 1; $i < count($params); $i++) {
								$parts = explode(':', $params[$i],2);
								$alyStatus['data'][trim($parts[0])][trim($parts[1])] = true;
							}
						}
						
						$startStr = 'Capture successfully for Order ';
						if (startsWith($alyStatus['entry'], $startStr)) {
							
							$alyStatus['data']['order numbers'][trim(substr($alyStatus['entry'], strlen($startStr)))] = true;
							$alyStatus['entry'] = trim($startStr);
						}
						
						break;
					case 'soapNews.ds':
					case 'sopaVideos.ds':
						
						$params = explode('; Url: ', $alyStatus['entry'], 2);
						
						if (count($params) > 1) {
							$alyStatus['data']['Urls'][trim($params[1])] = true;
							$alyStatus['entry'] = $params[0];
						}
						
						$params = explode(', SearchPhrase:', $alyStatus['entry'], 2);
						if (count($params) > 1) {
							$alyStatus['data']['SearchPhrases'][trim($params[1])] = true;
							$alyStatus['entry'] = $params[0];
						}
						
						
						break;
				}
				
				if ($errorLineLayout == 'extended') $alyStatus['data']['sites'][$this->extractSiteID(trim($messageParts[2]))] = true;
				
				$errorsWithAdditionalLineToParse = array('Error executing script', 'Script execution timeout');
				
				if (in_array($alyStatus['errorType'], $errorsWithAdditionalLineToParse)) {
					$newLine = trim(fgets($this->filePointer, 4096));
					$alyStatus['stacktrace'] .= $newLine;
					$alyStatus['lineNumber']++;
				}
				
				// get aditional information from the next line
				switch ($alyStatus['errorType']) {
					case 'Error executing script':
						$alyStatus['entry'] .= ' ' . $newLine;
						break;
				}
				
			} else {
				d($parts);
				d('SOMETHING STRANGE: ' . $line);
			}
			
			
		}
		
		return $alyStatus;
	}
	
	function analyse_error_line($alyStatus, $line){
			
		if ($alyStatus['enter']) {  // && substr($line, 0, 1) == '[' && substr($line, 25, 4) == 'GMT]' [2012-05-22 00:11:56.785 GMT]
			// initial error definition
			$alyStatus['enter'] = false;
			$alyStatus['entryNumber'] = $alyStatus['lineNumber'];
			$alyStatus['data'] = array('sites' => array(), 'customers' => array(), 'dates' => array(), 'GMT timestamps' => array(), 'pipelines' => array(), 'urls' => array());
			$alyStatus['stacktrace'] .= $line;
			
			$isExtended = substr($line, 0, 1) == '[' && substr($line, 25, 4) == 'GMT]';
			
			if ($isExtended) {
				$errorLineLayout = 'extended';
				$alyStatus['timestamp'] = strtotime(substr($line, 1, 27));
				$alyStatus['data']['dates'][substr($line, 1, 10)] = true;
				$alyStatus['data']['GMT timestamps'][substr($line, 11, 9)] = true;
				$parts = explode(' "', $line, 2);
			} else {
				$errorLineLayout = 'core_extract';
				$parts = explode(':', $line, 2);
			}
			
			if (count($parts) > 1) {
				
				$alyStatus['entry'] = trim($parts[1]);
				$messageParts = ($isExtended) ? explode('|', trim(substr(str_replace('ERROR', '', $parts[0]), 29))): array(); // 0 => basic description, 1 => timestamp?, 2 => Site, 3 => Pipeline, 4 => another description? 5 => Cryptic stuff
				
				// d($line);
				$alyStatus = $this->extractMeaningfullData($alyStatus);
				
				switch($alyStatus['errorType']){
					default:
						$alyStatus['entry'] = $alyStatus['entry'];
						if ($errorLineLayout == 'extended' && count($messageParts) > 2) $alyStatus['data']['sites'][$this->extractSiteID(trim($messageParts[2]))] = true;
						break;
					case 'TypeError':
					case 'com.demandware.beehive.core.capi.pipeline.PipeletExecutionException':
					case 'Wrapped com.demandware.beehive.core.capi.common.NullArgumentException':
					case 'Exception occurred during request processing':
					case 'ISH-CORE-2351':
					case 'ISH-CORE-2354':
						
						if ($errorLineLayout == 'extended') {
						
							switch ($messageParts[0]) {
								default:
									$pipeline = $messageParts[3];
									$siteID = $this->extractSiteID(trim($messageParts[2]));
									break;
								case 'JobThread':
									
									$partlets = explode(' ', $messageParts[3]);
									$pipeline = trim($partlets[0]);
									$siteID = $this->extractSiteID(trim($partlets[4]));
									
									break;
							}
							
							
							
							$alyStatus['entry'] = $pipeline . ' > ' . $alyStatus['entry'];
							$alyStatus['data']['sites'][$siteID] = true;
						} else {
							$alyStatus['entry'] = $alyStatus['entry'];
						}
						break;
					
					// errors with pipeline, but second line has the real error message
					case 'ISH-CORE-2368':
					case 'ISH-CORE-2355':
						$alyStatus['entry'] = ($errorLineLayout == 'extended') ? $messageParts[3] . ' > ' : '';
						if ($errorLineLayout == 'extended') $alyStatus['data']['sites'][$this->extractSiteID(trim($messageParts[2]))] = true;
						break;
					
					// Job errors
					case 'ISH-CORE-2652':
						
						$infosBefore = explode('[', $alyStatus['entry'], 2);
						$infosAfter = explode(']', $infosBefore[1], 2);
						
						$partlets = explode(':', $infosAfter[1]);
						
						$params = explode(', ', $infosAfter[0]);
						
						$alyStatus['entry'] = $infosBefore[0] . " " . $params[0] . " " . $partlets[0] . " " . $partlets[count($partlets) - 1];
						if (count($params) > 2) $alyStatus['data']['sites'][$this->extractSiteID(trim($params[2]))] = true;
						
						break;
					
					// internal errors
					case 'ISH-CORE-2482':
						break;
					case '[bc_search] error':
						
						$parts = explode("'", $alyStatus['entry'], 3);
						
						if (count($parts) > 2) {
							$alyStatus['entry'] = $parts[0] . ' {- different items -} ' . $parts[2];
						} else {
							d($parts);
						}
						break;
					
				}
				
				$errorsWithAdditionalLineToParse = array('ISH-CORE-2482', 'ISH-CORE-2351', 'ISH-CORE-2354', 'ISH-CORE-2368', 'ISH-CORE-2355', 'com.demandware.beehive.core.capi.pipeline.PipeletExecutionException');
				
				if (in_array($alyStatus['errorType'], $errorsWithAdditionalLineToParse)) {
					$newLine = trim(fgets($this->filePointer, 4096));
					$alyStatus['stacktrace'] .= $newLine;
					$alyStatus['lineNumber']++;
				}
				
				// get aditional information from the next line
				switch ($alyStatus['errorType']) {
					case 'ISH-CORE-2482':
					case 'ISH-CORE-2351':
					case 'ISH-CORE-2368':
					case 'ISH-CORE-2355':
						$alyStatus['entry'] .= ' ' . $newLine;
						break;
					case 'ISH-CORE-2354':
						
						// try to find the real eror
						$lines = 1;
						while (! startsWith($newLine, 'org.mozilla.javascript.EcmaError:') && ! startsWith($newLine, 'com.demandware.beehive.core.capi.pipeline.PipelineExecutionException:') && $lines < 5){
							$newLine = trim(fgets($this->filePointer, 4096));
							$alyStatus['stacktrace'] .= $newLine;
							$alyStatus['lineNumber']++;
							$lines++;
						}
						
						$alyStatus['entry'] .= ' ' . $newLine;
						
						break;
					case 'com.demandware.beehive.core.capi.pipeline.PipeletExecutionException':
						if (endsWith($alyStatus['entry'], 'Script execution stopped with exception:')) {
							$alyStatus['entry'] .= ' ' . $newLine;
						}
						break;
				}
				
			} else {
				
				d($parts);
				d('SOMETHING STRANGE: ' . $line);
			}
		} else if (startsWith($line, '"')) { // a log entry is finished, if we find a " in the first place
			$alyStatus['add'] = true;
		} else {
			$alyStatus['stacktrace'] .= $line;
		}
		
		return $alyStatus;
	}
	
	function getErrorType_1($entry){
		$errorType = explode(' ', $entry, 3);
		array_shift($errorType);
		return $errorType;
	}
	function getErrorType_2($entry){ return array($entry); }
	
	function extractMeaningfullData($alyStatus){
		
		
		$exceptionStarts = array(
			array(
				'starts' => array('Wrapped '),
				'errorType' => 'getErrorType_1'
			),
			array(
				'starts' => array('No start node specified for pipeline', 'Customer password could not be updated.', 'Java constructor for'),
				'errorType' => 'getErrorType_2'
			)
		);
		
		$errorExceptions = array(
			array(
				  'start' => 'Unable to parse SEO url - no match found - {'
				, 'type' => 'SEO url parse error'
				, 'weight'	=> 1
				, 'solve' => function($alyStatus){
					$alyStatus['data']['urls'][substr($alyStatus['entry'], 44, -1)] = true;
					$alyStatus['entry'] = 'Unable to parse SEO url - no match found';
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'No start node'
				, 'type' => 'No start node'
				, 'weight'	=> 0
				, 'solve' => function($alyStatus){
					$alyStatus['data']['pipelines'][substr($alyStatus['entry'], 38, -7)] = true; // getting the pipeline
					$alyStatus['entry'] = 'No start node specified for pipeline call.';
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Customer password could not be updated.'
				, 'type' => 'Invalid Customer password update'
				, 'weight'	=> 0
				, 'solve' => function($alyStatus){
					$alyStatus['data']['passwords'][substr($errorType[0], 80, -1)] = true;
					$alyStatus['entry'] = substr($errorType[0], 0, 78);
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Maximum number of sku(s) exceeds limit'
				, 'type' => 'Maximum limit exceed'
				, 'weight'	=> 0
				, 'solve' => function($alyStatus){
					$alyStatus['data']['limits'][substr($alyStatus['entry'], 39)] = true;
					$alyStatus['entry'] = 'Maximum number of sku(s) exceeds limit.';
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Java constructor for'
				, 'type' => 'Java constructor'
				, 'weight'	=> 3
			),
		);
		
		$continue = true;
		
		for ($i = 0; $i < count($errorExceptions); $i++) {
			if (startsWith($alyStatus['entry'], $errorExceptions[$i]['start'])) {
				$alyStatus['errorType'] = $errorExceptions[$i]['type'];
				if (array_key_exists('solve', $errorExceptions[$i])) $alyStatus = $errorExceptions[$i]['solve']($alyStatus);
				$continue = false;
				break;
			}
		}
		
		if ($continue) {
		
			if (startsWith($alyStatus['entry'], 'Wrapped ')){
				$errorType = explode(' ', $alyStatus['entry'], 3);
				array_shift($errorType);
			} else {
				$errorType = explode(':', $alyStatus['entry'], 2);
			}
			
			if (count($errorType) > 1) {
				
					$dots = explode('.', trim(str_replace(':', '', $errorType[0])));
					
					$alyStatus['errorType'] = trim(array_pop($dots));
					$alyStatus['entry'] = trim($errorType[1]);
				
			} else {
				d($errorType);	
				$alyStatus['errorType'] = $alyStatus['entry'];
			};
		}
		
		return $alyStatus;
	}
	
	function extractMeaningfullCustomData($alyStatus){
		
		$errorExceptions = array(
			array(
				  'start' => 'Error executing script'
				, 'type' => 'Error executing script'
				, 'weight'	=> 1
				, 'solve' => function($alyStatus){
					$alyStatus['entry'] = substr($alyStatus['entry'], 23);
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Timeout while executing script'
				, 'type' => 'Script execution timeout'
				, 'weight' => 1
				, 'solve' => function($alyStatus){
					$alyStatus['entry'] = substr($alyStatus['entry'], 23);
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Unknown category ID'
				, 'type' => 'Unknown category ID'
				, 'weight' => 1
				, 'solve' => function($alyStatus){
					$entry = explode('Unknown category ID ', $alyStatus['entry'], 2);
					$entry = explode(' ', $entry[1], 2);
					
					$alyStatus['data']['Category IDs'][trim($entry[0])] = true;
					$alyStatus['entry'] = 'Unknown category ID ' . $entry[1];
					
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Timeout while executing script'
				, 'type' => 'Script execution timeout'
				, 'weight' => 1
			)
		);
		
		$continue = true;
		
		for ($i = 0; $i < count($errorExceptions); $i++) {
			if (startsWith($alyStatus['entry'], $errorExceptions[$i]['start'])) {
				$alyStatus['errorType'] = $errorExceptions[$i]['type'];
				if (array_key_exists('solve', $errorExceptions[$i])) $alyStatus = $errorExceptions[$i]['solve']($alyStatus);
				$continue = false;
				break;
			}
		}
		
		if ($continue) {
		
			// 'Unknown category ID'
			
			$errorType = explode(':', $alyStatus['entry'], 2);
			$errorType[0] = trim($errorType[0]);
			
			if (count($errorType) > 1 && trim($errorType[1]) != '') {
				
				if (startsWith($errorType[0], 'Exception while evaluating script expression')){
					$alyStatus['errorType'] = 'Script Exception';
					$alyStatus['entry'] = $errorType[1]; // substr($errorType[0], 45) . 
				} else if (startsWith($errorType[0], 'Error executing script')) {
					$alyStatus['errorType'] = 'Error executing script';
					$alyStatus['entry'] = substr($alyStatus['entry'], 23) . ' ';
				} else {
					$alyStatus['entry'] = trim($errorType[1]);
					$errorType = (startsWith($errorType[0], 'org.')) ? explode('.', $errorType[0]) : explode(' ', $errorType[0]) ;
					$alyStatus['errorType'] = array_pop($errorType);
				}
			} else {
				d($alyStatus['entry']);
				$alyStatus['entry'] = $alyStatus['entry'];
			}
		}
		
		return $alyStatus;
	}
	
	function extractSiteID($siteString) {
		
		if (startsWith($siteString, 'Sites-') && endsWith($siteString, '-Site')) {
		
			$result = substr($siteString, 6);
			$result = substr($result, 0, -5);
			return ($result) ? $result : $siteString;
		}
		
		return $siteString;
	}
	
}

?>