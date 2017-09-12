<?php
/**
 * Cobby cache api
 */
class Mash2_Cobby_Model_Cache_Api extends Mage_Api_Model_Resource_Abstract
{
    private function isCacheEnabled()
    {
        if(Mage::helper('core')->isModuleEnabled('Phoenix_VarnishCache')){
            return true;
        }

        return false;
    }

    /**
     * Purge categories
     * @param $categoryIds
     * @return bool
     */
    public function purgeCategories($categoryIds)
    {
        $result  = array();

        if( !$this->isCacheEnabled() )
            return $result;

        $categories = Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToFilter('entity_id', array('in' => $categoryIds));

        foreach($categories as $category)
        {
            Mage::dispatchEvent('cobby_clean_category_cache', array('category' => $category));
            $result[] = $category->getId();
        }

        return $result;
    }

    /**
     * Purge products
     * @param $productIds
     * @return array
     */
    public function purgeProducts($productIds)
    {
        $result  = array();

        if( !$this->isCacheEnabled() )
            return $result;

        /* @var $products Mage_Catalog_Model_Resource_Product_Collection */
        $products = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToFilter('entity_id', array('in' => $productIds));

        foreach($products as $product)
        {
            Mage::dispatchEvent('cobby_clean_product_cache', array('product' => $product));
            $result[] = $product->getId();
        }
        return $result;
    }
}