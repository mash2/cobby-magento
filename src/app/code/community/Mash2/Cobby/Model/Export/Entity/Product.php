<?php

/**
 * Cobby export product model
 */
class Mash2_Cobby_Model_Export_Entity_Product extends Varien_Object
{
    /**
     * Value that means all entities (e.g. websites, groups etc.)
     */
    const VALUE_ALL = 'all';

    const COL_STORE = '_store';
    const COL_SKU = '_sku';
    const COL_HASH = '_hash';
    const COL_MAGENTO_ID = '_entity_id';
    const COL_TYPE = '_type';
    const COL_ATTR_SET = '_attribute_set';
    const COL_ATTRIBUTES = '_attributes';
    const COL_CATEGORY = '_categories';
    const COL_WEBSITE = '_websites';
    const COL_IMAGE_GALLERY = '_image_gallery';
    const COL_INVENTORY = '_inventory';
    const COL_GROUP_PRICE = '_group_price';
    const COL_TIER_PRICE = '_tier_price';
    const COL_LINKS = '_links';
    const COL_SUPER_PRODUCT_ATTRIBUTES = '_super_product_attributes';
    const COL_SUPER_PRODUCT_SKUS = '_super_product_skus';
    const COL_CUSTOM_OPTIONS = '_custom_options';
    const COL_BUNDLE_OPTIONS = '_bundle_options';

    protected $_storeIdToCode = array();
    protected $_websiteIdToCode = array();
    protected $_connection;
    protected static $_attrCodes = null;
    protected $_parameters = array();

    /**
        attrs not supported in cobby
     **/
    protected $skipUnsupportedAttrCodes =  array('is_recurring', 'recurring_profile', 'category_ids', 'giftcard_amounts');

    /**
     * load all stores
     *
     * @return $this
     */
    protected function _initStores()
    {
        foreach (Mage::app()->getStores(true) as $store) {
            $this->_storeIdToCode[$store->getId()] = $store->getCode();
        }
        ksort($this->_storeIdToCode); // to ensure that 'admin' store (ID is zero) goes first

        return $this;
    }

    protected function _initWebsites()
    {
        foreach (Mage::app()->getWebsites(true) as $website) {
            $this->_websiteIdToCode[$website->getId()] = $website->getCode();
        }
        ksort($this->_websiteIdToCode); // to ensure that 'admin' store (ID is zero) goes first

        return $this;
    }


    /**
     * filter for active attributes
     *
     * @param Mage_Eav_Model_Mysql4_Entity_Attribute_Collection $collection
     * @return Mage_Eav_Model_Mysql4_Entity_Attribute_Collection
     */
    public function filterAttributeCollection(Mage_Eav_Model_Mysql4_Entity_Attribute_Collection $collection)
    {
        $collection->load();

        foreach ($collection as $attribute) {
            if (in_array($attribute->getAttributeCode(), $this->_disabledAttrs)) {
                $collection->removeItemByKey($attribute->getId());
            }
        }
        return $collection;
    }

    /**
     * Retrieve exportable attribute codes
     *
     * @return array|null
     */
    protected function _getExportAttrCodes()
    {
        if (null === self::$_attrCodes) {
            if (!empty($this->_parameters[Mage_ImportExport_Model_Export::FILTER_ELEMENT_SKIP])
                && is_array($this->_parameters[Mage_ImportExport_Model_Export::FILTER_ELEMENT_SKIP])
            ) {
                $skipAttr = array_flip($this->_parameters[Mage_ImportExport_Model_Export::FILTER_ELEMENT_SKIP]);
            } else {
                $skipAttr = array();
            }
            $attrCodes = array();

            foreach ($this->getAttributeCollection() as $attribute) { //$this->filterAttributeCollection(
                if( in_array($attribute->getAttributeCode(), $this->skipUnsupportedAttrCodes)) {
                    continue;
                }

                if (!isset($skipAttr[$attribute->getAttributeId()])
                    || in_array($attribute->getAttributeCode(), $this->_permanentAttributes)
                ) {
                    $attrCodes[] = $attribute->getAttributeCode();
                }
            }
            self::$_attrCodes = $attrCodes;
        }
        return self::$_attrCodes;
    }

    /**
     * Entity attributes collection getter.
     *
     * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Attribute_Collection
     */
    public function getAttributeCollection()
    {
        return Mage::getResourceModel('catalog/product_attribute_collection');
    }

