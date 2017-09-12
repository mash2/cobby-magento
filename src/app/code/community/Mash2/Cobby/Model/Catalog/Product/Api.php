<?php
/**
 * Cobby Catalog product api
 *
 */
class Mash2_Cobby_Model_Catalog_Product_Api extends Mage_Catalog_Model_Product_Api
{ 
    public function updateSkus($rows)
    {
        Mage::register('is_cobby_import', 1);

        $result = array();
        $productIds = array();

        foreach($rows as $row) {
            $productId = $row['product_id'];
            $sku = $row['sku'];
            $changed = false;

            if (!empty($sku)) {
                $product = Mage::getModel('catalog/product')
                    ->load($productId);

                if ($product->getSku() != null && $product->getSku() !== $sku) {
                    $product->setSku($sku);
                    $product->save();
                    $changed = true;
                }
            }
            $result[] = array('product_id' => $productId, 'sku'  => $sku, 'changed' => $changed);
            $productIds[] = $productId;
        }

        Mage::getModel('mash2_cobby/product')->updateHash($productIds);

        return $result;
    }

    public function getAllIds($pageNum, $pageSize){
        $result = array();
        
        $collection = Mage::getResourceModel('catalog/product_collection')
            ->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
            ->setPage($pageNum, $pageSize)
            ->load();

        foreach ($collection as $item){
            $result[] = array(
                'entity_id' => $item->getId(),
                'sku'  => $item->getSku(),
                'type_id' => $item->getTypeId()
            ); 
        }

        return $result;
    }

    public function updateWebsites($rows)
    {
        Mage::register('is_cobby_import', 1);
        $connection = Mage::getSingleton('core/resource')->getConnection('write');
        $tableName = Mage::getModel('importexport/import_proxy_product_resource')->getProductWebsiteTable();

        foreach (Mage::app()->getWebsites() as $website) {
            $websiteIds[] = $website->getId();
        }

        $productIds = array_keys($rows);
        $collection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToFilter('entity_id', array('in' => $productIds));

        $existingProductIds = $collection->getAllIds();

        $result = array();

        if ($rows) {
            foreach ($rows as $productId => $websites) {
                if (!in_array($productId, $existingProductIds)) {
                    continue;
                }

                $item = array(
                    'product_id' => $productId,
                    'added' => array(),
                    'removed' => array());

                if ($websites['add']) {
                    foreach ($websites['add'] as $websiteId) {
                        if(in_array($websiteId, $websiteIds)) {
                            $websitesData[] = array(
                                'product_id' => $productId,
                                'website_id' => $websiteId
                            );
                            $item['added'][] = $websiteId;
                        }
                    }

                    $connection->insertOnDuplicate($tableName, $websitesData);
                }
                if ($websites['remove']) {
                    $connection->delete($tableName,
                        array($connection->quoteInto('product_id = ?', $productId),
                            $connection->quoteInto('website_id IN (?)', $websites['remove'])
                        )
                    );
                    $item['removed'] = $websites['remove'];
                }

                $result[] = $item;
            }
            Mage::getModel('mash2_cobby/product')->updateHash($productIds);
        }

        return $result;
    }
}