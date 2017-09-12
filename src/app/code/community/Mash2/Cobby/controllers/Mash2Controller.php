<?php
/*
 * Copyright 2013 mash2 GbR http://www.mash2.com
 *
 * ATTRIBUTION NOTICE
 * Parts of this work are adapted from Branko Ajzele
 * Original title Inchoo_Api
 * The work can be found http://ext.2magento.com/Inchoo_Api.html
 *
 * ORIGINAL COPYRIGHT INFO
 *
 * author      Branko Ajzele, ajzele@gmail.com
 * category    Inchoo
 * package     Inchoo_Api
 * copyright   Copyright (c) Inchoo LLC (http://inchoo.net)
 * license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*/

/**
 * Json api controller
 */
class Mash2_Cobby_Mash2Controller extends Mage_Api_Controller_Action
{
    /**
     * Access xml-rpc api as json
     */
    public function jsonAction()
    {
        $this->_getServer()
            ->init($this, 'mash2_cobby_json', 'mash2_cobby_json')
            ->run();
    }

    public function htmlAction()
    {
        $this->_getServer()
            ->init($this, 'mash2_cobby_html')
            ->run();
    }

    public function loginAction()
    {
        $username = $this->getRequest()->getPost('username');
        $password = $this->getRequest()->getPost('password');
        $session = Mage::getSingleton('mash2_cobby/admin_session');
        $auth = $session->login($username, $password);
        $result = '';
        if($auth->getId()){
            $result = $session->getSessionId();
        }

        if(!empty($result))
        {
            $result = $result . ';' . $session->getUser()->getId(). ';' . $session->getUser()->getRole()->getId();
        }

        echo $result;
    }

    public function isLoggedInAction()
    {
        $username = $this->getRequest()->getPost('username');
        $roleId = $this->getRequest()->getPost('roleid');
        $session = Mage::getSingleton('admin/session');

        if(
            $session->isLoggedIn()
            && strtolower($session->getUser()-> getUsername()) == strtolower($username)
            && $roleId == $session->getUser()->getRole()->getId()
        )
        {
            echo 'true';
        }
        else
        {
            echo 'false';
        }
    }

    /**
     * Retrive product media config
     *
     * @return Mage_Catalog_Model_Product_Media_Config
     */
    private function getMediaConfig()
    {
        return Mage::getSingleton('catalog/product_media_config');
    }

    public function getGalleryImagesAction(){

        $result = "";
        $id = $this->getRequest()->getParam('id');
        $type = $this->getRequest()->getParam('type');

        if($id)
        {
            $product = Mage::getModel('catalog/product')->load($id);
            foreach ($product->getMediaGallery('images') as $image) {
                if($type)
                    $result .= Mage::helper('catalog/image')->init($product, $type, $image['file']) . ';';
                else
                    $result .= $this->getMediaConfig()->getMediaUrl($image['file']) . ';';
            }
        }

        echo $result;
    }
}
