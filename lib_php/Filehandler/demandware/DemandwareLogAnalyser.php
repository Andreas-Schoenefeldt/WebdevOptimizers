<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../FileAnalyser.php');

define("LOGFILE_ERROR",     "Logfile Error");

class DemandwareLogAnalyser extends FileAnalyser {
	
	var $cartridgePath = array(); // the cartridgepath in order of inclusion
	var $alertConfiguration = array();
	
	function __construct($file, $layout, $settings, $alertConfiguration)  {
		$this->alertConfiguration = $alertConfiguration;
		parent::__construct($file, 'demandware', $layout, $settings);
	}
	
	function analyse($fileIdent){
		
		// init the analysation status
		$this->initAlyStatus($fileIdent, 0);
		
		// check file thresholds for whole logfile
		$alertMail = $this->checkAlert(0, '');
		if (!empty($alertMail)) {
			$this->alertMails[$this->layout." logfile threshold exceeded"] = $alertMail;
		}
		
		while ($line = $this->getNextLineOfCurrentFile()) {
			
			// d('OW: ' . $line);
			
			while ($line) {
				$line = $this->analyseLine($line);
				
				//d('IW: ' . $line);
				
				if ($this->alyStatus['add']) {	
					/*
					d($this->alyStatus);
					$this->io->read('Add error from line ' . $this->alyStatus['entryNumber'] . ': ' .  $this->alyStatus['errorType']);
					*/
					
					$errorCount = $this->addEntry($this->alyStatus['timestamp'], $this->alyStatus['errorType'], $this->alyStatus['entry'], $this->alyStatus['entryNumber'], $this->alyStatus['fileIdent'], $this->alyStatus['data'], $this->alyStatus['stacktrace']);
					
					$alertMail = $this->checkAlert($errorCount, $this->alyStatus['stacktrace']);
					
					if (!empty($alertMail)) {
						$this->alertMails[$this->alyStatus['entry']] = $alertMail;
					}
					
					$this->initAlyStatus($fileIdent, $this->alyStatus['lineNumber'], $line);
				}
			}
		}
		
	}
	
	// analyse a single line
	function analyseLine($line) {
		switch($this->layout) {
			default:
				throw new Exception('Don\'t know how to handel ' . $this->layout . ' files.');
				break;
			case 'error':
				$line = $this->analyse_error_line($line);
				break;
			case 'customwarn':
			case 'customerror':
				$line = $this->analyse_customerror_line($line);
				break;
			case 'quota':
				$line = $this->analyse_quota_line($line);
				break;
		}
		return $line;
	}
	
	// init the ally status
	function initAlyStatus($fileIdent, $currentLineNumber, $line = false){
		// get the basic status from the abstract parent class
		parent::initAlyStatus($fileIdent, $currentLineNumber);
		$this->alyStatus['errorType'] = '-';
		$this->alyStatus['enter'] = true;
		$this->alyStatus['add'] = false;
		
		if ($line) $this->alyStatus['stacktrace'] = $line . "\n";
	}
	
	function startsWithTimestamp($line) {
		return substr($line, 0, 1) == '[' && substr($line, 25, 4) == 'GMT]';
	}
	
