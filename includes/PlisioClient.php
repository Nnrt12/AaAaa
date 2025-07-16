<?php
/**
 * Plisio Client - Official WHMCS Plugin Client
 * Version 1.0.3 - Integrated with Star Router Rent
 */

class PlisioClient
{
    protected $secretKey = '';
    public $apiEndPoint = 'https://api.plisio.net/api/v1';

    public function __construct($secretKey)
    {
        $this->secretKey = $secretKey;
    }

    protected function getApiUrl($commandUrl)
    {
        return trim($this->apiEndPoint, '/') . '/' . $commandUrl;
    }

    public function getBalances($currency = null)
    {
        $params = [];
        if ($currency) {
            $params['currency'] = $currency;
        }
        return $this->apiCall('balances', $params);
    }

    public function getShopInfo()
    {
        return $this->apiCall('shops');
    }

    public function getCurrencies($source_currency = 'USD')
    {
        $currencies = $this->guestApiCall("currencies/$source_currency");
        if (isset($currencies['data']) && is_array($currencies['data'])) {
            return array_filter($currencies['data'], function ($currency) {
                return isset($currency['hidden']) && $currency['hidden'] == 0;
            });
        }
        return [];
    }

    public function createTransaction($req)
    {
        $result = $this->apiCall('invoices/new', $req);
        
        // Ensure consistent response format
        if (!isset($result['status'])) {
            $result['status'] = 'success';
        }
        
        return $result;
    }

    /**
     * Creates a withdrawal from your account to a specified address.
     * @param float $amount The amount of the transaction (floating point to 8 decimals).
     * @param string $currency The cryptocurrency to withdraw.
     * @param string $address The address to send the coins to.
     */
    public function createWithdrawal($amount, $currency, $address)
    {
        $req = array(
            'currency' => $currency,
            'amount' => $amount,
            'to' => $address,
            'type' => 'cash_out',
        );
        $result = $this->apiCall('operations/withdraw', $req);
        
        // Ensure consistent response format
        if (isset($result['data'])) {
            return $result['data'];
        }
        
        return $result;
    }

    /**
     * Creates a mass withdrawal from your account to specified addresses.
     * @param array $payments Array of addresses and amounts.
     * @param string $currency The cryptocurrency to withdraw.
     */
    public function createMassWithdrawal($payments, $currency)
    {
        $req = array(
            'currency' => $currency,
            'amount' => implode(',', array_values($payments)),
            'to' => implode(',', array_keys($payments)),
            'type' => 'mass_cash_out',
        );
        return $this->apiCall('operations/withdraw', $req);
    }

    private function isSetup()
    {
        return !empty($this->secretKey);
    }

    protected function getCurlOptions($url)
    {
        return [
            CURLOPT_URL => $url,
            CURLOPT_HTTPGET => true,
            CURLOPT_FAILONERROR => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'StarRouterRent-PlisioClient/1.0.3 (PHP/' . PHP_VERSION . ')',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
        ];
    }

    private function apiCall($cmd, $req = array())
    {
        if (!$this->isSetup()) {
            return array('status' => 'error', 'message' => 'API key not configured');
        }
        return $this->guestApiCall($cmd, $req);
    }

    private function guestApiCall($cmd, $req = array())
    {
        // Generate the query string
        $queryString = '';
        if (!empty($this->secretKey)) {
            $req['api_key'] = $this->secretKey;
        }
        if (!empty($req)) {
            $post_data = http_build_query($req, '', '&');
            $queryString = '?' . $post_data;
        }

        try {
            $apiUrl = $this->getApiUrl($cmd . $queryString);

            $ch = curl_init();
            curl_setopt_array($ch, $this->getCurlOptions($apiUrl));
            $data = curl_exec($ch);

            if ($data !== FALSE) {
                $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $body = substr($data, $header_size);
                $dec = $this->jsonDecode($body);
                if ($dec !== NULL && count($dec)) {
                    return $dec;
                } else {
                    return array('status' => 'error', 'message' => 'Unable to parse JSON result (' . json_last_error() . ')');
                }
            } else {
                return array('status' => 'error', 'message' => 'cURL error: ' . curl_error($ch));
            }
        } catch (Exception $e) {
            return array('status' => 'error', 'message' => 'Could not send request to API : ' . $apiUrl);
        } finally {
            if (isset($ch)) {
                curl_close($ch);
            }
        }
    }

    private function jsonDecode($data)
    {
        if (PHP_INT_SIZE < 8 && version_compare(PHP_VERSION, '5.4.0') >= 0) {
            // We are on 32-bit PHP, so use the bigint as string option
            $dec = json_decode($data, TRUE, 512, JSON_BIGINT_AS_STRING);
        } else {
            $dec = json_decode($data, TRUE);
        }
        return $dec;
    }
}
?>