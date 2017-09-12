<?php
/*
 * Copyright 2013 mash2 GbR http://www.mash2.com
 *
 * ATTRIBUTION NOTICE
 * Parts of this work are adapted from Daniel Sloof
 * Original title Danslo_ApiImport_Model_Observer
 * The work can be found https://github.com/danslo/ApiImport
 *
 * ORIGINAL COPYRIGHT INFO
 *
 * Copyright 2011 Daniel Sloof
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
*/

/**
 * Cobby indexer api
 */
class Mash2_Cobby_Model_Indexer_Api
    extends Mage_Api_Model_Resource_Abstract
{
    /**
     * Indexes product stock.
     *
     * @param Mage_Index_Model_Event $event
     */
    protected function indexStock(&$event)
    {
        Mage::getResourceSingleton('cataloginventory/indexer_stock')
            ->catalogProductMassAction($event);
    }

    /**
     * Indexes product price.
     *
     * @param Mage_Index_Model_Event $event
     */
    protected function indexPrice(&$event)
    {
        Mage::getResourceSingleton('catalog/product_indexer_price')
            ->catalogProductMassAction($event);
    }

    /**
     * Indexes product category relation.
     *
     * @param Mage_Index_Model_Event $event
     */
    protected function indexCategoryRelation(&$event)
    {
        Mage::getResourceSingleton('catalog/category_indexer_product')
            ->catalogProductMassAction($event);
    }

    /**
     * Indexes product URL rewrites.
     *
     * @param array $productIds
     */
    protected function indexProductRewrites(&$productIds)
    {
        $indexer = Mage::getResourceSingleton('ecomdev_urlrewrite/indexer');
        if ($indexer) { // use EcomDev Indexer
            $indexer->updateProductRewrites($productIds);
        } else {
            $indexer = Mage::getSingleton('catalog/url');
            foreach ($productIds as $productId) {
                $indexer->refreshProductRewrite($productId);
            }
        }
    }

    /**
     * Indexes product search.
     *
     * @param array $productIds
     * @return Mage_CatalogSearch_Model_Resource_Fulltext
     */
    protected function indexSearch(&$productIds)
    {
        return Mage::getResourceSingleton('catalogsearch/fulltext')
            ->rebuildIndex(null, $productIds);
    }

    /**
     * Indexes product EAV attributes.
     *
     * @param Mage_Index_Model_Event $event
     * @return Mage_Catalog_Model_Resource_Product_Indexer_Eav
     */
    protected function indexEav(&$event)
    {
        Mage::getResourceSingleton('catalog/product_indexer_eav')
            ->catalogProductMassAction($event);

        foreach(Mage::app()->getStores() as $store) {
            if(Mage::helper('catalog/product_flat')->isEnabled($store)){
                $data = $event->getNewData();
                if (!empty($data['product_ids'])) {
                    Mage::getSingleton('catalog/product_flat_indexer')
                        ->saveProduct($data['product_ids']);
                }
                break;
            }
        }

        return $this;
    }

    /**
     * Generates an index event based on imported productIds.
     *
     * @param array $productIds
     * @return Mage_Index_Model_Event
     */
    protected function getIndexEvent(&$productIds)
    {
        // Generate a fake mass update event that we pass to our indexers.
        $event = Mage::getModel('index/event');
        $data = array(
            // for product_indexer_price
            'reindex_price_product_ids' => &$productIds,
            // for indexer_stock
            'reindex_stock_product_ids' => &$productIds,
            // for category_indexer_product
            'product_ids'               => &$productIds,
            // for product_indexer_eav
            'reindex_eav_product_ids'   => &$productIds
        );
        $event->setNewData($data);
        return $event;
    }

    /**
     * Retrieve magento edition specific index model
     *
     * @return Mage_Core_Model_Abstract
     */
    private function _getIndexer() {
        if( Mage::helper('core')->isModuleEnabled('Enterprise_ImportExport') ) {
            return Mage::getSingleton('enterprise_index/indexer');
        }
        return Mage::getSingleton('index/indexer');
    }

    /**
     * Update Index Status
     *
     * @param $index
     * @param $status
     * @return bool
     */
    public function changeStatus($index, $status)
    {
        $process = Mage::getSingleton('index/indexer')
            ->getProcessByCode($index)
            ->changeStatus($status);
        return array('result' => true);
    }

    /**
     * Get all indexes
     *
     * @return array
     */
    public function export()
    {
        $result = array();
        $collection = $this->_getIndexer()->getProcessesCollection();
        foreach ($collection as $process) {
            $result[] = array(
                'code' => $process->getIndexerCode(),
                'indexer_type' => $process->getIndexerType(),
                'status' => $process->getStatus(),
                'mode' => $process->getMode()
            );
        }
        return $result;
    }

    private function useAsyncIndex($index)
    {
        $moduleEnabled = Mage::helper('core')->isModuleEnabled('Hackathon_AsyncIndex');
        $asyncActive = Mage::getStoreConfigFlag('system/asyncindex/auto_index');
        if ($moduleEnabled && $asyncActive) {
            $blacklistCfg = Mage::getStoreConfig('system/asyncindex/blacklist_indexes');
            $blacklist = explode(',', $blacklistCfg);
            if(in_array($index, $blacklist)){
                return false;
            }
            return true;
        }
        return false;
    }

    protected function _logIndexEvents($index, $productIds)
    {
        $indexer = Mage::getSingleton('index/indexer');
        $product = Mage::getModel('catalog/product');

        if( $index == 'cataloginventory_stock') {
            Mage::getResourceModel('cataloginventory/indexer_stock')->reindexProducts($productIds);
        } else if ($index == 'catalog_product_price') {
            foreach ($productIds as $productId) {
                $product->setId($productId);
                $indexer->logEvent($product, 'catalog_product', 'catalog_reindex_price');
            }
        } else {
            foreach ($productIds as $productId) {
                $product->setId($productId);
                $indexer->logEvent($product, 'catalog_product', 'save');
            }
        }
    }

    /**
     * reindex products by productIds
     * @param $index
     * @param $productIds
     * @return bool
     */
    public function reindexProducts($index, $productIds)
    {
        $result = false;
        if (!count($productIds)) {
            return $result;
        }

        // use async index
        if($this->useAsyncIndex($index)) {
            $this->_logIndexEvents($index, $productIds);
        } else {
            $result = $this->_runIndexer($index, $productIds);
        }

        return $result;;
    }

    private function _runIndexer($index, $productIds)
    {
        try {
            $event = $this->getIndexEvent($productIds);
            switch ( $index )
            {
                case 'cataloginventory_stock':
                    $this->indexStock($event);
                    break;
                case 'catalog_product_price':
                    $this->indexPrice($event);
                    break;
                case 'catalog_category_product':
                    $this->indexCategoryRelation($event);
                    break;
                case 'catalog_product_flat':
                    $this->indexEav($event);
                    break;
                case 'catalogsearch_fulltext':
                    $this->indexSearch($productIds);
                    break;
                case 'catalog_url':
                    $this->indexProductRewrites($productIds);
                    break;
            }
        } catch (Exception $e) {
            Mage::logException($e);
            return false;
        }

        return true;
    }
}