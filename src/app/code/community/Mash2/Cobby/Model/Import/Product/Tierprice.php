<?php
class Mash2_Cobby_Model_Import_Product_Tierprice extends Mash2_Cobby_Model_Import_Product_Abstract
{
    /**
     * @var string category Table Name
     */
    protected $tierPriceTable;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->tierPriceTable = Mage::getModel('importexport/import_proxy_product_resource')
            ->getTable('catalog/product_attribute_tier_price');
    }

    public function import($rows)
    {
        $result = array();

        $productIds = array_keys($rows);
        $existingProductIds = $this->loadExistingProductIds($productIds);
        Mage::dispatchEvent('cobby_import_product_tierprice_import_before', array( 'products' => $productIds ));
        $changedProductIds = array();

        $groupIds = array();
        $group = Mage::getModel('customer/group')->getCollection();
        foreach ($group as $eachGroup) {
            $groupIds[] = $eachGroup->getCustomerGroupId();
        }

        $tierPricesIn  = array();
        $delProductIds = array();

        foreach($rows as $productId => $productPriceItems){
            if(!in_array($productId, $existingProductIds))
                continue;

            $delProductIds[] = $productId;
            $changedProductIds[] = $productId;

            foreach ($productPriceItems as $productPriceItem){
                if(!in_array($productPriceItem['customer_group_id'], $groupIds) && $productPriceItem['all_groups'] != "1"){
                    continue;
                }

                $productPriceItem['entity_id'] = $productId;
                $tierPricesIn[] = $productPriceItem;
            }
        }

        if(count($delProductIds) > 0){
            $this->connection->delete($this->tierPriceTable, $this->connection->quoteInto('entity_id IN (?)', $delProductIds));
        }

        if(count($tierPricesIn) > 0){
            $this->connection->insertOnDuplicate($this->tierPriceTable, $tierPricesIn, array('value'));
        }

        $this->touchProducts($changedProductIds);

        Mage::dispatchEvent('cobby_import_product_tierprice_import_after', array( 'products' => $changedProductIds ));

        return $result;
    }
}