	function analyse_quota_line($line) {
		if ($this->alyStatus['enter']) { // every line is a error
			
			$this->alyStatus['entryNumber'] = $this->alyStatus['lineNumber'];
			$this->alyStatus['data'] = array('sites' => array(), 'dates' => array(), 'GMT timestamps' => array(), 'max actual' => array(), 'pipeline' => array());
			$this->alyStatus['add'] = true;
			
			if (substr($line, 0, 1) == '[' && substr($line, 25, 4) == 'GMT]') {
				$errorLineLayout = 'extended';
				$parts = explode(']', substr($line, 29), 2);
				$this->alyStatus['data']['dates'][substr($line, 1, 10)] = true;
				$this->alyStatus['timestamp'] = strtotime(substr($line, 1, 27));
				$this->alyStatus['data']['GMT timestamps'][substr($line, 11, 6)] = true;
			} else {
				$errorLineLayout = 'core_extract';
				$parts = explode(':', $line, 2);
			}
			
			if (count($parts) > 1) {
				
				$this->alyStatus['entry'] = trim($parts[1]);
				
				$messageParts = explode('|', trim(substr($parts[0], 2))); // 0 => basic description, 1 => timestamp?, 2 => Site, 3 => Pipeline, 4 => another description? 5 => Cryptic stuff
				
				$description = explode(':', $this->alyStatus['entry'], 2);
				
				if (count($description) > 1) {
					$errorType = explode(' ', trim($description[0]), 2); // remove the quota or what else
					
					if (count($errorType) > 1) {
						$this->alyStatus['errorType'] = trim($errorType[1]);
					} else {
						$errorType = explode(':', trim($description[1]), 2);
						$this->alyStatus['errorType'] = trim($errorType[0]);
						$description[1] = $errorType[1];
					}
				} else {
					d($this->alyStatus['entry']);
				}
				
				switch($this->alyStatus['errorType']){
					default:
						
						preg_match('/^(.*)(\(.*?,.*?,.*?\))$/', $this->alyStatus['errorType'], $matchesHead);
						if (count($matchesHead) > 2) {
							$this->alyStatus['errorType'] = $matchesHead[1];
							$this->alyStatus['entry'] = $matchesHead[1] . ' ' . $matchesHead[2];
						}
						
						$message = $description[1];
						
						preg_match('/(, max actual was [0-9]*?),/', $message, $matches);
						
						if (count($matches) > 1) {
							$message = str_replace($matches[1], '', $message);
							$maxExceeds = explode(' ', $matches[1]);
							$this->alyStatus['data']['max actual']['#' . $maxExceeds[count($maxExceeds) - 1]] = true;
						} 
					
						// $this->alyStatus['entry'] = $this->alyStatus['errorType'] . ': ' . $message;
						if ($errorLineLayout == 'extended' && count($messageParts) > 2) {
							$this->alyStatus['data']['sites'][$this->extractSiteID(trim($messageParts[2]))] = true;
						}
						
						break;
				}
				
			} else {
				d($this->alyStatus);
				d($parts);
				$this->displayError($line);
			}
			
			
		}
	}
	
