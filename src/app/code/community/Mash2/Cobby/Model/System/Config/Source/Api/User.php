<?php
class Mash2_Cobby_Model_System_Config_Source_Api_User
{
    protected $_options;

    public function toOptionArray()
    {
        if (!$this->_options) {
            $users =  Mage::getModel('api/user')
                ->getCollection()
                ->load();

            $acl = Mage::getResourceModel('api/acl')->loadAcl();

            $this->_options = array();
            foreach($users as $user)
            {
                if(count($user->getRoles()) == 0)
                    continue;

                $isAllowed = $acl->isAllowed($user->getAclRole(), 'all') || $acl->isAllowed($user->getAclRole(), 'cobby');

                if($isAllowed) {
                    $this->_options[] = array(
                        'label' => $user->getUsername(),
                        'value' => $user->getUsername()
                    );
                }
            }
        }

        return $this->_options;
    }
}