    /**
     * Converts a dictionary to a string
     *
     * @param $dict
     * @return string
     */
    public function dictToString($dict)
    {
        $result = '';
        foreach ($dict as $key => $value) {
            $result .= $key . ':' . $value . ';';
        }
        return $result;
    }


    /**
     * @param $filterProducts
     * @return array
     */
    public function filterChangedProducts($filterProducts)
    {
        $result = $filterProducts;
        $collection = Mage::getResourceModel('mash2_cobby/product_collection')
            ->addFieldToFilter('entity_Id', array('in'=>array_keys($result)));

        foreach ($collection as $item) {
            $productId = $item->getEntityId();

            if (isset($result[$productId]) &&  $item->getHash() == $result[$productId]) {
                unset($result[$productId]);
            }
            else {
                $result[$productId] = $item->getHash();
            }
        }
        return $result;
    }

    /**
     * Retrieve products
     *
     * @param $filterProductParams
     * @return array|stdClass
     * @internal param $filterProductFilters
     */
    public function exportProducts($filterProductParams)
    {
        $stockItemRows = array();
        $linksRows = array();
        $rowTierPrices   = array();
        $rowGroupPrices  = array();
        $rowCustomOptions = array();
        $rowBundleOptions = array();
        $dataRows = array();
        $this->_initStores();
        $this->_initWebsites();
        $exportAttrCodes = $this->_getExportAttrCodes(); //TODO: MOVE TO FILTER COLLECTION
        $validAttrCodes = $this->_getExportAttrCodes();
        $defaultStoreId = Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID;
        $this->_connection = Mage::getSingleton('core/resource')->getConnection('write');
        $filterChangedProducts = $this->filterChangedProducts($filterProductParams);

		$unchangedProducts = array_diff_key($filterProductParams, $filterChangedProducts);
		
		foreach($unchangedProducts as $productId => $hash)
        {
            $dataRows[$productId][self::COL_MAGENTO_ID] = $productId;
			$dataRows[$productId][self::COL_HASH] = 'UNCHANGED';
        }
		
        // prepare multi-store values and system columns values
        // go through all stores
        foreach ($this->_storeIdToCode as $storeId => $storeCode) {
            $storeKey = (string)$storeId."|";
            $collection = Mage::getResourceModel('mash2_cobby/catalog_product_collection'); //$this->_prepareEntityCollection(

            foreach ($exportAttrCodes as $attrCode) {
                $collection->addAttributeToSelect($attrCode);
            }
            $collection
                ->setStoreId($storeId);

            $collection->addAttributeToFilter('entity_id', array('in' => array_keys($filterChangedProducts)));
            $allIds = $collection->getAllIds();

            $collection->load();

            if ($defaultStoreId == $storeId) {
                $collection
                    ->addCategoryIds()
                    ->addWebsiteNamesToResult();

                $stockItemRows = $this->_prepareCatalogInventory($allIds);
                // prepare links information
                $linksRows = $this->_prepareLinks($allIds);
                // tier and group price data getting only once
                $rowTierPrices = $this->_prepareTierPrices($allIds);
                $rowGroupPrices = $this->_prepareGroupPrices($allIds);

                $rowCustomOptions = $this->_prepareCustomOptions($allIds);
                $rowBundleOptions = $this->_prepareBundleOptions($allIds);
                $mediaGalery      = $this->_prepareMediaGallery($allIds, array_keys($this->_storeIdToCode));
            }

            foreach ($collection as $itemId => $item) { // go through all products

                if ($defaultStoreId == $storeId) {
                    $dataRowProduct = array(
                        self::COL_SKU => $item->getSku(),
                        self::COL_MAGENTO_ID => $itemId,
                        self::COL_HASH => $filterChangedProducts[$itemId],
                        self::COL_ATTR_SET => $item->getAttributeSetId(),
                        self::COL_TYPE => $item->getTypeId(),
                        self::COL_CATEGORY => implode(",", $item->getCategoryIds()),
                        self::COL_WEBSITE => implode(",", $item->getWebsites()),
                        self::COL_INVENTORY => new stdClass(),
                        self::COL_GROUP_PRICE => array(),
                        self::COL_TIER_PRICE => array(),
                        self::COL_LINKS     => array(),
                        self::COL_IMAGE_GALLERY => array(),
                        self::COL_ATTRIBUTES => array(),
                        self::COL_CUSTOM_OPTIONS => array(),
                        self::COL_BUNDLE_OPTIONS => array(),
                    );

                    if (!empty($stockItemRows[$itemId])) {
                        $dataRowProduct[self::COL_INVENTORY] = $stockItemRows[$itemId];
                    }

                    if(!empty($linksRows[$itemId])) {
                        $dataRowProduct[self::COL_LINKS] = $linksRows[$itemId];
                    }

                    if (!empty($rowGroupPrices[$itemId])) {
                        $dataRowProduct[self::COL_GROUP_PRICE] = $rowGroupPrices[$itemId];
                    }

                    if (!empty($mediaGalery[$itemId])) {
                        $dataRowProduct[self::COL_IMAGE_GALLERY] = $mediaGalery[$itemId];
                    }

                    // #2320 prp Produkt zuweisen
                    if(!empty($rowCustomOptions[$itemId]))
                    {
                        $dataRowProduct[self::COL_CUSTOM_OPTIONS] = $rowCustomOptions[$itemId];
                    }

                    if(!empty($rowBundleOptions[$itemId])) { //TODO: empty oder set?
                        $dataRowProduct[self::COL_BUNDLE_OPTIONS] = $rowBundleOptions[$itemId];
                    }

                    if (!empty($rowTierPrices[$itemId])) {
                        $dataRowProduct[self::COL_TIER_PRICE] = $rowTierPrices[$itemId];
                    }

                    $dataRows[$itemId] = $dataRowProduct;
                }

                $attributes = array('store_id' => $storeId);
                foreach ($validAttrCodes as &$attrCode) { // go through all valid attribute codes
                    $attrValue = $item->getData($attrCode);

                    if(!is_array($attrValue)) {
                        $attributes[$attrCode] = $attrValue;
                    }
                }
                $dataRows[$itemId][self::COL_ATTRIBUTES][] = $attributes;
            }
        }

        $productIds = array_keys($dataRows);

        $configurableProductsCollection = Mage::getResourceModel('catalog/product_collection');
        $configurableProductsCollection->addAttributeToFilter( 'entity_id', array( 'in' => $productIds ))
            ->addAttributeToFilter( 'type_id', array( 'eq' => Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE));

        $configurableProductIds = $configurableProductsCollection->getAllIds();
        $configurableAttributes = $this->_prepareConfigurableProductAttributes($configurableProductIds);
        $configurablePrices = $this->_prepareConfigurableProductPrices($configurableProductIds);
        $configurableLabels = $this->_prepareConfigurableProductLabels($configurableProductIds);

        while ($product = $configurableProductsCollection->fetchItem()) {
            $configurableData = array();
            $productId  = $product->getId();

            //if not set then product without super attributes
            if(isset($configurableAttributes[$productId]))
            {
                foreach($configurableAttributes[$productId] as $attributeId => $configurableAttribute)
                {
                    $superAttributeId = $configurableAttribute['product_super_attribute_id'];
                    $configurableData[$attributeId] = array(
                        'attribute_code' => $configurableAttribute['attribute_code'],
                        'attribute_id' => $attributeId,
                        'labels' => isset($configurableLabels[$productId][$attributeId]) ? $configurableLabels[$productId][$attributeId] : array(),
                        'options' => array());


                    if(isset($configurablePrices[$productId][$superAttributeId])) {
                        foreach($configurablePrices[$productId][$superAttributeId] as $option => $price)
                        {
                            $configurableData[$attributeId]['options'][] = array(
                                'option' => $option,
                                'prices'    => $price
                            );
                        }
                    }
                }
            }

            $dataRows[$product->getId()][self::COL_SUPER_PRODUCT_ATTRIBUTES] = array_values($configurableData);
        }

        $configurableLinkedIds = $this->_prepareConfigurableProductLinkedIds($configurableProductIds);
        foreach($configurableLinkedIds as $productId => $value)
        {
            $dataRows[$productId][self::COL_SUPER_PRODUCT_SKUS] = $value;
        }

        if(count($dataRows) > 0) {

            $transportObject = new Varien_Object();
            $transportObject->setRows($dataRows);

            Mage::dispatchEvent('cobby_catalog_product_export_after',
                array('transport' => $transportObject));

            return array_values($transportObject->getRows());
        }
        return array();
    }

