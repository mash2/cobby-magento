<?php
/**
 * Cobby sync resource model
 *
 */
class Mash2_Cobby_Model_Resource_Indexer_Sync
{
    /**
     * @var Mash2_Cobby_Helper_Cobbyapi
     */
    private $cobbyApi;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->cobbyApi = Mage::helper('mash2_cobby/cobbyapi');
    }

    /**
     * Handler for "Reindex" action in the admin panel or console
     */
    public function reindexAll()
    {
        $this->cobbyApi->notifyCobbyService('indexer', 'resync', '');
    }
}