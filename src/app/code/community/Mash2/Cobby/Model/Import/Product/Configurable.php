<?php
class Mash2_Cobby_Model_Import_Product_Configurable extends Mash2_Cobby_Model_Import_Product_Abstract
{
    /**
     * Super attributes codes in a form of code => TRUE array pairs.
     *
     * @var array
     */
    protected $superAttributes = array();

    /**
     * @var Mash2_Cobby_Helper_Resource
     */
    private $resourceHelper;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->resourceHelper = Mage::helper('mash2_cobby/resource');
    }

    /**
     * @param $attrCode
     * @return bool
     */
    private function isAttributeSuper($attrCode)
    {
        return isset($this->superAttributes[$attrCode]);
    }

    private function isValidProductSuperData($productSuperData, $existingProductIds, $associatedProducts)
    {
        $productId = $productSuperData['product_id'];

        if (!in_array($productId, $existingProductIds)) {
            return false;
        }

        foreach ($productSuperData['attributes'] as $attribute) {
            $attributeCode = $attribute['code'];
            if (!$this->isAttributeSuper($attributeCode)) { // check attribute superity
                return false;
            }

            foreach ($attribute['options'] as $option) {
                $optionKey = strtolower($option['option_id']);
                if (!isset($this->superAttributes[$attributeCode]['options'][$optionKey])) {
                    return false;
                }
            }
        }

        $associatedIds = $productSuperData['associated_ids'];
        if (count(array_intersect_key($associatedIds, $associatedProducts)) !== count($associatedIds)) {
            //NOT required keys exist!
            return false;
        }

        return true;
    }

    /**
     * Returns attributes all values in label-value or value-value pairs form. Labels are lower-cased.
     *
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attribute
     * @return array
     */
    public function getAttributeOptions(Mage_Eav_Model_Entity_Attribute_Abstract $attribute)
    {
        $options = array();

        if ($attribute->usesSource()) {
            $index =  'value';

            // only default (admin) store values used
            $attribute->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);

            try {
                foreach ($attribute->getSource()->getAllOptions(false) as $option) {
                    $value = is_array($option['value']) ? $option['value'] : array($option);
                    foreach ($value as $innerOption) {
                        if (strlen($innerOption['value'])) { // skip ' -- Please Select -- ' option
                            $options[strtolower($innerOption[$index])] = $innerOption['value'];
                        }
                    }
                }
            } catch (Exception $e) {
                // ignore exceptions connected with source models
            }
        }
        return $options;
    }

    /**
     * Initialize configurable attributes
     *
     * @return array
     */
    private function loadConfigurableAttributes()
    {
        $result = array();

        /* @var $collection Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Attribute_Collection */
        $collection = Mage::getResourceModel('catalog/product_attribute_collection');
        $attributes = $collection
            ->addVisibleFilter()
            ->addFilter('is_global', 1)
            ->addFilter('frontend_input', 'select');

        foreach ($attributes as $attribute) {
            $attributeCode = $attribute->getAttributeCode();
            $attributeId   = $attribute->getId();

            $result[$attributeCode] = array(
                'id'               => $attributeId,
                'code'             => $attributeCode,
                'options'          => $this->getAttributeOptions($attribute)
            );
        }

        return $result;
    }

    public function import($rows)
    {
        $mainTable       = $this->resourceModel->getTableName('catalog/product_super_attribute');

        $productsSuperData = array();
        $usedAssociatedIds = array();

        $productIds = array_keys($rows);

        Mage::dispatchEvent('cobby_import_product_configurable_import_before', array( 'products' => $productIds ));

        foreach($rows as $productId => $row)
        {
            $attributes = $row['attributes'];
            $associatedIds = $row['associated_ids'];
            $attributeSet = $row['attr_set_code'];

            $productsSuperData[] = array(
                'product_id'        => $productId,
                'attr_set_code'     => $attributeSet,
                'attributes'        => $attributes,
                'associated_ids'    => $associatedIds
            );

            $usedAssociatedIds = array_merge($usedAssociatedIds, $associatedIds);
        }

        $usedAssociatedIds = array_unique($usedAssociatedIds);

        $this->superAttributes = $this->loadConfigurableAttributes();
        $associatedProductIds = $this->loadExistingProductIds($usedAssociatedIds);
        $existingProductIds = $this->loadExistingProductIds($productIds);

        $validProductsSuperData = array();
        foreach ($productsSuperData as $productSuperData) {
            if ($this->isValidProductSuperData($productSuperData, $existingProductIds, $associatedProductIds)) {
                $validProductsSuperData[] = $productSuperData;
            }
        }

        $importProductData = array(
            'attributes' => array(),
            'labels'     => array(),
            'pricing'    => array(),
            'super_link' => array(),
            'relation'   => array()
        );

        $nextAttrId      = $this->resourceHelper->getNextAutoincrement($mainTable);

        foreach($validProductsSuperData as $validProductSuperData)
        {
            $productId = $validProductSuperData['product_id'];
            $position = 0;
            foreach($validProductSuperData['attributes'] as $attribute)
            {
                $productSuperAttrId = $nextAttrId++;
                $attrCode = $attribute['code'];
                $attrId = $attribute['attribute_id'];
                $labels = $attribute['labels'];
                $options = $attribute['options'];

                $importProductData['attributes'][$productId][$attrId] = array(
                    'product_super_attribute_id' => $productSuperAttrId, 'position' => $position
                );
                $position++;

                foreach($options as $option)
                {
                    $importProductData['pricing'][] = array(
                        'product_super_attribute_id' => $productSuperAttrId,
                        'value_index'   => $option['option_id'],
                        'is_percent'    => $option['is_percent'] == true,
                        'pricing_value' => (float) $option['price'],
                        'website_id'    => $option['website_id']
                    );
                }

                foreach($labels as $labelData)
                {
                    $importProductData['labels'][] = array(
                        'product_super_attribute_id' => $productSuperAttrId,
                        'store_id'    => $labelData['store_id'],
                        'use_default' => $labelData['use_default'],
                        'value'       => $labelData['value']
                    );
                }
            }

            foreach($validProductSuperData['associated_ids'] as $associatedId)
            {
                $importProductData['super_link'][] = array(
                    'product_id' => $associatedId,
                    'parent_id' => $productId
                );

                $importProductData['relation'][] = array(
                    'parent_id' => $productId,
                    'child_id' => $associatedId
                );
            }
        }

        $this->saveData($importProductData);

        $this->touchProducts($productIds);

        Mage::dispatchEvent('cobby_import_product_configurable_import_after', array( 'products' => $productIds ));
        return array( 'result' => true );
    }

    protected function saveData($importProductData)
    {
        $mainTable       = $this->resourceModel->getTableName('catalog/product_super_attribute');
        $labelTable      = $this->resourceModel->getTableName('catalog/product_super_attribute_label');
        $priceTable      = $this->resourceModel->getTableName('catalog/product_super_attribute_pricing');
        $linkTable       = $this->resourceModel->getTableName('catalog/product_super_link');
        $relationTable   = $this->resourceModel->getTableName('catalog/product_relation');

        //remove old
        $quoted = $this->connection->quoteInto('IN (?)', array_keys($importProductData['attributes']));
        $this->connection->delete($mainTable, "product_id {$quoted}");
        $this->connection->delete($linkTable, "parent_id {$quoted}");
        $this->connection->delete($relationTable, "parent_id {$quoted}");

        $mainData = array();

        foreach ($importProductData['attributes'] as $productId => $attributesData) {
            foreach ($attributesData as $attrId => $row) {
                $row['product_id']   = $productId;
                $row['attribute_id'] = $attrId;
                $mainData[]          = $row;
            }
        }

        if ($mainData) {
            $this->connection->insertOnDuplicate($mainTable, $mainData);
        }

        if ($importProductData['labels']) {
            $this->connection->insertOnDuplicate($labelTable, $importProductData['labels']);
        }

        if ($importProductData['pricing']) {
            $this->connection->insertOnDuplicate(
                $priceTable,
                $importProductData['pricing'],
                array('is_percent', 'pricing_value')
            );
        }

        if ($importProductData['super_link']) {
            $this->connection->insertOnDuplicate($linkTable, $importProductData['super_link']);
        }

        if ($importProductData['relation']) {
            $this->connection->insertOnDuplicate($relationTable, $importProductData['relation']);
        }
    }
}