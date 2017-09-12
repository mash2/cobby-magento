<?php
/*
 * Copyright 2013 mash2 GbR http://www.mash2.com
 *
 * ATTRIBUTION NOTICE
 * Parts of this work are adapted from Andreas von Studnitz
 * Original title AvS_FastSimpleImport
 * The work can be found https://github.com/avstudnitz/AvS_FastSimpleImport
 *
 * ORIGINAL COPYRIGHT INFO
 *
 * category   AvS
 * package    AvS_FastSimpleImport
 * author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */


/**
 * Cobby import product model
 */
class Mash2_Cobby_Model_Import_Entity_Product extends Mage_ImportExport_Model_Import_Entity_Product
{
    const COBBY_DEFAULT = '[Use Default Value]';
    const COL_ENTITY_ID = '_id';

    protected $_typeModels = array();

    protected $_usedSkus = array();

    /**
     * constructor
     *
     * @param $args override initTypeModels
     *
     */
    public function __construct($args)
    {
        $this->setTypeModels($args['typeModels']);
        $this->setUsedSkus($args['usedSkus']);
        $this->_indexValueAttributes = array_merge($this->_indexValueAttributes, $this->getSelectAndMultiSelectAttributeCodes());

        parent::__construct();
    }

     /**
     * set Type Models
     *
     * @param $typeModels
     */
    public function setTypeModels($typeModels)
    {
        if(!is_null($typeModels) && !empty($typeModels)){
            $this->_typeModels = $typeModels;
        }
    }

    public function setUsedSkus($skus)
    {
        if(!is_null($skus) && !empty($skus)){
            $this->_usedSkus = $skus;
        }
    }

    protected function getSelectAndMultiSelectAttributeCodes()
    {
        $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
            ->addFieldToFilter('frontend_input', (array('select', 'multiselect')))
            ->load();

        $valueAttributes = array();
        foreach($attributes as $attribute)
        {
            $valueAttributes[] = $attribute->getAttributeCode();
        }
        return $valueAttributes;
    }

    protected function _getCobbyModels()
    {
        return array(
            'simple'        => 'mash2_cobby/import_entity_product_type_simple',
            'configurable'  => 'mash2_cobby/import_entity_product_type_configurable',
            'virtual'       => 'mash2_cobby/import_entity_product_type_simple',
            'grouped'       => 'mash2_cobby/import_entity_product_type_grouped',
            'bundle'        => 'mash2_cobby/import_entity_product_type_bundle'
        );
    }

    /**
     * Initialize product type models.
     *
     * @throws Exception
     * @return Mage_ImportExport_Model_Import_Entity_Product
     */
    protected function _initTypeModels()
    {
        $typeMap = $this->_getCobbyModels();

        foreach ($typeMap as $type => $typeModel) {
            if( !empty($this->_typeModels) && !in_array($type, $this->_typeModels))
                continue;

            if (!($model = Mage::getModel($typeModel, array($this, $type)))) {
                Mage::throwException("Entity type model '{$typeModel}' is not found");
            }
            if (! $model instanceof Mage_ImportExport_Model_Import_Entity_Product_Type_Abstract) {
                Mage::throwException(
                    Mage::helper('importexport')->__('Entity type model must be an instance of Mage_ImportExport_Model_Import_Entity_Product_Type_Abstract')
                );
            }
            if ($model->isSuitable()) {
                $this->_productTypeModels[$type] = $model;
            }
            $this->_particularAttributes = array_merge(
                $this->_particularAttributes,
                $model->getParticularAttributes()
            );
        }
        // remove doubles
        $this->_particularAttributes = array_unique($this->_particularAttributes);

        return $this;
    }

    /**
     * Set import source
     *
     * @param $source
     * @return $this
     */
    public function setArraySource($source)
    {
        $this->_source = $source;
        $this->_dataValidated = false;

        return $this;
    }

    /**
     * Set import behavior
     *
     * @param $behavior
     */
    public function setBehavior($behavior)
    {
        $this->_parameters['behavior'] = $behavior;

        return $this;
    }

