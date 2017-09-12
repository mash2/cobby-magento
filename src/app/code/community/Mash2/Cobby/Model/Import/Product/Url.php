<?php
class Mash2_Cobby_Model_Import_Product_Url extends Mash2_Cobby_Model_Import_Product_Abstract
{

    protected $urlKeyAttribute = null;

    private $isEnterprise = false;
    /**
     * Store ID to its website stores IDs.
     *
     * @var array
     */
    protected $storeIdToWebsiteStoreIds = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->initStores();
        $this->urlKeyAttribute = Mage::getSingleton('importexport/import_proxy_product_resource')
            ->getAttribute('url_key');

        $this->isEnterprise = Mage::helper('core')->isModuleEnabled('Enterprise_ImportExport');
    }

    private function initStores()
    {
        foreach (Mage::app()->getStores(true) as $store) {
            $this->storeIdToWebsiteStoreIds[$store->getId()] = $store->getWebsite()->getStoreIds();
        }
        return $this;
    }

    public function import($rows)
    {
        $attributesData = array();

        $productIds = array_keys($rows);
        $existingProductIds = $this->loadExistingProductIds($productIds);
        $changedProductIds = array();
        Mage::dispatchEvent('cobby_import_product_url_import_before', array( 'products' => $productIds ));

        foreach($rows as $productId => $rowData) {
            if (!in_array($productId, $existingProductIds)) {
                continue;
            }

            $attributesData[$productId] = $this->prepareUrlAttributes($productId, $rowData);
            $changedProductIds[] = $productId;
        }

        $this->saveProductAttributes($attributesData);

        $this->touchProducts($changedProductIds);

        Mage::dispatchEvent('cobby_import_product_url_import_after', array( 'products' => $changedProductIds ));

        return $attributesData;
    }

    private function prepareUrlAttributes($productId, $storeValues)
    {
        $result = array();

        foreach($storeValues as $storeValue)
        {
            if ($this->urlKeyAttribute->getIsGlobal() == Mage_ImportExport_Model_Import_Entity_Product::SCOPE_DEFAULT) {
                $result[0] = $this->formatUrlKey($productId, $storeValue['url_key']);

            }elseif($this->urlKeyAttribute->getIsGlobal() == Mage_ImportExport_Model_Import_Entity_Product::SCOPE_WEBSITE) {
                $storeIds = $this->storeIdToWebsiteStoreIds[$storeValue['store_id']];

                foreach ($storeIds as $storeId) {
                    $result[$storeId] = $this->formatUrlKey($productId, $storeValue['url_key']);
                }

            }else {
                $result[$storeValue['store_id']] = $this->formatUrlKey($productId, $storeValue['url_key']);
            }
        }

        return $result;
    }

    private function formatUrlKey($productId, $urlKey) {
        if ( !empty($urlKey) && $urlKey != Mash2_Cobby_Model_Import_Entity_Product::COBBY_DEFAULT) {
            $urlKey = Mage::getModel('catalog/product')->formatUrlKey($urlKey);

            if($this->isEnterprise) {
                $urlKey = $this->_generateNextUrlKeyWithSuffix($productId, $urlKey);
            }
        }

        return $urlKey;
    }

    /**
     * Save product attributes.
     *
     * @param array $attributesData
     * @return $this
     */
    private function saveProductAttributes(array $attributesData)
    {
        $tableName = $this->urlKeyAttribute->getBackend()->getTable();
        $tableData = array();
        $attributeId = $this->urlKeyAttribute->getId();
        $entityTypeId = Mage::getModel('catalog/product')->getResource()->getTypeId();

        foreach ($attributesData as $productId => $storeValues) {
            foreach ($storeValues as $storeId => $storeValue) {
                if ( $storeValue == Mash2_Cobby_Model_Import_Entity_Product::COBBY_DEFAULT) {
                    //TODO: evtl delete mit mehreren daten auf einmal
                    /** @var Magento_Db_Adapter_Pdo_Mysql $connection */
                    $this->connection->delete($tableName, array(
                        'entity_id=?'      => (int) $productId,
                        'entity_type_id=?' => (int) $entityTypeId,
                        'attribute_id=?'   => (int) $attributeId,
                        'store_id=?'       => (int) $storeId,
                    ));
                } else {
                    $tableData[] = array(
                        'entity_id'      => $productId,
                        'entity_type_id' => $entityTypeId,
                        'attribute_id'   => $attributeId,
                        'store_id'       => $storeId,
                        'value'          => $storeValue
                    );
                }
            }
        }

        if (count($tableData)) {
            $this->connection->insertOnDuplicate($tableName, $tableData, array('value'));
        }

        return $this;
    }

    /**
     * Generate unique url key if current url key already occupied
     **/
    protected function _generateNextUrlKeyWithSuffix($productId, $prefixValue)
    {
        $eavHelper = $this->_eavHelper = Mage::getResourceHelper('eav');
        $requestPathField = new Zend_Db_Expr($this->connection->quoteIdentifier('value'));
        //select increment part of request path and cast expression to integer
        $urlIncrementPartExpression = $eavHelper->getCastToIntExpression(
            $this->connection->getSubstringSql(
                $requestPathField,
                strlen($prefixValue) + 1,
                $this->connection->getLengthSql($requestPathField) . ' - ' . strlen($prefixValue)
            )
        );

        $prefixRegexp = preg_quote($prefixValue);
        $orCondition = $this->connection->select()
            ->orWhere(
                $this->connection->prepareSqlCondition(
                    'value',
                    array(
                        'regexp' => '^' . $prefixRegexp . '$',
                    )
                )
            )->orWhere(
                $this->connection->prepareSqlCondition(
                    'value',
                    array(
                        'regexp' => '^' . $prefixRegexp . '-[0-9]*$',
                    )
                )
            )->getPart(Zend_Db_Select::WHERE);
        $select = $this->connection->select();
        $select->from(
            $this->urlKeyAttribute->getBackendTable(),
            new Zend_Db_Expr('MAX(ABS(' . $urlIncrementPartExpression . '))')
        )
            ->where('value LIKE :url_key')
            ->where('entity_id <> :entity_id')
            ->where(implode('', $orCondition));
        $bind = array(
            'url_key' => $prefixValue . '%',
            'entity_id' => (int) $productId,
        );

        $suffix = $this->connection->fetchOne($select, $bind);
        if (!is_null($suffix)) {
            $suffix = (int) $suffix;
            return sprintf('%s-%s', $prefixValue, ++$suffix);
        }

        return $prefixValue;
    }
}