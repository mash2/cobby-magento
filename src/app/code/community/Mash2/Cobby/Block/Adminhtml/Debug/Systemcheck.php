<?php

class Mash2_Cobby_Block_Adminhtml_Debug_Systemcheck extends Mage_Adminhtml_Block_Widget_Form
{
    private $helper;
    protected $phpVersion;
    protected $memory;
    protected $credentials;
    protected $maintenance;
    protected $indexer;
    protected $url;

    public function __construct()
    {
        $this->setTemplate('cobby/system/config/debug/systemcheck.phtml');
        $this->helper = Mage::helper('mash2_cobby/systemcheck');
        $this->setCredentials();
        $this->setMemory();
        $this->setPhpVersion();
        $this->setMaintenanceMode();
        $this->setIndexerStatus();
        $this->setUrl();
    }

    public function getMemory()
    {
        return $this->htmlBuilder($this->memory);
    }

    public function getPhpVersion()
    {
        return $this->htmlBuilder($this->phpVersion);
    }

    public function getCredentials()
    {
        return $this->htmlBuilder($this->credentials);
    }

    public function getMaintenanceMode()
    {
        return $this->htmlBuilder($this->maintenance);
    }

    public function getIndexerStatus()
    {
        return $this->htmlBuilder($this->indexer);
    }

    public function getUrlCheck()
    {
        return $this->htmlBuilder($this->url);
    }

    public function getIcon($section)
    {
        $ret = '<img src="/skin/adminhtml/default/default/images/';
        $code = $section[Mash2_Cobby_Helper_Systemcheck::CODE];

        switch ($code) {
            case Mash2_Cobby_Helper_Systemcheck::OK:
                $ret .= 'fam_bullet_success.gif">';
                break;
            case Mash2_Cobby_Helper_Systemcheck::ERROR:
                $ret .= 'error_msg_icon.gif">';
                break;
            case Mash2_Cobby_Helper_Systemcheck::EXCEPTION:
                $ret .= 'fam_bullet_error.gif">';
                break;
        }

        return $ret;
    }

    private function htmlBuilder($transport)
    {
        $code = $transport[Mash2_Cobby_Helper_Systemcheck::CODE];
        $value = $transport[Mash2_Cobby_Helper_Systemcheck::VALUE];
        $link = $transport[Mash2_Cobby_Helper_Systemcheck::LINK];
        $ret = '';

        switch ($code) {
            case Mash2_Cobby_Helper_Systemcheck::OK:
                $ret = '<span class="ok">' . $this->__($value) . '</span>';
                break;
            case Mash2_Cobby_Helper_Systemcheck::ERROR:
                $ret =  '<span class="error">' . $this->__($value) . '</span>';
                $ret .=  '<a target="_blank" href=' . $link . '><div class="field-tooltip"></div></a>';
                break;
            case Mash2_Cobby_Helper_Systemcheck::EXCEPTION:
                $ret = '<span class="exception">' . $this->__($value) . '</span>';
                $ret .= '<a target="_blank" href=' . $link . '><div class="field-tooltip"></div></a>';
                break;
        }

        return $ret;
    }

    private function setMemory()
    {
        $this->memory = $this->helper->checkMemory();
    }

    private function setPhpVersion()
    {
        $this->phpVersion = $this->helper->checkPhpVersion();
    }

    private function setCredentials()
    {
        $this->credentials = $this->helper->checkCredentials();
    }

    private function setMaintenanceMode()
    {
        $this->maintenance = $this->helper->checkMaintenanceMode();
    }

    private function setIndexerStatus()
    {
        $this->indexer = $this->helper->checkIndexerStatus();
    }

    private function setUrl()
    {
        $this->url = $this->helper->checkUrl();
    }
}
