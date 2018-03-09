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
        if ($auth->getId()) {
            $result = $session->getSessionId();
        }

        if (!empty($result)) {
            $result = $result . ';' . $session->getUser()->getId() . ';' . $session->getUser()->getRole()->getId();
        }

        echo $result;
    }

    public function isLoggedInAction()
    {
        $username = $this->getRequest()->getPost('username');
        $roleId = $this->getRequest()->getPost('roleid');
        $session = Mage::getSingleton('admin/session');

        if (
            $session->isLoggedIn()
            && strtolower($session->getUser()->getUsername()) == strtolower($username)
            && $roleId == $session->getUser()->getRole()->getId()
        ) {
            echo 'true';
        } else {
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

    private function getImagePath($id, $filename)
    {
        $baseMediaUrl = Mage::getBaseUrl('media');
        $baseMediaDir = Mage::getBaseDir('media') . DS;

        if ((int)$id){
            $product = Mage::getModel('catalog/product')->load($id);
            if ($product->getId()) {
                foreach ($product->getMediaGallery('images') as $image) {
                    $tokens = explode('/', $image['file']);
                    $str = trim(end($tokens));
                    if ($str == $filename){
                        $file = Mage::helper('catalog/image')->init($product, 'thumbnail', $image['file']);
                        $fileString = (string)$file;
                        $filePath = str_replace($baseMediaUrl, $baseMediaDir, $fileString);

                        return $filePath;
                    }
                }
            }
        } else if (file_exists($baseMediaDir . 'import/'. $filename)) {
            $filePath = $baseMediaDir . 'import/' . $filename;

            return $filePath;
        }

        $placeholder = Mage::getDesign()->getSkinUrl('images/catalog/product/placeholder/thumbnail.jpg');
        $baseSkinUrl = Mage::getBaseUrl('skin');
        $baseSkinDir = Mage::getBaseDir() . DS . 'skin' . DS;
        $filePath = str_replace($baseSkinUrl, $baseSkinDir, $placeholder);

        return $filePath;
    }

    public function getImageAction()
    {
        $ioAdapter = new Varien_Io_File();

        $fileName = $this->getRequest()->getParam('filename');
        $id = $this->getRequest()->getParam('id');

        $filePath = $this->getImagePath($id, $fileName);

        $contentType = 'image/jpeg';
        $this->getResponse()
            ->setHttpResponseCode(200)
//            ->setHeader('Pragma', 'public', true)
//            ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
            ->setHeader('Content-type', $contentType, true);
//            ->setHeader('Content-Length', filesize($file), true)
//            ->setHeader('Content-Disposition', 'attachment; filename="'.$file.'"', true)
//            ->setHeader('Last-Modified', date('r'), true);

        $this->getResponse()->clearBody();
        $this->getResponse()->sendHeaders();

        $ioAdapter->open(array('path' => $ioAdapter->dirname($filePath)));
        $ioAdapter->streamOpen($filePath, 'r');
        while ($buffer = $ioAdapter->streamRead()) {
            print $buffer;
        }
        $ioAdapter->streamClose();
    }

    public function getGalleryImagesAction()
    {

        $result = array();
        $id = $this->getRequest()->getParam('id');
        $type = $this->getRequest()->getParam('type');

        if ($id) {
            $product = Mage::getModel('catalog/product')->load($id);
            foreach ($product->getMediaGallery('images') as $image) {
                if ($type)
                    $result[] = Mage::helper('catalog/image')->init($product, $type, $image['file']);
                else
                    $result[] = $this->getMediaConfig()->getMediaUrl($image['file']);
            }
        }

        echo join($result, ';');
    }
}
