<?php

/**
 * cobby resource helper
 */
class Mash2_Cobby_Helper_Resource extends Mage_Core_Helper_Abstract
{
    /**
     * Find next value of autoincrement key for specified table.
     *
     * @param string $tableName
     * @throws Exception
     * @return string
     */
    public function getNextAutoincrement($tableName)
    {
        $connection  = Mage::getSingleton('core/resource')->getConnection('write');
        $entityStatus = $connection->showTableStatus($tableName);

        if (empty($entityStatus['Auto_increment'])) {
            Mage::throwException(Mage::helper('importexport')->__('Can not get autoincrement value'));
        }
        return $entityStatus['Auto_increment'];
    }

}
