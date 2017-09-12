<?php
class Mash2_Cobby_Model_Import_Product_Groupprice extends Mash2_Cobby_Model_Import_Product_Abstract
{
    /**
     * @var string category Table Name
     */
    protected $groupPriceTable;
    
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->groupPriceTable = Mage::getModel('importexport/import_proxy_product_resource')
            ->getTable('catalog/product_attribute_group_price');
    }
    
    public function import($rows)
    {
        $result = array();

        $productIds = array_keys($rows);
        $existingProductIds = $this->loadExistingProductIds($productIds);
        $changedProductIds = array();

        Mage::dispatchEvent('cobby_import_product_groupprice_import_before', array( 'products' => $productIds));

        $groupIds = array();
        $group = Mage::getModel('customer/group')->getCollection();
        foreach ($group as $eachGroup) {
            $groupIds[] = $eachGroup->getCustomerGroupId();
        }

        $groupPricesIn  = array();
        $delProductIds = array();

        foreach($rows as $productId => $productPriceItems){
            if(!in_array($productId, $existingProductIds))
                continue;

            $delProductIds[] = $productId;
            $changedProductIds[] = $productId;

            foreach ($productPriceItems as $productPriceItem){
                if(!in_array($productPriceItem['customer_group_id'], $groupIds)){
                    continue;
                }

                $productPriceItem['entity_id'] = $productId;
                $productPriceItem['all_groups'] = 0;
                $groupPricesIn[] = $productPriceItem;
            }
        }

        if(count($delProductIds) > 0){
            $this->connection->delete($this->groupPriceTable, $this->connection->quoteInto('entity_id IN (?)', $delProductIds));
        }

        if(count($groupPricesIn) > 0){
            $this->connection->insertOnDuplicate($this->groupPriceTable, $groupPricesIn, array('value'));
        }

        $this->touchProducts($changedProductIds);

        Mage::dispatchEvent('cobby_import_product_groupprice_import_after', array( 'products' => $productIds));

        return $result;
    }
} 