<?php

class Mash2_Cobby_Model_Resource_Queue extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {
        $this->_init('mash2_cobby/queue', 'queue_id');
    }

    /**
     * @param Mage_Core_Model_Abstract $object
     * @return Mash2_Cobby_Model_Resource_Queue
     */
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        parent::_beforeSave($object);

        /** @var $dateModel Mage_Core_Model_Date */
        $dateModel = Mage::getModel('core/date');

        if ($object->isObjectNew()) {
            $object->setData('created_at', $dateModel->gmtDate());
        }

        //$object->setData('updated_at', $dateModel->gmtDate());

        return $this;
    }

    public function reset()
    {
        $this->_getWriteAdapter()->delete($this->getMainTable());
        return $this;
    }

}
