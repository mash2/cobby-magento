<?php

class Mash2_Cobby_Model_Core_Debug_Api extends Mage_Api_Model_Resource_Abstract
{
    public function export()
    {
        $result = array();

        $model = Mage::getModel('catalog/product');
        $modelTypes = Mage::getModel('catalog/product_type')->getOptionArray();

        foreach (Mage::app()->getStores(true) as $store) {
            foreach ($modelTypes as $key => $value) {
                $typesCount[$key] = $model
                    ->getCollection()
                    ->addStoreFilter($store->getId())
                    ->addAttributeToFilter('type_id', $key)
                    ->getSize();
            }

            $result[] = array(
                "store_id"              => $store->getId(),
                "store_name"            => $store->getName(),
                "store_code"            => $store->getCode(),
                "website_id"            => $store->getWebsite()->getId(),
                "website_name"          => $store->getWebsite()->getName(),
                "product_count"         => array_sum($typesCount),
                "product_types"         => $typesCount);
        }

        return $result;
    }
}