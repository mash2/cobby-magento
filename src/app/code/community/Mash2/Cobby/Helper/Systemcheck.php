<?php
/**
 *
 */

class Mash2_Cobby_Helper_Systemcheck extends Mage_Core_Helper_Abstract
{
    const PHP_MIN_VERSION = "5.6";
    const API_ROUTE = 'index.php/api/mash2/json';
    const OK = 0;
    const ERROR = 1;
    const EXCEPTION = -1;
    const MIN_MEMORY = 512;
    const MAINTENANCE_MODE = 'maintenance.flag';
    const URL = 'https://help.cobby.io';
    const VALUE = 'value';
    const CODE = 'code';
    const LINK = 'link';

    private $relevantIndexers = array(
        'catalog_category_product' => 'Category Products',
        'catalog_product_price' => 'Product Prices',
        'cataloginventory_stock' => 'Stock Status',
        'catalog_product_flat' => 'Product Flat Data',
        'catalog_category_flat' => 'Category Flat Data'
    );

    public function getReport()
    {
        $result = array(
            'phpversion' => $this->checkPhpVersion(),
            'memory' => $this->checkMemory(),
            'credentials' => $this->checkCredentials(),
            'maintenance' => $this->checkMaintenanceMode(),
            'indexer' => $this->checkIndexerStatus(),
            'url' => $this->checkUrl(),
            'cobby_active' => $this->checkCobbyActive(),
            'cobby_version' => $this->checkPhpVersion()
        );

        return $result;
    }

