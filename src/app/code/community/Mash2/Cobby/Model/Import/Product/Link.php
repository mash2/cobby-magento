<?php
class Mash2_Cobby_Model_Import_Product_Link extends Mash2_Cobby_Model_Import_Product_Abstract
{

    /**
     * @var string link table name
     */
    private $linkTable;


    /**
     * @var Mash2_Cobby_Helper_Resource
     */
    private $resourceHelper;

    /**
     * Links attribute name-to-link type ID.
     *
     * @var array
     */
    protected $linkNameToId = array(
        'upsell'    => Mage_Catalog_Model_Product_Link::LINK_TYPE_UPSELL,
        'crosssell' => Mage_Catalog_Model_Product_Link::LINK_TYPE_CROSSSELL,
        'related'   => Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED
    );

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->linkTable = $this->resourceModel->getTableName('catalog_product_link');
        $this->resourceHelper = Mage::helper('mash2_cobby/resource');
    }

    public function import($rows)
    {
        $result = array();

        $positionAttrId = array();
        $nextLinkId     = $this->resourceHelper->getNextAutoincrement($this->linkTable);

        // pre-load 'position' attributes ID for each link type once
        foreach ($this->linkNameToId as $linkName => $linkTypeId) {
            $select = $this->connection->select()
                ->from(
                    $this->resourceModel->getTableName('catalog/product_link_attribute'),
                    array('id' => 'product_link_attribute_id')
                )
                ->where('link_type_id = :link_id AND product_link_attribute_code = :position');
            $bind = array(
                ':link_id' => $linkTypeId,
                ':position' => 'position'
            );
            $positionAttrId[$linkTypeId] = $this->connection->fetchOne($select, $bind);
        }

        $linkRows              = array();
        $positionRows          = array();
        $productIds            = array_keys($rows);

        foreach ($rows as $row) {
            foreach ($this->linkNameToId as $linkName => $linkTypeId) {
                if (!isset($row[$linkName])) {
                    continue;
                }

                $links = $row[$linkName];
                foreach ($links as $linkedId) {
                    $productIds[] = $linkedId;
                }
            }
        }

        $productIds = array_unique($productIds);
        $existingProductIds = $this->loadExistingProductIds($productIds);

        Mage::dispatchEvent('cobby_import_product_link_import_before', array( 'products' => $productIds ));

        $touchedProductIds = array();
        foreach ($rows as $productId => $row) {
            if (!in_array($productId, $existingProductIds)) {
                continue;
            }

            $touchedProductIds[] = $productId;
            $deleteLinkTypeIds = array();
            $productLinks = array('product_id' => $productId);

            foreach ($this->linkNameToId as $linkName => $linkTypeId) {
                if (!isset($row[$linkName])) {
                    continue;
                }

                $links = $row[$linkName];
                $deleteLinkTypeIds[$linkTypeId] = true;
                $productLinks[$linkName] = array();
                foreach ($links as $pos => $linkedId) {
                    if (!in_array($linkedId, $existingProductIds)) {
                        continue;
                    }

                    if ($productId == $linkedId) {
                        continue;
                    }

                    $linkRows[] = array(
                        'link_id'           => $nextLinkId,
                        'product_id'        => $productId,
                        'linked_product_id' => $linkedId,
                        'link_type_id'      => $linkTypeId
                    );

                    $positionRows[] = array(
                        'link_id'                   => $nextLinkId,
                        'product_link_attribute_id' => $positionAttrId[$linkTypeId],
                        'value'                     => $pos
                    );
                    $nextLinkId++;
                    $productLinks[$linkName][] = $linkedId;
                }
            }

            $result[] = $productLinks;

            if (count($deleteLinkTypeIds) > 0) {
                $this->connection->delete($this->linkTable, array(
                    $this->connection->quoteInto('product_id = ?', $productId),
                    $this->connection->quoteInto('link_type_id IN (?)', array_keys($deleteLinkTypeIds))));
            }
        }

        if($linkRows) {
            $this->connection->insertOnDuplicate($this->linkTable, $linkRows, array('link_id'));
            $this->connection->insertOnDuplicate(
                $this->resourceModel->getTableName('catalog/product_link_attribute_int'),
                $positionRows,
                array('value')
            );
        }

        $this->touchProducts($touchedProductIds);

        Mage::dispatchEvent('cobby_import_product_link_import_after', array( 'products' => $productIds ));

        return $result;
    }
}