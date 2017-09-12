<?php
class Mash2_Cobby_Model_Import_Product_Category extends Mash2_Cobby_Model_Import_Product_Abstract
{
    /**
     * @var string category Table Name
     */
    protected $categoryTable;

    /**
     * @var Mage_Catalog_Model_Resource_Category_Collection
     */
    private $categoryCollection;

    /**
     * @var Mash2_Cobby_Helper_Settings
     */
    private $settings;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->categoryTable        = $this->resourceModel->getTableName('catalog/category_product');
        $this->categoryCollection   = Mage::getResourceModel('catalog/category_collection');
        $this->settings             = Mage::helper('mash2_cobby/settings');
    }

    private function getCategoryProductPositions($productIds)
    {
        $select = $this->connection->select()
            ->from($this->categoryTable)
            ->where('product_id IN (?)', $productIds);

        $stmt = $this->connection->query($select);
        $result = array();
        while ($row = $stmt->fetch()) {
            $productId = $row['product_id'];
            if(!isset($result[$productId])) {
                $result[$productId] = array();
            }

            $result[$productId][$row['category_id']] = $row['position'];
        }
        return $result;
    }

    private function getCategoryIds()
    {
        return $this->categoryCollection->getAllIds();
    }

    public function import($rows)
    {
        $result = array();

        if ($rows) {
            $defaultPosition = $this->settings->getProductCategoryPosition();
            $productIds = $this->getColumnValues($rows, 'product_id');
            $productCategoryPositions = $this->getCategoryProductPositions($productIds);
            $availableCategoryIds = $this->getCategoryIds();
            $existingProductIds = $this->loadExistingProductIds($productIds);

            $categoriesIn = array();
            $changedCategoryIds = array();
            $changedProductIds = array();

            foreach ($rows as $row) {
                $productId = $row['product_id'];
                $productLog = array( 'product_id' => $productId, 'categories' => array(), 'log' => 'not found');
                if (in_array($productId, $existingProductIds)) {
                    $productLog['log'] = 'added';

                    $changedProductIds[] = $productId;
                    $categoryPositions = array();
                    if (isset($productCategoryPositions[$productId])) {
                        $categoryPositions = $productCategoryPositions[$productId];
                        $productLog['log'] = 'updated';
                    }

                    foreach ($row['categories'] as $categoryId) {
                        $categoryLog = array(
                            'category_id' => $categoryId,
                            'position' => $defaultPosition,
                            'log' => 'not found');

                        if (in_array($categoryId, $availableCategoryIds)) {
                            $categoryLog['log'] = 'added';

                            $position = $defaultPosition;
                            if (array_key_exists($categoryId, $categoryPositions)) {
                                $position = (int)$categoryPositions[$categoryId];
                                $categoryLog['log'] = 'updated';
                            }
                            $categoryLog['position'] = $position;

                            $changedCategoryIds[] = $categoryId;

                            $categoriesIn[] = array(
                                'product_id' => $productId,
                                'category_id' => $categoryId,
                                'position' => $position);
                        }
                        $productLog['categories'][] = $categoryLog;
                    }
                }
                $result[] = $productLog;
            }

            Mage::dispatchEvent('cobby_import_product_category_import_before', array(
                'products' => $changedProductIds
            ));

            $this->connection->delete(
                $this->categoryTable,
                $this->connection->quoteInto('product_id IN (?)', $changedProductIds)
            );

            if ($categoriesIn) {
                $this->connection->insertOnDuplicate($this->categoryTable, $categoriesIn, array('position'));
            }

            $this->touchProducts($changedProductIds);

            Mage::dispatchEvent('cobby_import_product_category_import_after', array(
                'products' => $changedProductIds,
                'categories' => $changedCategoryIds
            ));
        }

        return $result;
    }
}