<?php

require_once(str_replace('//','/',dirname(__FILE__).'/') .'../XMLFileparser.php');
require_once(str_replace('//','/',dirname(__FILE__).'/') .'DemandwareFormHandler.php');

class FormFileparser extends XMLFileparser {
	var $translatableFormAttributes = array('label', 'missing-error', 'value-error', 'parse-error', 'range-error', 'description');
	
	function __construct($file, $structureInit, $mode, $environment) {
		parent::__construct($file, $structureInit, $mode, $environment);
	}
	
	/**
	 *
	 * @param XMLNode $node		-	The xml node to parse
	 */
	function extractIncludes($node){
		
		$formHandler = new DemandwareFormHandler($this);
		
		// form include
		$formHandler->checkFormNodeForForms($node);
		
		
	}
	
	function extractKeys($node) {
		
		switch ($node->nodeName) {
			case 'field':
				
				for ($i = 0; $i < count($this->translatableFormAttributes); $i++){
					if($attr = $node->attr($this->translatableFormAttributes[$i])) {
						$this->addTranslationKey($attr, 'forms', $node->lineNumber);
					}
				}
				
				
				
				break;
			case 'option':
				if($attr = $node->attr('label')) {
					$this->addTranslationKey($attr, 'forms', $node->lineNumber);
				}
				break;
		}
	}
	
	
	function extractValues($node) {
		switch ($node->nodeName) {
			case 'field':
				
				for ($i = 0; $i < count($this->translatableFormAttributes); $i++){
					if($attr = $node->attr($this->translatableFormAttributes[$i])) {
						$this->addTranslationKey($attr, 'forms', $node->lineNumber);
					}
				}
				
				
				
				break;
			case 'option':
				if($attr = $node->attr('label')) {
					$this->addTranslationKey($attr, 'forms', $node->lineNumber);
				}
				break;
		}
	}

}

?>