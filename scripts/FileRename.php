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
			'p' => array(
				'name' => 'postfix',
				'datatype' => 'String',
				'required' => true,
				
				'description' => 'This value will be added as a postfix to the files.'
			)
		),
		
		'Renames a bunch of files'
	);

	$dir = getcwd();
	$postfix = $params->getVal('p');
	
	forEachFile($dir, 'pdf', false, function($path){
		global $postfix, $io;
		$info = pathinfo($path);
		
		$newName = $info['dirname'] . '/' . $info['filename'] . $postfix . '.' . $info['extension'];
		
		if(! endsWith($info['filename'], $postfix)) {
			rename($path, $newName);
		
			$io->out('> renamed file to ' . $newName);	
		} else {
			$io->out('> SKIP ' . $path);	
		}
		
	});
		
		


?>