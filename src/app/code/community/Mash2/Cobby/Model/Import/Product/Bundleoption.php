<?php
class Mash2_Cobby_Model_Import_Product_Bundleoption extends Mash2_Cobby_Model_Import_Product_Abstract
{
    const ADD           = 'add';
    const DELETE        = 'delete';
    const UPDATE        = 'update';

    protected $optionTable;
    protected $titleTable;
    protected $selectionTable;
    protected $selectionPriceTable;
    protected $relationTable;

    protected $nextAutoOptionId;
    protected $nextAutoSelectionId;

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

    protected function init()
    {
        $coreResource               = Mage::getSingleton('core/resource');

        $this->optionTable          = $coreResource->getTableName('bundle/option');
        $this->titleTable           = $coreResource->getTableName('bundle/option_value');
        $this->selectionTable       = $coreResource->getTableName('bundle/selection');
        $this->selectionPriceTable  = $coreResource->getTableName('bundle/selection_price');
        $this->relationTable        = $coreResource->getTableName('catalog/product_relation');

        $this->nextAutoOptionId     = $this->resourceHelper->getNextAutoincrement($this->optionTable);
        $this->nextAutoSelectionId  = $this->resourceHelper->getNextAutoincrement($this->selectionTable);
    }

    public function import($rows)
    {
        $result = array();
        $this->init();

        $productIds = array_keys($rows);
        $existingProductIds = $this->loadExistingProductIds($productIds);
        $changedProductIds = array();

        $items = array
        (
            'add' => array(),
            'delete' => array(),
            'update' => array()
        );

        Mage::dispatchEvent('cobby_import_product_bundleoption_import_before', array( 'products' => $productIds));

        foreach($rows as $productId => $productBundleOptions) {
            if (!in_array($productId, $existingProductIds))
                continue;

            $changedProductIds[] = $productId;
//            $items[$productId] = array(
//                'relations' =>  array(),
//                'options' => array(),
//                'titles' => array(),
//                'prices' => array(),
//                'selections' => array(),
//            );

            $selectionIndex = 0;
            foreach($productBundleOptions as $productBundleOption) {
                $action = $productBundleOption['action'];
//                 if($action == self::DELETE) {
//                     continue;
//                 }
//
//                if (!isset($items[$action][$productId]['product'])) {
//                    $items[$action][$productId] = $product[$productId];
//                }

                if (isset($productBundleOption['option_id'])) {
                    $nextOptionId = $productBundleOption['option_id'];
                } else {
                    $nextOptionId = $this->nextAutoOptionId++;
                }

                $items[$action][$productId]['options'][] = array(
                    'option_id'     => $nextOptionId,
                    'parent_id'     => $productId,
                    'type'          => $productBundleOption['type'],
                    'required'      => $productBundleOption['required'],
                    'position'      => $productBundleOption['position'],
                );

                foreach ($productBundleOption['titles'] as $productCustomOptionTitle) {
                    $items[$action][$productId]['titles'][] = array(
                        'option_id' => $nextOptionId,
                        'store_id'  => $productCustomOptionTitle['store_id'],
                        'title'     => $productCustomOptionTitle['title']
                    );
                }

                foreach ($productBundleOption['selections'] as $selection) {

                    if(isset($selection['selection_id'])){
                        $nextSelectionId = $selection['selection_id'];
                    } else {
                        $nextSelectionId = $this->nextAutoSelectionId++;
                    }

                    $items[$action][$productId]['selections'][] = array(
                        'selection_id' => $nextSelectionId,
                        'option_id' => $nextOptionId,
                        'product_id' => $selection['assigned_product_id'],
                        'parent_product_id' =>  $productId,
                        'position' => $selection['position'],
                        'is_default' => $selection['is_default'],
                        'selection_qty' => $selection['qty'],
                        'selection_can_change_qty' => $selection['can_change_qty'],
                    );

                    $items[$action][$productId]['relations'][] = array(
                        'parent_id' => $productId,
                        'child_id'  => $selection['assigned_product_id']
                    );


                    foreach ($selection['prices'] as $selectionPrice) {
                        $websiteId = $selectionPrice['website_id'];
                        if($websiteId == 0 ){
                            $items[$action][$productId]['selections'][$selectionIndex]['selection_price_value'][] = $selectionPrice['price_value'];
                            $items[$action][$productId]['selections'][$selectionIndex]['selection_price_type'][] = $selectionPrice['price_type'];
                            $selectionIndex++;
                        } else {
                            $items[$action][$productId]['prices'][] = array(
                                'selection_id'          => $nextSelectionId,
                                'website_id'            => $websiteId,
                                'selection_price_value' => $selectionPrice['price_value'],
                                'selection_price_type'  => $selectionPrice['price_type'],
                            );
                        }
                    }
                }
            }
        }

        if (count($items[self::ADD]) > 0) {
            $result[] = $this->addBundle($items[self::ADD]);
        }
        if (count($items[self::DELETE]) > 0) {
            $result[] = $this->deleteBundle($items[self::DELETE]);
        }
        if (count($items[self::UPDATE]) > 0) {
            $result[] = $this->updateBundle($items[self::UPDATE]);
        }

        $this->touchProducts($changedProductIds);

        Mage::dispatchEvent('cobby_import_product_bundleoption_import_after', array( 'products' => $changedProductIds));

        return array('product_ids' => $result);
    }

    protected function addBundle($items)
    {
        $result = array();
        foreach ($items as $productId => $item) {
            if ($item['options'] && count($item['options']) > 0) {
                $this->connection->insertOnDuplicate($this->optionTable, $item['options']);
                $this->connection->insertOnDuplicate($this->titleTable, $item['titles'], array('title'));
                if ($item['selections'] && count($item['selections']) > 0) {
                    $this->connection->insertMultiple($this->selectionTable, $item['selections']);
                    $this->connection->insertOnDuplicate($this->relationTable, $item['relations']);
                    if ($item['prices'] && count($item['prices']) > 0) {
                        $this->connection->insertOnDuplicate($this->selectionPriceTable, $item['prices'], array('selection_price_value', 'selection_price_type'));
                    }
                }
            }
            $result[] = $productId;
        }
        return $result;
    }

    protected function deleteBundle($items)
    {
        $result = array();
        foreach ($items as $productId => $item) {
            $result[] = $productId;
        }

        $this->connection->delete($this->optionTable, $this->connection->quoteInto('parent_id IN (?)', $result));
        $this->connection->delete($this->relationTable, $this->connection->quoteInto('parent_id IN (?)', $result));

        return $result;
    }

    protected function updateBundle($items)
    {

    }
}
