<?php
class Mash2_Cobby_Block_Adminhtml_Notifications extends Mage_Adminhtml_Block_Template
{
    protected function _construct()
    {
        $this->addData(array('cache_lifetime'=> null));
    }

    public function isInitialized()
    {
        return Mage::getStoreConfig('cobby/settings/api_key') != '';
    }

    public function getConfigUrl()
    {
        return $this->getUrl('adminhtml/system_config/edit/section/cobby/');
    }

    protected function _toHtml()
    {
        if (Mage::getSingleton('admin/session')->isAllowed('system/cobby')) {
            return parent::_toHtml();
        }
        return '';
    }
}
