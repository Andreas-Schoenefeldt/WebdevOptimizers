DOSKEY mergeTranslation=php "C:\Users\Andreas\Dropbox\Programming\fileScripts\MergeTranslationFile.php" $*
DOSKEY repo=php "C:\Users\Andreas\Dropbox\Programming\fileScripts\codecontrol.php" $*
DOSKEY ls=DIR $* 
DOSKEY cp=COPY $* 
DOSKEY xcp=XCOPY $*
DOSKEY mv=MOVE $* 
DOSKEY clear=CLS
DOSKEY h=DOSKEY /HISTORY
DOSKEY alias=if ".$*." == ".." ( DOSKEY /MACROS ) else ( DOSKEY $* )