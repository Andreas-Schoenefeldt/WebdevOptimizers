#!/usr/local/bin/php -q
<?php
	date_default_timezone_set("Europe/Berlin");
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/CmdIO.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Filehandler/staticFunctions.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Debug/libDebug.php');
	$baseDirectory = $argv[1];
	
	if(!$baseDirectory){
		printUsage();
	} else {
		$io->cmd_print('Starting to crush as much as possible images in '.$baseDirectory, true, 1);
		$io->cmd_print('');
		
		$crushdata = array();
		
		$baseDirectory = ($baseDirectory == '.') ? getcwd() : $baseDirectory;
		
		$globalSaving = 0; // for statuses at the end
		$fileCount = 0;
		
		recurseIntoFolderContent($baseDirectory, $baseDirectory, function ($filepath, $baseDirectory){
			global $io;
			global $crushdata;
			global $globalSaving;
			global $fileCount;
			
			// try to open the hidden .crushdata file
			$hiddenpath = dirname($filepath) . "/.crushdata";
			
			// check if the file is already in the array, if not parse the file or create a new one	
			if(!array_key_exists($hiddenpath, $crushdata)) {
				$crushdata[$hiddenpath] = array();
				if (file_exists($hiddenpath)) {
					// parse the file
					
					$fp = @fopen($hiddenpath, "r") or die ("can't read file $hiddenpath.");
 
					while($line = fgets($fp, 1024)){
						$parts = explode('|',$line);
						$crushdata[$hiddenpath][$parts[0]] = array(intval($parts[1]), false);
					}

					fclose($fp);					
					
				} else {
					// create the file
					$fileHandle = fopen($hiddenpath, 'w') or die("can't open file $hiddenpath");
					fclose($fileHandle);
				}	
			}
			
			
			
			
			try{
				$pathCrumbs = explode('/',str_replace('"', '', $filepath));
				$reducedName = str_replace('_crushed', '', $pathCrumbs[count($pathCrumbs) - 1]);
				
				$ext = pathinfo($filepath, PATHINFO_EXTENSION);
				$lower_ext = strtolower($ext);
				
				if (in_array($lower_ext, array('png', 'jpg', 'jpeg'))){
					if(uncrushed($filepath, $hiddenpath)){
						
						clearstatcache();
						
						$oldSize = filesize($filepath);
						
						$target = str_replace('.' . $ext, '_crushed.' . $ext, $filepath);
						
						// use the power of the comand line tool, to handle the suported image files		
						switch ($lower_ext) {
							case 'png':
								
								exec ('pngcrush -e _crushed.png -brute -rem alla -reduce "'.$filepath.'"');
								
								// also use pngout
								$pngout_target = str_replace('.' . $ext, '_pngout_crushed.' . $ext, $filepath);
								exec ('pngout "'. $filepath . '" "'. $pngout_target . '"');
								
								// deside which one to take
								$newSize = filesize($target);
								$newSize2 = filesize($pngout_target);
								
								if ($newSize2 < $newSize) {
									$method = 'pngout';
									unlink($target);
									$target = $pngout_target;
								} else {
									$method = 'pngcrush';
									unlink($pngout_target);
								}
								
								break;
							case 'jpg':
							case 'jpeg':
								
								$targetJpgOptim = str_replace('.' . $ext, '_jpegoptim_crushed.' . $ext, $filepath);
								exec('cp "'.$filepath.'" "'.$targetJpgOptim.'"');
								exec ('jpegoptim "'.$targetJpgOptim.'"');
								
								exec ('jpegtran -copy none -optimize -perfect "' . $filepath . '" > "' . $target . '"');
								
								// deside which one to take
								$newSize = filesize($target);
								$newSize2 = filesize($targetJpgOptim);
								
								if ($newSize2 < $newSize) {
									$method = 'jpegoptim';
									unlink($target);
									$target = $targetJpgOptim;
								} else {
									$method = 'jpegtran';
									unlink($targetJpgOptim);
								}
								break;
						}
						
						$newSize = filesize($target);
						$saving = ($oldSize - $newSize);
						$savingPercent = round($saving / $oldSize * 100, 1);
						$saving = $saving.'';
						$savingPercent = $savingPercent.'';
						
						if (file_exists($target)){
						
							if ($saving > 0 && $newSize > 0) {
								unlink($filepath);
								exec("mv \"$target\" \"$filepath\"");
								
								$globalSaving += $saving;
								$fileCount++;
								
							} else {
								unlink($target);
								$saving = '('. $saving .') 0';
							}
							
						}
						
						// add the new filetime to the array
						$crushdata[$hiddenpath][$reducedName] = array(filemtime($filepath), true);
						
						
						for ($i = strlen($saving); $i < 8; $i++ ) {
							$saving = ' '.$saving;	
						}
						$io->cmd_print("  >  $saving bytes saved (".$savingPercent."%) [$method]:  $reducedName");
					
					} else {
						// $io->cmd_print("  >            no changes:  $reducedName");
					}
				} 
			} catch (Exception $e) {
				$io->cmd_print('some error with file: '.$filepath);
			}
		}, true);
		
		writeCrushdata();
		
		$units = array('byte', 'kByte', 'MB', 'GB', 'TB');
		$index = 0;
		
		while ($globalSaving > 1024) {
			$globalSaving = $globalSaving / 1024;
			$index++;
		}
		
		if ($fileCount) {
			$io->cmd_print('Total Savings in '.$baseDirectory.': ' .  round($globalSaving, 2) . ' ' . $units[$index] . ' in ' . $fileCount . ' file(s) ', true, 1);
		} else {
			$io->cmd_print('-- no changes in this folder since the last run --');
		}
			
		$io->cmd_print("\n");
	}
	
	
	
	
	
	
	
	function printUsage(){
		$io = new CmdIO();
		$io->cmd_print("A script to crush as much of a pngs inside a folderstructure as possible:\n\n   1. parameter: the rootfolder, that is meant to be crushed.\n", true, 1);
	}
	
	/**
	 * Checks and updates the crushdata representation
	 */
	function uncrushed($filepath, $dataPath){
		global $crushdata;
		
		$filepath = str_replace('_crushed', '', $filepath);
		
		$filetime = filemtime($filepath);
		$fileParts = explode('/', $filepath);
		$filename = $fileParts[count($fileParts) - 1];
		if (array_key_exists( $filename, $crushdata[$dataPath])){
			$crushdata[$dataPath][$filename][1] = true;
			if ($filetime <= $crushdata[$dataPath][$filename][0]){
				return false;
			}
		}
		
		$crushdata[$dataPath][$filename] = array($filetime, true);
		return true;
	}
	
	/**
	 * Function to write transform the $crushdata structure into files
	 */
	function writeCrushdata() {
		global $crushdata;
		
		foreach ($crushdata as $datafilepath => $filearray){
			
			if (count($filearray) > 0) {
			
				$fileHandle = fopen($datafilepath, 'w') or die("can't open file $datafilepath");
				foreach($filearray as $filename => $arFt){
					if($arFt[1]) fwrite($fileHandle, $filename."|".$arFt[0]."\n");
				}
				
				fclose($fileHandle);
			} else if (file_exists($datafilepath)) {
				// delet the file if it is not needed
				unlink($datafilepath);
			}
		}
		
	}

?>