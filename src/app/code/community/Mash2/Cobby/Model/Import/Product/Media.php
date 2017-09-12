<?php

class Mash2_Cobby_Model_Import_Product_Media extends Mash2_Cobby_Model_Import_Product_Abstract
{
    protected $_fileUploader;

    const ERROR_FILE_NOT_FOUND      = 1;

    private $_uploadMediaFiles = array();

    /**
     * Product entity type id.
     *
     * @var int
     */
    protected $_entityTypeId;

    /**
     *  Import product resource
     *
     * @var Mage_ImportExport_Model_Import_Proxy_Product_Resource
     */
    protected $_resource;

    /**
     * Htaccess User from cobby settings
     *
     * @var mixed
     */
    protected $_htUser;

    /**
     * Htaccess Password from cobby settings
     * @var string
     */
    protected $_htPassword;

    /**
     * Shop media Url
     *
     * @var string
     */
    protected $_mediaUrl;

    /**
     * @var Mash2_Cobby_Helper_Settings
     */
    private $settings;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        // use cached eav config
        $this->_entityTypeId = Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Product::ENTITY)->getId();

        $this->_resource = Mage::getModel('importexport/import_proxy_product_resource');

        $this->settings = Mage::helper('mash2_cobby/settings');
        $this->_htUser = $this->settings->getHtaccessUser();
        $this->_htPassword = $this->settings->getHtaccessPassword();
        $this->_mediaUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
    }

    public function import($rows)
    {
        $result = array();

        $this->_createMediaImportFolder();

        $this->_getUploader()->setAllowRenameFiles(!$this->settings->getOverwriteImages());

        $mediaGallery = $this->_processRows($rows);
        $productIds = array_keys($mediaGallery);

        Mage::dispatchEvent('cobby_import_product_media_import_before', array( 'products' => $productIds ));

        $this->_saveMediaImages($mediaGallery);
        $this->_saveMediaGallery($mediaGallery);
        $this->_saveProductImageAttributes($mediaGallery);

        //enable when added errors for images
//        foreach($mediaGallery as $productId => $value ) {
//            $result[$productId] = $value['errors'];
//        }

        Mage::getModel('mash2_cobby/product')->updateHash($productIds);

        Mage::dispatchEvent('cobby_import_product_media_import_after', array( 'products' => $productIds ));

        return $result;
    }

    protected function _processRows($rows)
    {
        $mediaGallery = array();
        $uploadedGalleryFiles = array();

        $productIds = array_keys($rows);
        $existingProductIds = $this->loadExistingProductIds($productIds);
        $storeIds = array();
        foreach(Mage::app()->getStores(true) as $store) {
            $storeIds[] = (int)$store->getId();
        }

        foreach($rows as $productId => $mediaData)
        {
            if(!in_array($productId, $existingProductIds))
                continue;

            $result[$productId] = array();
            $mediaGallery[$productId] = array(
                'images' => array(),
                'gallery' => array(),
                'attributes' => array(),
                'errors' => array());

            $images = $mediaData['images'];
            $gallery = $mediaData['gallery'];
            $attributes = $mediaData['attributes'];
            $useDefaultStores = $mediaData['use_default_stores'];

            foreach($images as $imageData)
            {
                $image = $imageData['image'];

                if(!empty($imageData['import'])) {
                    if(empty($imageData['name'])) {
                        $imageData['name'] = $image;
                    }

                    //only copy if exists in import folder
                    if (is_file($this->_getUploader()->getTmpDir() . DS . $imageData['import'])) {
                        copy($this->_getUploader()->getTmpDir() . DS . $imageData['import'], $this->_getUploader()->getTmpDir() . DS . $imageData['name']);
                    }

                }else  if(!empty($imageData['upload'])) {
                    if(empty($imageData['name'])) {
                        $imageData['name'] = basename(parse_url($imageData['upload'], PHP_URL_PATH));
                    }

                    $this->_copyExternalImageFile($imageData['upload'], $imageData['name']);
                }

                if(!empty($imageData['import']) || !empty($imageData['upload'])) {
                    if (!array_key_exists($imageData['name'], $uploadedGalleryFiles)) {
                        $uploadedGalleryFiles[$imageData['name']] = $this->_uploadMediaFiles($imageData['name']);
                    }
                    $imageData['file'] = $uploadedGalleryFiles[$imageData['name']];
                }

                if($imageData['file'] == '') {
                    $mediaGallery[$productId]['errors'][$imageData['image']] = self::ERROR_FILE_NOT_FOUND;
                }

                if(!isset($mediaGallery[$productId]['errors'][$imageData['image']])){
                    $mediaGallery[$productId]['images'][$imageData['image']] = $imageData['file'];
                }
            }

            foreach($gallery as $storeId => $storeGalleryData)
            {
                if(!in_array($storeId, $storeIds))
                    continue;

                $mediaGallery[$productId]['gallery'][$storeId] = array();
                foreach($storeGalleryData as $galleryData){
                    if(!isset($mediaGallery[$productId]['errors'][$galleryData['image']])) {
                        $mediaGallery[$productId]['gallery'][$storeId][] = array(
                            'image' => $galleryData['image'],
                            'disabled' => $galleryData['disabled'],
                            'position' => $galleryData['position'],
                            'label' => $galleryData['label'],
                            'use_default' => in_array($storeId, $useDefaultStores)
                        );
                    }
                }
            }

            foreach($attributes as $storeId => $storeAttributeData)
            {
                if(!in_array($storeId, $storeIds))
                    continue;

                $mediaGallery[$productId]['attributes'][$storeId] = array();
                foreach($storeAttributeData as $imageAttribute => $image) {

                    if(!isset($mediaGallery[$productId]['errors'][$image])) {

                        if (in_array($storeId, $useDefaultStores))
                            $image = '';

                        $mediaGallery[$productId]['attributes'][$storeId][$imageAttribute] = $image;
                    }
                }
            }
        }

        return $mediaGallery;
    }

    protected function _saveProductImageAttributes(array $mediaGalleryData)
    {
        $attributesData = array();

        foreach ($mediaGalleryData as $productId => $productImageData)
        {
            $attributes = $productImageData['attributes'];
            $images = $productImageData['images'];

            foreach($attributes as $storeId => $storeAttributeData)
            {
                foreach ($storeAttributeData as $key => $value)
                {
                    $file = null;
                    if(!empty($value)){
                        $file = $value == 'no_selection' ? 'no_selection' : $images[$value];
                    }

                    $attribute = $this->_resource->getAttribute($key);
                    $attrTable = $attribute->getBackend()->getTable();
                    $attrId = $attribute->getId();
                    $attributesData[$attrTable][$productId][$attrId][$storeId] = $file;
                }
            }
        }

        $this->_saveProductAttributes($attributesData);
        return $this;
    }

    protected function _saveProductAttributes(array $attributesData)
    {
        foreach ($attributesData as $tableName => $productData) {
            $tableData = array();

            foreach ($productData as $productId => $attributes) {

                foreach ($attributes as $attributeId => $storeValues) {
                    foreach ($storeValues as $storeId => $storeValue) {
                        if (!is_null($storeValue)) {
                            $tableData[] = array(
                                'entity_id'      => $productId,
                                'entity_type_id' => $this->_entityTypeId,
                                'attribute_id'   => $attributeId,
                                'store_id'       => $storeId,
                                'value'          => $storeValue
                            );
                        } else {
                            $this->connection->delete($tableName, array(
                                'entity_id=?'      => (int) $productId,
                                'entity_type_id=?' => (int) $this->_entityTypeId,
                                'attribute_id=?'   => (int) $attributeId,
                                'store_id=?'       => (int) $storeId,
                            ));
                        }
                    }
                }
            }

            if (count($tableData)) {
                $this->connection->insertOnDuplicate($tableName, $tableData, array('value'));
            }
        }
        return $this;
    }

    /**
     * Create the media/import folder
     */
    protected function _createMediaImportFolder()
    {
        $dir = Mage::getBaseDir('media') . DS . 'import';
        if (!is_dir($dir)) {
            mkdir($dir);
        }
    }

    protected function _uploadMediaFiles($fileName)
    {
        try {
            // cache uploaded files
            if(isset($this->_uploadMediaFiles[$fileName]))
                return $this->_uploadMediaFiles[$fileName];

            $res = $this->_getUploader()->move($fileName);
            $this->_uploadMediaFiles[$fileName] = $res['file'];
            return $res['file'];
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Download image file from url to tmp folder
     *
     * @param $url
     * @param $fileName
     */
    protected function _copyExternalImageFile($url, $fileName)
    {
        if (strpos($url, 'http') === 0 && strpos($url, '://') !== false) {
            try {
                $dir = $this->_getUploader()->getTmpDir();
                if (!is_dir($dir)) {
                    mkdir($dir);
                }
                $fileHandle = fopen($dir . DS . basename($fileName), 'w+');
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_TIMEOUT, 50);
                curl_setopt($ch, CURLOPT_FILE, $fileHandle);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                // use basic auth ony current installation
                if( $this->_htUser != '' && $this->_htPassword != '' && parse_url($url, PHP_URL_HOST) == parse_url($this->_mediaUrl, PHP_URL_HOST))
                {
                    curl_setopt($ch, CURLOPT_USERPWD, "$this->_htUser:$this->_htPassword");
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                }
                curl_exec($ch);
                curl_close($ch);
                fclose($fileHandle);
            } catch (Exception $e) {
                Mage::throwException('Download of file ' . $url . ' failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get media uploader
     *
     * @return Mage_ImportExport_Model_Import_Uploader
     */
    protected function _getUploader()
    {
        if (is_null($this->_fileUploader)) {
            $this->_fileUploader    = new Mage_ImportExport_Model_Import_Uploader();

            $this->_fileUploader->init();

            $tmpDir     = Mage::getConfig()->getOptions()->getMediaDir() . '/import';
            $destDir    = Mage::getConfig()->getOptions()->getMediaDir() . '/catalog/product';
            if (!is_writable($destDir)) {
                @mkdir($destDir, 0777, true);
            }
            if (!$this->_fileUploader->setTmpDir($tmpDir)) {
                Mage::throwException("File directory '{$tmpDir}' is not readable.");
            }
            if (!$this->_fileUploader->setDestDir($destDir)) {
                Mage::throwException("File directory '{$destDir}' is not writable.");
            }
        }
        return $this->_fileUploader;
    }

    protected function _saveMediaImages(array $mediaGalleryData)
    {
        $galleryAttributeId = Mage::getSingleton('catalog/product')->getResource()
            ->getAttribute('media_gallery')
            ->getAttributeId();

        $mediaGalleryTableName = $this->_resource->getTable('catalog/product_attribute_media_gallery');
        $mediaValueTableName = $this->_resource->getTable('catalog/product_attribute_media_gallery_value');

        foreach ($mediaGalleryData as $productId => $productImageData) {
            $mediaValues = $this->connection->fetchPairs($this->connection->select()
                ->from($mediaGalleryTableName, array('value', 'value_id'))
                ->where('entity_id IN (?)', $productId) );

            $images = $productImageData['images'];
            $newImages = array_diff(array_values($images),array_keys($mediaValues));
            $deletedImages = array_diff(array_keys($mediaValues), array_values($images));

            foreach($deletedImages as $file)
            {
                if (array_key_exists($file, $mediaValues)) {
                    $deleteValueId =  $mediaValues[$file];
                    $this->connection->delete($mediaValueTableName, $this->connection->quoteInto('value_id IN (?)', $deleteValueId));
                    $this->connection->delete($mediaGalleryTableName, $this->connection->quoteInto('value_id IN (?)', $deleteValueId));
                }
            }

            foreach($newImages as $file)
            {
                if (!in_array($file, $mediaValues)) {
                    $valueArr = array(
                        'attribute_id' => $galleryAttributeId,
                        'entity_id'    => $productId,
                        'value'        => $file
                    );
                    $this->connection->insertOnDuplicate($mediaGalleryTableName, $valueArr, array('entity_id'));
                }
            }
        }

        return $this;
    }

    /**
     * Save product media gallery.
     *
     * @param array $mediaGalleryData
     * @return $this
     */
    protected function _saveMediaGallery(array $mediaGalleryData)
    {
        $mediaGalleryTableName = $this->_resource->getTable('catalog/product_attribute_media_gallery');
        $mediaValueTableName = $this->_resource->getTable('catalog/product_attribute_media_gallery_value');

        foreach ($mediaGalleryData as $productId => $productImageData) {
            $mediaValues = $this->connection->fetchPairs($this->connection->select()
                    ->from($mediaGalleryTableName, array('value', 'value_id'))
                    ->where('entity_id IN (?)', $productId) );

            $images = $productImageData['images'];
            $gallery = $productImageData['gallery'];

            foreach($gallery as $storeId => $storeGalleryData)
            {
                foreach($storeGalleryData as $galleryData)
                {
                    $image = $galleryData['image'];
                    $file = $images[$image];
                    $valueId = $mediaValues[$file];

                    $this->connection->delete($mediaValueTableName, array(
                        'value_id=?'      => (int) $valueId,
                        'store_id=?'       => (int) $storeId,
                    ));

                    if($galleryData['use_default'] == false)
                    {
                        $insertValueArr = array(
                                    'value_id' => $valueId,
                                    'store_id' => $storeId,
                                    'label'    => $galleryData['label'],
                                    'position' => $galleryData['position'],
                                    'disabled' => $galleryData['disabled']
                                );
                        $this->connection->insertOnDuplicate($mediaValueTableName, $insertValueArr, array('value_id'));
                    }
                }
            }
        }

        return $this;
    }

    private function _getProductImages( $productId )
    {
        $mediaAttributesTableName = $this->_resource->getTable('catalog_product_entity_varchar');
        $attributeIds = $this->getImageAttributeIds();

        $productImages = $this->connection->fetchPairs($this->connection->select()
            ->from($mediaAttributesTableName, array('value', 'value_id'))
            ->where('entity_id IN (?)', $productId)
            ->where('attribute_id IN (?) ', $attributeIds)
        );

        return array_keys($productImages);
    }

    public function deleteDuplicateImages($data)
    {
        $result = array();
        $this->_loadExistingProductIds($data);

        $deleteImageIds = array();
        $mediaValueTableName = $this->_resource->getTable('catalog/product_attribute_media_gallery_value');
        $mediaGalleryTableName = $this->_resource->getTable('catalog/product_attribute_media_gallery');
        $mediaBaseDir = Mage::getBaseDir('media');

        // Products
        foreach($this->_existingProductIds as $productId)
        {
            $productImages = $this->connection->fetchPairs($this->connection->select()
                ->from($mediaGalleryTableName, array('value', 'value_id'))
                ->where('entity_id IN (?)', $productId) );

            $usedProductImages = $this->_getProductImages($productId);

            $productResult = array();
            $productImagesResult = array();
            $sizeImages = array();

            $deleteImageFiles = array();

            // Group by Size
            $priority = 0;
            foreach ($productImages as $imageFile => $imageId)
            {
                $imagePath =  $mediaBaseDir . '/catalog/product' .  $imageFile;

                // Image not found
                if(!file_exists($imagePath))
                {
                    $imageReturn = array();
                    $imageReturn['file'] = $imageFile;
                    $imageReturn['status'] = 'nf';
                    $productImagesResult[] = $imageReturn;
                    continue;
                }

                $fileSize = filesize($imagePath);

                $imageItem = array( );
                $imageItem['path'] =  $imagePath;
                $imageItem['size'] = $fileSize;
                $imageItem['file'] = $imageFile;
                $imageItem['imageId'] = $imageId;
                $priority++;
                $imageItem['priority'] = $priority;
                if(in_array($imageFile, $usedProductImages)) {
                    $imageItem['priority'] = 0;
                }

                if(!isset($sizeImages[$fileSize]))
                {
                    $sizeImages[$fileSize] = array();
                }

                $sizeImages[$fileSize][] = $imageItem;
            }

            // Compare Hash
            foreach($sizeImages as $group)
            {
                if(count($group) < 2)
                {
                    if(count($group) == 1)
                    {
                        $singleImage = array();
                        $singleImage['file'] = $group[0]['file'];
                        $singleImage['status'] = 'ex';
                        $productImagesResult[] = $singleImage;
                    }

                    continue;
                }

                $groupHashes = array();

                //sort by priority, so we start with selected images first (thumb/small/base/...)
                usort($group, function($a, $b) {
                    return $a['priority'] - $b['priority'];
                });

                foreach($group as $groupItem)
                {
                    $groupItemHash = md5_file($groupItem['path']);
                    $imageReturn = array();
                    $imageReturn['file'] = $groupItem['file'];

                    if(in_array($groupItemHash, $groupHashes) && $groupItem['priority'] > 0 )
                    {
                        $deleteImageIds[] = $groupItem['imageId'];
                        $deleteImageFiles[] = $groupItem['file'];
                        $imageReturn['status'] = 'de';
                    }
                    else
                    {
                        // add Hash
                        $groupHashes[] = $groupItemHash;
                        $imageReturn['status'] = 'ex';
                    }

                    $productImagesResult[] = $imageReturn;
                }
            }

            $productResult['magentoId'] = $productId;
            $productResult['images'] = $productImagesResult;

            $result[] = $productResult;
        }

        if(count($deleteImageIds)> 0)
        {
            // delete Images
            $this->connection->delete($mediaValueTableName, $this->connection->quoteInto('value_id IN (?)', $deleteImageIds));
            $this->connection->delete($mediaGalleryTableName, $this->connection->quoteInto('value_id IN (?)', $deleteImageIds));
        }

        return $result;
    }

    public function getImageAttributeIds()
    {
        // @var $collection Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Attribute_Collection /
        $collection = Mage::getResourceModel('catalog/product_attribute_collection');
        $collection->setEntityTypeFilter($this->_entityTypeId);
        $collection->setFrontendInputTypeFilter('media_image');

        $result = array();

        foreach ($collection as $attribute) {
            $result[] = $attribute->getAttributeId();
        }

        return $result;
    }
}