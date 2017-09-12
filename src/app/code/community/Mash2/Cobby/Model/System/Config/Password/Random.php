<?php
class Mash2_Cobby_Model_System_Config_Password_Random extends Mage_Adminhtml_Model_System_Config_Backend_Encrypted
{
    protected function _afterLoad()
    {
        $value = Mage::helper('core')->getRandomString($length = 20);
        $this->setValue($value);
    }
}