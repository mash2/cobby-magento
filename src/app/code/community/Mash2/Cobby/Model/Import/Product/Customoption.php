<?php
class Mash2_Cobby_Model_Import_Product_Customoption extends Mash2_Cobby_Model_Import_Product_Abstract
{
    const ADD           = 'add';
    const DELETE        = 'delete';
    const NONE          = 'none';
    const UPDATE        = 'update';
    const UPDATE_TYPE   = 'update_type';


    private $productTable;
    private $optionTable;
    private $priceTable;
    private $titleTable;
    private $typePriceTable;
    private $typeTitleTable;
    private $typeValueTable;
    private $nextAutoOptionId;
    private $nextAutoValueId;


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
        $coreResource   = Mage::getSingleton('core/resource');
        $this->productTable   = $coreResource->getTableName('catalog/product');
        $this->optionTable    = $coreResource->getTableName('catalog/product_option');
        $this->priceTable     = $coreResource->getTableName('catalog/product_option_price');
        $this->titleTable     = $coreResource->getTableName('catalog/product_option_title');
        $this->typePriceTable = $coreResource->getTableName('catalog/product_option_type_price');
        $this->typeTitleTable = $coreResource->getTableName('catalog/product_option_type_title');
        $this->typeValueTable = $coreResource->getTableName('catalog/product_option_type_value');

