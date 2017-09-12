<?php
/**
 * Website API
 *
 */
class Mash2_Cobby_Model_Core_Website_Api extends Mage_Api_Model_Resource_Abstract
{
    /**
     * Retrieve website list
     *
     * @return array
     */
    public function export()
    {
        // Retrieve websites
        $websites = Mage::app()->getWebsites(true, true);

        // Make result array
        $result = array();
        $sortOrder = 0;
        foreach ($websites as $website) {
            $result[] = array(
                'website_id'        => $website->getWebsiteId(),
                'code'              => $website->getCode(),
                'name'              => $website->getName(),
                'default_group_id'  => $website->getDefaultGroupId(),
                'is_default'        => $website->getIsDefault(),
                'sort_order'        => $sortOrder //$website->getSortOrder()
            );
            $sortOrder++;
        }

        return $result;
    }
}