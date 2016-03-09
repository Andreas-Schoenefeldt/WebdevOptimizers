# WebDev Optimzer Suite

This is a set of php scripts, that are supposed to make the life of a webdeveloper more easy. Its a loose set of functions mainly for automating repetive and anoying tasks, that are quite error prone to humans.

## main scripts
* AnalyseLogfiles.php - Automatied Logfile Parsing Script
* codecontrol.php - A Wrapper Script to unify SVN and git access
* CreateSeleniumTestSuite.php - Automate a Test generation for Selenium
* AddTranslation.php - A script, that can parse template files for plaon text strings and replace them by a i18n like localisation function like ${Resource.msg('account', 'account.headline.main', null)} - it is still verry experimental and should not be used without a possibility of rollback all the changes it has made.
* MergeTranslationFile.php - A script to export and import translations from and into csv

### MergeTranslationFile.php

In order to get the options:
```
$> php scripts/MergeTranslationFile.php
```

In order to export all translations of a demandware project
```
$> php scripts/MergeTranslationFile.php -xa -f <your export folder> <root pat of your project, eg cartridges for dw>
```

To import Translations from a demandware project
```
$> php scripts/MergeTranslationFile.php -f <your new translation.csv (seperated with , !)>  <root pat of your project, eg cartridges for dw>
```