    public function checkMemory()
    {
        $value = $this->__('Memory ok');
        $code = self::OK;
        $link = '';

        try {
            $memory = ini_get('memory_limit');
            if ((int)$memory < self::MIN_MEMORY) {
                $code = self::ERROR;
                $value = $this->__('Memory is %sB, it has to be at least %sMB', $memory, self::MIN_MEMORY);
                $link = self::URL;
            }
        } catch (Exception $e) {
            $code = self::EXCEPTION;
            $value = $e->getMessage();
            $link = self::URL;
        }

        return array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    public function checkPhpVersion()
    {
        $value = $this->__('PHP version ok');
        $code = self::OK;
        $link = '';

        try {
            $version  = phpversion();
            if (version_compare($version, self::PHP_MIN_VERSION, '<')) {
                $$code = self::ERROR;
                $link = self::URL;
                $value = $this->__('PHP version is %s, it must be at least %s', $version, self::PHP_MIN_VERSION);
            }
        } catch (Exception $e) {
            $code = self::EXCEPTION;
            $value = $e->getMessage();
            $link = self::URL;
        }

        return array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    public function checkMaintenanceMode()
    {
        $value = $this->__('Is not active');
        $code = self::OK;
        $link = '';

        try {
            $maintenanceOn = file_exists($_ENV['PWD'] . '/' . self::MAINTENANCE_MODE);
            if ($maintenanceOn) {
                $value = $this->__('Is active');
                $code = self::ERROR;
                $link = self::URL;
            }
        } catch (Exception $e) {
            $code = self::EXCEPTION;
            $value = $e->getMessage();
            $link = self::URL;
        }

        return array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    public function checkIndexerStatus()
    {
        $value = $this->__('Index is valid');
        $code = self::OK;
        $link = '';

        $runningIndexers = array();

        $indexerModel = Mage::getModel('mash2_cobby/indexer_api');
        $indexers = $indexerModel->export();

        foreach ($indexers as $indexer) {
            if (key_exists($indexer['code'], $this->relevantIndexers) && $indexer['status'] == 'working') {
                $runningIndexers[] = $indexer['code'];
            }
        }

        if (!empty($runningIndexers)) {
            $value = $this->__('Indexing is in progress for: ') . implode('; ', $runningIndexers);
            $code = self::ERROR;
            $link = self::URL;
        }

        return array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    public function checkUrl()
    {
        $value = $this->__('URL is up to date');
        $code = self::OK;
        $link = '';

        $baseUrl = Mage::helper('mash2_cobby/settings')->getDefaultBaseUrl();
        $cobbyUrl = Mage::helper('mash2_cobby/settings')->getCobbyUrl();

        $len = strlen($cobbyUrl);

        if (substr($baseUrl, 0, $len) !== $cobbyUrl) {
            $value = $this->__('The cobby URL doesn’t match the base URL, save config or disable cobby');
            $code = self::ERROR;
            $link = self::URL;
        }

        return array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    public function checkCobbyActive()
    {
        $value = $this->__('Cobby is active');
        $code = self::OK;
        $link = '';

        $active = Mage::getStoreConfigFlag('cobby/settings/active');

        if (!$active) {
            $value = $this->__('Cobby must be activated to work as expected');
            $code = self::ERROR;
            $link = self::URL;
        }

        return array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    public function checkCobbyVersion()
    {
        $code = self::OK;
        $value = $this->__('Your module version is synchronized');
        $link = '';

        $moduleVersion = Mage::getConfig()->getNode('modules/Mash2_Cobby/version')->asArray();
        $cobbyVersion = Mage::getStoreConfig(Mash2_Cobby_Helper_Settings::XML_PATH_COBBY_DBVERSION );

        if ($cobbyVersion != $moduleVersion) {
            $value = $this->__('Your module version is not synchronized, save config for synchronization');
            $code = self::ERROR;
            $link = self::URL;
        }

        return array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    private function getApiUrl()
    {
        $baseUrl = '';
        $defaultGroup = Mage::app()->getWebsite(true)->getDefaultGroup();
        if ($defaultGroup) {
            $defaultStore = $defaultGroup->getDefaultStore();
            if ($defaultStore) {
                $baseUrl = $defaultStore->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
            }
        }

        $url = explode(':', $baseUrl);

        return $url[0] . ':' . $url[1] . '/' . self::API_ROUTE;
    }

    protected function _getLoginData()
    {
        $apiUserName = Mage::helper('mash2_cobby/settings')->getApiUser();
        $apiKey = Mage::helper('core')->decrypt(Mage::getStoreConfig('cobby/settings/api_key'));


        $data = array(
            "method" => "login",
            "params" => array($apiUserName, $apiKey),
            "id" => "id"
        );

        return json_encode($data);
    }

    public function checkCredentials()
    {
        $value = $this->__('Login data is set up correctly');
        $code = self::OK;
        $link = '';

        $url = $this->getApiUrl();
        $data = $this->_getLoginData();

        if ($data) {
            $login = $this->_login($url, $data);
            if (!$login) {
                $code = self::ERROR;
                $value = $this->__('It seems the provided credentials are wrong');
                $link = self::URL;
            }
        } else {
            $code = self::EXCEPTION;
            $value = $this->__('It seems like you have no login data, enter your credentials and save config');
            $link = self::URL;
        }

        return array(self::VALUE => $value, self::CODE => $code, self::LINK => $link);
    }

    protected function _login($url, $data)
    {
        if (strpos($url, 'http') === 0 && strpos($url, '://') !== false) {
            try {

                $curl = new Varien_Http_Adapter_Curl();
                $curl->setConfig(array(
                    'timeout'   => 15    //Timeout in no of seconds
                ));

                $curl->write(Zend_Http_Client::POST, $url, '1.1', array(), $data);
                $response = $curl->read();

                $http_code = $curl->getInfo(CURLINFO_HTTP_CODE);
                $header_size = $curl->getInfo(CURLINFO_HEADER_SIZE);

                $body = json_decode(substr($response, $header_size));
                $token = $body->result;

                $curl->close();

                if ($http_code !== 200) {
                    Mage::throwException("Http code: " .$http_code);
                }

                if ($token) {
                    return true;
                }

                return false;
            } catch (Exception $e) {

                return  false;
            }
        }

        return false;
    }
}
