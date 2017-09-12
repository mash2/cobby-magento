<?php
class Mash2_Cobby_Model_Resource_Catalog_Product_Collection extends Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection
{
    public function isEnabledFlat()
    {
        return false;
    }

    /**
     * Retrieve attributes load select
     *
     * @param string $table
     * @param array|int $attributeIds
     * @return Mage_Eav_Model_Entity_Collection_Abstract
     */
    protected function _getLoadAttributesSelect($table, $attributeIds = array())
    {
        if (empty($attributeIds)) {
            $attributeIds = $this->_selectAttributes;
        }
        $storeId = $this->getStoreId();

        if ($storeId) {

            $adapter        = $this->getConnection();
            $entityIdField  = $this->getEntity()->getEntityIdField();

            $select = $adapter->select()->distinct(true)
                ->from(array('t' => $table), array($entityIdField, 'attribute_id'))
                ->joinLeft(
                    array('t_d' => $table),
                    implode(' AND ', array(
                        't.attribute_id = t_d.attribute_id',
                        't.entity_id = t_d.entity_id',
                        $adapter->quoteInto('t_d.store_id = ?', 0)
                    )),
                    array())
                ->joinLeft(
                    array('t_s' => $table),
                    implode(' AND ', array(
                        't.attribute_id = t_s.attribute_id',
                        't.entity_id = t_s.entity_id',
                        $adapter->quoteInto('t_s.store_id = ?', $storeId)
                    )),
                    array())
                ->where('t.entity_type_id = ?', $this->getEntity()->getTypeId())
                ->where("t.{$entityIdField} IN (?)", array_keys($this->_itemsById))
                ->where('t.attribute_id IN (?)', $attributeIds)
                ->where('t.store_id  IN (?)', array(0, $storeId));

        } else {
            $select = parent::_getLoadAttributesSelect($table)
                ->where('store_id = ?', $this->getDefaultStoreId());
        }

        return $select;
    }
}
