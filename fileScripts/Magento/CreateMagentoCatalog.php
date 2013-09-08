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
				'datatype' => 'String',
				'default' => 'magento_import_' . date('Y_m_d_h_i') . '.csv',
				
				'description' => 'The import filename'
				
			),
			'xi' => array(
				'name' => 'export_inventory',
				'datatype' => 'String',
				
				'description' => 'The inventory filename. If leaft blank, no inventory will be exported'
				
			),
		),
		'A Script to create a Magento Katalog and inventory Import file. Target of this script is a confog file.'
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
		
		$exportCounter = 0;
		$outFileName = $outputFilePath . 'magento_import_' . $exportCounter . '.csv';
		$io->out('> Writing file ' . $outFileName);
		
		$writer = new FixedFieldFileWriter($mappingConfig, $outFileName);
		
		$inventory = array();
		$inventoryExportFile = $params->getVal('xi') ? $params->getVal('xi') : $outputInventoryFilePath;
		if ($inventoryExportFile) {
			$invenotryWriter = new FixedFieldFileWriter($inventoryMappingConfig, $inventoryExportFile);
			$invenotryWriter->printHeader();
			
			// read the inventory
			$inventory = read_inventory($inventoryDefinitionPath, $invenotryWriter);
		} else {
			$io->error("You are missing the inventory output file definiton. Inventory will be ignored.");
		}
		
		$input = fopen($inputFilePath, 'r');
		
		$writer->printHeader();
		
		// getting the header -> index relation
		$headers = fgetcsv($input);
		
		$lineCounter = 0;
		$productCounter = 0;
		while (($data = fgetcsv($input)) !== FALSE) {
			$lineCounter++;
			
			$configurableProductIdentifyerArr = array();
			$configProdArrayValues = array( '_super_products_sku' => array());
			$line = array();
			$configProdLine = array();
			for ($i = 0; $i < count($data); $i++) {
				$name = $headers[$i];
				$value = $writer->parseInput($data[$i], $name);
				if (! is_array($value)) {
					
					switch ($name) {
						case $new_to_definition_attr['name']:
							if ($value) {
								$now = time() - 86400; // yesterday
								$line['news_from_date'] = $now;
								$configProdLine['news_from_date'] = $now;
							}
							break;
						case 'Kurzbeschreibung':
							if (strlen($value) > 170) {
								$value = substr($value, 0, 170) . '...';
								$io->warn("Short description is too long. Cutted at char 170.", "Line: " . $lineCounter);
							}
							break;
					} 					
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
					
					switch ($name) {
						case $image_definition_attr['name']:
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
							break;
					}
					
					$configProdArrayValues[$name] = $value;
				}
				
			}
			
			// if we have only 1 product, we are not creating a configurable one
			if (count($configurableProductIdentifyerArr) < 2 ) {
				$line[$skuFieldName] = $configProdLine[$skuFieldName] ;
				$line[$configurable_product_attr['name']] = $configurableProductIdentifyerArr[0];
				$line['visibility'] = 4;
				
				// setting the inventory
				if (array_key_exists($line[$skuFieldName], $inventory)) {
					$line[$inventory_definition_attr['name']] = $inventory[$line[$skuFieldName]]['qty'];
					if ($inventory[$line[$skuFieldName]]['qty'] > 0) {
						$line[$in_stock_definition_attr['name']] = 1;
					} else {
						$line[$in_stock_definition_attr['name']] = 0;
					}
				}
				
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
				
				$maxcount = 0;
				foreach ($configProdArrayValues as $name => $values) {
					if(count($values) > $maxcount) $maxcount = count($values); // to now how many option lines we have to write
					if(count($values) > 0) $line[$name] = $values[0];
				}
				
				$writer->printLine($line);
				$productCounter++;
				
				if ($inventoryExportFile) {
					$inventoryLine[$skuFieldName] = $configProdLine[$skuFieldName];
					$invenotryWriter->printLine($line);
				}
				
			} else {
				
				$configProdArrayValues['_super_attribute_price_corr'] = array();
				
				// writing the simple products for the configurable products
				for ($i = 0; $i < count($configurableProductIdentifyerArr); $i++) {
					
					$allProductsOutOfStock = true;
					$line['Kategorie'] = ''; // we are removing the Kategorie for this products, because only the configurable one should show up in the Kategory Sort area
					$line[$skuFieldName] = $configProdLine[$skuFieldName] . '-' . $configurableProductIdentifyerArr[$i];
					$line[$configurable_product_attr['name']] = $configurableProductIdentifyerArr[$i];
					
					$configProdArrayValues['_super_products_sku'][] = $line[$skuFieldName];
					
					// setting the inventory
					if (array_key_exists($line[$skuFieldName], $inventory)) {
						$line[$inventory_definition_attr['name']] = $inventory[$line[$skuFieldName]]['qty'];
						$configProdArrayValues['_super_attribute_price_corr'][] = $inventory[$line[$skuFieldName]]['price_correction'];
						if ($inventory[$line[$skuFieldName]]['qty'] > 0) {
							$allProductsOutOfStock = false;
							$line[$in_stock_definition_attr['name']] = 1;
						} else {
							$line[$in_stock_definition_attr['name']] = 0;
						}
					}
					
					$writer->printLine($line);
					$productCounter++;
					if ($inventoryExportFile) {
						$inventoryLine[$skuFieldName] = $line[$skuFieldName];
						$invenotryWriter->printLine($line);
					}
				}
				
				// setting up the configurable product
				$configProdLine['_type'] = 'configurable';
				$configProdLine['has_options'] = 1;
				$configProdLine['required_options'] = 1;
				$configProdLine['visibility'] = 4;
				if (! $allProductsOutOfStock) $configProdLine[$in_stock_definition_attr['name']] = 1;
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
				$productCounter++;
				
			}
			
			// we print the option lines for all products, that have it
			// we start with 1, because the first options are already in the first line
			for ($i = 1; $i < $maxcount; $i++) {
				$optionLine = array();
				foreach ($configProdArrayValues as $name => $values) {
					if ($i < count($values)) $optionLine[$name] = $values[$i];
				}
				$writer->printLine($optionLine, 'configurableProduct');
			}
			
			if ($productCounter >= MAX_PRODUCT_PER_IMPORT_FILE) {
				$writer->close();
				
				$exportCounter++;
				$outFileName = $outputFilePath . 'magento_import_' . $exportCounter . '.csv';
				$io->warn('> Reached product ' . $productCounter . ' starting new file ' . $outFileName, 'line ' . $lineCounter);
				
				$writer = new FixedFieldFileWriter($mappingConfig, $outFileName);
				$writer->printHeader();
				
				$productCounter = 0;
			}
			
		}
		
		$writer->close();
		if ($inventoryExportFile) {
			$invenotryWriter->close();
		}
		
		
	}
	
	// ----- Functions ------------------------------------
	
	
	// a function in order to get a inventory. returns an array with SKU => Inventory
	// @param FixexFieldFileWriter $invenotryWriter - The writer for parsing the values
	function read_inventory($inventoryDefinitionCSVFilePath, $invenotryWriter) {
		global $io;
		$inventory = array();
		if (! file_exists($inventoryDefinitionCSVFilePath)) {
			$io->error("The inventory file does not exist for this catalog. All Products will have the default qty (possibly 0)");
		} else {
			$io->out("> reading inventory definition $inventoryDefinitionCSVFilePath");
			
			$file = fopen($inventoryDefinitionCSVFilePath, 'r');
			$headers = fgetcsv($file); // get the header
			
			while (($data = fgetcsv($file)) !== FALSE) {
				$line = array();
				for ($i = 0; $i < count($data); $i++) {
					$name = $headers[$i];
					$value = $invenotryWriter->parseInput($data[$i], $name);
					
					$line[$name] = $value;
				}
				
				$inventory[$line['Artikelnummer']] = array('qty' => $line['Anzahl'], 'price_correction' => $line['Preisanpassung']);
			}
		}
		
		return $inventory;
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