<?php
class Mash2_Cobby_Model_Stock_Item extends Mage_CatalogInventory_Model_Stock_Item
{
    /**
     * Unset old fields data from the object.
     *
     * $key can be a string only. Array will be ignored.
     *
     * @param string $key
     * @return Varien_Object
     */
    public function unsetOldData($key=null)
    {
        if (is_null($key)) {
            if($this->_oldFieldsMap) {
                foreach ($this->_oldFieldsMap as $key => $newFieldName) {
                    unset($this->_data[$key]);
                }
            }
        } else {
            unset($this->_data[$key]);
        }
        return $this;
    }
}
