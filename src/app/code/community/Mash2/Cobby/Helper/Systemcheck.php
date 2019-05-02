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
    const URL = 'https://help.cobby.io';
    const VALUE = 'value';
    const CODE = 'code';
    const LINK = 'link';

    public function checkMemory()
    {
        $value = $this->__('You have enough memory');
        $code = self::OK;
        $link = '';

        try {
            $memory = ini_get('memory_limit');
            if ((int)$memory < self::MIN_MEMORY) {
                $code = self::ERROR;
                $value = $this->__('Your memory is %sB, it has to be at least %sMB', $memory, self::MIN_MEMORY);
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
        $value = $this->__('Your php version is ok');
        $code = self::OK;
        $link = '';

        try {
            $version  = phpversion();
            if (version_compare($version, self::PHP_MIN_VERSION, '<')) {
                $$code = self::ERROR;
                $link = self::URL;
                $value = $this->__('Your php version is %s, it must be at least %s', $version, self::PHP_MIN_VERSION);
            }
        } catch (Exception $e) {
            $code = self::EXCEPTION;
            $value = $e->getMessage();
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
                $value = $this->__('It seems like your login data is incorrect, check your credentials');
                $link = self::URL;
            }
        } else {
            $code = self::EXCEPTION;
            $value = $this->__('It seems like you have no login data, enter your credentials and hit "Save Config"');
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
