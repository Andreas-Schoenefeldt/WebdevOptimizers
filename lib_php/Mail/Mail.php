<?php

/* -----------------------------------------------------------------------
 * Class encapsulates mail functions
 * ----------------------------------------------------------------------- */
class Mail {
	
	// sends mail regarding given parameters
	public static function sendMail($recipient, $subject, $messge, $sender) {
		return mail($recipient, $subject, $messge, $sender);
	}
	
	// sends all DW specific mails in given alertMails object
	public static function sendDWAlertMails($alertMails, $currentFolder, $alertConfiguration, $currentLayout) {
		if (!empty($alertMails)) {
			$senderemailaddress = $alertConfiguration['senderemailaddress'];
			$emailadresses = $alertConfiguration['emailadresses'];
			$subject = !empty($alertConfiguration['subject']) ? "{$alertConfiguration['subject']} ": "LOG ALERT: ";
			$storagePath = $currentFolder . '/sendalertmails'.$currentLayout.'.sdb';
			$tmpStoragePath = $currentFolder . '/sendalertmails'.$currentLayout.'.tmp';
			$mailStorage=array();
			
			// check for already sent mails
			if (file_exists($storagePath)) {
				$mailStorage=file($storagePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				$timestamp=array_pop($mailStorage);
				// if storage is older than one day delete it and make it possible to send mails again
				if ($timestamp!=date("mdy")) {
					$mailStorage=array();;
				}
			}
			// ensure to delete any tmp mail storage
			if (file_exists($tmpStoragePath)) {
				unlink($tmpStoragePath);
			}
			
			// send mail based on collected alertMails object
			foreach ($alertMails as $errorType => $errorTypeMail) {
				foreach ($errorTypeMail as $threshold => $thresholdMail) {
					$success=true;
					//only send when not already sent before
					if (!in_array($errorType.$threshold, $mailStorage)) {
						d("<br/>mail [$errorType]");
						$success = Mail::sendMail(	join(',',$emailadresses), 
									"$subject [$errorType] - ".$thresholdMail['subject'], 
									"An alert has been raised by Log File Monitor!\n\nError Type: $errorType\n\n".$thresholdMail['message'], 
									"From:" . $senderemailaddress
								);
					} 
					// fill mail storage
					if ($success) {
						file_put_contents($tmpStoragePath,$errorType.$threshold."\n", FILE_APPEND);
					}
				}
			}
			
			if (file_exists($tmpStoragePath)) {
				// rename tmp file when it exists
				file_put_contents($tmpStoragePath,date("mdy")."\n", FILE_APPEND);
				rename($tmpStoragePath, $storagePath);
			} else if (file_exists($storagePath)) {
				// otherwise delete mail storage when nothing new was sent
				unlink($storagePath);
			}
		}
	
	}

}

?>

