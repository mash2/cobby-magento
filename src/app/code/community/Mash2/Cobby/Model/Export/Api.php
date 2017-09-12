<?php
/**
 * Export API
 */
class Mash2_Cobby_Model_Export_Api extends Mage_Api_Model_Resource_Abstract
{
    public function exportProducts($filterProductIds)
    {
        return Mage::getModel('mash2_cobby/export_entity_product')
            ->exportProducts($filterProductIds);
    }
}