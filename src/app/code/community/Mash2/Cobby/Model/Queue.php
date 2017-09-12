<?php

class Mash2_Cobby_Model_Queue extends Mage_Core_Model_Abstract
{
    const CONTEXT_NONE      = 'none';
    const CONTEXT_BACKEND   = 'backend';
    const CONTEXT_FRONTEND  = 'frontend';
    const CONTEXT_API       = 'api';

    protected function _construct()
    {
        $this->_init('mash2_cobby/queue');
    }

    public function reset()
    {
        $this->_getResource()->reset();
        return $this;
    }

    protected function _beforeSave()
    {
        $context = $this->getCurrentContext();
        $this->setUserName($this->getContextUserName($context));
        $this->setContext($context);

        parent::_beforeSave();
    }

    /**
     * Retrieve UserName based on current context
     *
     * @param $context
     * @return string
     */
    private function getContextUserName($context)
    {
        $result = $this->getUserName();
        switch($context) {
            case self::CONTEXT_BACKEND:
                $result = $this->getSessionUserName(Mage::getSingleton('admin/session'));
                break;
            case self::CONTEXT_FRONTEND:
                $result = $this->getSessionUserName(Mage::getSingleton('customer/session'));
                break;
            case self::CONTEXT_API:
                $result = $this->getSessionUserName(Mage::getSingleton('api/session'));
                break;
            case self::CONTEXT_NONE:
                $result = '';
            break;
        }

        return $result;
    }

    /**
     * Retrieve UserName when session exists
     *
     * @param $session
     * @return string
     */
    private function getSessionUserName($session)
    {
        if($session && $session->getUser())
        {
            return $session->getUser()->getUsername();
        }
        return '';
    }

    /**
     * Retrieve current context based on session
     *
     * @return string
     */
    private function getCurrentContext()
    {
        if($this->getContext()) {
            return $this->getContext();
        }elseif(Mage::getSingleton('admin/session')->getUser()) {
            return self::CONTEXT_BACKEND;
        }elseif(Mage::getModel('customer/session')->getUser()) {
            return self::CONTEXT_FRONTEND;
        }elseif(Mage::getSingleton('api/session')->getUser()) {
            return self::CONTEXT_API;
        }

        return self::CONTEXT_NONE;
    }
}