    protected function _prepareConfigurableProductAttributes($productIds)
    {
        $result = array();
        $resource = Mage::getSingleton('core/resource');
        $select = $this->_connection->select()
            ->from(
                array('attr' => $resource->getTableName('catalog/product_super_attribute')),
                array('attr.product_id', 'attr.attribute_id', 'attr.product_super_attribute_id', 'attr.position')
            )
            ->join(
                array('ea' => $resource->getTableName('eav/attribute')),
                '(ea.attribute_id = attr.attribute_id)',
                array('ea.attribute_code', 'ea.frontend_label')
            )
            ->where('attr.product_id IN (?)', $productIds)
            ->order('attr.position');
        $stmt = $this->_connection->query($select);
        while ($row = $stmt->fetch()) {
            $productId = $row['product_id'];
            $result[$productId][$row['attribute_id']] = array(
                'product_super_attribute_id' => $row['product_super_attribute_id'],
                'attribute_code' => $row['attribute_code'],
                'frontend_label' => $row['frontend_label'])
            ;
        }
        return $result;
    }

    protected function _prepareConfigurableProductLabels($productIds)
    {
        $result = array();
        $resource = Mage::getSingleton('core/resource');
        $select = $this->_connection->select()
            ->from(
                array('attr' => $resource->getTableName('catalog/product_super_attribute')),
                array('attr.product_id', 'attr.attribute_id', 'label.value', 'label.store_id')
            )
            ->join(
                array('label' => $resource->getTableName('catalog/product_super_attribute_label')),
                '(label.product_super_attribute_id = attr.product_super_attribute_id)',
                array('label.use_default')
            )
            ->where('attr.product_id IN (?)', $productIds);
        $stmt = $this->_connection->query($select);
        while ($row = $stmt->fetch()) {
            $productId = $row['product_id'];
            $result[$productId][$row['attribute_id']][] = array(
                'storeId'=>$row['store_id'],
                'label' => $row['value'],
                'use_default' => $row['use_default']);
        }
        return $result;
    }

