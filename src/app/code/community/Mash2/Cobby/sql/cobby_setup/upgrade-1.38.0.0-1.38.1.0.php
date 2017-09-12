<?php
$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer->startSetup();

$table = $installer->getConnection()
    ->newTable($installer->getTable('mash2_cobby/product'))
    ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
    ), 'Entity ID')
    ->addColumn('hash', Varien_Db_Ddl_Table::TYPE_TEXT, 100, array(
    ), 'Hash')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
    ), 'Creation Time')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
    ), 'Update Time')
    ->addForeignKey(
        $installer->getFkName(
            'mash2_cobby/product',
            'entity_id',
            'catalog/product',
            'entity_id'
        ),
        'entity_id', $installer->getTable('catalog/product'), 'entity_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE)
    ->setComment('Cobby Product Table');

$installer->getConnection()->createTable($table);

$installer->run("
    INSERT INTO `{$installer->getTable('mash2_cobby/product')}`
    (`entity_id`, `hash`)
        SELECT `entity_id`, 'init'
            FROM `{$installer->getTable('catalog/product')}`;
    ");

$installer->endSetup();