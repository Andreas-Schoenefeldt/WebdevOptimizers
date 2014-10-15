#!/usr/local/bin/php -q
<?php
	date_default_timezone_set("Europe/Berlin");
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../../lib_php/CmdIO.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../../lib_php/Debug/libDebug.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../../lib_php/Filehandler/staticFunctions.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../../lib_php/ComandLineTools/CmdParameterReader.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../../lib_php/Filehandler/XMLStreamReader.php');
	
	$io = new CmdIO();
	
	$params = new CmdParameterReader($argv, array(
			'f' => array(
				'name' => 'outputfile',
				'datatype' => 'String',
				'default' => 'result.csv',
				
				'description' => 'The output file to write to (csv).'
			)
		)
		, 'A script to parse DW order xml files from a folder and converting them into a csv spreadsheet file.'
	);
	
	$folderToParse = $params->getFileName();
	
	if(!$folderToParse){
		$params->print_usage();
	} else {
	
		$resultFile = fopen($params->getVal('f'), 'w');
		
		forEachFile($folderToParse, '.*\.xml', true, 'parseXmlFile');
		
		fclose($resultFile);
	}
	
		
		
	function parseXmlFile($xmlFile) {
		global $resultFile;
		
		d($xmlFile);
		
		$reader = new XMLStreamReader($xmlFile);

		while($event = $reader->getNextEvent()){
			$xmlNode = $event->getContentObject();
			
			switch($event->getEventType()) {
				case EVENT_NODE_OPEN:
				
					switch($xmlNode->nodeName){
						case 'order':
							// This is also the init of a csv line
							$csvLine = array();
							$csvLine['orderNo'] = $xmlNode->attr('order-no');
							
							$inOrderTotal = false;
							
							break;
						case 'order-total':
							$inOrderTotal = true;
							break;
						case 'gross-price':
							if ($inOrderTotal) $csvLine['order total'] = $xmlNode->getText();
							break;
						case 'order-date':
						case 'card-type':
						case 'transaction-id':
						case 'processor-id':
						case 'customer-name':
						case 'customer-email':
						case 'payment-status':
						case 'order-status':
						case 'currency':
							$csvLine[$xmlNode->nodeName] = $xmlNode->getText();
							break;
						case 'custom-attribute':
							switch($xmlNode->attr('attribute-id')){
								case 'authorized3DS':
								case 'ogonePaymentMethod':
								case 'orderOrigin':
								case 'siteID':
								case 'ogoneStatus':
								case 'gender':
									$csvLine[$xmlNode->attr('attribute-id')] = $xmlNode->getText();
									break;
							}
							break;
					}
					break;
				case EVENT_NODE_CLOSE:
					switch($xmlNode->nodeName){
						case 'order':
							d($csvLine);
							die();
							break;
						case 'order-total':
							$inOrderTotal = false;
							break;
					}
					break;
			}
		}
		
		
		die();
	}
	
	
?>