    protected function _prepareConfigurableProductPrices($productIds)
    {
        $result = array();
        $resource = Mage::getSingleton('core/resource');
        $select = $this->_connection->select()
            ->from(
                array('attr' => $resource->getTableName('catalog/product_super_attribute')),
                array('attr.product_id', 'attr.product_super_attribute_id', 'price.value_index', 'price.is_percent', 'price.pricing_value', 'price.website_id')
            )
            ->join(
                array('price' => $resource->getTableName('catalog/product_super_attribute_pricing')),
                '(price.product_super_attribute_id = attr.product_super_attribute_id)',
                array()
            )
            ->join(
                array('eaov' => $resource->getTableName('eav/attribute_option_value')),
                '(eaov.option_id = price.value_index AND eaov.store_id = 0)',
                array('value')
            )
            ->where('attr.product_id IN (?)', $productIds);
        $stmt = $this->_connection->query($select);
        while ($row = $stmt->fetch()) {
            $superAttributeId=$row['product_super_attribute_id'];
            $productId = $row['product_id'];
            $result[$productId][$superAttributeId][$row['value']][] = array(
                'is_percent'=>$row['is_percent'],
                'price' => $row['pricing_value'],
                'website_id' => $row['website_id']);
        }
        return $result;
    }

    /**
     * Prepare configurable products data
     *
     * @param array $productIds
     * @return array
     */
    protected function _prepareConfigurableProductLinkedIds(array $productIds)
    {
        $result = array();
        $resource = Mage::getSingleton('core/resource');
        $select = $this->_connection->select()
            ->from(
                array('cpsl' => $resource->getTableName('catalog/product_super_link')),
                array('cpsl.parent_id', 'cpe.sku', 'cpsl.product_id')
            )
            ->joinLeft(
                array('cpe' => $resource->getTableName('catalog/product')),
                '(cpe.entity_id = cpsl.product_id)',
                array()
            )
            ->where('parent_id IN (?)', $productIds);
        $stmt = $this->_connection->query($select);
        while ($row = $stmt->fetch()) {
            $result[$row['parent_id']][$row['product_id']] = $row['sku'];
        }

        return $result;
    }

