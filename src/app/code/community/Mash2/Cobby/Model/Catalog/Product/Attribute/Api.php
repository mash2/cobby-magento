<?php
/**
 * Cobby Catalog product attribute api
 *
 */
class Mash2_Cobby_Model_Catalog_Product_Attribute_Api extends Mage_Catalog_Model_Product_Attribute_Api
{
    /**
     * Product entity type id
     *
     * @var int
     */
    protected $_entityTypeId;

    /**
     * Constructor. Initializes default values.
     */
    public function __construct()
    {
        parent::__construct();
        $this->_ignoredAttributeTypes = array_diff($this->_ignoredAttributeTypes, array('media_image'));
        $this->_entityTypeId = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
    }

    /**
     * Get scope for attribute
     *
     * @param $attribute
     * @return string
     */
    private function _getScope($attribute)
    {
        $scope  = '';
        if ($attribute->getFrontendInput() == 'price') {
            $priceScope = (int) Mage::app()->getStore()->getConfig(Mage_Core_Model_Store::XML_PATH_PRICE_SCOPE);
            switch ($priceScope) {
                case 1:
                    $scope = 'website';
                    break;
                case 2:
                    $scope = 'store';
                    break;
                default:
                    $scope = 'global';
                    break;
            }
        } else {
            if (!$attribute->getId() || $attribute->isScopeGlobal()) {
                $scope = 'global';
            } elseif ($attribute->isScopeWebsite()) {
                $scope = 'website';
            } else {
                $scope = 'store';
            }
        }

        return $scope;
    }

    /**
     * Retrieve attributes from specified attribute set
     *
     * @param int $setId
     * @return array
     */
    public function export($setId)
    {
        $attributes = Mage::getModel('catalog/product')->getResource()
            ->loadAllAttributes()
            ->getSortedAttributes($setId);
        $result = array();

        foreach ($attributes as $attribute) {
            /* @var $attribute Mage_Catalog_Model_Resource_Eav_Attribute */
            if ((!$attribute->getId() || $attribute->isInSet($setId))
                && $this->_isAllowedAttribute($attribute) )
            {
                $storeLabels = array(
                    array(
                        'store_id' => 0,
                        'label' => $attribute->getFrontendLabel()
                    )
                );
                foreach ($attribute->getStoreLabels() as $store_id => $label) {
                    $storeLabels[] = array(
                        'store_id' => $store_id,
                        'label' => $label
                    );
                }

                $attributeData = array_merge(
                    $attribute->getData(),
                    array(
                        'scope' => $this->_getScope($attribute),
                        'apply_to' => $attribute->getApplyTo(),
                        'store_labels' => $storeLabels
                    ));

                $transportObject = new Varien_Object();
                $transportObject->setData($attributeData);

                Mage::dispatchEvent('cobby_catalog_product_attribute_export_after',
                    array('attribute' => $attribute, 'transport' => $transportObject));

                $result[] = $transportObject->getData();
            }
        }

        return $result;
    }

    /**
     * Get full information about attribute with list of options
     *
     * @param integer|string $attributeId attribute ID
     * @return array
     */
    public function info($attributeId)
    {
        $attribute = $this->_getAttribute($attributeId);

        $storeLabels = array(
            array(
                'store_id' => 0,
                'label' => $attribute->getFrontendLabel()
            )
        );
        foreach ($attribute->getStoreLabels() as $store_id => $label) {
            $storeLabels[] = array(
                'store_id' => $store_id,
                'label' => $label
            );
        }

        $result = array_merge(
            $attribute->getData(),
            array(
                'scope' => $this->_getScope($attribute),
                'apply_to' => $attribute->getApplyTo(),
                'store_labels' => $storeLabels
            ));

        // set options
        $options = $this->options($attribute->getId());
        // remove empty first element
        if ($attribute->getFrontendInput() != 'boolean') {
            array_shift($options);
        }

        if (count($options) > 0) {
            $result['options'] = $options;
        }

        $transportObject = new Varien_Object();
        $transportObject->setData($result);

        Mage::dispatchEvent('cobby_catalog_product_attribute_export_after',
            array('attribute' => $attribute, 'transport' => $transportObject));

        return $transportObject->getData();
    }

    /**
     * Load model by attribute ID
     * @param int|string $attributeId
     * @return Mage_Catalog_Model_Resource_Eav_Attribute
     */
    protected function _getAttribute($attributeId)
    {
        $model = Mage::getModel('catalog/resource_eav_attribute')
            ->setEntityTypeId($this->_entityTypeId)
            ->load($attributeId);

        if (!$model->getId()) {
            $this->_fault('attribute_not_exists');
        }

        return $model;
    }

    /**
     * Retrieve attribute options
     *
     * @param int $attributeId
     * @param string|int $store
     * @return array
     */
    public function options($attributeId, $store = null)
    {
        $result = array();
        $stores = Mage::app()->getStores(true);
        foreach($stores as $storeId => $storeValue){
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
            if ( $attribute->getFrontendInput() === 'boolean'  &&
                ($attribute->getData('source_model') == '' || $attribute->getData('source_model') == 'eav/entity_attribute_source_table') ) {
                $attribute->setSourceModel('eav/entity_attribute_source_boolean');
            }

            if ($attribute->usesSource()) {
                if( $attribute->getSource() instanceof Mage_Eav_Model_Entity_Attribute_Source_Table  ) {
                    $options = Mage::getResourceModel('eav/entity_attribute_option_collection')
                        ->setPositionOrder('asc')
                        ->setAttributeFilter($attributeId)
                        ->setStoreFilter($storeId);

                    foreach($options as $option)
                    {
                        $result[] = array(
                            'store_id' => $storeId,
                            'value' => $option->getId(),
                            'label' => $option->getValue(),
                            'use_default' => $storeId > Mage_Core_Model_App::ADMIN_STORE_ID && $option->getStoreDefaultValue() == null
                        ) ;
                    }
                }else {
                    foreach ($attribute->getSource()->getAllOptions(false, true) as $optionId => $optionValue) {
                        $value = $optionValue['value'];
                        $label = $optionValue['label'];
                        if (is_array($value)) {
                            foreach ($value as $item){
                                $result[] = array(
                                    'store_id' => $storeId,
                                    'value' => $item['value'],
                                    'label' => $label . ' - ' .$item['label'],
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

        $transportObject = new Varien_Object();
        $transportObject->setOptions($result);

        Mage::dispatchEvent('cobby_catalog_product_attribute_option_export_after',
            array('attribute' => $attribute, 'transport' => $transportObject));

        return $transportObject->getOptions();
    }
}