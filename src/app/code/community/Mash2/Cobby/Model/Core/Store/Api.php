<?php
/**
 * Store API
 *
 */
class Mash2_Cobby_Model_Core_Store_Api extends Mage_Api_Model_Resource_Abstract
{
    /**
     * Retrieve stores list
     *
     * @return array
     */
    public function export()
    {
        // Retrieve stores
        $stores = Mage::app()->getStores(true);

        // Make result array
        $result = array();
        $sortOrder = 0;
        foreach ($stores as $store) {
            $result[] = array(
                'store_id'    => $store->getId(),
                'code'        => $store->getCode(),
                'group_id'    => $store->getGroupId(),
                'website_id'  => $store->getWebsiteId(),
                'name'        => $store->getName(),
                'is_active'   => $store->getIsActive(),
                'sort_order'  => $sortOrder, //$store->getSortOrder(),
                'locale'      => $store->getConfig('general/locale/code')
            );
            $sortOrder++;
        }

        return $result;
    }
}