<?php
/**
 * Enhanced Plisio WHMCS Gateway Integration
 * Based on the official Plisio WHMCS plugin v1.0.3
 * Integrated with Star Router Rent payment system
 */

require_once __DIR__ . '/PlisioClient.php';

class PlisioWHMCSGateway {
    private $api_key;
    private $api_url = 'https://api.plisio.net/api/v1';
    private $timeout = 30;
    private $max_retries = 3;
    private $version = '1.0.3';
    
    public function __construct($api_key) {
        if (empty($api_key)) {
            throw new InvalidArgumentException('Plisio API key is required');
        }
        $this->api_key = $api_key;
    }
    
    /**
     * Create cryptocurrency invoice using WHMCS-compatible API
     */
    public function createInvoice($params) {
        $this->validateInvoiceParams($params);
        
        $client = new PlisioClient($this->api_key);
        
        $data = [
            'order_name' => $params['description'] ?? 'Star Router Rent Payment',
            'order_number' => $params['order_id'],
            'description' => $params['description'] ?? 'Payment for services',
            'source_amount' => number_format($params['amount'], 8, '.', ''),
            'source_currency' => 'USD',
            'currency' => $params['currency'] ?? 'USDT',
            'email' => $params['email'] ?? '',
            'plugin' => 'star-router-rent',
            'version' => $this->version
        ];
        
        // Add optional URLs
        if (!empty($params['callback_url'])) {
            $data['callback_url'] = $params['callback_url'];
        }
        if (!empty($params['success_callback_url'])) {
            $data['success_url'] = $params['success_callback_url'];
        }
        if (!empty($params['fail_callback_url'])) {
            $data['cancel_url'] = $params['fail_callback_url'];
        }
        
        $response = $client->createTransaction($data);
        
        if (!$response || $response['status'] === 'error') {
            $error_message = 'Unknown error';
            if (isset($response['data']['message'])) {
                $error_message = $response['data']['message'];
            } elseif (isset($response['message'])) {
                $error_message = $response['message'];
            }
            throw new Exception('Plisio API error: ' . $error_message);
        }
        
        if (!isset($response['data']['txn_id']) || !isset($response['data']['invoice_url'])) {
            throw new Exception('Invalid response from Plisio API: missing required fields');
        }
        
        return [
            'txn_id' => $response['data']['txn_id'],
            'invoice_url' => $response['data']['invoice_url'],
            'amount' => $response['data']['amount'] ?? $params['amount'],
            'currency' => $response['data']['psys_cid'] ?? $params['currency'],
            'status' => 'pending'
        ];
    }
    
    /**
     * Get invoice status
     */
    public function getInvoiceStatus($invoice_id) {
        if (empty($invoice_id)) {
            throw new InvalidArgumentException('Invoice ID is required');
        }
        
        $client = new PlisioClient($this->api_key);
        
        // Use the client's built-in method if available
        $url = "invoices/{$invoice_id}";
        return $this->makeDirectApiCall($url, []);
    }
    
    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies() {
        $client = new PlisioClient($this->api_key);
        $currencies = $client->getCurrencies('USD');
        
        // Filter to get top 10 most popular cryptocurrencies
        $top_currencies = [
            'BTC' => 'Bitcoin',
            'ETH' => 'Ethereum', 
            'USDT' => 'Tether',
            'USDT_BEP20' => 'Tether (BEP20)',
            'BCH' => 'Bitcoin Cash',
            'TRX' => 'TRON',
            'XMR' => 'Monero',
            'DASH' => 'Dash',
            'ZEC' => 'Zcash'
        ];
        
        // Return only supported currencies that are in our top list
        $filtered = [];
        if (is_array($currencies)) {
            foreach ($currencies as $code => $data) {
                if (isset($top_currencies[$code])) {
                    $filtered[$code] = $data;
                }
            }
        }
        
        return $filtered;
    }
    
    /**
     * Create withdrawal (payout)
     */
    public function createWithdrawal($params) {
        $this->validateWithdrawalParams($params);
        
        $client = new PlisioClient($this->api_key);
        
        $result = $client->createWithdrawal(
            $params['amount'],
            $params['currency'],
            $params['address']
        );
        
        if (!$result || $result['status'] === 'error') {
            throw new Exception('Withdrawal creation failed: ' . ($result['message'] ?? 'Unknown error'));
        }
        
        return $result;
    }
    
    /**
     * Get withdrawal status
     */
    public function getWithdrawalStatus($operation_id) {
        if (empty($operation_id)) {
            throw new InvalidArgumentException('Operation ID is required');
        }
        
        return $this->makeDirectApiCall("operations/{$operation_id}", []);
    }
    
    /**
     * Verify webhook callback using WHMCS-compatible method
     */
    public function verifyCallback($post_data, $verify_hash) {
        if (empty($verify_hash)) {
            return false;
        }
        
        // Remove verify_hash from data for verification
        $data_to_verify = $post_data;
        if (isset($data_to_verify['verify_hash'])) {
            unset($data_to_verify['verify_hash']);
        }
        
        // Sort the data
        ksort($data_to_verify);
        
        // Handle special fields as per WHMCS plugin
        if (isset($data_to_verify['expire_utc'])) {
            $data_to_verify['expire_utc'] = (string)$data_to_verify['expire_utc'];
        }
        if (isset($data_to_verify['tx_urls'])) {
            $data_to_verify['tx_urls'] = html_entity_decode($data_to_verify['tx_urls']);
        }
        
        // Create the verification string using serialize (WHMCS method)
        $post_string = serialize($data_to_verify);
        $check_key = hash_hmac('sha1', $post_string, $this->api_key);
        
        return hash_equals($check_key, $verify_hash);
    }
    
