<?php

class Mash2_Cobby_Model_Catalog_Product_Attribute_Option_Api extends Mage_Catalog_Model_Api_Resource
{
    const ERROR_NOT_EXISTS = 'attribute_not_exists';
    const ERROR_OPTION_ALREADY_EXISTS = 'option_already_exists';

    public function export($attributeId)
    {
        $result = $this->options($attributeId, null);
        $transportObject = new Varien_Object();
        $transportObject->setOptions($result);
        $attribute = Mage::getModel('catalog/product')
            ->getResource()
            ->getAttribute($attributeId);

        Mage::dispatchEvent('cobby_catalog_product_attribute_option_export_after',
            array('attribute' => $attribute, 'transport' => $transportObject));

        return $transportObject->getOptions();
    }

    public function import($rows)
    {
        Mage::register('is_cobby_import', 1);
        $result = array();
        $model = Mage::getModel('catalog/resource_eav_attribute')
            ->setEntityTypeId(
                Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId()
            );

        foreach ($rows as $row) {
            $attributeId = $row['attribute_id'];
            $attribute = $model->load($attributeId);

            if (!$attribute->getId()) {
                $result[] = array('attribute_id' => $attributeId,
                    'options' => null,
                    'error_code' => self::ERROR_NOT_EXISTS);
            } else {
                foreach ($row['options'] as $requestedOption) {
                    $label = $requestedOption['labels']['0']['value'];
                    $options = $this->options($attributeId, $label);

                    if (empty($options) || (int)$requestedOption['option_id']) {
                        $this->_saveAttributeOptions($attribute, array($requestedOption));
                        $options = $this->options($attributeId, $label);
                        $result[] = array('attribute_id' => $attributeId, 'options' => $options);
                    } else {
                        $result[] = array('attribute_id' => $attributeId,
                            'options' => $options, 'error_code' => self::ERROR_OPTION_ALREADY_EXISTS);
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Retrieve attribute options
     *
     * @param int $attributeId
     * @param string $filter
     * @return array
     */
    public function options($attributeId, $filter)
    {
        $result = array();
        $stores = Mage::app()->getStores(true);
        foreach ($stores as $storeId => $store) {
            $attribute = Mage::getModel('catalog/product')
                ->getResource()
                ->getAttribute($attributeId)
                ->setStoreId($storeId);

            /* @var $attribute Mage_Catalog_Model_Entity_Attribute */
            if (!$attribute) {
                $this->_fault('not_exists');
            }

            //some magento extension use boolean as input type, but forgot to set source model too boolean
            //magento renders the fields properly because of dropdown fields
            //we are setting the source_model to boolean to get the localized values for yes/no fields
            if ($attribute->getFrontendInput() === 'boolean' &&
                ($attribute->getData('source_model') == '' || $attribute->getData('source_model') == 'eav/entity_attribute_source_table')
            ) {
                $attribute->setSourceModel('eav/entity_attribute_source_boolean');
            }

            if ($attribute->usesSource()) {
                if ($attribute->getSource() instanceof Mage_Eav_Model_Entity_Attribute_Source_Table) {
                    if ($filter) {
                        $options = Mage::getResourceModel('eav/entity_attribute_option_collection')
                            ->addFieldToFilter('tdv.value', $filter)
                            ->setPositionOrder('asc')
                            ->setAttributeFilter($attributeId)
                            ->setStoreFilter($storeId);

                        foreach ($options as $option) {
                            if ($option->getValue() == $filter) {
                                $result[] = array(
                                    'store_id' => $storeId,
                                    'value' => $option->getId(),
                                    'label' => $option->getValue(),
                                    'use_default' => $storeId > Mage_Core_Model_App::ADMIN_STORE_ID && $option->getStoreDefaultValue() == null
                                );
                            }
                        }
                    } else {
                        $options = Mage::getResourceModel('eav/entity_attribute_option_collection')
                            ->setPositionOrder('asc')
                            ->setAttributeFilter($attributeId)
                            ->setStoreFilter($storeId);

                        foreach ($options as $option){
                            $result[] = array(
                                'store_id' => $storeId,
                                'value' => $option->getId(),
                                'label' => $option->getValue(),
                                'use_default' => $storeId > Mage_Core_Model_App::ADMIN_STORE_ID && $option->getStoreDefaultValue() == null
                            );
                        }
                    }
                } else {
                    foreach ($attribute->getSource()->getAllOptions(false, true) as $optionId => $optionValue) {
                        $value = $optionValue['value'];
                        $label = $optionValue['label'];
                        if (is_array($value)) {
                            foreach ($value as $item) {
                                $result[] = array(
                                    'store_id' => $storeId,
                                    'value' => $item['value'],
                                    'label' => $label . ' - ' . $item['label'],
                                    'use_default' => false
                                );
                            }
                        } else {
                            $result[] = array(
                                'store_id' => $storeId,
                                'value' => $optionValue['value'],
                                'label' => $optionValue['label'],
                                'use_default' => false
                            );
                        }
                    }
                }
            }
        }
        return $result;
    }

    private function _saveAttributeOptions($attribute, $data)
    {
        $addedValues = array();
        if (!$attribute->usesSource()) {
            $this->_fault('invalid_frontend_input');
        }

        /** @var $helperCatalog Mage_Catalog_Helper_Data */
        $helperCatalog = Mage::helper('catalog');

        $optionValues = array();
        foreach ($data as $row) {

            $optionLabels = array();
            foreach ($row['labels'] as $label) {
                $storeId = $label['store_id'];
                $labelText = $helperCatalog->stripTags($label['value']);

                if (is_array($storeId)) {
                    foreach ($storeId as $multiStoreId) {
                        $optionLabels[$multiStoreId] = $labelText;
                        if ($storeId == 0) {
                            $addedValues[$labelText] = 0;
                        }
                    }
                } else {
                    $optionLabels[$storeId] = $labelText;
                    if ($storeId == 0) {
                        $addedValues[$labelText] = 0;
                    }
                }
            }

            $optionValues[$row['option_id']] = $optionLabels;
        }

        // data in the following format is accepted by the model
        // it simulates parameters of the request made to
        // Mage_Adminhtml_Catalog_Product_AttributeController::saveAction()
        $modelData = array(
            'option' => array(
                'value' => $optionValues,
//                'order' => array(
//                    'option_1' => (int) $data['order']
//                )
            )
        );

        $attribute->addData($modelData);
        try {
            $attribute->save();
        } catch (Exception $e) {
            $this->_fault('unable_to_add_option', $e->getMessage());
        }

        return;
    }

}