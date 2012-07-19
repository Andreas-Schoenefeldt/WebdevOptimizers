<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../CmdIO.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'../Debug/libDebug.php');


/* -----------------------------------------------------------------------
 * A function to open a file and parse the content into keymaps
 * ----------------------------------------------------------------------- */
class FileAnalyser {

	var $io = null;
	var $files = array(); // the files which will be analysed
	var $filePointer = null;
	var $environment = null;
	var $layout = null;
	
	var $workingDir = null;
	var $timestamp = null;
	
	var $entrys = array(); // an entry of a file array( 'entry text' => array( count => Int, line => line, fileIdent => fileIndex))
	var $errorcount = 0;
	var $allerrorscount = 0;

	function __construct($files, $environment, $layout)  {
		$this->io = new CmdIO();
		$this->files = $files;
		$this->environment = $environment;
		$this->layout = $layout;
		
		// get the file
		
		for ($i = 0; $i < count($this->files); $i++) {
			
			$filename = $this->files[$i]; 
			if (file_exists($filename)){
			
				$this->filePointer = fopen($filename, 'r');
				
				// analyse the file
				$this->analyse($i);
				
				fclose($this->filePointer);
				
			} else {
				$this->io->error("File $filename does not exist");
			}	
		}
	}
	
	function getAllErrorCount() {		return $this->allerrorscount;	}
	function getErrorCount() { 			return $this->errorcount; 		}
	function setTime($timestamp) { 		$this->timestamp = $timestamp;	}
	
	/**
	 * Sets and creates the working dir.
	 */
	function setWorkingDir($dir) {
		if (! file_exists($dir)) {
			mkdir($dir);
		}
		
		$this->workingDir = $dir;
	}
	
	/**
	 *	the analyse function of the File Analyser. Should be implemented in concrete environment based childclasses
	 */
	function analyse($fileIdent) {
		throw new Exception("Not Implemented.");
	}
	
	
	function addEntry($type, $key, $lineNumber, $fileIdent, $data, $stacktrace) {
		
		$key = str_replace(array("\n", "\r"), '' , $key);
		
		if (! array_key_exists($key, $this->entrys)){
			$this->entrys[$key] = array('count' => 0, 'type' => $type, 'line' => $lineNumber, 'fileIdent' => $fileIdent, 'data' => array(), 'stacktrace' => $stacktrace);
			$this->errorcount++; // errors +1
		}
		
		if ($lineNumber < $this->entrys[$key]['line']) {
			$this->entrys[$key]['line'] = $lineNumber;
			$this->entrys[$key]['fileIdent'] = $fileIdent;
			$this->entrys[$key]['stacktrace'] = $stacktrace;
		}
		
		$this->entrys[$key]['count']++;
		$this->entrys[$key]['data'] = array_merge_recursive($this->entrys[$key]['data'], $data);
		
		$this->allerrorscount++;
	}
	
	function printResults($format = 'cmd'){
		
		
		uasort($this->entrys, function($a, $b){
			if ($a['count'] == $b['count']) {
				
				// sort for the first line number afterwards
				if ($a['line'] == $b['line']) {
					return 0;
				}
				return ($a['line'] < $b['line']) ? -1 : 1;
			}
			return ($a['count'] < $b['count']) ? 1 : -1;
		});
		
		switch ($format) {
			default:
				$this->io->out('Results:', true, 1);
				
				foreach ($this->entrys as $message => $stats) {
					$countString = $stats['count'] . '';
					$countString = str_repeat(' ', 4 - strlen($countString)) . $countString;
					$this->io->out($countString . ' times since line ' . $stats['line']. ' in file ' . $stats['fileIdent'] . ' | ' . $message);
					
					$siteString = str_repeat(' ', 5 ) . 'Sites:';
					foreach ($stats['data']['sites'] as $siteId => $val) {
						$siteString .= ', ' . $siteId;
					}
					$this->io->out($siteString);
					
					$this->io->out();
				}
				break;
			case 'html':
				
				$this->writeCss();
				$this->writeJs();
				
				$filename = $this->writeErrorList();
				
				break;
		}
		
		return $filename;
	}
	
	function writeCss(){
		$filepath = $this->workingDir . '/style.css';
		copy(str_replace('//','/',dirname(__FILE__).'/') .'../templates/analyse/style.css', $filepath);
	}
	
	function writeErrorList(){
		$filename = $this->timestamp . '_' . $this->layout .'.html';
		$filepath = $this->workingDir . '/' . $filename;
		
		$file = fopen($filepath, 'w');
		
		$title = $this->layout . ' logs overview - ' . date('d.m.Y', $this->timestamp);
		
		$navigation = '<div class="navigation"><a href="index.html">back to overview</a></div>';
		
		$fileString = '
<!DOCTYPE html>
<html>
<head>
	<meta content="text/html; charset=utf-8" http-equiv="Content-Type">

	<title>'.$title.'</title>
	<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js" type="text/javascript"></script>
	<script src="app.js" type="text/javascript"></script>
	<link href="style.css" type="text/css" rel="stylesheet">
</head>
<body>
	<div class="page">'. $navigation .'
		<h1>'.$title.'</h1>
		
		<div class="error_table">' . "\n";
		
		foreach ($this->entrys as $message => $stats) {
			
			$pathexplodes = explode('/', $this->files[$stats['fileIdent']]);
			
			$fileString .= '<div class="error_row widget_showAditionals">' . "\n";
			$fileString .= '	<div class="entry number">' . $stats['count'] . ' x</div>' . "\n";
			$fileString .= '	<div class="entry type">' . htmlentities($stats['type']) . '</div>' . "\n";
			$fileString .= '	<div class="entry actions"><a class="widget_traceoverlay minibutton" title="show the raw stacktrace of this error">raw<span class="hidden overlay"><span class="headline">First occurence in line ' . $stats['line'] . ', logfile ' . $pathexplodes[count($pathexplodes) - 1] . '</span><pre class="preformated">' . $stats['stacktrace'] . '</pre></span></a></div>' . "\n";
			$fileString .= '
	<div class="entry message">
		<div>' . htmlentities($message) . '</div>
		<div class="aditionals">' . "\n";
			
			foreach ($stats['data'] as $headline => $data){
				$first = true;
				$valString = '';
				
				if (count($data)) {
					
					// sort the keys
					ksort($data);
				
					foreach ($data as $value => $om) {
						if (! $first) $valString .= ', ';
						$valString .= $value;
						$first = false;
					}
					
					$fileString .=  '<div><strong>' . htmlentities($headline) . ':</strong> ' . htmlentities($valString) . '</div>'. "\n";
				}
			}
			$fileString .= '
		</div>
	</div>
			' . "\n";
			$fileString .= '	<div class="clear"><!-- Karmapa Tchenno --></div>' . "\n";
			$fileString .= '</div>' . "\n";
		}
		
		
		$fileString .= '	
		</div>'. $navigation .'
	</div>
</body>
</html>		
		';
		
		
		fwrite ($file, utf8_encode($fileString));
		
		
		fclose($file);
		
		return $filename;
	}
	
	
	function writeJs(){
		copy(str_replace('//','/',dirname(__FILE__).'/') .'../templates/analyse/app.js', $this->workingDir . '/app.js');
	}

}

?>

