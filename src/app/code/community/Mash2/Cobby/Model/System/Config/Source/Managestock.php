<?php
/**
 * Created by PhpStorm.
 * User: Slavko
 * Date: 09.02.2017
 * Time: 16:25
 */

class Mash2_Cobby_Model_System_Config_Source_Managestock
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => Mash2_Cobby_Helper_Settings::MANAGE_STOCK_ENABLED,
                'label' => Mage::helper('mash2_cobby/settings')->__('enabled')
            ),
            array(
                'value' => Mash2_Cobby_Helper_Settings::MANAGE_STOCK_READONLY,
                'label' => Mage::helper('mash2_cobby/settings')->__('readonly')
            ),
            array(
                'value' => Mash2_Cobby_Helper_Settings::MANAGE_STOCK_DISABLED,
                'label' => Mage::helper('mash2_cobby/settings')->__('disabled')
            )
        );
    }
}