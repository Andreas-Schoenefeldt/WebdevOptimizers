<?php
	date_default_timezone_set("Europe/Berlin");
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/CmdIO.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../lib_php/Debug/libDebug.php');
	
	$io = new CmdIO();
	
	// write mail content assets in different locals
	
	$entrys = array('de', 'de-AT', 'de-BE', 'de-LU', 'x-default', 'en-IE', 'es', 'fr', 'fr-BE', 'fr-LU', 'it-IT', 'lb-LU', 'nl-BE');
	$locals = array('en', 'fr', 'de', 'it', 'es');
	
	$templateDir = '/Volumes/Data/Andreas/Dropbox/Programming/fileScripts/WriteDWMailAssets/templates/';
	
	$workingDir = '/Users/Andreas/Desktop/Kunden_Working_Data/Quiksilver/DC/Mail-Template/MailContentLibrary/';
	$fileName = 'mailLibrary.xml';
	
	$translationMap = array(
		  'cap.contact-us' => array('en' => 'CONTACT US', 'fr' => 'CONTACTS', 'de' => 'KONTAKT', 'es' => 'CONTACTENOS')
		, 'cap.customer-service' => array('en' => 'CUSTOMER SERVICE', 'fr' => 'SERVICE CLIENT', 'de' => 'KUNDENSERVICE', 'es' => 'AREA DEL CLIENTE')
		, 'cap.about-dc-shoes' => array('en' => 'ABOUT DC SHOES', 'fr' => 'A PROPOS DE DC SHOES', 'de' => 'ÜBER DC SHOES', 'es' => 'ABOUT DC SHOES')
		, 'cap.our-brands' => array('en' => 'OUR BRANDS', 'fr' => 'NOS MARQUES', 'de' => 'UNSERE MARKEN', 'es' => 'NUESTRAS MARCAS')
		, 'cap.stay-connected' => array('en' => 'STAY CONNECTED', 'fr' => 'STAY CONNECTED', 'de' => 'Folge Uns', 'es' => 'SIGA CONECTADO')
		
		, 'text.email-us' => array('en' => 'Email Us', 'fr' => 'Par E-Mail', 'de' => 'Email', 'es' => 'Email')
		, 'text.call-us' => array('en' => 'Call Us', 'fr' => 'Par Téléphone', 'de' => 'Ruf uns an', 'es' => 'Teléfono')
		, 'text.delivery' => array('en' => 'Delivery', 'fr' => 'Modes d\'expédition', 'de' => 'Versandinformationen', 'es' => 'Envio')
		, 'text.payment' => array('en' => 'Payment', 'fr' => 'Moyens de Paiement', 'de' => 'Zahlungsmethoden', 'es' => 'Forma de pago')
		, 'text.returns' => array('en' => 'Returns', 'fr' => 'Retours', 'de' => 'Rückgaberecht', 'es' => 'Devolución')
		, 'text.sizing' => array('en' => 'Sizing', 'fr' => 'Grille de taille', 'de' => 'Größentabelle', 'es' => 'Guia de tallas')
		, 'text.my-account' => array('en' => 'My Account', 'fr' => 'Suivi de commande', 'de' => 'My Account', 'es' => 'Estado de pedid')
		, 'text.corporate' => array('en' => 'Corporate Info', 'fr' => 'Corporate Info', 'de' => 'Corporate Info', 'es' => 'Corporate Info')
		, 'text.careers' => array('en' => 'Careers', 'fr' => 'Travailler chez Dc Shoes', 'de' => 'Karriere', 'es' => 'Trabajar en DC Shoes')
		
		, 'text.free-shipping' => array('en' => 'FREE SHIPPING', 'fr' => 'LIVRAISON GRATUITE', 'de' => 'VERSANDKOSTENFREI', 'es' => 'ENTREGA GRATUITA')
		, 'text.all-orders' => array('en' => 'FOR ALL ORDERS', 'fr' => 'TOUTE COMMANDE', 'de' => 'FÜR ALLE BESTELLUNGEN', 'es' => 'VALIDO PARA CUALQUIER PEDIDO')
		, '' => array('en' => '', 'fr' => '', 'de' => '', 'es' => '')
		, '' => array('en' => '', 'fr' => '', 'de' => '', 'es' => '')
		, '' => array('en' => '', 'fr' => '', 'de' => '', 'es' => '')
	);
	
	$siteAssets = array(
		'email-footer' => array(
			'templateFile' => 'email-footer.html',
			'displayName' => 'Email Default Footer',
			'vars' => array(
				'email-us' => array(
					'GB' => 'http://www.dcshoes-uk.co.uk/on/demandware.store/Sites-DC-GB-Site/en_GB/CustomerService-ContactUs',
					'FR' => 'http://www.dcshoes.fr/on/demandware.store/Sites-DC-FR-Site/fr_FR/CustomerService-ContactUs',
					'BE' => 'http://www.dcshoes-belgium.be/on/demandware.store/Sites-DC-BE-Site/nl_BE/CustomerService-ContactUs',
					'DE' => 'http://www.dcshoes.de/on/demandware.store/Sites-DC-DE-Site/de_DE/CustomerService-ContactUs',
					'ES' => 'http://www.dcshoes.es/on/demandware.store/Sites-DC-ES-Site/es_ES/CustomerService-ContactUs',
					'LU' => 'http://www.dcshoes.lu/on/demandware.store/Sites-DC-LU-Site/lb_LU/CustomerService-ContactUs',
					'AT' => 'http://www.dcshoes-austria.at/on/demandware.store/Sites-DC-AT-Site/de_AT/CustomerService-ContactUs',
					'IE' => 'http://www.dcshoes.ie/on/demandware.store/Sites-DC-IE-Site/en_IE/CustomerService-ContactUs',
					'IT' => ''
				),
				'call-us' => array(
					'GB' => 'http://www.dcshoes-uk.co.uk/Call-Us/customer-service-call-us,en_GB,pg.html',
					'FR' => 'http://www.dcshoes.fr/Par-T%C3%A9l%C3%A9phone/customer-service-call-us,fr_FR,pg.html',
					'BE' => 'http://www.dcshoes-belgium.be/Call-Us/customer-service-call-us,nl_BE,pg.html',
					'DE' => 'http://www.dcshoes.de/Ruf-uns-an/customer-service-call-us,de_DE,pg.html',
					'ES' => 'http://www.dcshoes.es/Tel%C3%A9fono/customer-service-call-us,es_ES,pg.html',
					'LU' => 'http://www.dcshoes.lu/Call-Us/customer-service-call-us,lb_LU,pg.html',
					'AT' => 'http://www.dcshoes-austria.at/Ruf-uns-an/customer-service-call-us,de_AT,pg.html',
					'IE' => 'http://www.dcshoes.ie/Call-Us/customer-service-call-us,en_IE,pg.html',
					'IT' => ''
				),
				'delivery' => array(
					'GB' => 'http://www.dcshoes-uk.co.uk/DELIVERY/customer-service-shipping-methods-local,en_GB,pg.html',
					'FR' => 'http://www.dcshoes.fr/Mode-de-livraison/customer-service-shipping-methods-local,fr_FR,pg.html',
					'BE' => 'http://www.dcshoes-belgium.be/Mode-de-livraison/customer-service-shipping-methods-local,fr_BE,pg.html',
					'DE' => 'http://www.dcshoes.de/Versandinformationen/customer-service-shipping-methods-local,de_DE,pg.html',
					'ES' => 'http://www.dcshoes.es/MODO-DE-ENVIO/customer-service-shipping-methods-local,es_ES,pg.html',
					'LU' => 'http://www.dcshoes.lu/DELIVERY/customer-service-shipping-methods-local,lb_LU,pg.html',
					'AT' => 'http://www.dcshoes-austria.at/Versandinformationen/customer-service-shipping-methods-local,de_AT,pg.html',
					'IE' => 'http://www.dcshoes.ie/DELIVERY/customer-service-shipping-methods-local,en_IE,pg.html',
					'IT' => ''
				),
				'payment' => array(
					'GB' => 'http://www.dcshoes-uk.co.uk/Payment-Security/customer-service-payment-methods,en_GB,pg.html',
					'FR' => 'http://www.dcshoes.fr/Moyens-de-paiement-S%C3%A9curit%C3%A9/customer-service-payment-methods,fr_FR,pg.html',
					'BE' => 'http://www.dcshoes-belgium.be/Payment-Security/customer-service-payment-methods,nl_BE,pg.html',
					'DE' => 'http://www.dcshoes.de/Zahlungsmethoden-Sicherheit/customer-service-payment-methods,de_DE,pg.html',
					'ES' => 'http://www.dcshoes.es/Forma-de-pago-y-Seguridad/customer-service-payment-methods,es_ES,pg.html',
					'LU' => 'http://www.dcshoes.lu/Payment-Security/customer-service-payment-methods,lb_LU,pg.html',
					'AT' => 'http://www.dcshoes-austria.at/Zahlungsmethoden-Sicherheit/customer-service-payment-methods,de_AT,pg.html',
					'IE' => 'http://www.dcshoes.ie/Payment-Security/customer-service-payment-methods,en_IE,pg.html',
					'IT' => ''
				),
				'returns' => array(
					'GB' => 'http://www.dcshoes-uk.co.uk/Returns/customer-service-returns,en_GB,pg.html',
					'FR' => 'http://www.dcshoes.fr/Retours/customer-service-returns,fr_FR,pg.html',
					'BE' => 'http://www.dcshoes-belgium.be/Returns/customer-service-returns,nl_BE,pg.html',
					'DE' => 'http://www.dcshoes.de/R%C3%BCcksendungen/customer-service-returns,de_DE,pg.html',
					'ES' => 'http://www.dcshoes.es/Devoluci%C3%B3n/customer-service-returns,es_ES,pg.html',
					'LU' => 'http://www.dcshoes.lu/Returns/customer-service-returns,lb_LU,pg.html',
					'AT' => 'http://www.dcshoes-austria.at/R%C3%BCcksendungen/customer-service-returns,de_AT,pg.html',
					'IE' => 'http://www.dcshoes.ie/Returns/customer-service-returns,en_IE,pg.html',
					'IT' => ''
				),
				'sizing' => array (
					'GB' => 'http://www.dcshoes-uk.co.uk/on/demandware.store/Sites-DC-GB-Site/en_GB/SizeChart-Show',
					'FR' => 'http://www.dcshoes.fr/on/demandware.store/Sites-DC-FR-Site/fr_FR/SizeChart-Show',
					'BE' => 'http://www.dcshoes-belgium.be/on/demandware.store/Sites-DC-BE-Site/nl_BE/SizeChart-Show',
					'DE' => 'http://www.dcshoes.de/on/demandware.store/Sites-DC-DE-Site/de_DE/SizeChart-Show',
					'ES' => 'http://www.dcshoes.es/on/demandware.store/Sites-DC-ES-Site/es_ES/SizeChart-Show',
					'LU' => 'http://www.dcshoes.lu/on/demandware.store/Sites-DC-LU-Site/fr_LU/SizeChart-Show',
					'AT' => 'http://www.dcshoes-austria.at/on/demandware.store/Sites-DC-AT-Site/de_AT/SizeChart-Show',
					'IE' => 'http://www.dcshoes.ie/on/demandware.store/Sites-DC-IE-Site/en_IE/SizeChart-Show',
					'IT' => ''
				),
				'my-account' => array(
					'GB' => 'http://www.dcshoes-uk.co.uk/on/demandware.store/Sites-DC-GB-Site/en_GB/Account-Show',
					'FR' => 'http://www.dcshoes.fr/on/demandware.store/Sites-DC-FR-Site/fr_FR/Account-Show',
					'BE' => 'http://www.dcshoes-belgium.be/on/demandware.store/Sites-DC-BE-Site/nl_BE/Account-Show',
					'DE' => 'http://www.dcshoes.de/on/demandware.store/Sites-DC-DE-Site/de_DE/Account-Show',
					'ES' => 'http://www.dcshoes.es/on/demandware.store/Sites-DC-ES-Site/es_ES/Account-Show',
					'LU' => 'http://www.dcshoes.lu/on/demandware.store/Sites-DC-LU-Site/lb_LU/Account-Show',
					'AT' => 'http://www.dcshoes-austria.at/on/demandware.store/Sites-DC-AT-Site/de_AT/Account-Show',
					'IE' => 'http://www.dcshoes.ie/on/demandware.store/Sites-DC-IE-Site/en_IE/Account-Show',
					'IT' => ''
				)
			)
		),
		
		/*
		'email-header-menu' => array(
		
		),
		*/
		
		'email-header-logo' => array (
			'templateFile' => 'email-header-logo.html',
			'displayName' => 'Email Default Logo',
			'vars' => array(
				'homepage' => array(
					'GB' => 'http://www.dcshoes-uk.co.uk/',
					'FR' => 'http://www.dcshoes.fr/',
					'BE' => 'http://www.dcshoes-belgium.be/',
					'DE' => 'http://www.dcshoes.de/',
					'ES' => 'http://www.dcshoes.es/',
					'LU' => 'http://dcshoes.lu/',
					'AT' => 'http://www.dcshoes-austria.at/',
					'IE' => 'http://dcshoes.ie/',
					'IT' => ''
				)
			)
		),
		
		'email-header-banner' => array (
			'templateFile' => 'email-header-banner.html',
			'displayName' => 'Email Default Banner',
			'vars' => array(
				'homepage' => array(
					'GB' => 'http://www.dcshoes-uk.co.uk/',
					'FR' => 'http://www.dcshoes.fr/',
					'BE' => 'http://www.dcshoes-belgium.be/',
					'DE' => 'http://www.dcshoes.de/',
					'ES' => 'http://www.dcshoes.es/',
					'LU' => 'http://dcshoes.lu/',
					'AT' => 'http://www.dcshoes-austria.at/',
					'IE' => 'http://dcshoes.ie/',
					'IT' => ''
				)
			)
		)
		
	);
	
	
	// now write the file
	
	$outputFile = $workingDir . $fileName;
	$opFP = fopen($outputFile, 'w');
	
	fwrite($opFP, '<?xml version="1.0" encoding="UTF-8"?>' . "\n");
	fwrite($opFP, '<library xmlns="http://www.demandware.com/xml/impex/library/2006-10-31">' . "\n");
	
	foreach($siteAssets as $name => $vars) {
		
		fwrite($opFP, '<content content-id="' . $name . '">'. "\n");
		fwrite($opFP, '<display-name xml:lang="x-default">' . $vars['displayName'] . '</display-name>'. "\n");
		fwrite($opFP, '<online-flag>true</online-flag>'. "\n");
		fwrite($opFP, '<searchable-flag>true</searchable-flag>' . "\n");
		fwrite($opFP, '<page-attributes/>' . "\n");
		fwrite($opFP, '<custom-attributes>' . "\n");
		
		$templateFileName = $templateDir . $vars['templateFile'];
		$tfFp = fopen($templateFileName, 'r');
		$localisedContents = array();
		
		while ($line = fgets($tfFp, 2048)){
			
			// now do all the locals
			for ($i = 0; $i < count($entrys); $i++) {
				$entry = $entrys[$i];
				$locale = getLanguage($entry);
				$site = getSite($entry);
				
				if (! array_key_exists($entry, $localisedContents)) $localisedContents[$entry] = '<custom-attribute attribute-id="body" xml:lang="' . $entry . '">';
				
				$localisedContents[$entry] .=  str_replace(array("&", "<", ">", "\"", "'"), array("&amp;", "&lt;", "&gt;", "&quot;", "&apos;"), processTemplate($line, $locale, $site, $vars['vars']));
				
			}
		}
		
		// write the localised bodys
		foreach($localisedContents as $entry => $text) {
			$text .= '</custom-attribute>' . "\n";	
			fwrite($opFP, $text);
		}
		
		fwrite($opFP, '</custom-attributes>' . "\n");
		fwrite($opFP, '<folder-links>' . "\n");
		fwrite($opFP, "\t" . '<classification-link folder-id="EMAILS"/>' . "\n");
		fwrite($opFP, '</folder-links>' . "\n");
		fwrite($opFP, '</content>' . "\n\n");
	}
	
	fwrite($opFP, '</library>');

	
	/// Functions -----------------------
	
	
	function getLanguage($entry) {
		$splits = explode('-', $entry);
		return ($splits[0] == 'x') ? 'en' : $splits[0];
	}
	
	function getSite($entry) {
		$splits = explode('-', $entry);
		
		if (count($splits) == 1) return strtoupper($splits[0]);
		return ($splits[1] == 'default') ? 'GB' : strtoupper($splits[1]);
	}
	
	
	function processTemplate($string, $locale, $site, $vars) {
		
		preg_match_all('/\${[ ]*?_t\([ ]*?["\'][ ]*?(?P<key>.*?)[ ]*?["\'][ ]*?\)}/', $string, $matches, PREG_PATTERN_ORDER);
					
		for ($i = 0; $i < count($matches['key']); $i++){
			
			$key = $matches['key'][$i];
			
			$string = str_replace($matches[0][$i], translate($key, $locale), $string);
		}
		
		preg_match_all('/\${[ ]*?_var\([ ]*?["\'][ ]*?(?P<var>.*?)[ ]*?["\'][ ]*?\)}/', $string, $matches, PREG_PATTERN_ORDER);
					
		for ($i = 0; $i < count($matches['var']); $i++){
			
			$key = $matches['var'][$i];
			
			$string = str_replace($matches[0][$i], $vars[$key][$site], $string);
		}
		
		return $string;
		
	}
	
	function translate($key, $locale) {
		global $io, $translationMap;
		
		if (array_key_exists($key, $translationMap)) {
			if (array_key_exists($locale, $translationMap[$key])) {
				return $translationMap[$key][$locale];
			}
			
			foreach ($translationMap[$key] as $loc => $value) {
				$io->error('Locale ' . $locale . ' not found in translationMap for key ' .$key .'. Fallback to locale ' . $loc . ': "' . $value. '"' , 'translate()');
				return $value;
			}
		}
		$io->error($key . ' not found in translationMap.');
		return ':' . $key . ':';
	}
	
	
	


?>