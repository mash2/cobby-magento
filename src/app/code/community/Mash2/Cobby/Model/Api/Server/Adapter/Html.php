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
 * Json Xml Rpc webservice controller
 */
class Mash2_Cobby_Model_Api_Server_Adapter_Html extends Varien_Object implements Mage_Api_Model_Server_Adapter_Interface
{
    /**
     * REST Server
     *
     * @var Zend_Json_Server
     */
    protected $_json = null;

    /**
     * Set handler class name for webservice
     *
     * @param string $handler
     * @return $this
     */
    public function setHandler($handler)
    {
        $this->setData('handler', $handler);
        return $this;
    }

    /**
     * Retrieve handler class name for webservice
     *
     * @return mixed
     */
    public function getHandler()
    {
        return $this->getData('handler');
    }

    /**
     *
     * Set webservice api controller
     *
     * @param Mage_Api_Controller_Action $controller
     * @return $this
     */
    public function setController(Mage_Api_Controller_Action $controller)
    {
        $this->setData('controller', $controller);
        return $this;
    }

    /**
     *
     * Retrieve webservice api controller
     *
     * @return mixed
     */
    public function getController()
    {
        return $this->getData('controller');
    }

    /**
     * Run webservice
     *
     * @return $this
     */
    public function run()
    {
        $apiConfigCharset = Mage::getStoreConfig("api/config/charset");

        $this->_json = new Zend_Json_Server();
        $this->_json->setAutoEmitResponse(false);
        $this->_json->setClass($this->getHandler());

        $this->getController()->getResponse()
            ->clearHeaders()
            ->setHeader('Content-Type', 'text/html; charset=' . $apiConfigCharset)
            ->setBody($this->_json->handle());

        return $this;
    }

    /**
     * Dispatch webservice fault
     *
     * @param int $code
     * @param string $message
     * @throws Zend_Json_Server_Exception
     */
    public function fault($code, $message)
    {
        throw new Zend_Json_Server_Exception($message, $code);
    }
}