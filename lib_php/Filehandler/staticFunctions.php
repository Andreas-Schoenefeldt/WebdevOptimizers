<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../CmdIO.php');

$io = new CmdIO();

$globalIgnoreFiles = array('.svn' => true, '.git' => true, '.DS_Store' => true, '.' => true, '..' => true);

/* ------------------------------------------------------------------------
 * A function to iterate recursivly over the contents of an folder. The fileFunction will be called on each file with the parameters of the current filepath and the starting directory
 *
 * @param $filepath - The directory to iterate over the content
 * @param $baseDirectory - the start directory of the function
 * @param $fileFunction - the function to call on the object
 * @param $silent - (default: false) switch debug messages on or off
 * ------------------------------------------------------------------------ */
function recurseIntoFolderContent($filepath, $baseDirectory, $fileFunction, $silent = false){
		
		global $io, $globalIgnoreFiles;
		
		$file = explode('/', $filepath);
		$fileName = $file[count($file) - 1];
		
		if (! array_key_exists($fileName, $globalIgnoreFiles) || !$globalIgnoreFiles[$fileName]) {
		
				if(is_dir($filepath)){
					$subDir = opendir($filepath); 
					if (! $silent) $io->cmd_print('> found dir .' . str_replace($baseDirectory, '', $filepath));
					while($entryName = readdir($subDir)) {
						if(! array_key_exists($entryName, $globalIgnoreFiles) || !$globalIgnoreFiles[$entryName]) {
							$ndir = $filepath.'/'.$entryName;
							recurseIntoFolderContent($ndir, $baseDirectory, $fileFunction, $silent);
						}
					}
					closedir($subDir);
				} else{
					$fileFunction($filepath, $baseDirectory);
				}
		
		}
}



function copyr($source, $dest){
    // Simple copy for a file
    if (is_file($source)) {
		$c = copy($source, $dest);
		chmod($dest, 0777);
		return $c;
    }
     
    // Make destination directory
    if (!is_dir($dest)) {
		$oldumask = umask(0);
		mkdir($dest, 0777);
		umask($oldumask);
    }
     
    // Loop through the folder
    $dir = dir($source);
    while (false !== $entry = $dir->read()) {
		// Skip pointers
		if ($entry == "." || $entry == "..") {
			continue;
		}
     
		// Deep copy directories
		if ($dest !== "$source/$entry") {
			copyr("$source/$entry", "$dest/$entry");
		}
    }
     
    // Clean up
    $dir->close();
    return true;
}




/* ------------------------------------------------------------------------
 * A function to iterate recursivly over the complete contents of an folder. Will also call the file Function, if a folder was found.
 *
 * @param $filepath - The directory to iterate over the content
 * @param $baseDirectory - the start directory of the function
 * @param $fileFunction - the function to call on the object
 * @param $silent - (default: false) switch debug messages on or off
 * ------------------------------------------------------------------------ */
function recurseOverCompleteStructure($filepath, $baseDirectory, $fileFunction, $silent = false){
		global $io, $globalIgnoreFiles;
		
		$file = explode('/', $filepath);
		$fileName = $file[count($file) - 1];
		
		if (! array_key_exists($fileName, $globalIgnoreFiles) || !$globalIgnoreFiles[$fileName]) {
				
				$fileFunction($filepath, $baseDirectory);
				
				if(is_dir($filepath)){
					$subDir = opendir($filepath);
					if (! $silent) $io->cmd_print('> found dir .' . str_replace($baseDirectory, '', $filepath));
					while($entryName = readdir($subDir)) {
						if (! array_key_exists($entryName, $globalIgnoreFiles) || !$globalIgnoreFiles[$entryName]) {
							$ndir = $filepath.'/'.$entryName;
							recurseOverCompleteStructure($ndir, $baseDirectory, $fileFunction, $silent);
						}
					}
					closedir($subDir);
				}
		}
}


function deleteFiles($folder) {
		if(is_dir($folder)){
				$subDir = opendir($folder);
				while($entryName = readdir($subDir)) {
						if($entryName != '.' && $entryName != '..' && $entryName != '.svn') {
							$ndir = $folder.'/'.$entryName;
							deleteFiles($ndir);
						}
				}
				
				closedir($subDir);
				rmdir($folder);
		} else {
			unlink($folder);	
		}
}

/**
 *	Function to generate the relativ include from a specific file into a root folder location
 *
 *	@param $relPath		The relative Path of the file in relation to the projects root folder
 *	@param $includeFile	The file location path starting from the root folder
 *
 *	@return				The relative include path. For example ../../../includes/func.php
 */
function getRootIncludeString($relPath, $includeFile){
	
	$relPath = normalizePath($relPath);
	$includeFile = normalizePath($includeFile);
	
	if (substr($relPath, 0 , 1) == '/') {
		$relPath = substr($relPath, 1);
	}
	
	$folderComponentsCount = count(explode('/', $relPath)) -1;
	
	return str_repeat('../', $folderComponentsCount) . $includeFile;
}


/**
 *	Function to remove a starting /
 *
 */
function normalizePath($relPath){
	if (substr($relPath, 0 , 1) == '/') {
		$relPath = substr($relPath, 1);
	}
	
	return $relPath;
}


function cleanupFilename($relPath, $extension = false) {

		$relPath = str_replace('&quot;', '', $relPath);
		$path_parts = pathinfo($relPath);
				
		$extension = (! $extension && array_key_exists('extension', $path_parts)) ? '.' . $path_parts['extension'] : '.' . $extension; 
		$folders = ($path_parts['dirname'] && $path_parts['dirname'] != '.') ? $path_parts['dirname'] . '/' : '';
		
		return $folders . $path_parts['filename'] . $extension;
}

/**
 *	Capitalises the first Letter of a String
 */
function capitalise($string) {
	return strtoupper(substr($string, 0,1)) . substr($string, 1);
}

function startsWith($haystack, $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle) {
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    $start  = $length * -1; //negative
    return (substr($haystack, $start) === $needle);
}





?>