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

    private function getMediaDir($ioAdapter)
    {
        $dir = Mage::getBaseDir('media');
//        if (!is_dir($dir)) {
//            mkdir($dir);
//        }
        $ioAdapter->checkAndCreateFolder($dir);
        return $dir;
    }

    public function getImageAction()
    {
        $ioAdapter = new Varien_Io_File();
        $baseUrl = Mage::getBaseUrl('media');
        $baseDir = Mage::getBaseDir('media') . DS;
        $fileName = $this->getRequest()->getParam('filename');
        $id = $this->getRequest()->getParam('id');
        $type = 'thumbnail';
        $filePath = '';

        $product = Mage::getModel('catalog/product')->load($id);
        if ($product->getEntityId()) {
            foreach ($product->getMediaGallery('images') as $image) {
                $tokens = explode('/', $image['file']);
                $str = trim(end($tokens));
                if ($str == $fileName){
                    $file = Mage::helper('catalog/image')->init($product, $type, $image['file']);
                    $fileString = (string)$file;
                    $filePath = str_replace($baseUrl, $baseDir, $fileString);

                }
            }
        } else if (file_exists($baseDir . 'import/'. $fileName)) {
            $filePath = $baseDir . 'import/' . $fileName;
        } else {
            $placeholder = Mage::getDesign()->getSkinUrl('images/no_image.jpg');
            $baseUrl = Mage::getBaseUrl('skin');
            $baseDir = Mage::getBaseDir() . DS . 'skin/';
            //not basDir media
            $filePath = str_replace($baseUrl, $baseDir, $placeholder);
            $test = Mage::getSingleton('catalog/product_media_config')->getBaseMediaUrl(). '/placeholder/' .Mage::getStoreConfig("catalog/placeholder/small_image_placeholder");
        }



//        header('Content-Type:image/jpeg');
//        readfile($filePath);
//        return;

//        $type = $this->getRequest()->getParam('type');

//        $filePath = $this->getRequest()->getParam('filename');
//        $prefixPath = '/var/www/html';
//        $file = $prefixPath . $filePath;

        $contentType = 'image/jpeg';

//        $mediaDir = $this->getMediaDir($ioAdapter);
//        if ($ioAdapter->checkAndCreateFolder(Mage::getBaseDir('media'))) {
//            $mediaDir = Mage::getBaseDir('media');
//        } else {
//            echo 'no media folder';
//        }

//        $importDir = $mediaDir . DS . 'import';
//        $catProdDir = $mediaDir . DS . 'catalog/product';

//        if (!is_file($catProdDir . $fileName)) {
//            if (!is_file($importDir . $fileName)) {
//                return "no file";
//            }  else {
//                $file = $importDir . $fileName;
//            }
//
//        } else {
//            $file = $catProdDir . $fileName;
//        }

//        if (!$ioAdapter->fileExists($catProdDir . DS .$fileName)) {
//            if (!$ioAdapter->fileExists($importDir . DS . $fileName)) {
//                return "no file";
//            } else {
//                $file = $importDir . DS . $fileName;
//            }
//        } else {
//            $file = $catProdDir . DS . $fileName;
//        }

//        if ($id) {
//            $product = Mage::getModel('catalog/product')->load($id);
//            if ($type)
//            foreach ($product->getMediaGallery('images') as $image) {
//                $newFile = Mage::helper('catalog/image')->init($product, $type, $image['file']);
//            }
//        }


//        header('Content-Type:'.$type);
//        header('Content-Length: ' . filesize($file));
//        readfile($file);

//        $ioAdapter = new Varien_Io_File();
        $this->getResponse()
            ->setHttpResponseCode(200)
//            ->setHeader('Pragma', 'public', true)
//            ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
            ->setHeader('Content-type', $contentType, true);
//            ->setHeader('Content-Length', filesize($file), true)
//            ->setHeader('Content-Disposition', 'attachment; filename="'.$file.'"', true)
//            ->setHeader('Last-Modified', date('r'), true);

//        if (!is_null($content)) {
//            if ($isFile) {
        $this->getResponse()->clearBody();
        $this->getResponse()->sendHeaders();


        $ioAdapter->open(array('path' => $ioAdapter->dirname($filePath)));
        $ioAdapter->streamOpen($filePath, 'r');
        while ($buffer = $ioAdapter->streamRead()) {
            print $buffer;
        }
        $ioAdapter->streamClose();
//                if (!empty($content['rm'])) {
//                    $ioAdapter->rm($file);
//                }

//                exit(0);
//            } else {
//                $this->getResponse()->setBody($content);
//            }
//        }

//        $response = $this->getResponse();
//        $response->clearAllHeaders();
//        $response->setHeader('Content-Type', $type);
//        $response->setHeader('Content-Length', filesize($file));
//
//        $image = @ImageCreateFromJpeg($file);
////        $response->setBody(imagejpeg($image));
//
//        imagejpeg($file);
////        $response->sendResponse();
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