    /**
     * Validate import data
     *
     * @return mixed
     */
    public function validateData()
    {
        $this->_particularAttributes = array_merge($this->_particularAttributes, array('_id'));

        return parent::validateData();
    }

    public function validateRow(array $rowData, $rowNum)
    {
        $rowScope = $this->getRowScope($rowData);

        if (self::SCOPE_DEFAULT == $rowScope) { // SKU is specified, row is SCOPE_DEFAULT, new product block begins
            $sku = $rowData[self::COL_SKU];
            $entityId = $rowData[self::COL_ENTITY_ID];

            if (!empty($entityId) && !isset($this->_oldSku[$sku])) { // can we get all necessary data from existant DB product?
                $this->addRowError('sku ' .$sku. ' does not match product id ' .$entityId, $rowNum);
            }
        }
        
        return parent::validateRow($rowData, $rowNum);
    }

    /**
     * Create Product entity from raw data.
     *
     * @throws Exception
     * @return bool Result of operation.
     */
    public function _importData()
    {
        //$result = parent::_importData();
        // we use parent code, to skip saveData for configurables
        $this->_saveProducts();
        $this->_saveCustomOptions();
        foreach ($this->_productTypeModels as $productType => $productTypeModel) {
            if($productType != Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE){
                $productTypeModel->saveData();
            }
        }
        Mage::dispatchEvent('catalog_product_import_finish_before', array('adapter'=>$this));

        return true;
    }
	
	public function getProcessedProducts()
    {
        return $this->_newSku;
    }

    protected function _saveProducts()
    {
        /** @var $resource Mage_ImportExport_Model_Import_Proxy_Product_Resource */
        $resource = Mage::getModel('importexport/import_proxy_product_resource');
        $priceIsGlobal = Mage::helper('catalog')->isPriceGlobal();
        $productLimit = null;
        $productsQty = null;
        $productIds = array();

        $existingStoreIds = array_keys(Mage::app()->getStores(true));

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $entityRowsIn = array();
            $entityRowsUp = array();
            $attributes = array();
            $websites = array();
            $tierPrices = array();
            $previousType = null;
            $previousAttributeSet = null;

            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }
                $rowScope = $this->getRowScope($rowData);

                if (self::SCOPE_DEFAULT == $rowScope) {
                    $rowSku = $rowData[self::COL_SKU];

                    // 1. Entity phase
                    if (isset($this->_oldSku[$rowSku])) { // existing row
                        $entityRowsUp[] = array(
                            'updated_at' => now(),
                            'entity_id' => $this->_oldSku[$rowSku]['entity_id']
                        );
                        $productIds[] = $this->_oldSku[$rowSku]['entity_id'];
                    } else { // new row
                        if (!$productLimit || $productsQty < $productLimit) {
                            $entityRowsIn[$rowSku] = array(
                                'entity_type_id' => $this->_entityTypeId,
                                'attribute_set_id' => $this->_newSku[$rowSku]['attr_set_id'],
                                'type_id' => $this->_newSku[$rowSku]['type_id'],
                                'sku' => $rowSku,
                                'created_at' => now(),
                                'updated_at' => now()
                            );
                            $productsQty++;
                        } else {
                            $rowSku = null; // sign for child rows to be skipped
                            $this->_rowsToSkip[$rowNum] = true;
                            continue;
                        }
                    }
                } elseif (null === $rowSku) {
                    $this->_rowsToSkip[$rowNum] = true;
                    continue; // skip rows when SKU is NULL
                } elseif (self::SCOPE_STORE == $rowScope) { // set necessary data from SCOPE_DEFAULT row
                    $rowData[self::COL_TYPE] = $this->_newSku[$rowSku]['type_id'];
                    $rowData['attribute_set_id'] = $this->_newSku[$rowSku]['attr_set_id'];
                    $rowData[self::COL_ATTR_SET] = $this->_newSku[$rowSku]['attr_set_code'];
                }
                if (!empty($rowData['_product_websites'])) { // 2. Product-to-Website phase
                    $websites[$rowSku][$this->_websiteCodeToId[$rowData['_product_websites']]] = true;
                }
                if (!empty($rowData['_tier_price_website'])) { // 4. Tier prices phase
                    $tierPrices[$rowSku][] = array(
                        'all_groups' => $rowData['_tier_price_customer_group'] == self::VALUE_ALL,
                        'customer_group_id' => $rowData['_tier_price_customer_group'] == self::VALUE_ALL ?
                            0 : $rowData['_tier_price_customer_group'],
                        'qty' => $rowData['_tier_price_qty'],
                        'value' => $rowData['_tier_price_price'],
                        'website_id' => self::VALUE_ALL == $rowData['_tier_price_website'] || $priceIsGlobal ?
                            0 : $this->_websiteCodeToId[$rowData['_tier_price_website']]
                    );
                }

