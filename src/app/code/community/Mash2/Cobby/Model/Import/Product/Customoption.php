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

        if (count($items[self::ADD]) > 0) {
            $this->addOption($items[self::ADD]);
        }
        if (count($items[self::DELETE]) > 0) {
            $this->deleteOption($items[self::DELETE]);
        }
        if (count($items[self::UPDATE]) > 0) {
            $this->updateOption($items[self::UPDATE]);
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
        $subOptions = array();
        $optionType = array();

        foreach ($options as $productId => $item) {
            if($item['options'] && count($item['options']) > 0) {
//                foreach ($item['options'] as $option) {
//                    $optionId = $option['option_id'];
//                    $this->connection->update($this->optionTable, $option, array(
//                        $this->connection->quoteInto('option_id = ?', $optionId)
//                    ));
//                }
                //$this->connection->update($this->optionTable, $item['options']);
                $this->connection->insertOnDuplicate($this->optionTable, $item['options']);

            }
            if ($item['titles'] && count($item['titles']) > 0) {
//                foreach ($item['titles'] as $title) {
//                    $this->connection->update($this->titleTable, $title, array(
//                        $this->connection->quoteInto('option_id = ?', $title['option_id'])
//                    ));
//                }
//                $this->connection->update($this->titleTable, $item['titles'], array(
//                    $this->connection->quoteInto('option_id IN (?)', $item['titles'])
//                ));
                $this->connection->insertOnDuplicate($this->titleTable, $item['titles']);
            }
            if($item['prices'] && count($item['prices']) > 0) {
//                foreach ($item['prices'] as $price) {
//                    $this->connection->update($this->priceTable, $price, array(
//                        $this->connection->quoteInto('option_id = ?', $price['option_id'])
//                    ));
//                }
//                $this->connection->update($this->priceTable, $item['prices'], array(
//                    $this->connection->quoteInto('option_id IN (?)', $item['prices'])
//                ));
                $this->connection->insertOnDuplicate($this->priceTable, $item['prices']);
            }

            /*
             * $suboption[$action][$subOpId][$value|$title|$price]
             */

            foreach ($item['values'] as $value) {
                $value['tableName'] = $this->typeValueTable;
                $subOptions[$value['option_type_id']][] = $value;
            }
            foreach ($item['values_titles'] as $value) {
                $value['tableName'] = $this->typeTitleTable;
                $subOptions[$value['option_type_id']][] = $value;
            }
            foreach ($item['values_prices'] as $value) {
                $value['tableName'] = $this->typePriceTable;
                $subOptions[$value['option_type_id']][] = $value;
            }

            foreach ($subOptions as $subOptionId => $options) {
                $action = null;
                foreach ($options as $option) {
                    if (isset($option['action'])) {
                        $action = $option['action'];
                        unset($option['action']);
                    }
                    $optionType[$action][$subOptionId][] = $option;
                }
            }

//            foreach ($item['values'] as $value) {
//                $subOptionId = $value['option_type_id'];
//                $action = $value['action'];
//                unset($value['action']);
//                $subOptions[$action][$subOptionId][$this->typeValueTable][] = $value;
//            }
//            foreach ($item['values_titles'] as $value) {
//                $subOptions[$action][$subOptionId][$this->typeTitleTable][] = $value;
//            }
//            foreach ($item['values_prices'] as $value) {
//                $subOptions[$action][$subOptionId][$this->typePriceTable][] = $value;
//            }

//            foreach ($optionTypes as $table => $optionType) {
//                $action = $optionType['action'];
//                $valuesTitles = $optionType['values_titles'];
//                $valuesPrices= $optionType['values_prices'];
//                //unset($optionType['values_titles']);
//                //unset($optionType['values_prices']);
//                unset($optionType['action']);
//
//
//                $subOptions[$action][$this->typeValueTable] = $optionType;
//                $subOptions[$action][$this->typeTitleTable] = $valuesTitles;
//                $subOptions[$action][$this->typePriceTable] = $valuesPrices;
//            }
        }

//        if (count($subOptions[self::ADD]) > 0) {
//            $this->addSubOption($subOptions[self::ADD]);
//        }
//        if (count($subOptions[self::DELETE]) > 0) {
//            $this->deleteSubOption($subOptions[self::DELETE]);
//        }
//        if (count($subOptions[self::UPDATE]) > 0) {
//            $this->updateSubOption($subOptions[self::UPDATE]);
//        }

        if (count($optionType[self::ADD]) > 0) {
            $this->addSubOption($optionType[self::ADD]);
        }
        if (count($optionType[self::DELETE]) > 0) {
            $this->deleteSubOption($optionType[self::DELETE]);
        }
        if (count($optionType[self::UPDATE]) > 0) {
            $this->updateSubOption($optionType[self::UPDATE]);
        }

    }

    protected function addSubOption($options)
    {
        $add = array();

        foreach ($options as $subOptionId => $subOptions) {
            foreach ($subOptions as $subOption) {
                $tableName = $subOption['tableName'];
                unset($subOption['tableName']);
                $add[$tableName][] = $subOption;

//                switch ($tableName) {
//                    case $this->typeValueTable:
//                        $values = array('sku', 'sort_order');
//                        break;
//                    case $this->typeTitleTable:
//                        $values = array('title');
//                        break;
//                    case $this->typePriceTable:
//                        $values = array('price', 'price_type');
//                        break;
//                }
                //$this->connection->insertOnDuplicate($tableName, $subOptionValue, $values);
            }
        }
        foreach ($add as $tableName => $value) {
            $values = null;
            switch ($tableName) {
                case $this->typeValueTable:
                    $values = array('sku', 'sort_order');
                    break;
                case $this->typeTitleTable:
                    $values = array('title');
                    break;
                case $this->typePriceTable:
                    $values = array('price', 'price_type');
                    break;
            }
            $this->connection->insertOnDuplicate($tableName, $value, $values);
        }

    }

    protected function deleteSubOption($options)
    {
        $optionTypeIds = array_keys($options);
//        foreach ($options as $subOption) {
//            $optionTypeIds[] = $subOption['option_type_id'];
//        }

        $this->connection->delete($this->typeValueTable, $this->connection->quoteInto('option_type_id IN (?)', $optionTypeIds));
    }

    protected function updateSubOption ($options)
    {
        $add = array();
        foreach ($options as $subOptionId => $subOptions) {
            foreach ($subOptions as $subOption) {
                $tableName = $subOption['tableName'];
                unset($subOption['tableName']);
                $add[$tableName][] = $subOption;

//            $this->connection->update($tableName, $subOption, array(
//                $this->connection->quoteInto('option_type_id = ?', $subOption['option_type_id'])));
            }
        }

        foreach ($add as $tableName => $value) {
            $this->connection->insertOnDuplicate($tableName, $value);
        }
    }
}