    /**
     * Prepare products media gallery
     *
     * @param int $storeId
     * @param  array $productIds
     * @return array
     */
    protected function _prepareMediaGallery(array $productIds, $storeIds)
    {
        if (empty($productIds)) {
            return array();
        }
        $resource = Mage::getSingleton('core/resource');
        $select = $this->_connection->select()
            ->from(
                array('mg' => $resource->getTableName('catalog/product_attribute_media_gallery')),
                array(
                    'mg.entity_id', 'mg.attribute_id', 'filename' => 'mg.value', 'mgv.label',
                    'mgv.position', 'mgv.disabled', 'mgv.store_id'
                )
            )
            ->joinLeft(
                array('mgv' => $resource->getTableName('catalog/product_attribute_media_gallery_value')),
                '(mg.value_id = mgv.value_id )',
                array()
            )
            ->where('mg.entity_id IN(?)', $productIds);

        $rowMediaGallery = array();
        $stmt = $this->_connection->query($select);
        while ($mediaRow = $stmt->fetch()) {
            $productId = $mediaRow['entity_id'];
            $storeId = isset($mediaRow['store_id']) ? (int)$mediaRow['store_id'] : 0;
            if (in_array($storeId, $storeIds)) {
                $rowMediaGallery[$productId][] = array(
                    'store_id'      => $storeId,
                    'attribute_id'  => $mediaRow['attribute_id'],
                    'filename'      => $mediaRow['filename'],
                    'label'         => $mediaRow['label'],
                    'position'      => $mediaRow['position'],
                    'disabled'      => $mediaRow['disabled'],
                );
            }
        }

        return $rowMediaGallery;
    }

    /**
     * Prepare catalog inventory
     *
     * @param  array $productIds
     * @return array
     */
    protected function _prepareCatalogInventory(array $productIds)
    {
        if (empty($productIds)) {
            return array();
        }
        $select = $this->_connection->select()
            ->from(Mage::getResourceModel('cataloginventory/stock_item')->getMainTable())
            ->where('product_id IN (?)', $productIds);

        $stmt = $this->_connection->query($select);
        $stockItemRows = array();
        while ($stockItemRow = $stmt->fetch()) {
            $productId = $stockItemRow['product_id'];
            unset(
            $stockItemRow['item_id'], $stockItemRow['product_id'], $stockItemRow['low_stock_date'],
            $stockItemRow['stock_id'], $stockItemRow['stock_status_changed_automatically']
            );
            $stockItemRows[$productId] = $stockItemRow;
        }
        return $stockItemRows;
    }

    /**
     * Prepare product links
     *
     * @param  array $productIds
     * @return array
     */
    protected function _prepareLinks(array $productIds)
    {
        if (empty($productIds)) {
            return array();
        }
        $resource = Mage::getSingleton('core/resource');
        $adapter = $this->_connection;
        $select = $adapter->select()
            ->from(
                array('cpl' => $resource->getTableName('catalog/product_link')),
                array(
                    'cpl.product_id', 'cpl.linked_product_id', 'cpe.sku', 'cpl.link_type_id',
                    'position' => 'cplai.value', 'default_qty' => 'cplad.value'
                )
            )
            ->joinLeft(
                array('cpe' => $resource->getTableName('catalog/product')),
                '(cpe.entity_id = cpl.linked_product_id)',
                array()
            )
            ->joinLeft(
                array('cpla' => $resource->getTableName('catalog/product_link_attribute')),
                $adapter->quoteInto(
                    '(cpla.link_type_id = cpl.link_type_id AND cpla.product_link_attribute_code = ?)',
                    'position'
                ),
                array()
            )
            ->joinLeft(
                array('cplaq' => $resource->getTableName('catalog/product_link_attribute')),
                $adapter->quoteInto(
                    '(cplaq.link_type_id = cpl.link_type_id AND cplaq.product_link_attribute_code = ?)',
                    'qty'
                ),
                array()
            )
            ->joinLeft(
                array('cplai' => $resource->getTableName('catalog/product_link_attribute_int')),
                '(cplai.link_id = cpl.link_id AND cplai.product_link_attribute_id = cpla.product_link_attribute_id)',
                array()
            )
            ->joinLeft(
                array('cplad' => $resource->getTableName('catalog/product_link_attribute_decimal')),
                '(cplad.link_id = cpl.link_id AND cplad.product_link_attribute_id = cplaq.product_link_attribute_id)',
                array()
            )
            ->where('cpl.link_type_id IN (?)', array(
                Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED,
                Mage_Catalog_Model_Product_Link::LINK_TYPE_UPSELL,
                Mage_Catalog_Model_Product_Link::LINK_TYPE_CROSSSELL,
                Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED
            ))
            ->where('cpl.product_id IN (?)', $productIds);

        $stmt = $adapter->query($select);
        $linksRows = array();
        while ($linksRow = $stmt->fetch()) {
            $productId = $linksRow['product_id'];
            $linksRows[$productId][] = array(
                'product_id'    => $linksRow['linked_product_id'],
                'sku'           => $linksRow['sku'],
                'link_type_id'  => $linksRow['link_type_id'],
                'position'      => $linksRow['position'],
                'default_qty'   => $linksRow['default_qty']
            );
        }

        return $linksRows;
    }

