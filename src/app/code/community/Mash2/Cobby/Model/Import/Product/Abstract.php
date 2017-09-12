<?php
abstract class Mash2_Cobby_Model_Import_Product_Abstract extends Mage_Core_Model_Abstract
{
    /**
     * @var Mage_Core_Model_Resource
     */
    protected $resourceModel;

    /**
     * DB connection.
     *
     * @var Varien_Db_Adapter_Interface
     */
    protected $connection;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->resourceModel = Mage::getSingleton('core/resource');
        $this->connection  = $this->resourceModel->getConnection('write');
    }

    /**
     * load existing product Ids
     *
     * @param $productIds
     * @return array
     */
    protected function loadExistingProductIds($productIds)
    {
        $collection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('entity_id', array('in' => $productIds));

        return $collection->getAllIds();
    }

    /**
     * set updated_at to now
     *
     * @param $productIds
     * @return $this
     */
    protected function touchProducts($productIds){
        $entityRowsUp = array();

        foreach($productIds as $productId) {
            $entityRowsUp[] = array( 'updated_at' => now(), 'entity_id' => $productId );
        }

        if(count($entityRowsUp) > 0) {
            Mage::getModel('mash2_cobby/product')->updateHash($productIds);
            $productTable = $this->resourceModel->getTableName('catalog/product');
            $this->connection->insertOnDuplicate($productTable, $entityRowsUp, array('updated_at') );
        }

        return $this;
    }

    /**
     * @param array $rows
     * @return array
     */
    public abstract function import($rows);

    /**
     * @param array $array
     * @param $column
     * @return array
     */
    protected function getColumnValues(array $array, $column)
    {
        return array_map(function($element) use ($column) {
            return $element[$column];
        }, $array);
    }
}