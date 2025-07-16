<?php
/**
 * Enhanced Plisio Payment Gateway
 * Production-ready implementation with comprehensive error handling
 */

class PlisioPaymentGateway {
    private $api_key;
    private $api_url = 'https://api.plisio.net/api/v1';
    private $timeout = 30;
    private $max_retries = 3;
    
    public function __construct($api_key) {
        if (empty($api_key)) {
            throw new InvalidArgumentException('Plisio API key is required');
        }
        $this->api_key = $api_key;
    }
    
    /**
     * Create cryptocurrency invoice
     */
    public function createInvoice($params) {
        $this->validateInvoiceParams($params);
        
        $data = [
            'order_name' => $params['description'] ?? 'Payment',
            'order_number' => $params['order_id'],
            'description' => $params['description'] ?? 'Payment for services',
            'source_amount' => number_format($params['amount'], 8, '.', ''),
            'source_currency' => 'USD',
            'currency' => $params['currency'] ?? 'USDT',
            'email' => $params['email'] ?? '',
            'plugin' => 'star-router-rent',
            'version' => '2.0'
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
        
        $response = $this->makeRequest('invoices/new', $data);
        
        if (!isset($response['txn_id']) || !isset($response['invoice_url'])) {
            throw new Exception('Invalid response from Plisio API: missing required fields');
        }
        
        return [
            'txn_id' => $response['txn_id'],
            'invoice_url' => $response['invoice_url'],
            'amount' => $response['amount'] ?? $params['amount'],
            'currency' => $response['psys_cid'] ?? $params['currency']
        ];
    }
    
    /**
     * Get invoice status
     */
    public function getInvoiceStatus($invoice_id) {
        if (empty($invoice_id)) {
            throw new InvalidArgumentException('Invoice ID is required');
        }
        
        return $this->makeRequest("invoices/{$invoice_id}", []);
    }
    
    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies() {
        return $this->makeRequest('currencies/USD', []);
    }
    
    /**
     * Create withdrawal
     */
    public function createWithdrawal($params) {
        $this->validateWithdrawalParams($params);
        
        $data = [
            'currency' => $params['currency'],
            'amount' => $params['amount'],
            'to' => $params['address'],
            'type' => 'cash_out'
        ];
        
        return $this->makeRequest('operations/withdraw', $data);
    }
    
    /**
     * Verify webhook signature
     */
    public function verifyWebhook($post_data, $verify_hash) {
        if (empty($verify_hash)) {
            return false;
        }
        
        $data_to_verify = $post_data;
        if (isset($data_to_verify['verify_hash'])) {
            unset($data_to_verify['verify_hash']);
        }
        
        ksort($data_to_verify);
        
        $post_string = serialize($data_to_verify);
        $check_key = hash_hmac('sha1', $post_string, $this->api_key);
        
        return hash_equals($check_key, $verify_hash);
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
     * Make API request
     */
    private function makeRequest($endpoint, $data, $retry_count = 0) {
        $data['api_key'] = $this->api_key;
        $url = $this->api_url . '/' . $endpoint;
        
        if (!empty($data)) {
            $url .= '?' . http_build_query($data);
        }
        
        error_log("Plisio API Request: {$url}");
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'StarRouterRent/2.0 (PHP/' . PHP_VERSION . ')',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Cache-Control: no-cache'
            ]
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            if ($retry_count < $this->max_retries) {
                sleep(pow(2, $retry_count));
                return $this->makeRequest($endpoint, $data, $retry_count + 1);
            }
            throw new Exception('Network error: ' . $curl_error);
        }
        
        error_log("Plisio API Response: HTTP {$http_code}");
        
        if ($http_code !== 200) {
            throw new Exception("HTTP error {$http_code}");
        }
        
        $result = json_decode($response, true);
        
        if (!$result) {
            throw new Exception('Invalid JSON response from Plisio API');
        }
        
        if (!isset($result['status']) || $result['status'] !== 'success') {
            $error_message = $result['message'] ?? 'Unknown error';
            throw new Exception('Plisio API error: ' . $error_message);
        }
        
        return $result['data'] ?? $result;
    }
    
    /**
     * Test API connection
     */
    public function testConnection() {
        try {
            $response = $this->makeRequest('currencies/USD', []);
            if ($response) {
                return ['success' => true, 'message' => 'Connection successful'];
            } else {
                return ['success' => false, 'message' => 'Invalid response from API'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
?>