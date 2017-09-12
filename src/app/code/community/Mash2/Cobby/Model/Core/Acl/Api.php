<?php
class Mash2_Cobby_Model_Core_Acl_Api extends Mage_Api_Model_Resource_Abstract
{
    // admin/catalog/products/edit_product_status
    // admin/catalog/products/read_product_price/edit_product_price

    /**
     * Path to cobby_edit_product_inventory node in ACL
     *
     * Used to check if admin has permission to edit product inventory
     */
    const EDIT_PRODUCT_INVENTORY_ACL_PATH = 'admin/catalog/products/cobby_edit_product_inventory';
    const EDIT_PRODUCT_STATUS_ACL_PATH = 'admin/catalog/products/edit_product_status';
    const EDIT_PRODUCT_PRICE_ACL_PATH = 'admin/catalog/products/read_product_price/edit_product_price';

    private $_supportedPermissions = array(
        self::EDIT_PRODUCT_STATUS_ACL_PATH,
        self::EDIT_PRODUCT_PRICE_ACL_PATH,
        self::EDIT_PRODUCT_INVENTORY_ACL_PATH
    );

    private function getPermissionsBase($permissionLevel)
    {
        $result = array();
        foreach($this->_supportedPermissions as $option)
        {
            $result[$option] = $permissionLevel;
        }

        return $result;
    }

    private function getAllowedStoreIds($role)
    {
        $result      = array();
        $websites    = $role->getGwsWebsites();
        $storeGroups = $role->getGwsStoreGroups();

        //always grant access to default store id
        $result[] = Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID;

        if( !empty($websites) ) {
            $websiteIds = explode(',', $websites);

            foreach (Mage::app()->getWebsites(true) as $website) {
                if(in_array($website->getId(), $websiteIds)) {
                    foreach ($website->getGroups() as $group) {
                        foreach ($group->getStores() as $store) {
                            $result[] = (int)$store->getId();
                        }
                    }
                }
            }

        } elseif( !empty($storeGroups) ) {
            $storeGroupsIds = explode(',', $storeGroups);

            foreach (Mage::app()->getWebsites() as $website) {
                foreach ($website->getGroups() as $group) {
                    if(in_array($group->getId(), $storeGroupsIds)) {
                        foreach ($group->getStores() as $store) {
                            $result[] = (int)$store->getId();
                        }
                    }
                }
            }
        } else {
            foreach(Mage::app()->getStores() as $store) {
                $result[] = (int)$store->getId();
            }
        }

        return $result;
    }

    public function export()
    {
        $result  = array();

        $roles = Mage::getModel('admin/roles')->getCollection();
        foreach($roles as $role)
        {
            $roleItem = array();
            $roleId = $role->getId();

            $rules = Mage::getResourceModel('admin/rules_collection')->getByRoles($roleId);


            $preValue = 'allow';

            $rolePermissions = $this->getPermissionsBase($preValue);

            foreach($rules as $rule)
            {
                $resourceId =  $rule->getResourceId();

                if(in_array($resourceId, $this->_supportedPermissions ))
                {
                    $rolePermissions[$resourceId] = $rule->getPermission();
                }
            }
            $roleItem['roleId'] = (int)$roleId;
            $roleItem['roleName'] = $role->getRoleName();
            $roleItem['permissions'] = $rolePermissions;
            $roleItem['stores'] = $this->getAllowedStoreIds($role);

            $result[] = $roleItem;
        }

        return $result;
    }
}
