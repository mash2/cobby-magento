<?php
/**
 * Cobby sync model
 *
 */
class Mash2_Cobby_Model_Indexer_Sync extends Mage_Index_Model_Indexer_Abstract
{

    /**
     * Initialize resource model
     *
     */
    protected function _construct()
    {
        $this->_init('mash2_cobby/indexer_sync');
    }

    /**
     * Retrieve Indexer name
     *
     * @return string
     */
    public function getName()
    {
        return Mage::helper('mash2_cobby')->__('cobby Index');
    }

    /**
     * Retrieve Indexer description
     *
     * @return string
     */
    public function getDescription()
    {
        return Mage::helper('mash2_cobby')->__('cobby Sync Index');
    }

    /**
     * Register data required by process in event object
     *
     * @param Mage_Index_Model_Event $event
     * @return $this
     */
    protected function _registerEvent(Mage_Index_Model_Event $event){}

    /**
     * Process event
     *
     * @param Mage_Index_Model_Event $event
     */
    protected function _processEvent(Mage_Index_Model_Event $event){}
}