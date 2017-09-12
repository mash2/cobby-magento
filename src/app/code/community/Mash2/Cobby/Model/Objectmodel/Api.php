<?php
/**
 * cobby objectmodel api (legacy)
 */
class Mash2_Cobby_Model_Objectmodel_Api extends Mage_Api_Model_Resource_Abstract
{
    /**
     * Retrieve current magento version
     *
     * @return mixed
     */
    public function currentVersion()
    {
        return Mage::getVersion();
    }

    /**
     * Retrieve store groups
     *
     * @return array
     */
    public function storegroups()
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