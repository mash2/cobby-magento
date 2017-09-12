<?php
/**
 * Created by PhpStorm.
 * User: Dima
 * Date: 03.07.14
 * Time: 15:30
 */

/**
 * Cobby import entity product type bundle
 *
 */
class Mash2_Cobby_Model_Import_Entity_Product_Type_Bundle
    extends Mage_ImportExport_Model_Import_Entity_Product_Type_Abstract
{

    /**
     * Attributes' codes which will be allowed anyway, independently from its visibility property.
     *
     * @var array
     */
    protected $_forcedAttributesCodes = array(
        'weight_type',
        'price_type',
        'sku_type',
        'shipment_type'
    );

    /*
    'related_tgtr_position_behavior', 'related_tgtr_position_limit',
    'upsell_tgtr_position_behavior', 'upsell_tgtr_position_limit'
    */

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
     * Validate row attributes. Pass VALID row data ONLY as argument.
     *
     * @param array $rowData
     * @param int $rowNum
     * @param boolean $isNewProduct OPTIONAL.
     * @return boolean
     */
    public function isRowValid(array $rowData, $rowNum, $isNewProduct = true)
    {
        $error    = false;
        $rowScope = $this->_entityModel->getRowScope($rowData);

        if (Mage_ImportExport_Model_Import_Entity_Product::SCOPE_NULL != $rowScope) {
            foreach ($this->_getProductAttributes($rowData) as $attrCode => $attrParams) {
                // check value for non-empty in the case of required attribute?
                if (isset($rowData[$attrCode]) && strlen($rowData[$attrCode])) {
                    $error |= !$this->_entityModel->isAttributeValid($attrCode, $attrParams, $rowData, $rowNum);
                } elseif (
                    $this->_isAttributeRequiredCheckNeeded($attrCode)
                    && $attrParams['is_required']) {
                    // For the default scope - if this is a new product or
                    // for an old product, if the imported doc has the column present for the attrCode
                    if (Mage_ImportExport_Model_Import_Entity_Product::SCOPE_DEFAULT == $rowScope &&
                        ($isNewProduct || array_key_exists($attrCode, $rowData))) {

                        if($attrCode  == 'price' && isset($rowData['price_type']) && $rowData['price_type'] =='0' )
                        {
                            continue;
                        }

                        if($attrCode  == 'weight' && isset($rowData['weight_type']) && $rowData['weight_type'] =='0' )
                        {
                            continue;
                        }

                        $this->_entityModel->addRowError(
                            Mage_ImportExport_Model_Import_Entity_Product::ERROR_VALUE_IS_REQUIRED,
                            $rowNum, $attrCode
                        );
                        $error = true;
                    }
                }
            }
        }
        $error |= !$this->_isParticularAttributesValid($rowData, $rowNum);

        return !$error;
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
