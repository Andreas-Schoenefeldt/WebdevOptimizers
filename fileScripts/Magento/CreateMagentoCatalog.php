<?php
	
	date_default_timezone_set("Europe/Berlin");
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../../lib_php/CmdIO.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../../lib_php/Debug/libDebug.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../../lib_php/ComandLineTools/CmdParameterReader.php');
	require_once(str_replace('//','/',dirname(__FILE__).'/') .'../../lib_php/FileHandler/FixedFieldFileWriter.php');
	
	$io = new CmdIO();
	
	$params = new CmdParameterReader(
		$argv,
		array(
			'i' => array(
				'name' => 'input',
				'datatype' => 'String',
				'required' => true,
				
				'description' => 'the human readable input csv'
			),
			'o' => array(
				'name' => 'output',
				'datatype' => 'Sting',
				'default' => 'magento_import' . date('Y_m_d_h_i') . '.csv',
				
				'description' => 'The import filename'
				
			),
		),
		'A Script to create a demandware content import xml file in order to create multilingual sitespecific content assets.'
	);
	
	$configFileName = $params->getFileName();
	if(! file_exists($configFileName)) {
		$params->print_usage();
		$io->fatal("The config file " . $configFileName . " does not exist.", 'CreateMagentoCatalog');
	} else {
		// get the configuration
		try {
			include($configFileName);
		} catch (Exception $e) {
			$io->fatal($e->getMessage());
		}
		
		if (! isset($mappingConfig) || ! count($mappingConfig)) {
			$io->fatal('The given config file is empty or invalid: ' . $configFileName, 'CreateMagentoCatalog');
		}
		
		$inputFilePath = $params->getVal('i');
		if (! file_exists($inputFilePath)) {
			$io->fatal("The given input file " . $inputFilePath . 'does not exist.');
		}
		$io->out('> Writing file ' . $outputFilePath);
		
		$writer = new FixedFieldFileWriter($mappingConfig, $outputFilePath);
		$input = fopen($inputFilePath, 'r');
		
		$writer->printHeader();
		
		// getting the header -> index relation
		$headers = fgetcsv($input);
		
		while (($data = fgetcsv($input)) !== FALSE) {
			$configurableProductIdentifyerArr = array();
			$configProdArrayValues = array( '_super_products_sku' => array());
			$line = array();
			$configProdLine = array();
			for ($i = 0; $i < count($data); $i++) {
				$name = $headers[$i];
				$value = $writer->parseInput($data[$i], $name);
				
				if (! is_array($value)) {
					$line[$name] = $value;
					$configProdLine[$name] = $value;
				} else if (is_array($value) && $name == $configurable_product_attr['name']){
					$configurableProductIdentifyerArr = $value;
					$configProdArrayValues['_super_attribute_option'] = $value;
					$configProdArrayValues['_super_attribute_code'] = array();
					for ($k = 0; $k < count($value); $k++) { // adding the size assignment
						$configProdArrayValues['_super_attribute_code'][] = $configurable_product_attr['machineName'];
					}
				} else {
					// what to do with all the other arrays
					
					if ($name == $image_definition_attr['name']) {
						$configProdArrayValues['_media_position'] = array(); // add the image media position, as it was added in the import file
						$configProdArrayValues['_media_attribute_id'] = array();
						$configProdArrayValues['_media_is_disabled'] = array();
						$verifyedValue = array();
						for ($k = 0; $k < count($value); $k++) {
							
							// moving the images at this point already
							$magentoFilePath = generateAndMoveToMagentoImagePath($image_src_path, $image_target_path, $value[$k]);
							
							if ($magentoFilePath) {
								$verifyedValue[] = $magentoFilePath;
								$configProdArrayValues['_media_position'][] = $k + 1;
								$configProdArrayValues['_media_attribute_id'][] = 88;
								$configProdArrayValues['_media_is_disabled'][] = 0;
							}
						}
						
						$value = $verifyedValue;
					}
					
					$configProdArrayValues[$name] = $value;
				}
				
			}
			
			// if we have only 1 product, we are not creating a configurable one
			if (count($configurableProductIdentifyerArr) < 2 ) {
				$line[$skuFieldName] = $configProdLine[$skuFieldName] ;
				$line[$configurable_product_attr['name']] = $configurableProductIdentifyerArr[0];
				$line['visibility'] = 4;
				
				// setting up the images
				// TODO: What about more the one image?
				if (array_key_exists($image_definition_attr['name'], $configProdArrayValues) && count($configProdArrayValues[$image_definition_attr['name']]) > 0) {
					$line[$image_definition_attr['name']] = $configProdArrayValues[$image_definition_attr['name']][0];
					$line['_media_position'] = 1;
					$line['image'] = $configProdArrayValues[$image_definition_attr['name']][0];
					$line['small_image'] = $configProdArrayValues[$image_definition_attr['name']][0];
					$line['thumbnail'] = $configProdArrayValues[$image_definition_attr['name']][0];
					
					$line['_media_attribute_id'] = 88;
					$line['_media_is_disabled'] = 0;
				}
				
				$writer->printLine($line);
				
			} else {
				// writing the simple products for the configurable products
				for ($i = 0; $i < count($configurableProductIdentifyerArr); $i++) {
					
					$line[$skuFieldName] = $configProdLine[$skuFieldName] . '-' . $configurableProductIdentifyerArr[$i];
					$line[$configurable_product_attr['name']] = $configurableProductIdentifyerArr[$i];
					
					$configProdArrayValues['_super_products_sku'][] = $line[$skuFieldName];
					
					$writer->printLine($line);
				}
				
				// setting up the configurable product
				$configProdLine['_type'] = 'configurable';
				$configProdLine['has_options'] = 1;
				$configProdLine['required_options'] = 1;
				$configProdLine['visibility'] = 4;
				$configProdLine['qty'] = 0;
				
				// setting up the images
				if (array_key_exists($image_definition_attr['name'], $configProdArrayValues) && count($configProdArrayValues[$image_definition_attr['name']]) > 0) {
					$configProdLine['image'] = $configProdArrayValues[$image_definition_attr['name']][0];
					$configProdLine['small_image'] = $configProdArrayValues[$image_definition_attr['name']][0];
					$configProdLine['thumbnail'] = $configProdArrayValues[$image_definition_attr['name']][0];
				}
				
				$maxcount = 0;
				foreach ($configProdArrayValues as $name => $values) {
					if(count($values) > $maxcount) $maxcount = count($values); // to now how many option lines we have to write
					if(count($values) > 0) $configProdLine[$name] = $values[0];
				}
				
				// writing the configurable product
				$writer->printLine($configProdLine);
				
				// we start with 1, because the first options are already in the
				for ($i = 1; $i < $maxcount; $i++) {
					$optionLine = array();
					foreach ($configProdArrayValues as $name => $values) {
						if ($i < count($values)) $optionLine[$name] = $values[$i];
					}
					$writer->printLine($optionLine, 'configurableProduct');
				}
				
				
			}
		}
		
		$writer->close();
		
		
	}
	
	
	
	// this function will applay all the Magento transition, which is also done by the image upload
	function generateAndMoveToMagentoImagePath($image_src_path, $image_target_path, $image_name) {
		global $io;
		$functionName = 'IMAGE VERIFY';
		
		// first check the requirements
		if (! is_dir($image_target_path) ) $io->fatal('the variable $image_target_path must hold a valid folder, currently: ' . $image_target_path, $functionName );
		if ( ! ( is_dir($image_src_path) && file_exists($image_src_path)) ) $io->fatal('Something is wrong with the $image_src_path definition. Does this folder exist? ' . $image_target_path, $functionName );
		
		if (! $image_name) {
			$io->error('The function generateAndMoveToMagentoImagePath must be called with an image file');
			return null;
		}
		
		$targetFile = $image_src_path . $image_name;
		$magento_image_name = strtolower($image_name);
		if (! file_exists($targetFile)) {
			// fallback 1 .jpg .JPG case
			$parts = explode('.', $image_name);
			
			if (count($parts) != 2) {
				$io->error('Soemthing is wrong with the format of this image: ' . $image_name . ' it is skipped.', $functionName);
				return null;
			}
			
			$new_image_name = $parts[0] . '.' . strtolower($parts[1]);
			$new_target_file = $image_src_path . $new_image_name;
			
			if (! file_exists($new_target_file)) {
				$io->warn('The assigned image ' . $image_name . ' does not exist in the src folder and is skipped.', $functionName);
				return null;
			} else {
				// ok, we found it, and it will be silently replaced
				$image_name = $new_image_name;
				$targetFile = $new_target_file;
			}
			
		}
		
		if (! file_exists($image_target_path))  {
			$io->out('> Creating the image target folder ' . $image_target_path);
			mkdir($image_target_path);
		}
		
		$internal_magento_image_path = '/' . $magento_image_name;
		// creating the magento output folders
		
		/*
		 
		 // This logic was a mirrow of the export done by Magento
		 // beleving this blog: http://www.magentocommerce.com/boards/viewthread/6971/ it must all be in media/import/
		 
		for ($i = 0; $i < 2; $i++) {
			$subfolder = substr($magento_image_name, $i, 1) . '/';
			$internal_magento_image_path .= $subfolder;
			$image_target_path .= $subfolder;
			
			if (! file_exists($image_target_path)) {
				mkdir($image_target_path);
			}	
		}
		
		$internal_magento_image_path .= $magento_image_name;
		*/
		
		$image_target_path .= $magento_image_name;
		
		// coppy the image if not already there
		if (! file_exists($image_target_path)) copy($targetFile, $image_target_path) or $io->fatal('The image vould not be coppyed ' . $image_target_path, $functionName );
		
		return $internal_magento_image_path;
		
	}
	
?>