                //skip 5. Media gallery phase
                $rowStore = self::SCOPE_STORE == $rowScope ? $this->_storeCodeToId[$rowData[self::COL_STORE]] : 0;

                // 6. Attributes phase
                $productType = $rowData[self::COL_TYPE];
                if (!is_null($rowData[self::COL_TYPE])) {
                    $previousType = $rowData[self::COL_TYPE];
                }
                if (!is_null($rowData[self::COL_ATTR_SET])) {
                    $previousAttributeSet = $rowData[Mage_ImportExport_Model_Import_Entity_Product::COL_ATTR_SET];
                }
                if (self::SCOPE_NULL == $rowScope) {
                    // for multiselect attributes only
                    if (!is_null($previousAttributeSet)) {
                        $rowData[Mage_ImportExport_Model_Import_Entity_Product::COL_ATTR_SET] = $previousAttributeSet;
                    }
                    if (is_null($productType) && !is_null($previousType)) {
                        $productType = $previousType;
                    }
                    if (is_null($productType)) {
                        continue;
                    }
                }
                $rowData = $this->_productTypeModels[$productType]->prepareAttributesForSave($rowData);
                $attributes = $this->_prepareAttributes($rowData, $rowScope, $attributes, $rowSku, $rowStore);
            }
            $this->_saveProductEntity($entityRowsIn, $entityRowsUp)
                ->_saveProductWebsites($websites)
                ->_saveProductTierPrices($tierPrices)
                ->_saveProductAttributes($attributes);



        }

        if (!empty($productIds)) {
            Mage::getModel('mash2_cobby/product')->updateHash($productIds);
            Mage::helper('mash2_cobby/queue')->enqueueAndNotify('product', 'save', $productIds);
        }

        return $this;
    }

    /**
     * Retrieve pattern for time formatting
     *
     * @return string
     */
    protected function _getStrftimeFormat()
    {
        return Varien_Date::convertZendToStrftime(Varien_Date::DATETIME_INTERNAL_FORMAT, true, true);
    }

    /**
     * Retrieve attribute by specified code
     *
     * @param string $code
     * @return Mage_Eav_Model_Entity_Attribute_Abstract
     */
    protected function _getAttribute($code)
    {
        $attribute = Mage::getSingleton('importexport/import_proxy_product_resource')->getAttribute($code);
        $backendModelName = (string)Mage::getConfig()->getNode(
            'global/importexport/import/catalog_product/attributes/' . $attribute->getAttributeCode() . '/backend_model'
        );
        if (!empty($backendModelName)) {
            $attribute->setBackendModel($backendModelName);
        }
        return $attribute;
    }

    /**
     * Prepare attributes data
     *
     * @param array $rowData
     * @param int $rowScope
     * @param array $attributes
     * @param string|null $rowSku
     * @param int $rowStore
     * @return array
     */
    protected function _prepareAttributes($rowData, $rowScope, $attributes, $rowSku, $rowStore)
    {
        $product = Mage::getModel('importexport/import_proxy_product', $rowData);

        foreach ($rowData as $attrCode => $attrValue) {
            $attribute = $this->_getAttribute($attrCode);
            if ('multiselect' != $attribute->getFrontendInput()
                && self::SCOPE_NULL == $rowScope
            ) {
                continue; // skip attribute processing for SCOPE_NULL rows
            }
            $attrId = $attribute->getId();
            $backModel = $attribute->getBackendModel();
            $attrTable = $attribute->getBackend()->getTable();
            $storeIds = array(0);

            if($attrValue != self::COBBY_DEFAULT)
            {
                if ('datetime' == $attribute->getBackendType())
                {
                    if ($attrValue !== null && $attrValue != '')
                        $attrValue = gmstrftime($this->_getStrftimeFormat(), strtotime($attrValue));
                    else
                        $attrValue = NULL;
                } elseif('decimal' == $attribute->getBackendType() && $attrValue === ''){
                    $attrValue = NULL;
                } elseif ('url_key' == $attribute->getAttributeCode()) {
                    if (empty($attrValue)) {
                        $attrValue = $product->formatUrlKey($product->getName());
                    }else {
                        $attrValue = $product->formatUrlKey($product->getUrlKey());

                    }
                } elseif ($backModel) {
                    $attribute->getBackend()->beforeSave($product);
                    $attrValue = $product->getData($attribute->getAttributeCode());
                }
            }

            if (self::SCOPE_STORE == $rowScope) {
                if (self::SCOPE_WEBSITE == $attribute->getIsGlobal()) {
                    // always init with storeIds from website
                    $storeIds = $this->_storeIdToWebsiteStoreIds[$rowStore];
                } elseif (self::SCOPE_STORE == $attribute->getIsGlobal()) {
                    $storeIds = array($rowStore);
                }
            }
            foreach ($storeIds as $storeId) {
                if ('multiselect' == $attribute->getFrontendInput()) {
                    if (!isset($attributes[$attrTable][$rowSku][$attrId][$storeId])) {
                        $attributes[$attrTable][$rowSku][$attrId][$storeId] = $attrValue;
                    } else {
                        $attributes[$attrTable][$rowSku][$attrId][$storeId] .= ',' . $attrValue;
                    }
                } else {
                    $attributes[$attrTable][$rowSku][$attrId][$storeId] = $attrValue;
                }
            }
            $attribute->setBackendModel($backModel); // restore 'backend_model' to avoid 'default' setting
        }
        return $attributes;
    }



    /**
     * Save product attributes.
     *
     * @param array $attributesData
     * @return $this
     */
    protected function _saveProductAttributes(array $attributesData)
    {
        foreach ($attributesData as $tableName => $skuData) {
            $tableData = array();

            foreach ($skuData as $sku => $attributes) {
                $productId = $this->_newSku[$sku]['entity_id'];

                foreach ($attributes as $attributeId => $storeValues) {
                    foreach ($storeValues as $storeId => $storeValue) {
                        if ( $storeValue == self::COBBY_DEFAULT) {
                            //TODO: evtl delete mit mehreren daten auf einmal
                            /** @var Magento_Db_Adapter_Pdo_Mysql $connection */
                            $connection = $this->_connection;
                            $connection->delete($tableName, array(
                                'entity_id=?'      => (int) $productId,
                                'entity_type_id=?' => (int) $this->_entityTypeId,
                                'attribute_id=?'   => (int) $attributeId,
                                'store_id=?'       => (int) $storeId,
                            ));
                        } else {
                            $tableData[] = array(
                                'entity_id'      => $productId,
                                'entity_type_id' => $this->_entityTypeId,
                                'attribute_id'   => $attributeId,
                                'store_id'       => $storeId,
                                'value'          => $storeValue
                            );
                        }
                    }
                }
            }

            if (count($tableData)) {
                $this->_connection->insertOnDuplicate($tableName, $tableData, array('value'));
            }
        }
        return $this;
    }

    /**
     * Check one attribute. Can be overridden in child.
     *
     * @param string $attrCode Attribute code
     * @param array $attrParams Attribute params
     * @param array $rowData Row data
     * @param int $rowNum
     * @return boolean
     */
    public function isAttributeValid($attrCode, array $attrParams, array $rowData, $rowNum)
    {
        if($rowData[$attrCode] == self::COBBY_DEFAULT)
            return true;

        $message = '';
        switch ($attrParams['type']) {
            case 'varchar':
                $val   = Mage::helper('core/string')->cleanString($rowData[$attrCode]);
                $valid = Mage::helper('core/string')->strlen($val) < self::DB_MAX_VARCHAR_LENGTH;
                $message = 'String is too long, only ' . self::DB_MAX_VARCHAR_LENGTH . ' characters allowed.';
                break;
            case 'decimal':
                $val   = trim($rowData[$attrCode]);
                $valid = (float)$val == $val;
                $message = 'Decimal value expected.';
                break;
            case 'select':
            case 'multiselect':
                $valid = in_array($rowData[$attrCode], $attrParams['options']);
                $message = 'Possible options are: ' . implode(', ', array_values($attrParams['options']));
                break;
            case 'int':
                $val   = trim($rowData[$attrCode]);
                $valid = (int)$val == $val;
                $message = 'Integer value expected.';
                break;
            case 'datetime':
                $val   = trim($rowData[$attrCode]);
                $valid = strtotime($val) !== false
                    || preg_match('/^\d{2}.\d{2}.\d{2,4}(?:\s+\d{1,2}.\d{1,2}(?:.\d{1,2})?)?$/', $val);
                $message = 'Datetime value expected.';
                break;
            case 'text':
                $val   = Mage::helper('core/string')->cleanString($rowData[$attrCode]);
                $valid = Mage::helper('core/string')->strlen($val) < self::DB_MAX_TEXT_LENGTH;
                $message = 'String is too long, only ' . self::DB_MAX_TEXT_LENGTH . ' characters allowed.';
                break;
            default:
                $valid = true;
                break;
        }

        if (!$valid) {
            $this->addRowError(Mage::helper('importexport')->__("Invalid value for '%s'") . '. ' . $message, $rowNum, $attrCode);
        } elseif (!empty($attrParams['is_unique'])) {
            if (isset($this->_uniqueAttributes[$attrCode][$rowData[$attrCode]])) {
                $this->addRowError(Mage::helper('importexport')->__("Duplicate Unique Attribute for '%s'"), $rowNum, $attrCode);
                return false;
            }
            $this->_uniqueAttributes[$attrCode][$rowData[$attrCode]] = true;
        }
        return (bool) $valid;
    }

    /**
     * Save product websites.
     *
     * @param array $websiteData
     * @return Mage_ImportExport_Model_Import_Entity_Product
     */
    protected function _saveProductWebsites(array $websiteData)
    {
        static $tableName = null;

        if (!$tableName) {
            $tableName = Mage::getModel('importexport/import_proxy_product_resource')->getProductWebsiteTable();
        }
        if ($websiteData) {
            $websitesData = array();

            foreach ($websiteData as $delSku => $websites) {
                $productId      = $this->_newSku[$delSku]['entity_id'];

                foreach (array_keys($websites) as $websiteId) {
                    $websitesData[] = array(
                        'product_id' => $productId,
                        'website_id' => $websiteId
                    );
                }
            }
            if ($websitesData) {
                $this->_connection->insertOnDuplicate($tableName, $websitesData);
            }
        }
        return $this;
    }

    protected function _initCategories()
    {
        //we don't import categories here, just skip
        return $this;
    }

    /**
     * Initialize existent product SKUs.
     *
     * @return Mage_ImportExport_Model_Import_Entity_Product
     */
    protected function _initSkus()
    {
        if($this->_usedSkus) {
            $columns = array('entity_id', 'type_id', 'attribute_set_id', 'sku');
            $productTable   = Mage::getSingleton('core/resource')->getTableName('catalog/product');
            $select = $this->_connection->select()
                ->from($productTable, $columns)
                ->where('sku in (?)', $this->_usedSkus);

            foreach ($this->_connection->fetchAll($select) as $info) {
                $typeId = $info['type_id'];
                $sku = $info['sku'];
                $this->_oldSku[$sku] = array(
                    'type_id'        => $typeId,
                    'attr_set_id'    => $info['attribute_set_id'],
                    'entity_id'      => $info['entity_id'],
                    'supported_type' => isset($this->_productTypeModels[$typeId])
                );
            }
        } else {
            parent::_initSkus();
        }

        return $this;
    }
    
    protected function _isProductCategoryValid(array $rowData, $rowNum)
    {
        //we don't import categories here, so return always true
        return true;
    }

    protected function _saveMediaGallery(array $mediaGalleryData)
    {
        //we don't import images here, just skip
        return $this;
    }
}