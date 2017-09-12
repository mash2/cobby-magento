<?php
/*
 * Copyright 2013 mash2 GbR http://www.mash2.com
 *
 * ATTRIBUTION NOTICE
 * Parts of this work are adapted from Andreas von Studnitz
 * Original title AvS_FastSimpleImport
 * The work can be found https://github.com/avstudnitz/AvS_FastSimpleImport
 *
 * ORIGINAL COPYRIGHT INFO
 *
 * category   AvS
 * package    AvS_FastSimpleImport
 * author     Andreas von Studnitz <avs@avs-webentwicklung.de>
 */


/**
 * Cobby export entity product type simple configurable
 *
 */
class Mash2_Cobby_Model_Import_Entity_Product_Type_Configurable
    extends Mage_ImportExport_Model_Import_Entity_Product_Type_Configurable
{

    /**
     * Prepare attributes values for save:
     *
     * @param array $rowData
     * @return array
     */
    public function prepareAttributesForSave(array $rowData, $withDefaultValue = true)
    {
        $result = array();

        foreach ($this->_getProductAttributes($rowData) as $attrCode => $attrParams) {
            if (!$attrParams['is_static']) {
                if (isset($rowData[$attrCode]) && strlen($rowData[$attrCode])) {
                    $result[$attrCode] = $rowData[$attrCode];
                } elseif (array_key_exists($attrCode, $rowData)) {
                    if ( !$this->isSkuNew($rowData) || $rowData[$attrCode] != "" || $attrCode == 'url_key' )
                    {
                        $result[$attrCode] = $rowData[$attrCode];
                    }
                } elseif (null !== $attrParams['default_value'] && isset($rowData['sku'])) {
                    if ($this->isSkuNew($rowData)) {
                        $result[$attrCode] = $attrParams['default_value'];
                    }
                }
            }
        }
        return $result;
    }

    /**
     * check if sku isset and exists
     *
     * @param array $rowData
     * @return bool
     */
    protected function isSkuNew(array $rowData)
    {
        if (isset($rowData['sku']) && $rowData['sku'] != '') {
            $sku =  $rowData['sku'];
            $oldSkus = $this->_entityModel->getOldSku();
            return !isset($oldSkus[$sku]);
        };
        return false;
    }
}
