<?php
class Mash2_Cobby_Model_Import_Product_Grouped extends Mash2_Cobby_Model_Import_Product_Abstract
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * save stock data
     */
    public function import($rows)
    {
        $groupedLinkId = Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED;
        $resource      = Mage::getResourceModel('catalog/product_link');
        $mainTable     = $resource->getMainTable();
        $relationTable = $resource->getTable('catalog/product_relation');
        $attributes    = array();

        // pre-load attributes parameters
        $select = $this->connection->select()
            ->from($resource->getTable('catalog/product_link_attribute'), array(
                'id'   => 'product_link_attribute_id',
                'code' => 'product_link_attribute_code',
                'type' => 'data_type'
            ))->where('link_type_id = ?', $groupedLinkId);
        foreach ($this->connection->fetchAll($select) as $row) {
            $attributes[$row['code']] = array(
                'id' => $row['id'],
                'table' => $resource->getAttributeTypeTable($row['type'])
            );
        }

        $linksData     = array(
            'product_ids'      => array(),
            'links'            => array(),
            'attr_product_ids' => array(),
            'position'         => array(),
            'qty'              => array(),
            'relation'         => array()
        );
        $result = array();

        $productIds = array_keys($rows);
        $existingProductIds = $this->loadExistingProductIds($productIds);
        $changedProductIds = array();

        Mage::dispatchEvent('cobby_import_product_grouped_import_before', array( 'products' => $productIds ));

        foreach($rows as $productId => $rowData)
        {
            if(!in_array($productId, $existingProductIds))
                continue;

            $changedProductIds[] = $productId;
            $associatedIds = $rowData['_associated_ids'];

            $productData = array();

            $linksData['product_ids'][$productId] = true;

            foreach($associatedIds as $associatedId => $associatedData){
                $linksData['links'][$productId][$associatedId] = $groupedLinkId;
                $linksData['relation'][] = array('parent_id' => $productId, 'child_id' => $associatedId);
                $qty = empty($associatedData['qty']) ? 0 : $associatedData['qty'];
                $pos = empty($associatedData['pos']) ? 0 : $associatedData['pos'];
                $productData[$associatedId] = array('qty' => $qty, 'pos' => $pos);
                if ($qty || $pos) {
                    $linksData['attr_product_ids'][$productId] = true;
                    if ($pos) {
                        $linksData['position']["{$productId} {$associatedId}"] = array(
                            'product_link_attribute_id' => $attributes['position']['id'],
                            'value' => $pos
                        );
                    }
                    if ($qty) {
                        $linksData['qty']["{$productId} {$associatedId}"] = array(
                            'product_link_attribute_id' => $attributes['qty']['id'],
                            'value' => $qty
                        );
                    }
                }
            }

            $result[] = array('product_id' => $productId,'_associated_ids' =>  $productData);
        }

        //TODO: löschen oder behalten
        // save links and relations
        if ($linksData['product_ids']) { //&& $this->getBehavior() != Mage_ImportExport_Model_Import::BEHAVIOR_APPEND
            $this->connection->delete(
                $mainTable,
                $this->connection->quoteInto(
                    'product_id IN (?) AND link_type_id = ' . $groupedLinkId,
                    array_keys($linksData['product_ids'])
                )
            );
        }

        if ($linksData['links']) {
            $mainData = array();

            foreach ($linksData['links'] as $productId => $linkedData) {
                foreach ($linkedData as $linkedId => $linkType) {
                    $mainData[] = array(
                        'product_id'        => $productId,
                        'linked_product_id' => $linkedId,
                        'link_type_id'      => $linkType
                    );
                }
            }
            $this->connection->insertOnDuplicate($mainTable, $mainData);
            $this->connection->insertOnDuplicate($relationTable, $linksData['relation']);
        }

        // save positions and default quantity
        if ($linksData['attr_product_ids']) {
            $savedData = $this->connection->fetchPairs($this->connection->select()
                    ->from($mainTable, array(
                        new Zend_Db_Expr('CONCAT_WS(" ", product_id, linked_product_id)'), 'link_id'
                    ))
                    ->where(
                        'product_id IN (?) AND link_type_id = ' . $groupedLinkId,
                        array_keys($linksData['attr_product_ids'])
                    )
            );
            foreach ($savedData as $pseudoKey => $linkId) {
                if (isset($linksData['position'][$pseudoKey])) {
                    $linksData['position'][$pseudoKey]['link_id'] = $linkId;
                }
                if (isset($linksData['qty'][$pseudoKey])) {
                    $linksData['qty'][$pseudoKey]['link_id'] = $linkId;
                }
            }
            if ($linksData['position']) {
                $this->connection->insertOnDuplicate($attributes['position']['table'], $linksData['position']);
            }
            if ($linksData['qty']) {
                $this->connection->insertOnDuplicate($attributes['qty']['table'], $linksData['qty']);
            }
        }

        $this->touchProducts($changedProductIds);

        Mage::dispatchEvent('cobby_import_product_grouped_import_after', array( 'products' => $changedProductIds ));

        return $result;
    }
}
