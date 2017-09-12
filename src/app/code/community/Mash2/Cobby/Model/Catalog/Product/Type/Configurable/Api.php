<?php
/**
 * Cobby catalog configurable product api
 */
class Mash2_Cobby_Model_Catalog_Product_Type_Configurable_Api extends Mage_Catalog_Model_Api_Resource
{
    /**
     * Assigns simple products to configurable product
     * @param int|sku $configurableProductId
     * @param array $simpleProductIds
     * @param array $usedAttributes
     * @param array $labels
     * @param array $prices
     * @param null $store
     * @return bool
     */
    public function assign($configurableProductId, $simpleProductIds, $usedAttributes = array(), $labels = array(), $prices = array(), $store = null)
	{
		$product = $this->_initProduct($configurableProductId, 'configurable', $store);

		$candidateAttributes = $this->_getConfigurableAttributesCandidates($product);

		if (count($usedAttributes) > 0) {
			foreach ($candidateAttributes as $key => $attribute) {
				if (!in_array($attribute->getAttributeCode(),$usedAttributes)
					&& !in_array($attribute->getAttributeId(),$usedAttributes)
				) {
					unset($candidateAttributes[$key]);
				}
			}
		}

		if (!$product->getTypeInstance()->getUsedProductAttributeIds()) {
			$usedIds = array();
			foreach ($candidateAttributes as $attr) {
				$usedIds[] = $attr->getAttributeId();
			}

			$product->getTypeInstance()->setUsedProductAttributeIds( array_values($usedIds) );
		}

		$product->setCanSaveConfigurableAttributes(true)
			->setConfigurableProductsData($this->_getConfigurableProductsData($product, $simpleProductIds))
			->setConfigurableAttributesData($product->getTypeInstance(true)->getConfigurableAttributesAsArray($product));

		try {
			$product->save();
		} catch (Exception $e) {
			$this->_fault('data_invalid', Mage::helper('catalog')->__('Saving product failed.'));
		}

		$product = $this->_initProduct($configurableProductId, 'configurable', $store);
		$product->setCanSaveConfigurableAttributes(true);

		$attributesData = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);
		foreach ($attributesData as $key => $item) {
			foreach ($candidateAttributes as $attribute) {
				if ($attribute->getAttributeId() != $item['attribute_id']) {
					continue;
				}

                $attributesData[$key]['use_default'] = '1';

				if (isset($labels[$attribute->getAttributeCode()])) {
					$attributesData[$key]['label'] = $labels[$attribute->getAttributeCode()];
                    $attributesData[$key]['use_default'] = '0';
				} elseif (isset($labels[$attribute->getAttributeId()])) {
					$attributesData[$key]['label'] = $labels[$attribute->getAttributeId()];
                    $attributesData[$key]['use_default'] = '0';
				}
			}

			foreach ($item['values'] as $itemKey => $itemValue) {
				if (!isset($prices[$itemValue['label']])) {
					continue;
				}
				$price = $prices[$itemValue['label']];

				$changed = false;
				if (isset($price['index'])) {
					$itemValue[$itemKey]['value_index'] = $price['index'];
					$changed = true;
				}
				if (isset($price['is_percent'])) {
					$itemValue['is_percent'] = $price['is_percent'];
					$changed = true;
				}
				if (isset($price['pricing_value'])) {
					$itemValue['pricing_value'] = $price['pricing_value'];
					$changed = true;
				}

				if ($changed) {
					unset($itemValue['use_default_value']);
					$attributesData[$key]['values'][$itemKey] = $itemValue;
				}
			}
		}
		$product->setConfigurableAttributesData($attributesData);
		$product->save();
 	
		return array('result'=> true);
	}

	public function _getConfigurableProductsData($configurableProduct, $simpleProducts) {
		$data = array();

		foreach ($simpleProducts as $simpleProductId) {
			if (!$simpleProductId instanceof Mage_Catalog_Model_Product) {
				$simpleProductId = $this->_initProduct($simpleProductId,'simple');
			}				
			$simpleProductId = $simpleProductId->getId();

			foreach($configurableProduct->getTypeInstance(true)->getConfigurableAttributes($configurableProduct) as $attribute) {
				$data[$simpleProductId] = array(
					'attributeId' => $attribute->getProductAttribute()->getId()
				);
			}
		}
		return $data;
	}

	/**
 	* Retrieve list of configurable attributes
 	* if this returns an empty array, the configurable attributes have not been defined
 	*
 	* @param string $productId
 	* @return array
 	*/
	public function candidateAttributes($productId)
	{	
		$product = $this->_initProduct($productId,'configurable');
	
		return $this->_getConfigurableAttributeCandidatesAsArray($product);
	}

	/**
 	* 
 	*
 	* @param string $productId
 	* @return array
 	*/
	public function usedAttributes($productId)
	{	
		return $this->_initProduct($productId,'configurable')
			->getTypeInstance()
			->getUsedProductAttributeIds();	
	}

    /**
     * Initialize and return product model
     *
     * @param int $productId
     * @param string $type
     * @param null $store
     * @return Mage_Catalog_Model_Product
     */
	protected function _initProduct($productId, $type = 'configurable', $store = null)
	{
		if ($productId instanceof Mage_Catalog_Model_Product) {
			return $productId;
		}

		$product = Mage::getModel('catalog/product')
			->setStoreId($this->_getStoreId($store));

		$product->load($productId);

		if (!$product->getId()) {
			$this->_fault('product_not_exists');
		}

		switch ($type) {
			case 'configurable':
				if (!$product->isConfigurable()) {
					$this->_fault('product_not_configurable');
				}
				break;
			case 'simple':
				if ($product->getTypeId() != Mage_Catalog_Model_Product_Type::TYPE_SIMPLE) {
					$this->_fault('product_not_simple');
				}
				break;
			default:
				$this->_fault('product_type_mismatch');
		}

		return $product;
	}

	protected function _getConfigurableAttributesCandidates($product) {
		$attributes = $product->getTypeInstance(true)->getSetAttributes($product);
	
		foreach($attributes as $key => $attribute) {
			if(!$product->getTypeInstance(true)->canUseAttribute($attribute, $product)) {
				unset($attributes[$key]);
			}   	
		}
		return $attributes;
	}

	protected function _getConfigurableAttributeCandidatesAsArray($product) {
		$attributes = $this->_getConfigurableAttributesCandidates($product);

		$confAttributes = array();
		foreach($attributes as $attribute) {
			$confAttributes[$attribute->getAttributeId()] = array(
				'id'	=> $attribute->getAttributeId(),
				'label' => $attribute->getFrontend()->getLabel(),
			);	
		}
		return $confAttributes;
	}
}