    /**
     * Prepare products tier prices
     *
     * @param  array $productIds
     * @return array
     */
    protected function _prepareTierPrices(array $productIds)
    {
        if (empty($productIds)) {
            return array();
        }
        $resource = Mage::getSingleton('core/resource');
        $select = $this->_connection->select()
            ->from($resource->getTableName('catalog/product_attribute_tier_price'))
            ->where('entity_id IN(?)', $productIds);

        $rowTierPrices = array();
        $stmt = $this->_connection->query($select);
        while ($tierRow = $stmt->fetch()) {
            $rowTierPrices[$tierRow['entity_id']][] = array(
                'all_groups'        => $tierRow['all_groups'],
                'customer_group_id' => $tierRow['customer_group_id'],
                'qty'               => $tierRow['qty'],
                'value'             => $tierRow['value']   ,
                'website_id'        => $tierRow['website_id']
            );
        }

        return $rowTierPrices;
    }

    private function _getStoreLabel($storeId, $data)
    {
        return array(
            'store_id' => $storeId,
            'label' => $data['title'],
            'use_default_label' =>  $data['store_title'] === null? '1' : '0',
        );
    }

    private function _getStorePrice($storeId, $data)
    {
        return array(
            'store_id' => $storeId,
            'price' => $data['price'],
            'price_type' => $data['price_type'],
            'use_default_price' =>  $data['store_price'] === null? '1' : '0',
        );
    }

    /**
     * Prepare products group prices
     *
     * @param  array $productIds
     * @return array
     */
    protected function _prepareGroupPrices(array $productIds)
    {
        $result = array();
        if (empty($productIds)) {
            return $result;
        }

        if(version_compare(Mage::getVersion(), '1.7', '>=')) {
            $resource = Mage::getSingleton('core/resource');

            $select = $this->_connection->select()
                ->from($resource->getTableName('catalog/product_attribute_group_price'))
                ->where('entity_id IN(?)', $productIds);

            $statement = $this->_connection->query($select);
            while ($row = $statement->fetch()) {
                $result[$row['entity_id']][] = array(
                    'website_id'        => $row['website_id'],
                    'customer_group_id' => $row['customer_group_id'],
                    'value'             => $row['value']
                );
            }
        }
        return $result;
    }

    protected function _prepareCustomOptions(array $productIds)
    {
        $multiOptionsTypes = array('multiple','checkbox','radio','drop_down');
        $resultPrepareItem = array();

        foreach ($this->_storeIdToCode as $storeId => $storeCode) {
            $options = Mage::getResourceModel('catalog/product_option_collection')
                ->reset()
                ->addTitleToResult($storeId)
                ->addPriceToResult($storeId)
                ->addProductToFilter($productIds)
                ->addValuesToResult($storeId);

            foreach ($options as $option) {
                $productId = $option['product_id'];
                $optionId  = $option['option_id'];

                if(!isset($resultPrepareItem[$productId])) {
                    $resultPrepareItem[$productId] = array();
                }

                if(!isset($resultPrepareItem[$productId][$optionId])) {
                    $resultPrepareItem[$productId][$optionId] = array(
                        'id' => $optionId,
                        'type' => $option['type'],
                        'is_require' => $option['is_require'],
                        'sort_order' => $option['sort_order'],
                        'file_extension' => $option['file_extension'],
                        'image_size_x' => $option['image_size_x'],
                        'image_size_y' => $option['image_size_y'],
                        'max_characters' => $option['max_characters'],
                        'sku' => $option['sku'],
                        'titles' => array(),
                        'prices' => array(),
                        'options' => array(),
                    );
                }

                $resultPrepareItem[$productId][$optionId]['titles'][] = $this->_getStoreLabel($storeId, $option);

                if(in_array($option['type'], $multiOptionsTypes)) {
                    foreach($option->getValues() as $optionValue) {
                        $subOptionId = $optionValue['option_type_id'];

                        if(!isset($resultPrepareItem[$productId][$optionId]['options'][$subOptionId])) {
                            $resultPrepareItem[$productId][$optionId]['options'][$subOptionId] = array(
                                'sub_option_Id' => $subOptionId,
                                'sku' => $optionValue['sku'],
                                'sort_order' => $optionValue['sort_order'],
                                'titles' => array(),
                                'prices' => array(),
                            );
                        }

                        $resultPrepareItem[$productId][$optionId]['options'][$subOptionId]['prices'][] = $this->_getStorePrice($storeId, $optionValue);
                        $resultPrepareItem[$productId][$optionId]['options'][$subOptionId]['titles'][] = $this->_getStoreLabel($storeId, $optionValue);
                    }
                } else {
                    $resultPrepareItem[$productId][$optionId]['prices'][] = $this->_getStorePrice($storeId, $option);
                }
            }
        }

        $result = array();
        foreach($resultPrepareItem as $productId => $productResult) {
            $productOptions = array();
            foreach($productResult as $productOption) {
                $productArrayOption = $productOption;
                $productArrayOption['options'] = array_values($productOption['options']);
                $productOptions[] = $productArrayOption;
            }

            $result[$productId] = $productOptions;
        }

        return $result;
    }