        $this->nextAutoOptionId   = $this->resourceHelper->getNextAutoincrement($this->optionTable);
        $this->nextAutoValueId    = $this->resourceHelper->getNextAutoincrement($this->typeValueTable);
    }

    public function import($rows)
    {
        $result = array();

        $this->init();

        $productIds = array_keys($rows);
        $existingProductIds = $this->loadExistingProductIds($productIds);
        $changedProductIds = array();
        $deletePriceTable = array();
        $items = array
        (
            'add' => array(),
            'delete' => array(),
            'update' => array()
        );

        Mage::dispatchEvent('cobby_import_product_customoption_import_before', array( 'products' => $productIds ));

        foreach($rows as $productId => $productCustomOptions) {
            if (!in_array($productId, $existingProductIds))
                continue;

            $changedProductIds[] = $productId;
            $product[$productId] = array(
                'product' =>  array(
                    'entity_id'        => $productId,
                    'has_options'      => 0,
                    'required_options' => 0,
                    'updated_at'       => now()
                ),
                'options' => array(),
                'titles' => array(),
                'prices' => array(),
                'values' => array(),
                'values_titles' => array(),
                'values_prices' => array()
            );

            foreach($productCustomOptions as $productCustomOption) {
                if(isset($productCustomOption['option_id'])) {
                    $nextOptionId = $productCustomOption['option_id'];
                }else {
                    $nextOptionId = $this->nextAutoOptionId++;
                }

                $action = '';
                switch ($productCustomOption['action']) {
                    case self::ADD:
                        $action = 'add';
                        break;
                        case self::DELETE:
                        $action = 'delete';
                        break;
                    case self::UPDATE:
                        $action = 'update';
                        break;
                    case self::UPDATE_TYPE:
                        $deletePriceTable[] = $productCustomOption['option_id'];
                        $action = 'update';
                        break;

                }

                if (!isset($items[$action][$productId]['product'])) {
                    $items[$action][$productId] = $product[$productId];
                }

                $items[$action][$productId]['options'][] = array(
                    'option_id'      => $nextOptionId,
                    'sku'            => $productCustomOption['sku'],
                    'max_characters' => $productCustomOption['max_characters'],
                    'file_extension' => $productCustomOption['file_extension'],
                    'image_size_x'   => $productCustomOption['image_size_x'],
                    'image_size_y'   => $productCustomOption['image_size_y'],
                    'product_id'     => $productId,
                    'type'           => $productCustomOption['type'],
                    'is_require'     => $productCustomOption['is_require'],
                    'sort_order'     => $productCustomOption['sort_order'],
                );

                $items[$action][$productId]['product']['has_options'] = 1;
                if($productCustomOption['is_require'] == 1) { //if one is required, product should be set to required
                    $items[$action][$productId]['product']['required_options'] = 1;
                }

                foreach($productCustomOption['titles'] as $productCustomOptionTitle) {
                    $items[$action][$productId]['titles'][] = array(
                        'option_id' => $nextOptionId,
                        'store_id' => $productCustomOptionTitle['store_id'],
                        'title' => $productCustomOptionTitle['title']
                    );
                }

                foreach($productCustomOption['prices'] as $productCustomOptionPrice) {
                    $items[$action][$productId]['prices'][] = array(
                        'option_id' => $nextOptionId,
                        'store_id' => $productCustomOptionPrice['store_id'],
                        'price' => $productCustomOptionPrice['price'],
                        'price_type' => $productCustomOptionPrice['price_type']
                    );
                }

                foreach($productCustomOption['values'] as $value) {

                    if(isset($value['option_type_id'])){
                        $nextValueId = $value['option_type_id'];
                    } else {
                        $nextValueId = $this->nextAutoValueId++;
                    }

                    if ($productCustomOption['action'] == 'add' && $value['action'] == 'add') {
                        $items[$action][$productId]['values'][] = array(
                            'option_type_id' => $nextValueId,
                            'option_id' => $nextOptionId,
                            'sku' => $value['sku'],
                            'sort_order' => $value['sort_order']
                        );
                    }
                    else {
                        $items[$action][$productId]['values'][] = array(
                            'option_type_id' => $nextValueId,
                            'option_id' => $nextOptionId,
                            'action' => $value['action'],
                            'sku' => $value['sku'],
                            'sort_order' => $value['sort_order']
                        );
                    }

                    foreach($value['titles'] as $valueTitle) {
                        $items[$action][$productId]['values_titles'][] = array(
                            'option_type_id' => $nextValueId,
                            'store_id' => $valueTitle['store_id'],
                            'title' => $valueTitle['title'],
                        );
                    }

                    foreach($value['prices'] as $valuePrice) {
                        $items[$action][$productId]['values_prices'][] = array(
                            'option_type_id' => $nextValueId,
                            'store_id' => $valuePrice['store_id'],
                            'price' => $valuePrice['price'],
                            'price_type' => $valuePrice['price_type'],
                        );
                    }
                }
            }

            $result[] = $productId;
        }

        if (count($deletePriceTable) > 0) {
            $this->deletePriceTable($deletePriceTable);
        }

        if (count($items['add']) > 0) {
            $this->addOption($items['add']);
        }
        if (count($items['delete']) > 0) {
            $this->deleteOption($items['delete']);
        }
        if (count($items['update']) > 0) {
            $this->updateOption($items['update']);
        }

        $this->touchProducts($changedProductIds);

        Mage::dispatchEvent('cobby_import_product_customoption_import_after', array( 'products' => $changedProductIds ));

        return true;
    }


    protected function addOption($options)
    {
        foreach($options as $productId => $item) {
            $this->connection->insertOnDuplicate($this->productTable, $item['product'], array('has_options', 'required_options', 'updated_at'));
            if($item['options'] && count($item['options']) > 0) {
                $this->connection->insertOnDuplicate($this->optionTable, $item['options']);
                $this->connection->insertOnDuplicate($this->titleTable, $item['titles'], array('title'));
                if($item['prices'] && count($item['prices']) > 0) {
                    $this->connection->insertOnDuplicate($this->priceTable, $item['prices'], array('price', 'price_type'));
                }
                if($item['values'] && count($item['values']) > 0) {
                    $this->connection->insertOnDuplicate($this->typeValueTable, $item['values'], array('sku', 'sort_order'));
                    $this->connection->insertOnDuplicate($this->typeTitleTable, $item['values_titles'], array('title'));
                    $this->connection->insertOnDuplicate($this->typePriceTable, $item['values_prices'], array('price', 'price_type'));
                }
            }
        }
    }

    protected function deleteOption($options)
    {
        $items = array();
        foreach ($options as $productId => $optionsTableData) {
            foreach ($optionsTableData['options'] as $option) {
                $items[] = $option['option_id'];
            }
        }

        $this->connection->delete($this->optionTable, array($this->connection->quoteInto('option_id IN (?)', $items)));
    }

    protected function deletePriceTable($optionIds)
    {
        $this->connection->delete($this->priceTable, $this->connection->quoteInto('option_id IN (?)', $optionIds));
    }

    protected function updateOption($options)
    {
        foreach ($options as $productId => $item) {
            if($item['options'] && count($item['options']) > 0) {
                foreach ($item['options'] as $option) {
                    $optionId = $option['option_id'];
                    $this->connection->update($this->optionTable, $option, array(
                        $this->connection->quoteInto('option_id = ?', $optionId)
                    ));
                }
            }
            if ($item['titles'] && count($item['titles']) > 0) {
                foreach ($item['titles'] as $title) {
                    $this->connection->update($this->titleTable, $title, array(
                        $this->connection->quoteInto('option_id = ?', $title['option_id'])
                    ));
                }
            }
            if($item['prices'] && count($item['prices']) > 0) {
                foreach ($item['prices'] as $price)
                    $this->connection->update($this->priceTable, $price, array(
                        $this->connection->quoteInto('option_id = ?', $price['option_id'])
                    ));
            }

            $subOptions = array();

            foreach ($item['values'] as $value) {
                $subOptions[$value['option_type_id']] = $value;
            }
            foreach ($item['values_titles'] as $value) {
                if (array_key_exists($value['option_type_id'], $subOptions)) {
                    $subOptions[$value['option_type_id']]['values_titles'] = $value;
                }
            }
            foreach ($item['values_prices'] as $value) {
                if (array_key_exists($value['option_type_id'], $subOptions)) {
                    $subOptions[$value['option_type_id']]['values_prices'] = $value;
                }
            }

            foreach ($subOptions as $subOption) {
                $action = $subOption['action'];
                $valuesTitles = $subOption['values_titles'];
                $valuesPrices= $subOption['values_prices'];
                unset($subOption['values_titles']);
                unset($subOption['values_prices']);
                unset($subOption['action']);

                switch ($action) {
                    case self::ADD:
                        $this->connection->insertOnDuplicate($this->typeValueTable, $subOption, array('sku', 'sort_order'));
                        $this->connection->insertOnDuplicate($this->typeTitleTable, $valuesTitles, array('title'));
                        $this->connection->insertOnDuplicate($this->typePriceTable, $valuesPrices, array('price', 'price_type'));
                        break;
                        case self::DELETE:
                            $this->connection->delete($this->typeValueTable, $this->connection->quoteInto('option_type_id = ?', $subOption['option_type_id']));
                            break;
                            case self::UPDATE:
                                $this->connection->update($this->typeValueTable, $subOption, array(
                                    $this->connection->quoteInto('option_type_id = ?', $subOption['option_type_id'])));
                                $this->connection->update($this->typeTitleTable, $valuesTitles, array(
                                    $this->connection->quoteInto('option_type_id = ?', $subOption['option_type_id'])));
                                $this->connection->update($this->typePriceTable, $valuesPrices, array(
                                    $this->connection->quoteInto('option_type_id = ?', $subOption['option_type_id'])));
                }
            }
        }

    }
}