    /**
     * Get commission estimate
     */
    public function getCommissionEstimate($currency, $amount) {
        $data = [
            'currency' => $currency,
            'amount' => $amount
        ];
        
        return $this->makeDirectApiCall('operations/commission', $data);
    }
    
    /**
     * Get balance
     */
    public function getBalance($currency = null) {
        $client = new PlisioClient($this->api_key);
        return $client->getBalances($currency);
    }
    
    /**
     * Get shop information
     */
    public function getShopInfo() {
        $client = new PlisioClient($this->api_key);
        return $client->getShopInfo();
    }
    
    /**
     * Validate invoice parameters
     */
    private function validateInvoiceParams($params) {
        $required = ['amount', 'order_id'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw new InvalidArgumentException("Missing required parameter: {$field}");
            }
        }
        
        if (!is_numeric($params['amount']) || $params['amount'] <= 0) {
            throw new InvalidArgumentException('Amount must be a positive number');
        }
        
        if (strlen($params['order_id']) > 255) {
            throw new InvalidArgumentException('Order ID is too long (max 255 characters)');
        }
    }
    
    /**
     * Validate withdrawal parameters
     */
    private function validateWithdrawalParams($params) {
        $required = ['amount', 'currency', 'address'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw new InvalidArgumentException("Missing required parameter: {$field}");
            }
        }
        
        if (!is_numeric($params['amount']) || $params['amount'] <= 0) {
            throw new InvalidArgumentException('Amount must be a positive number');
        }
    }
    
    /**
     * Make direct API call for endpoints not covered by PlisioClient
     */
    private function makeDirectApiCall($endpoint, $data, $retry_count = 0) {
        $data['api_key'] = $this->api_key;
        $url = $this->api_url . '/' . $endpoint;
        
        if (!empty($data)) {
            $url .= '?' . http_build_query($data);
        }
        
        // Log request (without sensitive data)
        $log_data = $data;
        if (isset($log_data['api_key'])) {
            $log_data['api_key'] = '***HIDDEN***';
        }
        error_log("Plisio WHMCS API Request: {$url} - Data: " . json_encode($log_data));
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'StarRouterRent-WHMCS/' . $this->version . ' (PHP/' . PHP_VERSION . ')',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Cache-Control: no-cache'
            ],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // Handle cURL errors
        if ($curl_error) {
            if ($retry_count < $this->max_retries) {
                sleep(pow(2, $retry_count));
                return $this->makeDirectApiCall($endpoint, $data, $retry_count + 1);
            }
            throw new Exception('Network error: ' . $curl_error);
        }
        
        // Log response
        error_log("Plisio WHMCS API Response: HTTP {$http_code} - " . substr($response, 0, 500));
        
        // Handle HTTP errors with retry for temporary failures
        if (($http_code >= 500 || $http_code == 451) && $retry_count < $this->max_retries) {
            sleep(pow(2, $retry_count));
            return $this->makeDirectApiCall($endpoint, $data, $retry_count + 1);
        }
        
        if ($http_code !== 200) {
            $error_messages = [
                401 => 'Invalid API key or authentication failed',
                403 => 'Access forbidden - check API key permissions',
                404 => 'API endpoint not found',
                451 => 'Service unavailable in your region',
                429 => 'Rate limit exceeded - please try again later',
                500 => 'Plisio server error - please try again later',
                502 => 'Bad gateway - Plisio service temporarily unavailable',
                503 => 'Service unavailable - Plisio maintenance in progress',
                504 => 'Gateway timeout - request took too long'
            ];
            
            $error_message = $error_messages[$http_code] ?? "HTTP error {$http_code}";
            throw new Exception($error_message);
        }
        
        $result = json_decode($response, true);
        
        if (!$result) {
            throw new Exception('Invalid JSON response from Plisio API');
        }
        
        if (!isset($result['status']) || $result['status'] !== 'success') {
            $error_message = 'Unknown error';
            if (isset($result['message'])) {
                $error_message = $result['message'];
            } elseif (isset($result['data']['message'])) {
                $error_message = $result['data']['message'];
            }
            throw new Exception('Plisio API error: ' . $error_message);
        }
        
        return $result['data'] ?? $result;
    }
    
    /**
     * Test API connection
     */
    public function testConnection() {
        try {
            // Try to get shop info to test connection
            $shop_info = $this->getShopInfo();
            if ($shop_info) {
                return ['success' => true, 'message' => 'Connection successful'];
            } else {
                return ['success' => false, 'message' => 'Invalid response from API'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get API key status
     */
    public function getApiKeyStatus() {
        try {
            $response = $this->makeDirectApiCall('api-key-status', []);
            return $response;
        } catch (Exception $e) {
            throw new Exception('Failed to check API key status: ' . $e->getMessage());
        }
    }
}
?>