<?php
	
	// date_default_timezone_set("Europe/Berlin");
	$pathToPHPShellHelpers = str_replace('//','/',dirname(__FILE__).'/') .'../../PHP-Shell-Helpers/';
	
	require_once($pathToPHPShellHelpers .'CmdIO.php');
	require_once($pathToPHPShellHelpers .'Filehandler/staticFunctions.php');
	require_once($pathToPHPShellHelpers .'ComandLineTools/CmdParameterReader.php');
	require_once($pathToPHPShellHelpers .'ComandLineTools/functions.php');
	
	date_operating_system_timezone_set();
	
	$params = new CmdParameterReader(
		$argv,
		array(
			
			'co' => array(
				'name' => 'checkout',
				'datatype' => 'Boolean',
				'default' => false,
				
				'description' => 'Use this to checkout a repository.'
			),
			
			'b' => array(
				'name' => 'branch',
				'datatype' => 'String',
				
				'description' => 'Use this to define a certain branch, that should be used for the checkout.'
			),
			
			'v' => array(
				'name' => 'version',
				'datatype' => 'Boolean',
				'default' => false,
				
				'description' => 'Shows the version of the used repository system'
			),
			
			'c' => array(
				'name' => 'commit',
				'datatype' => 'Boolean',
				'default' => false,
				
				'description' => 'Use this to commit your changes of the code to the repository.'
			),
			'add' => array(
				'name' => 'add',
				'datatype' => 'Boolean',
				'default' => false,
				
				'description' => 'Use this to add files to your repository.'
			),
			
			'rm' => array(
				'name' => 'remove',
				'datatype' => 'Boolean',
				'default' => false,
				
				'description' => 'use this to remove specific files from your repository.'
			),
			
			'st' => array(
				'name' => 'status',
				'datatype' => 'Boolean',
				'default' => false,
				
				'description' => 'use this to display the current status of your code control.'
			),
			
			'up' => array(
				'name' => 'update',
				'datatype' => 'Boolean',
				'default' => false,
				
				'description' => 'Use this to update your local repository from the source control server.'
			),
			
			'a' => array(
				'name' => 'all',
				'datatype' => 'Boolean',
				'default' => false,
				
				'description' => 'Add this parameter, to commit also modified files.'
			),
			
			'm' => array(
				'name' => 'message',
				'datatype' => 'String',
				
				'description' => 'Use this to pass a massage to the commit.'
			),
			
			'mg' => array(
				'name' => 'merge',
				'datatype' => 'String',
				
				'description' => 'Pass also the branch you want to merge'
			),
			
			'h' => array(
				'name' => 'help',
				'datatype' => 'Boolean',
				
				'description' => 'Displays this help.'
			),
			
			'l' => array(
				'name' => 'log',
				'datatype' => 'Boolean',
				
				'description' => 'Shows the log of the repository.'
			),
			
			'df' => array(
				'name' => 'diff',
				'datatype' => 'Boolean',
				'description' => 'Is doing a diff on the changed code'
 			)
		),
		'Use this script to have a uniform syntax for git, mercurial and svn. At the same time, your commited messages are added to the emphasize time management tool.'
	);
	
	$workingDir = getcwd();
	$workingDirParts = explode(DIRECTORY_SEPARATOR, $workingDir);
	$incidents = array('git', 'svn', 'hg');
	$io = new CmdIO();
	$system = '';
	
	while (count($workingDirParts) > 0) {
		
		for ($i = 0; $i < count($incidents); $i++) {
			$fileIncident = implode('/',$workingDirParts) . '/.' . $incidents[$i];
			// $io->out('testing ' . $fileIncident);
			
			if (file_exists($fileIncident)) {
				// $io->out('this is a ' . $incidents[$i]);
				
				$system = $incidents[$i];
				break;
			}
		}
		
		
		if ($system) {
			break;
		} else {
			array_pop($workingDirParts);
		}
		
	}
	
	if (! $system) {
		$io->fatal( $workingDir . ' has no known repository system (' . implode(', ', $incidents) . ')' , 'codecontrol');
	} else {
		$name = strtoupper(substr( $system, 0,1)) . substr( $system, 1) . 'Wrapper';
		require_once($pathToPHPShellHelpers . 'ComandLineTools/' . $name .  '.php');
		
		$cc = new $name();
		
		// update
		if ($params->getVal('up')) {
			$cc->update();
		// commit
		} else if ($params->getVal('c')) {
			$cc->commit($params->getVal('m'), $params->getVal('a'), $params->getFiles());
		// checkout
		} else if ($params->getVal('co')) {
			$cc->checkout($params->getFiles(), $params->getVal('b'));
		// status
		} else if ($params->getVal('st')) {
			$cc->status(false, $params->getVal('b')); // TODO Move the branch command out here
		// version
		} else if ($params->getVal('v')) {
			$cc->version();
		// log
		} else if ($params->getVal('l')) {
			$cc->log($params->getVal('l'));
		// add
		} else if ($params->getVal('add')) {
			$cc->add($params->getFiles());
		// remove
		} else if ($params->getVal('rm')) {
			$cc->remove($params->getFiles());
		// merge
		} else if ($params->getVal('mg')) {
			$cc->merge($params->getVal('mg'));
		} else if ($params->getVal('df')) {
			$cc->diff();
		} else {
			$params->print_usage();
		}
		
	}
	
	

?>