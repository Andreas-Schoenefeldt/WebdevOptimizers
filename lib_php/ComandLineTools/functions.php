<?php

function date_operating_system_timezone_set() {
	
	$timezones = array(
		  'GMT' => 'Europe/London'
		, '0' => 'Europe/London'
		, '1' => 'Europe/London'
		, '2' => 'Europe/Berlin'
	);
	
	switch (PHP_OS){
		default:
			throw("Can'T handle OS: " . PHP_OS);
			break;
		case 'WIN':
		case 'WINNT':
				
			$shell = new COM("WScript.Shell") or die("Requires Windows Scripting Host");
			$time_bias = -($shell->RegRead("HKEY_LOCAL_MACHINE\\SYSTEM\\CurrentControlSet\\Control\\TimeZoneInformation\\Bias"))/60;
			$sc = $shell->RegRead("HKEY_USERS\\.DEFAULT\\Control Panel\\International\\sCountry"); 
			$timezone = -($shell->RegRead("HKEY_LOCAL_MACHINE\\SYSTEM\\CurrentControlSet\\Control\\TimeZoneInformation\\ActiveTimeBias"))/60;
			
			break;
		case 'MACOS':
			$timezone = exec('date +%Z');
			break;
	}
	
	if( array_key_exists($timezone, $timezones)) {		
		date_default_timezone_set($timezones[$timezone]);
	} else {
		die("Unknown Timezone: " . $timezone);
	}
}

?>