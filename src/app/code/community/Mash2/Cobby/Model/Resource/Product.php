<?php

class Mash2_Cobby_Model_Resource_Product extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {
        $this->_init('mash2_cobby/product', 'entity_id');
    }

    public function resetHash($hash)
    {
        $this->_getWriteAdapter()->update($this->getMainTable(), array('hash' => $hash));
        return $this;
    }

    public function updateHash($ids, $hash)
    {
        $select = $this->_getWriteAdapter()
            ->select()
            ->from(array('cp' => $this->getTable('catalog/product')), array('entity_id', new Zend_Db_Expr('"'. $hash . '" as hash')))
            ->where('cp.entity_id IN (?)', $ids)
            ->insertFromSelect($this->getMainTable(), array('entity_id', 'hash'), Varien_Db_Adapter_Interface::INSERT_ON_DUPLICATE);

        $this->_getWriteAdapter()->query($select);
        return $this;
    }
}
