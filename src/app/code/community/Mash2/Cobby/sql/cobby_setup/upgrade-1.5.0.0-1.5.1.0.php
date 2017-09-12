<?php
$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer->startSetup();
$configDataTable = $installer->getTable('core/config_data');
$conn = $installer->getConnection();

$select = $conn->select()
    ->from($configDataTable)
    ->where('path IN (?)',
        array(
            'cobby/settings/api_key',
            'cobby/htaccess/password'
        )
    );

$settings = $conn->fetchAll($select);
foreach ($settings as $setting) {
    if(!empty($setting['value'])){
        $encrypted =  Mage::helper('core')->encrypt($setting['value']);
        $installer->setConfigData('cobby/settings/api_key', $encrypted);
    }
}
$installer->endSetup();