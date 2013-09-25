<?php

function date_operating_system_timezone_set() {
	$parts = explode(' ', exec('systemsetup -gettimezone')); // outcome is something like "Time Zone: Europe/London" for Mac OS X
	$timezone = $parts[count($parts) - 1];
	date_default_timezone_set($timezone);
}

?>