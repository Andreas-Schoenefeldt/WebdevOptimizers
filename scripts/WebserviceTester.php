<?php

    
	class ChannelAdvisorAuth { 
		public $DeveloperKey; 
		public $Password; 

		public function __construct($key, $pass) 
		{ 
			$this->DeveloperKey = $key; 
			$this->Password = $pass; 
		} 
	} 

	function array_to_objecttree($array) {
	  if (is_numeric(key($array))) { // Because Filters->Filter should be an array
		foreach ($array as $key => $value) {
		  $array[$key] = array_to_objecttree($value);
		}
		return $array;
	  }
	  $Object = new stdClass;
	  foreach ($array as $key => $value) {
		if (is_array($value)) {
		  $Object->$key = array_to_objecttree($value);
		}  else {
		  $Object->$key = $value;
		}
	  }
	  return $Object;
	}


	$devKey     = "c7921b27-f076-4d37-ba91-0a071295392a"; 
	$password   = "Advisor1@"; 
	$accountId  = "71e320e8-c6b7-4dfd-b59f-8d4314e65b9e"; 



	$client = new SoapClient("https://api.channeladvisor.com/ChannelAdvisorAPI/v7/OrderService.asmx?WSDL", array('trace' => 1, "exception" => 0));

	$ns = 'http://api.channeladvisor.com/webservices/'; //Namespace of the WS. 

	//Body of the Soap Header. 
	$headerbody = array('DeveloperKey' => $devKey, 'Password' => $password); 
	//Create Soap Header.        
	$header = new SOAPHeader($ns, 'APICredentials', $headerbody);        

	//set the Headers of Soap Client. 
	$client->__setSoapHeaders($header); 



	// $header       = new SoapHeader("web", "APICredentials", array( 'DeveloperKey' => $devKey, 'Password' => $password ), false); 

	function getRefundItem($sku, $amount, $quantity, $adjustReason) {
		return array(
				"AdjustmentReason" => $adjustReason, 
				"Amount" => $amount, 
				"Quantity" => $quantity,
				"RefundRequestID"=> 0, 
				"SKU" => $sku,
				"ShippingAmount" => 0,
				"ShippingTaxAmount" => 0,
				"TaxAmount" => 0,
				"GiftWrapAmount" => 0,
				"GiftWrapTaxAmount" => 0,
				"RecyclingFee" => 0,
				"RefundRequested" => false,
				"RestockQuantity" => null,
				"LineItemID" => null
			);
	}

	
	$data = array( 
		"SubmitOrderRefund" => array( 
			// "OrderID" => "83079",
			"accountID"        => $accountId, 
			
			"request" => array(
				"OrderID" => "83079",
				"Amount" => 64.95 * 2,
				"RefundItems" => array(
					// getRefundItem('3613371269289', 64.95, 1, "CustomerReturnedItem"),
					getRefundItem('3613371279462', 64.95, 1, "CustomerReturnedItem"),
					getRefundItem('3613371333157', 64.95, 1, "CustomerReturnedItem")
				)
			)
		) 
	);

	print_r($data);

	try {
		// Call wsdl function 
		$result = $client->__soapCall("SubmitOrderRefund", $data, NULL, $header); 
	} catch(Exception $e) {
		$result = 'error';
	}

    echo "====== REQUEST HEADERS =====" . PHP_EOL;
	print_r($client->__getLastRequestHeaders());
    echo "========= REQUEST ==========" . PHP_EOL;
    
	var_dump($client->__getLastRequest());

	$dom = new DOMDocument;
	$dom->preserveWhiteSpace = FALSE;
	$dom->loadXML($client->__getLastRequest());
	$dom->formatOutput = TRUE;
	echo $dom->saveXml();

	// var_dump($client->__getLastRequest());



    echo "========= RESPONSE =========" . PHP_EOL;

	var_dump($client->__getLastResponse());

	$dom = new DOMDocument;
	$dom->preserveWhiteSpace = FALSE;
	$dom->loadXML($client->__getLastResponse());
	$dom->formatOutput = TRUE;
	echo $dom->saveXml();

	// var_dump($client->__getLastResponse());
	echo "========= RESPONSE Vars =========" . PHP_EOL;
    var_dump($result);

?>