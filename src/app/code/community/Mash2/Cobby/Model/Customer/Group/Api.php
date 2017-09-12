<?php
/**
 * Created by PhpStorm.
 * User: Dima
 * Date: 22.04.15
 * Time: 15:13
 */
class Mash2_Cobby_Model_Customer_Group_Api extends Mage_Api_Model_Resource_Abstract
{
    /**
     * Retrieve custoemr group list
     *
     * @return array
     */
    public function export()
    {
        $result = array();

        $customerGroups = Mage::getModel('customer/group')->getCollection();
        foreach($customerGroups as $customerGroup){
            $groupData = $customerGroup->getData();

            $result[] = array(
                'group_id'  => $groupData['customer_group_id'],
                'name'      => $groupData['customer_group_code']
            );
        }

        return $result;
    }
}