     protected function _prepareBundleOptions(array $productIds)
     {
         $resource = Mage::getSingleton('core/resource');

         $selectOptions = $this->_connection->select()
             ->from(array('o' => $resource->getTableName('bundle/option')))
             ->join(array('v' => $resource->getTableName('bundle/option_value')), '(o.option_id = v.option_id)')
             ->where('o.parent_id IN (?)', $productIds);

         $bundleOptions = array();
         $queryOptions = $this->_connection->query($selectOptions);
         while ($row = $queryOptions->fetch()) {
             $optionId = $row['option_id'];
             $productId = $row['parent_id'];

             if(!isset($bundleOptions[$productId][$optionId])) {
                 $bundleOptions[$productId][$optionId] = array(
                     'option_id'    => $optionId,
                     'required'     => $row['required'],
                     'position'     => $row['position'],
                     'type'         => $row['type'],
                     'titles'       => array(),
                     'selections'   => array(),
                 );
             }
             $bundleOptions[$productId][$optionId]['titles'][] = array(
                 'store_id' => $row['store_id'],
                 'title'    => $row['title']
             );
         }

         $selectSelections = $this->_connection->select()
             ->from(array('s' => $resource->getTableName('bundle/selection')))
             ->joinLeft(array('p' => $resource->getTableName('bundle/selection_price')),'(s.selection_id = p.selection_id)',
                 array( 'website_id' => 'website_id', 'website_price_type' => 'selection_price_type', 'website_price_value' => 'selection_price_value'))
             ->where('s.parent_product_id IN (?)', $productIds);

         $querySelections = $this->_connection->query($selectSelections);
         while ($row = $querySelections->fetch()) {
             $optionId = $row['option_id'];
             $productId = $row['parent_product_id'];
             $selectionId = $row['selection_id'];

             if(!isset($bundleOptions[$productId][$optionId][$selectionId])) {
                 $bundleOptions[$productId][$optionId]['selections'][$selectionId] = array(
                     'selection_id'         => $selectionId,
                     'assigned_product_id'  => $row['product_id'],
                     'position'             => $row['position'],
                     'is_default'           => $row['is_default'],
                     'qty'                  => $row['selection_qty'],
                     'can_change_qty'       => $row['selection_can_change_qty'],
                     'prices'               => array(array(
                         'website_id'           => 0,
                         'price_type'           => $row['selection_price_type'],
                         'price_value'          => $row['selection_price_value']))

                 );
             }

             if(isset($row['website_id'])) {
                 $bundleOptions[$productId][$optionId]['selections'][$selectionId]['prices'][] = array(
                     'website_id' => $row['website_id'],
                     'price_type' => $row['website_price_type'],
                     'price_value' => $row['website_price_value']);
             }
         }

         $result = array();
         foreach($bundleOptions as $productId => $bundleOption) {
             $productOptions = array();
             foreach($bundleOption as $productOption) {
                 $bundleSelections = $productOption;
                 $bundleSelections['selections'] = array_values($productOption['selections']);
                 $productOptions[] = $bundleSelections;
             }

             $result[$productId] = array_values($productOptions);
         }

        return $result;
     }
}