<?php

/**
 * StoreGroup API
 */
class Mash2_Cobby_Model_Core_Store_Group_Api extends Mage_Api_Model_Resource_Abstract
{
    /**
     * Retrieve store groups
     *
     * @return array
     */
    public function export()
    {
        $items = Mage::getModel('core/store_group')->getCollection();
        $result = array();

        foreach ($items as $item) {
            $result[] = array(
                'group_id' => $item->getGroupId(),
                'default_store_id' => $item->getDefaultStoreId(),
                'root_category_id' => $item->getRootCategoryId()
            );
        }
        return $result;
    }
}