	function analyse_customerror_line($line) {
		
		if ($this->alyStatus['enter']) { // every line is a error
			
			$this->alyStatus['entryNumber'] = $this->alyStatus['lineNumber'];
			$this->alyStatus['data'] = array('sites' => array(), 'order numbers' => array(), 'dates' => array(), 'GMT timestamps' => array());
			$this->alyStatus['add'] = true;
			
			$parseSecondLine = false;
			
			if (substr($line, 0, 1) == '[' && substr($line, 25, 4) == 'GMT]') {
				$errorLineLayout = 'extended';
				$parts = explode('== custom', $line, 2);
				
				$parts = (count($parts) > 1) ? $parts : explode(' custom  ', $line); // this is a message comming form Logger.error
				$this->alyStatus['timestamp'] = strtotime(substr($line, 1, 27));
				$this->alyStatus['data']['dates'][substr($line, 1, 10)] = true;
				$this->alyStatus['data']['GMT timestamps'][substr($line, 11, 6)] = true; // We only need a granularity by minute
			} else {
				$errorLineLayout = 'core_extract';
				$parts = explode(':', $line, 2);
			}
			
			if (count($parts) > 1) {
				
				$this->alyStatus['entry'] = trim($parts[1]);
				$messageParts = explode('|', trim(substr($parts[0], 29))); // 0 => basic description, 1 => timestamp?, 2 => Site, 3 => Pipeline, 4 => another description? 5 => Cryptic stuff
				
				$this->extractMeaningfullCustomData();
				
				switch($this->alyStatus['errorType']){
					default:
						break;
					case 'SendOgoneDeleteAuthorization.ds':
					case 'SendOgoneAuthorization.ds':
					case 'SendOgoneCapture.ds':
					case 'SendOgoneRefund.ds':
					case 'OgoneError':
						
						$this->parseOgoneError($this->alyStatus['entry']);
						
						if (endsWith($this->alyStatus['entry'], 'RequestUrl:')) $parseSecondLine = true;
						
						break;
					case 'soapNews.ds':
					case 'sopaVideos.ds':
						
						$params = explode('; Url: ', $this->alyStatus['entry'], 2);
						
						if (count($params) > 1) {
							$this->alyStatus['data']['Urls'][trim($params[1])] = true;
							$this->alyStatus['entry'] = $params[0];
						}
						
						$params = explode(', SearchPhrase:', $this->alyStatus['entry'], 2);
						if (count($params) > 1) {
							$this->alyStatus['data']['SearchPhrases'][trim($params[1])] = true;
							$this->alyStatus['entry'] = $params[0];
						}
						
						
						break;
					case 'COPlaceOrder-Start':
					case 'COPlaceOrder-HandleAsyncPaymentEntry':
						
						$params = explode(', OrderNo: ', $this->alyStatus['entry'], 2);
						if (count($params) > 1) {
							$this->alyStatus['data']['Order Numbers']['#' . trim($params[1])] = true;
							$this->alyStatus['entry'] = $params[0];
						}
						
						break;
					case 'Ogone-Declined':
						
						$params = explode('CustomerNo: ', $this->alyStatus['entry'], 2);
						if (count($params) > 1) {
							$this->alyStatus['data']['Customer Numbers']['#' . trim($params[1])] = true;
							$this->alyStatus['entry'] = $params[0];
						}
						
						break;
				}
				
				if ($errorLineLayout == 'extended') $this->alyStatus['data']['sites'][$this->extractSiteID(trim($messageParts[2]))] = true;
				
				$errorsWithAdditionalLineToParse = array('Error executing script', 'Script execution timeout');
				
				if (in_array($this->alyStatus['errorType'], $errorsWithAdditionalLineToParse) || $parseSecondLine) {
					$newLine = $this->getNextLineOfCurrentFile($this->alyStatus);
					$newLine = trim($newLine);
					
					// get aditional information from the next line
					switch ($this->alyStatus['errorType']) {
						case 'Error executing script':
							$this->alyStatus['entry'] .= ' ' . $newLine;
							break;
						case 'SendOgoneDeleteAuthorization.ds':
						case 'SendOgoneAuthorization.ds':
						case 'SendOgoneCapture.ds':
						case 'SendOgoneRefund.ds':
						case 'OgoneError':
							$this->parseOgoneError($newLine);
							break;
					}
				}
				
			} else {
				d($parts);
				$this->displayError($line);
			}
			
			
		}
	}
	
