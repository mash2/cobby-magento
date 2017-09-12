<?php

/**
 * cobby settings helper
 */
class Mash2_Cobby_Helper_Settings extends Mage_Core_Helper_Abstract
{
    const XML_PATH_PRODUCT_CATEGORY_POSITION            = 'cobby/settings/product_category_position';
    const XML_PATH_LICENSE_KEY                          = 'cobby/settings/license_key';
    const XML_PATH_COBBY_VERSION                        = 'cobby/settings/cobby_version';
    const XML_PATH_COBBY_HTACCESS_PASSWORD              = 'cobby/htaccess/password';
    const XML_PATH_COBBY_HTACCESS_USER                  = 'cobby/htaccess/user';
    const XML_PATH_COBBY_SETTINGS_CONTACT_EMAIL         = 'cobby/settings/contact_email';
    const XML_PATH_COBBY_SETTINGS_API_USER              = 'cobby/settings/api_user';
    const XML_PATH_COBBY_SETTINGS_API_PASSWORD          = 'cobby/settings/api_key';
    const XML_PATH_COBBY_MANAGE_STOCK                   = 'cobby/stock/manage';
    const XML_PATH_COBBY_PRODUCT_QUANTITY               = 'cobby/stock/quantity';
    const XML_PATH_COBBY_STOCK_AVAILABILITY             = 'cobby/stock/availability';
    const MANAGE_STOCK_ENABLED                          = 0;
    const MANAGE_STOCK_READONLY                         = 1;
    const MANAGE_STOCK_DISABLED                         = 2;

    /**
     * get default product category position
     *
     * @return int
     */
    public function getProductCategoryPosition()
    {
        return (int)Mage::getStoreConfig(self::XML_PATH_PRODUCT_CATEGORY_POSITION);
    }

    /**
     *  Get current license Key
     *
     * @return string
     */
    public function getLicenseKey()
    {
        return Mage::getStoreConfig(self::XML_PATH_LICENSE_KEY);
    }


    /**
     * Get stock management setting
     *
     * @return int
     */
    public function getManageStock()
    {
        return Mage::getStoreConfig(self::XML_PATH_COBBY_MANAGE_STOCK);
    }

    /**
     * Get default quantity
     *
     * @return int
     */
    public function getDefaultQuantity()
    {
        return Mage::getStoreConfig(self::XML_PATH_COBBY_PRODUCT_QUANTITY);
    }

    /**
     * Get default availability
     *
     * @return int
     */
    public function getDefaultAvailability()
    {
        return Mage::getStoreConfig(self::XML_PATH_COBBY_STOCK_AVAILABILITY);
    }

    /**
     * Get admin base url
     *
     * @return string
     */
    public function getDefaultBaseUrl()
    {
        return Mage::app()
            ->getStore(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
            ->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB, true);
    }

    /**
     * Get current cobby version
     *
     * @return string
     */
    public function getCobbyVersion()
    {
        return Mage::getStoreConfig(self::XML_PATH_COBBY_VERSION);
    }

    /**
     * Get contact e-mail
     *
     * @return string
     */
    public function getContactEmail()
    {
        return Mage::getStoreConfig(self::XML_PATH_COBBY_SETTINGS_CONTACT_EMAIL);
    }

    /**
     * Get htaccess user
     *
     * @return string
     */
    public function getHtaccessUser()
    {
        return Mage::getStoreConfig(self::XML_PATH_COBBY_HTACCESS_USER);
    }

    /**
     * Get htaccess password
     *
     * @return string
     */
    public function getHtaccessPassword()
    {
        $password = Mage::getStoreConfig(self::XML_PATH_COBBY_HTACCESS_PASSWORD);
        if (empty($password)) {
            return '';
        }

        return Mage::helper('core')->decrypt($password);
    }

    /**
     * Retrieve rename images
     *
     * @return string
     */
    public function getOverwriteImages()
    {
        return Mage::getStoreConfigFlag('cobby/settings/overwrite_images');
    }

    /**
     * Get api user
     *
     * @return string
     */
    public function getApiUser()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_COBBY_SETTINGS_API_USER);
    }

    public function getApiPassword()
    {
        $password = $this->scopeConfig->getValue(self::XML_PATH_COBBY_SETTINGS_API_PASSWORD);
        if (empty($password)) {
            return '';
        }
        return $this->encryptor->decrypt($password);
    }
}
