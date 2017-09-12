<?php
class Mash2_Cobby_Model_System_Config_Source_Chooseuser
{
    public function toOptionArray()
    {
        $result = array();

        $apiUsers = Mage::getModel('mash2_cobby/system_config_source_api_user')->toOptionArray();

        //show use existing, when api user with cobby permission exists
        if (count($apiUsers) > 0) {
            $result[] = array('value' => '', 'label'=>Mage::helper('mash2_cobby')->__('Please Select'));
            $result[] = array('value' => 1, 'label'=>Mage::helper('mash2_cobby')->__('Use Existing'));
        }

        $result[] = array('value' => 2, 'label'=>Mage::helper('mash2_cobby')->__('Create New'));
        return $result;
    }
}
