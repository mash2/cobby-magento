<?php
/**
 * Cobby import product api
 */
class Mash2_Cobby_Model_Import_Product_Api extends Mage_Api_Model_Resource_Abstract
{
    const START = 'start';
    const FINISH = 'finish';

    /**
     * DB connection.
     *
     * @var Varien_Adapter_Interface
     */
    protected $_connection;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->_connection      = Mage::getSingleton('core/resource')->getConnection('write');
    }

    /**
     * update product category associations
     *
     * @param $data
     * @return array
     */
    public function updateCategoryAssociations($data)
    {
        $model = Mage::getModel('mash2_cobby/import_product_category');
        $result = $model->import($data);

        return $result;
    }

    public function importProducts($data, $typeModels, $usedSkus)
    {
        if (!is_null($typeModels) && !is_array($typeModels)) {
            $typeModels = array($typeModels);
        }

        $transportObject = new Varien_Object();
        $transportObject->setRows($data);
        $transportObject->setTypeModels($typeModels);
        $transportObject->setUsedSkus($usedSkus);

        Mage::dispatchEvent('cobby_import_product_import_before', array('transport' => $transportObject));

        $importModel = Mage::getModel('mash2_cobby/import_entity_product',
            array('typeModels' => $transportObject->getTypeModels(),
                'usedSkus' => $transportObject->getUsedSkus()))
            ->setBehavior(Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE);

        $arrayAdapter = Mage::getModel('mash2_cobby/arrayAdapter', $transportObject->getRows());
        $validationResult = $importModel
            ->setArraySource($arrayAdapter)
            ->isDataValid();

        if ($importModel->getProcessedRowsCount() > 0) {
            if (!$validationResult) {
                Mage::throwException($this->_getErrorMessage($importModel));
            }

            $importModel->importData();

            Mage::dispatchEvent('cobby_import_product_import_after', array('transport' => $transportObject));
            return $importModel->getProcessedProducts();
        }

        return array();
    }

    private function _getErrorMessage($importModel)
    {
        $message =  'Input Data contains ' . $importModel->getInvalidRowsCount();
        $message .=  ' corrupt records (from a total of ' . $importModel->getProcessedRowsCount(). ')';

        foreach ($importModel->getErrorMessages() as $type => $lines) {
            $message .= "\n:::: " . $type . " ::::\nIn Line(s) " . implode(", ", $lines) . "\n";
        }
        return $message;
    }

    public function updateGroupedProductAssociations($data)
    {
        $model = Mage::getModel('mash2_cobby/import_product_grouped');
        $result = $model->import($data);
        return $result;
    }

    public function updateConfigurableProducts($data)
    {

        $model = Mage::getModel('mash2_cobby/import_product_configurable');
        $result = $model->import($data);

        return $result;
    }

    public function updateMedia($data)
    {
        $model = Mage::getModel('mash2_cobby/import_product_media');
        $result = $model->import($data);

        return $result;
	}
	
    /**
     * save stock
     */
    public function updateStock($data)
    {
        $model = Mage::getModel('mash2_cobby/import_product_stock');
        $result = $model->import($data);

        return $result;
    }

    public function updateLink($data)
    {
        $model = Mage::getModel('mash2_cobby/import_product_link');
        $result = $model->import($data);

        return $result;
    }

    public function deleteDuplicateImages($data)
    {
        $model = Mage::getModel('mash2_cobby/import_product_media');
        $result = $model->deleteDuplicateImages($data);

        return $result;
    }

    public function updateTierPrices($data)
    {
        $model = Mage::getModel('mash2_cobby/import_product_tierprice');
        $result = $model->import($data);

        return $result;
    }

    public function updateGroupPrices($data)
    {
        $model = Mage::getModel('mash2_cobby/import_product_groupprice');
        $result = $model->import($data);

        return $result;
	}   
        
    public function updateUrl($data)
    {
        $model = Mage::getModel('mash2_cobby/import_product_url');
        $result = $model->import($data);

        return $result;
    }

    public function updateCustomOptions($data)
    {
        $model = Mage::getModel('mash2_cobby/import_product_customoption');
        $result = $model->import($data);

        return $result;
    }

    public function updateBundleOptions($data)
    {
        $model = Mage::getModel('mash2_cobby/import_product_bundleoption');
        $result = $model->import($data);

        return $result;
    }

    public function start()
    {
        Mage::dispatchEvent('cobby_import_product_started');

        return true;
    }

    public function finish($entities)
    {
        Mage::dispatchEvent('cobby_import_product_finished', array('entities' => $entities));

        return true;
    }
}