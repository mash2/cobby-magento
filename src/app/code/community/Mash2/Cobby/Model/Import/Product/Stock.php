<?php
class Mash2_Cobby_Model_Import_Product_Stock extends Mash2_Cobby_Model_Import_Product_Abstract
{


    protected $defaultStockData = array(
        'manage_stock'                  => 1,
        'use_config_manage_stock'       => 1,
        'qty'                           => 0,
        'min_qty'                       => 0,
        'use_config_min_qty'            => 1,
        'min_sale_qty'                  => 1,
        'use_config_min_sale_qty'       => 1,
        'max_sale_qty'                  => 10000,
        'use_config_max_sale_qty'       => 1,
        'is_qty_decimal'                => 0,
        'backorders'                    => 0,
        'use_config_backorders'         => 1,
        'notify_stock_qty'              => 1,
        'use_config_notify_stock_qty'   => 1,
        'enable_qty_increments'         => 0,
        'qty_increments'                => 0,
        'use_config_qty_increments'     => 1,
        'is_in_stock'                   => 0,
        'low_stock_date'                => null,
        'use_config_enable_qty_increments' => 1, //changed in  1.6.0.0
        'stock_status_changed_automatically' => 0, //changed in 1.6.0.0
        'is_decimal_divided'            => 0, // added in 1.7.0.0
    );

    /**
     * @var string category Table Name
     */
    protected $stockTable;
    
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->stockTable = Mage::getResourceModel('cataloginventory/stock_item')->getMainTable();
    }

    /**
     * save stock data
     */
    public function import($rows)
    {
        $result = array();

        $manageStock = Mage::helper('mash2_cobby/settings')->getManageStock();
        $defaultQuantity  = Mage::helper('mash2_cobby/settings')->getDefaultQuantity();
        $defaultAvailability = Mage::helper('mash2_cobby/settings')->getDefaultAvailability();

        $helper = Mage::helper('catalogInventory');
        $productIds = array_keys($rows);
        $changedProductIds = array();

        $existingProductIds = $this->loadExistingProductIds($productIds);

        Mage::dispatchEvent('cobby_import_product_stock_import_before', array('products' => $productIds));

        foreach ($rows as $productData) {

            $productId = $productData['product_id'];
            unset($productData['product_id']);

            if (!in_array($productId, $existingProductIds)) {
                continue;
            }

            /** @var $stockItem Mage_CatalogInventory_Model_Stock_Item */
            $stockItem = Mage::getModel('mash2_cobby/stock_item');
            $stockItem->loadByProduct($productId);
            $existStockData = $stockItem->getData();
            $changedProductIds[] = $productId;

            $row = array();
            $row['product_id'] = $productId;
            $row['stock_id'] = 1;

            if ($manageStock == Mash2_Cobby_Helper_Settings::MANAGE_STOCK_ENABLED) {
                $row = array_merge(
                    $row,
                    $this->defaultStockData,
                    array_intersect_key($existStockData, $this->defaultStockData),
                    array_intersect_key($productData, $this->defaultStockData)
                );
            }
            elseif ((   $manageStock == Mash2_Cobby_Helper_Settings::MANAGE_STOCK_READONLY ||
                        $manageStock == Mash2_Cobby_Helper_Settings::MANAGE_STOCK_DISABLED) &&
                        !$existStockData) {
                $stock = array();
                $stock['qty'] = $defaultQuantity;
                $stock['is_in_stock'] = $defaultAvailability;

                $row = array_merge(
                    $row,
                    $this->defaultStockData,
                    $stock
                );
            }

            $stockItem->setData($row);

            if ($helper->isQty($productData['product_type'])) {
                if ($stockItem->verifyNotification()) {
                    $stockItem->setLowStockDate(Mage::app()->getLocale()
                        ->date(null, null, null, false)
                        ->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)
                    );
                }
                $stockItem->setStockStatusChangedAutomatically((int)!$stockItem->verifyStock());
            } else {
                $stockItem->setQty(0);
            }

            $updateData = $stockItem->unsetOldData()->getData();

            if (version_compare(Mage::getVersion(), '1.7.0.0', 'lt')) {
                unset($updateData['is_decimal_divided']);
            }

            if ($manageStock == Mash2_Cobby_Helper_Settings::MANAGE_STOCK_ENABLED || !$existStockData)
            {
                $result[] = $updateData;
            }

        }


        // Insert rows
        if ($result) {
            $this->connection->insertOnDuplicate($this->stockTable, $result);
        }

        $this->touchProducts($changedProductIds);

        Mage::dispatchEvent('cobby_import_product_stock_import_after', array('products' => $changedProductIds));

        return $result;

    }

}