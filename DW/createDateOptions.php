#!/usr/local/bin/php -q
<?php
	date_default_timezone_set("Europe/Berlin");
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/CmdIO.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Debug/libDebug.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Filehandler/staticFunctions.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/ComandLineTools/CmdParameterReader.php');
	
	
	$io = new CmdIO();
	
	$params = new CmdParameterReader($argv, array(
		'start' => array('default' => 0, 'description' => 'The start of the options.'),
		'end' => array('default' => 31, 'description' => 'The end of the options.'),
		'spacing' => array('description' => 'should the value be filled with 0, until the length of spacing is reached?.'),
	));
	
	if ( $params->getVal('start') != null && $params->getVal('end') != null){
		for ($i = intval($params->getVal('start')); $i <= intval($params->getVal('end')); $i++ ){
			$io->cmd_print('<option optionid="' . $i .'" value="' . $i .'" label="' . $i .'"/>');
		}
	} else {
		$params->print_usage("Will print date options to the shell.");
	}
	
?>