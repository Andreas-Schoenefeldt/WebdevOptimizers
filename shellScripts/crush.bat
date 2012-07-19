@echo off
set A=%1% 
set B=%A:~1,-6%

C:\Programme\PNGCrush\pngcrush.exe  -e _crushed.png -brute -rem alla -reduce %1 

echo erasing old
erase %1

php "C:\Dokumente und Einstellungen\Andreas\Eigene Dateien\Dropbox\My Dropbox\Programming\fileScripts\exchangePngCrush.php" %1% 