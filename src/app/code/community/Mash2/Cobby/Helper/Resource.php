<?php
/*
 * @copyright Copyright (c) 2021 mash2 GmbH & Co. KG. All rights reserved.
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0).
 */

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
    public function getNextAutoincrement($tableName, $primaryKey)
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('read');
        $select = $connection->select();
        $select->from($tableName, 'MAX('.$primaryKey.')');
        $result = (int)$connection->fetchOne($select) +1;
        return $result;
    }
}
