<?php
/**
 * Cobby Catalog category api
 *
 */
class Mash2_Cobby_Model_Catalog_Category_Api extends Mage_Catalog_Model_Category_Api
{
    
    public function export($storeId)
    {
        $result = array();

        /** var Mage_Catalog_Model_Resource_Category_Collection*/
        $categories = Mage::getModel('catalog/category')->getCollection()
            ->setStoreId($this->_getStoreId($storeId))
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('is_active')
            ->addAttributeToSort('name');

        foreach($categories as $category) {
            if($category->getParentId() == 0 && $category->getId() != 1) {
                continue;
            }

            $result[] = array(
                'category_id' => $category->getId(),
                'parent_id'   => $category->getParentId(),
                'name'        => $category->getName(),
                'is_active'   => $category->getIsActive(),
                'position'    => $category->getPosition(),
                'level'       => $category->getLevel()
            );
        }

        return $result;
    }
}