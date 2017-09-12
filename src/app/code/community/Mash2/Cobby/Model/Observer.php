<?php
/**
 * cobby settings observers
 */
class Mash2_Cobby_Model_Observer extends Mage_Core_Model_Abstract
{
    const SUCCESS_MESSAGE = 'Registration was successful. Excel is now linked to your store. The service is now being set up for the first use. This process can take some time. Once done, you will receive an email with further information.';
    const CHARS_DIGITS                          = '0123456789';

    const SAVE = Mage_Index_Model_Event::TYPE_SAVE;
    const DELETE = Mage_Index_Model_Event::TYPE_DELETE;

    /**
     * @var Mash2_Cobby_Helper_Data
     */
    protected $helper;

    /**
     * @var Mash2_Cobby_Helper_Queue
     */
    protected $queueHelper;

    /**
     * @var Mash2_Cobby_Helper_Cobbyapi
     */
    private $cobbyApi;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->helper = Mage::helper('mash2_cobby');
        $this->cobbyApi = Mage::helper('mash2_cobby/cobbyapi');
        $this->queueHelper = Mage::helper('mash2_cobby/queue');
    }

    /**
     * Notify cobby service about account registration
     *
     * @param $observer
     * @return $this
     */
    public function saveConfig($observer)
    {
        $chooseUser = (int)Mage::getStoreConfig('cobby/settings/choose_user');
        $apiUserModel = Mage::getModel('api/user');

        switch($chooseUser){
            case 1: // use existing user
                $apiUserName = Mage::getStoreConfig('cobby/settings/api_user');
                $apiKey = Mage::helper('core')->decrypt(Mage::getStoreConfig('cobby/settings/api_key'));
                break;
            case 2: // create new user
                $role = $this->helper->getCobbyRoles();

                if( !$role->getId() )
                {
                    $role = $this->helper->createCobbyRole();
                }
                $apiUserName = Mage::getStoreConfig('cobby/settings/new_api_user');
                $encryptedApiKey = Mage::getStoreConfig('cobby/settings/new_api_key');
                $apiKey = Mage::helper('core')->decrypt($encryptedApiKey);
                $storeEmail = Mage::getStoreConfig('trans_email/ident_general/email', Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);
                $emailSuffix = substr($storeEmail, strpos($storeEmail, "@"));
                $apiEmail = 'cobby.' . Mage::helper('core')->getRandomString(5, self::CHARS_DIGITS) . $emailSuffix;

                $user = $apiUserModel->loadByUsername($apiUserName);
                if( !$user->getId() )
                {
                    $user = $this->helper->createCobbyApiUser($apiUserName, $apiEmail, $apiKey);
                }
                $user->setRoleIds(array($role->getId()))
                    ->setRoleUserId($user->getUserId())
                    ->saveRelations();

                // new user added, set to existing user with same data
                $config = Mage::getModel('core/config')
                    ->saveConfig('cobby/settings/choose_user', '1' )
                    ->saveConfig('cobby/settings/api_user', $user->getUsername() )
                    ->saveConfig('cobby/settings/api_key', $encryptedApiKey );
                break;

            default:
                return $this;
        }

        $result = $this->cobbyApi->registerShop($apiUserName, $apiKey);

//        Mage::getModel('core/config')->saveConfig('cobby/settings/license_key', $licenseKey);

        Mage::getSingleton('index/indexer')
            ->getProcessByCode('cobby_sync')
            ->changeStatus(Mage_Index_Model_Process::STATUS_RUNNING);

        Mage::getSingleton('core/session')->addSuccess(Mage::helper('mash2_cobby')->__(self::SUCCESS_MESSAGE));

        //TODO: cache leeren?
        // clean cache
//        Mage::app()->getCacheInstance()->cleanType('config');

        return $this;
    }

    /**
     * run index for category deleted event
     * because the category delete event is not processed in indexer
     *
     * @param $observer
     * @return $this
     */
    public function catalogCategoryDeleteAfter($observer)
    {
        $event = $observer->getEvent();
        $category = $event->getCategory();

        $this->queueHelper
            ->enqueueAndNotify('category', self::DELETE, $category->getId()); //constant has different value
    }

    public function catalogCategorySaveAfter($observer)
    {
        $event = $observer->getEvent();
        $category = $event->getCategory();

        $this->queueHelper
            ->enqueueAndNotify('category', self::SAVE, $category->getId()); //constant has different value
    }

    public function catalogProductSaveAfter($observer)
    {
        $event = $observer->getEvent();
        $product = $event->getProduct();

        Mage::getModel('mash2_cobby/product')->updateHash($product->getId());
        $this->queueHelper
            ->enqueueAndNotify('product', self::SAVE, $product->getId()); //constant has different value
    }

    public function catalogProductDeleteAfter($observer)
    {
        $event = $observer->getEvent();
        $product = $event->getProduct();

        Mage::getModel('mash2_cobby/product')->updateHash($product->getId());
        $this->queueHelper
            ->enqueueAndNotify('product', self::DELETE, $product->getId()); //constant has different value
    }

    public function catalogProductAttributeUpdateBefore($observer)
    {
        $productIds = $observer->getData('product_ids');

        Mage::getModel('mash2_cobby/product')->updateHash($productIds);
        $this->queueHelper
            ->enqueueAndNotify('product', self::SAVE, $productIds); //constant has different value
    }

    public function catalogEntityAttributeSaveAfter($observer)
    {
        $event = $observer->getEvent();
        $attribute = $event->getAttribute();
        $this->queueHelper
            ->enqueueAndNotify('attribute', self::SAVE, $attribute->getId()); //constant has different value
    }

    private function _triggerObjectChanged($observer, $entity)
    {
        $event = $observer->getEvent();
        $object = $event->getObject();
        $this->queueHelper
            ->enqueueAndNotify($entity, self::SAVE, $object->getId());
    }

    private function _triggerSetReindexCobbyRequired()
    {
        Mage::getModel('mash2_cobby/product')->resetHash('store_changed');

        Mage::getSingleton('index/indexer')
            ->getProcessByCode('cobby_sync')
            ->changeStatus(Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX);

        return $this;
    }

    public function triggerCustomerGroupChanged($observer)
    {
        $this->_triggerObjectChanged($observer, Mage_Customer_Model_Group::ENTITY);
    }

    public function triggerRoleChanged($observer)
    {
        $this->_triggerObjectChanged($observer, 'role');
    }

    public function triggerUserChanged($observer)
    {
        $this->_triggerObjectChanged($observer, 'user');
    }

    public function triggerAfterProductImport($observer)
    {
        $event = $observer->getEvent();
        $entityIds = $event->getEntityId();

        if(count($entityIds)) {
            Mage::getModel('mash2_cobby/product')->updateHash($entityIds);
            $this->queueHelper->enqueueAndNotify('product', 'save', $entityIds);
        }
    }

    public function triggerCatalogProductByStock($observer)
    {
        $event = $observer->getEvent();
        // Reindex quote ids
        $quote = $event->getQuote();
        $productIds = array();
        foreach ($quote->getAllItems() as $item) {
            $productIds[] = $item->getProductId();
            $children   = $item->getChildrenItems();
            if ($children) {
                foreach ($children as $childItem) {
                    $productIds[] = $childItem->getProductId();
                }
            }
        }

        if( count($productIds)) {
            Mage::getModel('mash2_cobby/product')->updateHash($productIds);
            $this->queueHelper->enqueueAndNotify('stock', 'save', $productIds);
        }

        return $this;

    }

    public function eavEntityAttributeSetSaveAfter($observer)
    {
        $event = $observer->getEvent();
        $object = $event->getObject();
        $this->queueHelper
            ->enqueueAndNotify('attributeset', self::SAVE, $object->getId()); //constant has different value
    }

    public function eavEntityAttributeSetDeleteAfter($observer)
    {
        $event = $observer->getEvent();
        $object = $event->getObject();
        $this->queueHelper->enqueueAndNotify('attributeset', self::DELETE, $object->getId()); //constant has different value
    }

    public function cataloginventoryStockItemSaveAfter($observer)
    {
        $event = $observer->getEvent();
        $item = $event->getItem();
        $this->queueHelper
            ->enqueueAndNotify('stock', self::SAVE, $item->getProductId()); //constant has different value
    }

    public function coreConfigDataSaveCommitAfter($observer)
    {
        $relatedConfigSettings = array(
            Mage_Catalog_Helper_Data::XML_PATH_PRICE_SCOPE,
            Mage_CatalogInventory_Model_Stock_Item::XML_PATH_MANAGE_STOCK
        );

        $event = $observer->getEvent();
        $data = $event->getDataObject();

        if ($data && in_array($data->getPath(), $relatedConfigSettings) && $data->isValueChanged()){
            $this->_triggerSetReindexCobbyRequired();
        }
    }

    public function storeSaveAfter($observer)
    {
        $this->_triggerSetReindexCobbyRequired();
    }

    public function storeDeleteAfter($observer)
    {
        $this->_triggerSetReindexCobbyRequired();
    }

    public function storeGroupSaveAfter($observer)
    {
        $this->_triggerSetReindexCobbyRequired();
    }

    public function storeGroupDeleteAfter($observer)
    {
        $this->_triggerSetReindexCobbyRequired();
    }

    public function websiteSaveAfter($observer)
    {
        Mage::getModel('mash2_cobby/product')->resetHash('website_changed');
    }

    public function websiteDeleteAfter($observer)
    {
        Mage::getModel('mash2_cobby/product')->resetHash('website_changed');
    }

    /**
     * set cobby sync status to running
     * @param $observer
     * @return $this
     */
    public function updateCobbySyncStatus($observer)
    {
        Mage::getSingleton('index/indexer')
            ->getProcessByCode('cobby_sync')
            ->changeStatus(Mage_Index_Model_Process::STATUS_RUNNING);

        return $this;
    }

    public function cobbyHandleChanges($observer)
    {
        $entity = $observer->getEntity();
        if ($entity == 'product') {
            Mage::getModel('mash2_cobby/product')->updateHash($observer->getIds());
        }

        $this->queueHelper->enqueueAndNotify($entity, $observer->getAction(), $observer->getIds());
    }
}
