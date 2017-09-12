<?php

/** @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

$installer->run("
CREATE TABLE IF NOT EXISTS `{$installer->getTable('mash2_cobby/queue')}` (
  `queue_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `object_ids` text NOT NULL,
  `object_entity` varchar(255) NOT NULL,
  `object_action` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`queue_id`))
  ENGINE=InnoDB DEFAULT CHARSET=utf8 ;
");

$installer->endSetup();