	function parseOgoneError($line){
		
		$params = explode(' OrderNo:', $line, 2);
						
		// d($this->alyStatus['errorType']);
		// d($params);
		if (count($params) > 1) {
			$line = substr($params[0], 0, -1);
			$params = explode(',', $params[1]);
			
			// d($params);
			
			$this->alyStatus['data']['order numbers']['#' . trim($params[0])] = true;
			
			for ($i = 1; $i < count($params); $i++) {
				$parts = explode(':', $params[$i],2);
				$this->alyStatus['data'][trim($parts[0])][trim($parts[1])] = true;
			}
		}
		
		$params = explode(' Seconds since start:', $line, 2);
		
		if (count($params) > 1) {
			
			$line = substr($params[0], 0, -1);
			// $params = explode(',', $params[1], 2);
			// d($params);
			
			$this->alyStatus['data']['Seconds since start'][trim($params[1])] = true;
		}
		
		$startStr = 'Capture successfully for Order ';
		if (startsWith($line, $startStr)) {
			
			$this->alyStatus['data']['order numbers']['#' . trim(substr($line, strlen($startStr)))] = true;
			$line = trim($startStr);
		}
		
		// split the ogone Url
		$start = 'https://secure.ogone.com';
		if (startsWith($line, $start)) {
			
			$exceptions = array('java.net.SocketTimeoutException: Read timed out', 'Error connecting to Ogone Direct Link. Return Code: 404');
			$parseURL = true;
			
			for($i = 0; $i < count($exceptions); $i++){
				if (strrpos($this->alyStatus['entry'], $exceptions[$i]) > -1) {
					$line = $exceptions[$i]; // line is now the error message
					$parseURL = false;
					break;
				}	
			}
			
			if ($parseURL) {
				
				$parts = explode('; OgoneError: ', $line, 2);
				$url = explode('?', $parts[0]);
				$params = explode('&', $url[1]);
				
				$line = 'OgoneError: ' . $parts[1];
				
				for ($i = 0; $i < count($params); $i++) {
					$patlets = explode('=', $params[$i], 2);
					
					switch ($patlets[0]) {
						case 'PM':
						case 'OWNERTOWN':
						case 'OPERATION':
						case 'FLAG3D':
							$this->alyStatus['data'][$patlets[0]][trim($patlets[1])] = true;
							break;
						case 'AMOUNT':
							$this->alyStatus['data'][$patlets[0]][  substr(trim($patlets[1]), 0, -2) . '.' . substr(trim($patlets[1]), -2)] = true;
							break;
					}
				}
			}
		}
		
		$this->alyStatus['entry'] = $line;
	}
	
	
	// parsinfg of the error logs
	function analyse_error_line($line){
		if ($this->alyStatus['enter']) {  // && substr($line, 0, 1) == '[' && substr($line, 25, 4) == 'GMT]' [2012-05-22 00:11:56.785 GMT]
			// initial error definition
			$this->alyStatus['enter'] = false;
			$this->alyStatus['entryNumber'] = $this->alyStatus['lineNumber'];
			$this->alyStatus['data'] = array('sites' => array(), 'customers' => array(), 'dates' => array(), 'GMT timestamps' => array(), 'pipelines' => array(), 'urls' => array());
			
			$isExtended = $this->startsWithTimestamp($line);
			if ($isExtended) {
				$this->alyStatus['timestamp'] = strtotime(substr($line, 1, 27));
				$this->alyStatus['data']['dates'][substr($line, 1, 10)] = true;
				$this->alyStatus['data']['GMT timestamps'][substr($line, 11, 6)] = true;
				$line = substr($line, 30);
				$parts = explode(' "', $line, 2);
				$errorLineLayout = 'extended';
			} else {
				$errorLineLayout = 'core_extract';
				$parts = explode(':', $line, 2);
			}
			
			if (count($parts) > 1) {
				
				$this->alyStatus['entry'] = trim($parts[1]);
				$messageParts = ($isExtended) ? explode('|', trim(str_replace('ERROR', '', $parts[0]))): array(); // 0 => basic description, 1 => timestamp?, 2 => Site, 3 => Pipeline, 4 => another description? 5 => Cryptic stuff
				
				// d($line);
				$this->extractMeaningfullData();
				
				$interestingLine = 7101;
				if($this->alyStatus['entryNumber'] == $interestingLine) {
					d($this->alyStatus);
					//$this->io->read();
				}
				
				switch($this->alyStatus['errorType']){
					default:
						if ($errorLineLayout == 'extended' && count($messageParts) > 2) $this->alyStatus['data']['sites'][$this->extractSiteID(trim($messageParts[2]))] = true;
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
							
							
							
							$this->alyStatus['entry'] = $pipeline . ' > ' . $this->alyStatus['entry'];
							$this->alyStatus['data']['sites'][$siteID] = true;
						} else {
							$this->alyStatus['entry'] = $this->alyStatus['entry'];
						}
						break;
					
					// errors with pipeline, but second line has the real error message
					case 'ISH-CORE-2368':
					case 'ISH-CORE-2355':
						$this->alyStatus['entry'] = ($errorLineLayout == 'extended') ? $messageParts[3] . ' > ' : '';
						if ($errorLineLayout == 'extended') $this->alyStatus['data']['sites'][$this->extractSiteID(trim($messageParts[2]))] = true;
						break;
					
					// Job errors
					case 'ISH-CORE-2652':
						
						$infosBefore = explode('[', $this->alyStatus['entry'], 2);
						$infosAfter = explode(']', $infosBefore[1], 2);
						
						$partlets = explode(':', $infosAfter[1]);
						
						$params = explode(', ', $infosAfter[0]);
						
						$this->alyStatus['entry'] = $infosBefore[0] . " " . $params[0] . " " . $partlets[0] . " " . $partlets[count($partlets) - 1];
						if (count($params) > 2) $this->alyStatus['data']['sites'][$this->extractSiteID(trim($params[2]))] = true;
						
						break;
					
					// internal errors
					case 'ISH-CORE-2482':
						break;
					case '[bc_search] error':
						
						$parts = explode("'", $this->alyStatus['entry'], 3);
						
						if (count($parts) > 2) {
							$this->alyStatus['entry'] = $parts[0] . ' {- different items -} ' . $parts[2];
						} else {
							d($parts);
						}
						break;
					
				}
				
				$errorsWithAdditionalLineToParse = array(
					  'ISH-CORE-2482'
					, 'ISH-CORE-2351'
					, 'ISH-CORE-2354'
					, 'ISH-CORE-2368'
					, 'ISH-CORE-2355'
					, 'com.demandware.beehive.core.capi.pipeline.PipeletExecutionException'
					, 'Error while processing request'
					, 'ORMSQLException'
					, 'ABTestDataCollectionMgrImpl'
					, 'Error executing query'
				);
				
				if (in_array($this->alyStatus['errorType'], $errorsWithAdditionalLineToParse)) {
					$newLine = $this->getNextLineOfCurrentFile();
				
				
					// get aditional information from the next line
					switch ($this->alyStatus['errorType']) {
						default:
							$this->alyStatus['entry'] .= ' ' . $newLine;
							break;
						case 'ISH-CORE-2482':
							$this->alyStatus['entry'] = $newLine;
							$this->extractMeaningfullData();
							break;
						case 'Error executing query':
						case 'ABTestDataCollectionMgrImpl':
						case 'ORMSQLException':
						case 'Error while processing request':
						case 'ISH-CORE-2354':
							
							// try to find the real error
							$lines = 1;
							while ($lines < 5 && ! $this->startsWithTimestamp($newLine)){
								if (
									   startsWith($newLine, 'org.mozilla.javascript.EcmaError:')
									|| startsWith($newLine, 'com.demandware.beehive.core.capi.pipeline.PipelineExecutionException:')
									|| startsWith($newLine, 'com.demandware.beehive.orm.capi.common.ORMSQLException:')
								   ) $this->alyStatus['entry'] .= ' ' . $newLine;
								
								$newLine = $this->getNextLineOfCurrentFile();
								$lines++;
							}
							return $newLine;
							
							break;
						case 'com.demandware.beehive.core.capi.pipeline.PipeletExecutionException':
							if (endsWith($this->alyStatus['entry'], 'Script execution stopped with exception:')) {
								$this->alyStatus['entry'] .= ' ' . $newLine;
							}
							break;
					}
				}
				
				if($this->alyStatus['entryNumber'] == $interestingLine) {
					d($this->alyStatus);
					// $this->io->read();
				}
				
			} else {
				
				// we probably have an error like this
				// ERROR JobThread|14911671|BazaarProductCatalogExport|JobExecutor-Start system.dw.net.SFTPClient  {0}
				
				$parts = explode('|', $line);
				
				if (count($parts) > 3) {
					
					$look = 'ERROR';
					
					if ( startsWith($look, trim($parts[0]) )) $this->alyStatus['errorType'] = $look . ' ';
					$this->alyStatus['errorType'] .= trim($parts[2]);
					
					$partlets = explode(' ', trim($parts[3]));
					$this->alyStatus['data']['pipelines'][$partlets[0]] = true;
					$this->alyStatus['entry'] = trim($parts[0]) . ' ' . trim($parts[2]) . ' ';
					
				} else {
					
					$this->alyStatus['entry'] = $line;
					d($parts);
					$this->displayError($line);
				}
			}
		} else if ($this->startsWithTimestamp($line)) { // a log entry is unfortuatly only finished after we found the next entry or end of file 
			$this->alyStatus['add'] = true;
			return $line;
		} 
	}
	
	function getErrorType_1($entry){
		$errorType = explode(' ', $entry, 3);
		array_shift($errorType);
		return $errorType;
	}
	function getErrorType_2($entry){ return array($entry); }
	
	function extractMeaningfullData(){
		
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
				  'start' => 'SEOParsingException Unable to parse SEO url - no match found - {'
				, 'type' => 'SEO URL mismatch'
				, 'weight'	=> 1
				, 'solve' => function($definition, $alyStatus){
					$alyStatus['data']['urls'][substr($alyStatus['entry'], strlen($definition['start']), -2)] = true;
					$alyStatus['entry'] = 'Unable to parse SEO url - no match found';
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Invalid order status change from COMPLETED to OPEN for order '
				, 'type' => 'Invalid order status change'
				, 'weight'	=> 0
				, 'solve' => function($definition, $alyStatus){
					$alyStatus['data']['orders']['#' . substr($alyStatus['entry'], strlen($definition['start']))] = true;
					$alyStatus['entry'] = 'Invalid order status change from COMPLETED to OPEN';
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Unexpected error: JDBC/SQL error: '
				, 'type' => 'ORMSQLException'
				, 'weight'	=> 9
			),
			
			array(
				  'start' => 'No start node'
				, 'type' => 'No start node'
				, 'weight'	=> 0
				, 'solve' => function($definition, $alyStatus){
					$alyStatus['data']['pipelines'][substr($alyStatus['entry'], 38, -7)] = true; // getting the pipeline
					$alyStatus['entry'] = 'No start node specified for pipeline call.';
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Customer password could not be updated.'
				, 'type' => 'Invalid Customer password update'
				, 'weight'	=> 0
				, 'solve' => function($definition, $alyStatus){
					$alyStatus['data']['passwords'][substr($alyStatus['entry'], 80, -1)] = true;
					$alyStatus['entry'] = substr($alyStatus['entry'], 0, 78);
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Maximum number of sku(s) exceeds limit'
				, 'type' => 'Maximum limit exceed'
				, 'weight'	=> 0
				, 'solve' => function($definition, $alyStatus){
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
						
			array(
				  'start' => 'The basket is null'
				, 'type' => 'Missing Basket'
				, 'weight' => 1
			),
			
			array(
				  'start' => 'Invalid order status change from COMPLETED to CANCELLED for order '
				, 'type' => 'Invalid order status change'
				, 'weight'	=> 0
				, 'solve' => function($definition, $alyStatus){
					$alyStatus['data']['orders']['#' . substr($alyStatus['entry'], strlen($definition['start']))] = true;
					$alyStatus['entry'] = 'Invalid order status change from COMPLETED to CANCELLED';
					return $alyStatus;
				}
			),
		);
		
		$continue = true;
		
		for ($i = 0; $i < count($errorExceptions); $i++) {
			if (startsWith($this->alyStatus['entry'], $errorExceptions[$i]['start'])) {
				$this->alyStatus['errorType'] = $errorExceptions[$i]['type'];
				if (array_key_exists('solve', $errorExceptions[$i])) $this->alyStatus = $errorExceptions[$i]['solve']($errorExceptions[$i], $this->alyStatus);
				$continue = false;
				break;
			}
		}
		
		if ($continue) {
		
			if (startsWith($this->alyStatus['entry'], 'Wrapped ')){
				$errorType = explode(' ', $this->alyStatus['entry'], 3);
				array_shift($errorType);
			} else {
				$errorType = explode(':', $this->alyStatus['entry'], 2);
			}
			
			if (count($errorType) > 1) {
				
					$dots = explode('.', trim(str_replace(':', '', $errorType[0])));
					
					$this->alyStatus['errorType'] = trim(array_pop($dots));
					$this->alyStatus['entry'] = trim($errorType[1]);
				
			} else {
				$this->displayError($this->alyStatus['entry']);
				$this->alyStatus['errorType'] = $this->alyStatus['entry'];
			};
		}
	}
	
	function extractMeaningfullCustomData(){
		
		$errorExceptions = array(
			array(
				  'start' => 'Error executing script'
				, 'type' => 'Error executing script'
				, 'weight'	=> 1
				, 'solve' => function($definition, $alyStatus){
					$alyStatus['entry'] = substr($alyStatus['entry'], 23);
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Timeout while executing script'
				, 'type' => 'Script execution timeout'
				, 'weight' => 1
				, 'solve' => function($definition, $alyStatus){
					$alyStatus['entry'] = substr($alyStatus['entry'], 23);
					return $alyStatus;
				}
			),
			
			array(
				  'start' => 'Unknown category ID'
				, 'type' => 'Unknown category ID'
				, 'weight' => 1
				, 'solve' => function($definition, $alyStatus){
					$entry = explode('Unknown category ID ', $alyStatus['entry'], 2);
					$entry = explode(' for implicit search filters given.', $entry[1], 2);
					$alyStatus['data']['Category IDs'][trim($entry[0])] = true;
					$alyStatus['entry'] = 'Unknown category ID for implicit search filters given.';
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
			if (startsWith($this->alyStatus['entry'], $errorExceptions[$i]['start'])) {
				$this->alyStatus['errorType'] = $errorExceptions[$i]['type'];
				if (array_key_exists('solve', $errorExceptions[$i])) $this->alyStatus = $errorExceptions[$i]['solve']($errorExceptions[$i], $this->alyStatus);
				$continue = false;
				break;
			}
		}
		
		if ($continue) {
		
			// 'Unknown category ID'
			
			$errorType = explode(':', $this->alyStatus['entry'], 2);
			$errorType[0] = trim($errorType[0]);
			
			if (count($errorType) > 1 && trim($errorType[1]) != '') {
				
				if (startsWith($errorType[0], 'Exception while evaluating script expression')){
					$this->alyStatus['errorType'] = 'Script Exception';
					$this->alyStatus['entry'] = $errorType[1]; // substr($errorType[0], 45) . 
				} else if (startsWith($errorType[0], 'Error executing script')) {
					$this->alyStatus['errorType'] = 'Error executing script';
					$this->alyStatus['entry'] = substr($this->alyStatus['entry'], 23) . ' ';
				} else {
					$this->alyStatus['entry'] = trim($errorType[1]);
					$errorType = (startsWith($errorType[0], 'org.')) ? explode('.', $errorType[0]) : explode(' ', $errorType[0]) ;
					$this->alyStatus['errorType'] = array_pop($errorType);
				}
			} else {
				$this->displayError($this->alyStatus['entry']);
			}
		}
	}
	
	function extractSiteID($siteString) {
		if (startsWith($siteString, 'Sites-') && endsWith($siteString, '-Site')) {
		
			$result = substr($siteString, 6);
			$result = substr($result, 0, -5);
			return ($result) ? $result : $siteString;
		}
		
		return $siteString;
	}
	
	
	// check if alert has to be thrown and return mail object for notification
	function checkAlert($errorCount, $stacktrace) {
		$filename = $this->currentFile;
		$filesize = round(filesize($filename) / 1024, 2);
		// get configuration
		$thresholds = $this->alertConfiguration['thresholds'];
		$senderemailaddress = $this->alertConfiguration['senderemailaddress'];
		$emailadresses = $this->alertConfiguration['emailadresses'];
		// preset mail variables
		$message = ($errorCount>0 ? "Error Count: $errorCount\n\n" : "")."Last logfile impacted: ".substr (strrchr($filename,'/'), 1)."\n\nLogfile size: $filesize KB\n\n".$stacktrace; 
		$mail = array();
		//$mail[$errorType] = array();
		
		// check for ignore pattern
		if (isset($thresholds['ignorepattern'])) {
			$ignorePattern = $this->checkSimplePatternThreshold($thresholds['ignorepattern'], $stacktrace);
			if (!empty($ignorePattern)) {
				return null;
			}
		}
		
		// check for count pattern
		if (isset($thresholds['countpattern'])) {
			$countPattern = $this->checkSimplePatternThreshold($thresholds['countpattern'], $stacktrace);
			if (!empty($countPattern)) {
				// check for pattern error count
				if (isset($thresholds['patterncount'])) {
					$threshold = 'patterncount';
					$maxPatternCount = $this->checkSimpleValueThreshold($thresholds['patterncount'], $errorCount);
					if (!empty($maxPatternCount)) {
						$mail[$threshold] = array(
							'message' => "Threshold: Pattern Error Count $maxPatternCount for pattern $countPattern exceeded.\n\n".$message,
							'subject' => "Pattern Error Count $maxPatternCount for pattern $countPattern exceeded"
						);
						return $mail;
					}
				}
				return null;
			}
		}
		
		// check for any other threshold
		foreach ($thresholds as $threshold => $expression) {
			switch($threshold) {
				default:
					throw new Exception('Don\'t know how to handel ' . $threshold . ' threshold.');
					break;
				case 'errorcount':
					$maxErrorCount = $this->checkSimpleValueThreshold($expression, $errorCount);
					if (!empty($maxErrorCount)) {
						$mail[$threshold] = array(
							'message' => "Threshold: Error Count $maxErrorCount exceeded.\n\n".$message,
							'subject' => "Error Count $maxErrorCount exceeded"
						);
					}
					break;
				case 'matchpattern':
					$matchedPattern = $this->checkSimplePatternThreshold($expression, $stacktrace);
					if (!empty($matchedPattern)) {
						$mail[$threshold] = array(
							'message' => "Threshold: Pattern '".$matchedPattern."' matched.\n\n".$message,
							'subject' => "Pattern '".$matchedPattern."' matched"
						);
					}
					break;
				case 'filesize':
					// only check when no log file entry is provided (only check once per file)
					if($errorCount<=0 && empty($stacktrace)) {
						$maxFilesize = $this->checkSimpleValueThreshold($expression, $filesize);
						if (!empty($maxFilesize)) {
							$mail[$threshold] = array(
								'message' => "Threshold: Logfile size '".$maxFilesize."' KB exceeded.\n\n".$message,
								'subject' => "Logfile size $maxFilesize KB exceeded"
							);
						}
					}
					break;
				case 'ignorepattern':
				case 'countpattern':
				case 'patterncount':
					break;
			}
		}
		
		return $mail;
	}
	
	// checks simple threshold value for fiven threshold expression
	function checkSimpleValueThreshold($expression, $checkvalue) {
		$exceeded_value = null;
		$filelayoutExists = false;
		if (is_array($expression)) {
			foreach ($expression as $filelayout => $value) {
				if ($this->layout == $filelayout) {
					if ($filelayout!==0) {
						$filelayoutExists = true;
					}
					// allow default value when no threshold for current layout exists
					if (($checkvalue > $value || $checkvalue < 0) && ($filelayout!==0 || !$filelayoutExists)) {	
						$exceeded_value = $value;
					}
				}
			}
		}
		return $exceeded_value;
	}
	
	// checks simple threshold pattern for fiven threshold expression
	function checkSimplePatternThreshold($expression, $checkpattern) {
		$exceeded_value = null;
		foreach ($expression as $pattern) {
			if (preg_match("/$pattern/", $checkpattern)) {
				$exceeded_value = $pattern;
			}
		}
		return $exceeded_value;
	}
	
}

?>