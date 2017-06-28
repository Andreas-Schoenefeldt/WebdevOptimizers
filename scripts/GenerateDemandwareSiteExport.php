#!/usr/local/bin/php -q
<?php
	date_default_timezone_set("Europe/Berlin");
	
	$pathToPHPShellHelpers = str_replace('//','/',dirname(__FILE__).'/') .'../../PHP-Shell-Helpers/';
	
	require_once($pathToPHPShellHelpers . 'CmdIO.php');
	require_once($pathToPHPShellHelpers . 'Filehandler/staticFunctions.php');
	require_once($pathToPHPShellHelpers . 'ComandLineTools/CmdParameterReader.php');

	$params = new CmdParameterReader(
		$argv,
		array(
			/*
			'f' => array(
				'name' => 'folder',
				'datatype' => 'String',
				'description' => 'The folder, where the site import is genreated',
				'required' => true
			)
			*/
		),
		'To generate a demandware Site Import from a simple configuration file'
	);


	$folder = $params->getFileName();
	
	if(!$folder){
		$params->print_usage();
	} else {
	
		$payment_methods = [
			[
				  "id" => 'SVS_GIFT_CERTIFICATE'
				, 'name' => 'Dedicated SVS Gift Card - needs to be dissabled'
				, "enabled" => "false"
				, "processor-id" => 'SVS_GIFT_CERTIFICATE'
				, "custom-attributes" => [
					  'canBeCombined' => 'true'
					, 'isDefault' => 'false'
				]
			],
			
			[
				  "id" => 'CEGID_GIFT_CERTIFICATE'
				, 'name' => 'Dedicated Cegid Gift Card - needs to be dissabled'
				, "enabled" => "false"
				, "processor-id" => 'CEGID_GIFT_CERTIFICATE'
				, "custom-attributes" => [
					  'canBeCombined' => 'true'
					, 'isDefault' => 'false'
				]
			]
			
		];
		
		$setPathConfig = [
			'payment-methods' => $payment_methods
		];
		
		$sites = [
			  'QS-AT'	=> $setPathConfig
			, 'QS-BE'	=> $setPathConfig
			, 'QS-DE'	=> $setPathConfig
			, 'QS-DK'	=> $setPathConfig
			, 'QS-ES'	=> $setPathConfig
			, 'QS-FI'	=> $setPathConfig
			, 'QS-FR'	=> $setPathConfig
			, 'QS-GB'	=> $setPathConfig
			, 'QS-IE'	=> $setPathConfig
			, 'QS-IT'	=> $setPathConfig
			, 'QS-LU'	=> $setPathConfig
			, 'QS-NL'	=> $setPathConfig
			//, 'QS EU'	=> $setPathConfig
			, 'QS-PT'	=> $setPathConfig

			, 'RX-AT'	=> $setPathConfig
			, 'RX-BE'	=> $setPathConfig
			, 'RX-DE'	=> $setPathConfig
			, 'RX-DK'	=> $setPathConfig
			, 'RX-ES'	=> $setPathConfig
			, 'RX-FI'	=> $setPathConfig
			, 'RX-FR'	=> $setPathConfig
			, 'RX-GB'	=> $setPathConfig
			, 'RX-IE'	=> $setPathConfig
			, 'RX-IT'	=> $setPathConfig
			, 'RX-LU'	=> $setPathConfig
			, 'RX-NL'	=> $setPathConfig
			//, 'RX EU'	=> $setPathConfig
			, 'RX-PT'	=> $setPathConfig

			, 'DC-AT'	=> $setPathConfig
			, 'DC-BE'	=> $setPathConfig
			, 'DC-DE'	=> $setPathConfig
			, 'DC-CH'	=> $setPathConfig
			, 'DC-ES'	=> $setPathConfig
			, 'DC-FR'	=> $setPathConfig
			, 'DC-GB'	=> $setPathConfig
			, 'DC-IE'	=> $setPathConfig
			, 'DC-IT'	=> $setPathConfig
			, 'DC-LU'	=> $setPathConfig
			, 'DC-NL'	=> $setPathConfig

			//, 'DC EU'	=> $setPathConfig
	/*		
			, 'DC RU'	=> $setPathConfig
			, 'RX RU'	=> $setPathConfig
			, 'QS RU'	=> $setPathConfig
	*/
		];
		$nl = "\n";
		
		if (! is_dir($folder)) {
			mkdir($folder);
			chmod($folder, 0777);
		}	
		
		foreach ($sites as $site => $config) {
			$path = $folder . '/sites/' . $site;
			if (! is_dir($path)) {
				mkdir($path, 0777, true);
			}
			
			foreach ($config as $file => $details) {
				$filepath = $path . '/'	. $file . '.xml';
				$fp = fopen($filepath, 'w');
				
				fwrite($fp, '<?xml version="1.0" encoding="UTF-8"?>' . $nl);
				fwrite($fp, '<payment-settings xmlns="http://www.demandware.com/xml/impex/paymentsettings/2009-09-15">' . $nl);
				
				foreach ($details as $index => $method) {
					fwrite($fp, '	<payment-method method-id="' . $method['id'] . '">' . $nl);
					fwrite($fp, '		<name xml:lang="x-default">' . $method['name'] . '</name>' . $nl);
					fwrite($fp, '		<enabled-flag>' . $method['enabled'] . '</enabled-flag>' . $nl);
					fwrite($fp, '		<processor-id>' . $method['processor-id'] . '</processor-id>' . $nl);
					
					if($method['custom-attributes']) {
						fwrite($fp, '		<custom-attributes>' . $nl);
						
						foreach ($method['custom-attributes'] as $attr => $value) {
							fwrite($fp, '			<custom-attribute attribute-id="'. $attr .'">'. $value .'</custom-attribute>' . $nl);
						}	
						fwrite($fp, '		</custom-attributes>' . $nl);
					}
					
					
					
					fwrite($fp, '	</payment-method>' . $nl);
				}
				
				fwrite($fp, '</payment-settings>');
				
				fclose($fp);
			}
		}
		
		exec('zip -q -r -9 ' . basename($folder) . '.zip ' . $folder);
			
	}
