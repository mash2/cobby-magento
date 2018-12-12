<?php

class Mash2_Cobby_Helper_Queue extends Mage_Core_Helper_Abstract
{
     /**
     * Mysql TEXT Column is (64 Kilobytes) 65535 chars
     * UTF-8 space consumption is between 1 to 4 bytes per char
     * to be safe and have a reasonable performance we just use 10000
     */
    const MAX_MYSQL_TEXT_SIZE                   = 10000;

    /**
     * @var Mash2_Cobby_Helper_Cobbyapi
     */
    private $cobbyApi;

    /**
     * @var Mash2_Cobby_Helper_Settings
     */
    protected $settings;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->cobbyApi = Mage::helper('mash2_cobby/cobbyapi');
        $this->settings = Mage::helper('mash2_cobby/settings');
    }

    /**
     * save changes to queue in batches
     *
     * @param $entity
     * @param $action
     * @param $ids
     * @param $transactionId
     * @param $context
     * @param $username
     * @return array
     */
    private function enqueue($entity, $action, $ids, $transactionId, $context, $username)
    {
        $result = array();
        if (!isset($transactionId)){
            $transactionId = Mage::helper('core')->getRandomString(30);
        }

        $batches = $this->splitObjectIds($ids);
        foreach ($batches as $batch) {
            $queue = Mage::getModel('mash2_cobby/queue');
            $queue->setObjectIds($batch);
            $queue->setObjectEntity($entity);
            $queue->setObjectAction($action);
            $queue->setTransactionId($transactionId);
            $queue->setContext($context);
            $queue->setUserName($username);
            $queue->save();
            $result[] = $queue->getId();
        }

        return $result;
    }

    /**
     * save changes to queue and notify cobby service
     *
     * @param $entity
     * @param $action
     * @param $ids
     * @param null $transactionId
     * @param null $context
     * @param null $username
     */
    public function enqueueAndNotify($entity, $action, $ids, $transactionId = null, $context = null, $username = null)
    {
        if (!$this->settings->isCobbyEnabled()) {
            return;
        }
        if (!Mage::isInstalled() || Mage::registry('is_cobby_import') == 1) { //do nothing if is cobby import
            return;
        }

        $manageStock = $this->settings->getManageStock();

        /**
         * enqueue only if stockManagement is not disabled or
         * entity is other than 'stock'
         */
        if ($manageStock == Mash2_Cobby_Helper_Settings::MANAGE_STOCK_ENABLED ||
            $manageStock == Mash2_Cobby_Helper_Settings::MANAGE_STOCK_READONLY ||
            $entity != 'stock') {
            try {
                $queueIds = $this->enqueue($entity, $action, $ids, $transactionId, $context, $username);
                //notify only with with the id from the first batch
                $this->cobbyApi->notifyCobbyService($entity, $action, $queueIds[0]);

            } catch (Exception $e) {

            }
        }
    }

    /**
     * split string by MAX_MYSQL_TEXT column
     *
     * @param $ids
     * @return array
     */
    private function splitObjectIds($ids)
    {
        $objectIdsAsString = $ids;
        if (is_array($ids)) {
            $objectIdsAsString = implode('|', $ids);
        }

        $result = array();

        if(strlen($objectIdsAsString) < self::MAX_MYSQL_TEXT_SIZE ){
            $result[] = $objectIdsAsString;
        }else {
            while (true) {
                $objectIdsPart = substr($objectIdsAsString, 0, self::MAX_MYSQL_TEXT_SIZE);
                $lastPos = strrpos($objectIdsPart, "|");

                if ($lastPos > 0) {
                    $result[] = ltrim(substr($objectIdsPart, 0, $lastPos), "|");
                    $objectIdsAsString = substr($objectIdsAsString, $lastPos + 1);
                } else {
                    $result[] = $objectIdsPart;
                }

                if ($lastPos === false) {
                    break;
                }
            }
        }
        return $result;
    }
}
