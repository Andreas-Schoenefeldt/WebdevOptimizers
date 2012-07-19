<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../FileAnalyser.php');


class DemandwareLogAnalyser extends FileAnalyser {
	
	var $cartridgePath = array(); // the cartridgepath in order of inclusion
	
	function __construct($file, $layout)  {
		parent::__construct($file, 'demandware', $layout);
	}
	
	function analyse($fileIdent){
		
		$alyStatus = array('enter' => true, 'stacktrace' => '', 'lineNumber' => 1, 'fileIdent' => $fileIdent, 'add' => false);
		
		while ($line = fgets($this->filePointer, 4096)) {
		
		
			switch($this->layout) {
				default:
					throw new Exception('Don\'t know how to handel ' . $this->layout . ' files.');
					break;
				case 'error':
					$alyStatus = $this->analyse_error_line($alyStatus, $line);
					break;
				case 'customerror':
					$alyStatus = $this->analyse_customerror_line($alyStatus, $line);
					break;
				case 'quota':
					$alyStatus = $this->analyse_quota_line($alyStatus, $line);
					break;
			}
			
			if ($alyStatus['add']) {
				$this->addEntry($alyStatus['errorType'], $alyStatus['entry'], $alyStatus['entryNumber'], $alyStatus['fileIdent'], $alyStatus['data'], $alyStatus['stacktrace']);
			
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
					case 'SendOgoneAuthorization.ds':
					case 'SendOgoneCapture.ds':
					case 'SendOgoneRefund.ds':
						
						$params = explode(', OrderNo:', $alyStatus['entry'], 2);
						
						// d($alyStatus['errorType']);
						// d($params);
						
						$alyStatus['entry'] = $params[0];
						$params = explode(',', $params[1]);
						
						// d($params);
						
						$alyStatus['data']['order numbers'][trim($params[0])] = true;
						
						for ($i = 1; $i < count($params); $i++) {
							$parts = explode(':', $params[$i],2);
							$alyStatus['data'][trim($parts[0])][trim($parts[1])] = true;
						}
						break;
					case 'sopaVideos.ds':
						
						$params = explode('; Url: ', $alyStatus['entry'], 2);
						
						if (count($params) > 1) {
							$alyStatus['data']['Urls'][trim($params[1])] = true;
							$alyStatus['entry'] = $params[0];
						}
						
						break;
				}
				
				if ($errorLineLayout == 'extended') $alyStatus['data']['sites'][$this->extractSiteID(trim($messageParts[2]))] = true;
				
				$errorsWithAdditionalLineToParse = array('Error executing script');
				
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
	
	function extractMeaningfullData($alyStatus){
		
		if (startsWith($alyStatus['entry'], 'Wrapped ')){
			$errorType = explode(' ', $alyStatus['entry'], 3);
			array_shift($errorType);
		} else if (startsWith($alyStatus['entry'], 'No start node specified for pipeline') || startsWith($alyStatus['entry'], 'Customer password could not be updated.')){
			$errorType[] = $alyStatus['entry'];
		} else {
			$errorType = explode(':', $alyStatus['entry'], 2);
		}
		
		if (count($errorType) > 1) {
			
				$dots = explode('.', trim(str_replace(':', '', $errorType[0])));
				
				$alyStatus['errorType'] = trim(array_pop($dots));
				$alyStatus['entry'] = trim($errorType[1]);
			
		} else {
			
			if (startsWith($errorType[0], 'No start node')) {
				$alyStatus['errorType'] = 'No start node';
				$alyStatus['data']['pipelines'][substr($alyStatus['entry'], 38, -7)] = true; // getting the pipeline
				$alyStatus['entry'] = 'No start node specified for pipeline call.';
			} else if (startsWith($errorType[0], 'Unable to parse SEO url')) {
				$alyStatus['errorType'] = 'SEO url parse error';
				$alyStatus['data']['urls'][substr($alyStatus['entry'], 44, -1)] = true;
				$alyStatus['entry'] = 'No match found for url.';
			} else if (startsWith($errorType[0], 'Maximum number of sku(s) exceeds limit')) {
				$alyStatus['errorType'] = 'Maximum exceeds limit';
				$alyStatus['data']['limits'][substr($alyStatus['entry'], 39)] = true;
				$alyStatus['entry'] = 'Maximum number of sku(s) exceeds limit.';
			} else if (startsWith($errorType[0], 'Customer password could not be updated.')) {
				$alyStatus['errorType'] = 'Invalid Customer password update';
				$alyStatus['data']['passwords'][substr($errorType[0], 80, -1)] = true;
				$alyStatus['entry'] = substr($errorType[0], 0, 78);
			} else {
			
				d($errorType);
				
				$alyStatus['errorType'] = $alyStatus['entry'];
				
			}
		};
		
		
		
		return $alyStatus;
	}
	
	function extractMeaningfullCustomData($alyStatus){
		
		
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
			
			if (startsWith($errorType[0], 'Error executing script')) {
				$alyStatus['errorType'] = 'Error executing script';
				$alyStatus['entry'] = substr($alyStatus['entry'], 23) . ' ';
			} else if (startsWith($errorType[0], 'Timeout while executing script')) {
				$alyStatus['errorType'] = 'Script execution timeout';
				
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