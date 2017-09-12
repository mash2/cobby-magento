<?php
/**
 * cobby default helper
 */
class Mash2_Cobby_Helper_Data extends Mage_Core_Helper_Abstract
{

    /**
     * cobby role name
     */
    const COBBY_ROLE_NAME = 'cobby';

    public function getCobbyRoles()
    {
        $role = Mage::getModel('api/role')
            ->getCollection()
            ->addFieldToFilter('role_name', self::COBBY_ROLE_NAME)
            ->addFieldToFilter('role_type', Mage_Api_Model_Acl::ROLE_TYPE_GROUP)
            ->load()
            ->getFirstItem();

        return $role;
    }

    public function createCobbyRole()
    {
        $newAcl = Mage::getModel('api/rules');
        $newAcl->setResourceId('cobby')
            ->setRoleType(Mage_Api_Model_Acl::ROLE_TYPE_GROUP)
            ->setPermission('allow') // 1.5
			->setApiPermission('allow'); // > 1.5

        $newRole = Mage::getModel('api/role');
        $newRole->setRoleName(self::COBBY_ROLE_NAME)
            ->setRoleType(Mage_Api_Model_Acl::ROLE_TYPE_GROUP)
            ->save();

        $newAcl
            ->setRoleId($newRole->getId())
            ->saveRel()
            ->save();

        return $newRole;
    }

    public function createCobbyApiUser($userName, $email, $apiKey)
    {
        $newUser = Mage::getModel('api/user');

        $newUser
            ->setUsername($userName)
            ->setFirstname($userName)
            ->setLastname($userName)
            ->setEmail($email)
            ->setApiKey($apiKey)
            ->setIsActive(1);

        $newUser->save();

        return $newUser;
    }
}
