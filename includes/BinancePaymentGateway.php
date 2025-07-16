<?php
/**
 * Enhanced Binance Pay Integration
 * Production-ready implementation with comprehensive security and error handling
 */

class BinancePaymentGateway {
    private $api_key;
    private $secret_key;
    private $api_url = 'https://bpay.binanceapi.com';
    private $timeout = 30;
    private $max_retries = 3;
    
    public function __construct($api_key, $secret_key) {
        if (empty($api_key) || empty($secret_key)) {
            throw new InvalidArgumentException('Binance API key and secret key are required');
        }
        $this->api_key = $api_key;
        $this->secret_key = $secret_key;
    }
    
    /**
     * Create payment order
     */
    public function createOrder($params) {
        $this->validateOrderParams($params);
        
        $data = [
            'env' => [
                'terminalType' => 'WEB'
            ],
            'merchantTradeNo' => $params['order_id'],
            'orderAmount' => number_format($params['amount'], 2, '.', ''),
            'currency' => $params['currency'] ?? 'USDT',
            'goods' => [
                'goodsType' => '02',
                'goodsCategory' => 'Z000',
                'referenceGoodsId' => $params['order_id'],
                'goodsName' => $params['description'] ?? 'Payment',
                'goodsDetail' => $params['description'] ?? 'Payment for services'
            ]
        ];
        
        // Add optional URLs
        if (!empty($params['return_url'])) {
            $data['returnUrl'] = $params['return_url'];
        }
        if (!empty($params['cancel_url'])) {
            $data['cancelUrl'] = $params['cancel_url'];
        }
        
        $response = $this->makeRequest('/binancepay/openapi/v2/order', $data);
        
        if (!isset($response['prepayId']) || !isset($response['checkoutUrl'])) {
            throw new Exception('Invalid response from Binance Pay API: missing required fields');
        }
        
        return $response;
    }
    
    /**
     * Query order status
     */
    public function queryOrder($merchant_trade_no) {
        if (empty($merchant_trade_no)) {
            throw new InvalidArgumentException('Merchant trade number is required');
        }
        
        $data = [
            'merchantTradeNo' => $merchant_trade_no
        ];
        
        return $this->makeRequest('/binancepay/openapi/v2/order/query', $data);
    }
    
    /**
     * Create payout (withdrawal)
     */
    public function createPayout($params) {
        $this->validatePayoutParams($params);
        
        $data = [
            'requestId' => $params['request_id'],
            'batchName' => $params['batch_name'] ?? 'Withdrawal Batch',
            'currency' => $params['currency'] ?? 'USDT',
            'totalAmount' => number_format($params['amount'], 2, '.', ''),
            'totalNumber' => 1,
            'bizScene' => 'CRYPTO_BOX',
            'transferDetailList' => [
                [
                    'merchantSendId' => $params['merchant_send_id'],
                    'transferAmount' => number_format($params['amount'], 2, '.', ''),
                    'receiveType' => $params['receive_type'] ?? 'EMAIL',
                    'receiver' => $params['receiver']
                ]
            ]
        ];
        
        return $this->makeRequest('/binancepay/openapi/payout/transfer', $data);
    }
    
    /**
     * Query payout status
     */
    public function queryPayout($request_id) {
        if (empty($request_id)) {
            throw new InvalidArgumentException('Request ID is required');
        }
        
        $data = [
            'requestId' => $request_id
        ];
        
        return $this->makeRequest('/binancepay/openapi/payout/query', $data);
    }
    
    /**
     * Verify webhook signature
     */
    public function verifyWebhook($payload, $signature, $timestamp, $nonce) {
        if (empty($signature) || empty($timestamp) || empty($nonce)) {
            return false;
        }
        
        $string_to_sign = $timestamp . "\n" . $nonce . "\n" . $payload . "\n";
        $expected_signature = strtoupper(hash_hmac('sha512', $string_to_sign, $this->secret_key));
        
        return hash_equals($expected_signature, strtoupper($signature));
    }
    
    /**
     * Get supported currencies
     */
    public function getSupportedCurrencies() {
        return $this->makeRequest('/binancepay/openapi/v2/currencies', []);
    }
    
    /**
     * Validate order parameters
     */
    private function validateOrderParams($params) {
        $required = ['amount', 'order_id'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw new InvalidArgumentException("Missing required parameter: {$field}");
            }
        }
        
        if (!is_numeric($params['amount']) || $params['amount'] <= 0) {
            throw new InvalidArgumentException('Amount must be a positive number');
        }
        
        if (strlen($params['order_id']) > 32) {
            throw new InvalidArgumentException('Order ID is too long (max 32 characters)');
        }
    }
    
    /**
     * Validate payout parameters
     */
    private function validatePayoutParams($params) {
        $required = ['request_id', 'amount', 'receiver', 'merchant_send_id'];
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
     * Make authenticated API request
     */
    private function makeRequest($endpoint, $data, $retry_count = 0) {
        $timestamp = round(microtime(true) * 1000);
        $nonce = uniqid();
        $payload = json_encode($data);
        
        $string_to_sign = $timestamp . "\n" . $nonce . "\n" . $payload . "\n";
        $signature = strtoupper(hash_hmac('sha512', $string_to_sign, $this->secret_key));
        
        $headers = [
            'Content-Type: application/json',
            'BinancePay-Timestamp: ' . $timestamp,
            'BinancePay-Nonce: ' . $nonce,
            'BinancePay-Certificate-SN: ' . $this->api_key,
            'BinancePay-Signature: ' . $signature,
            'User-Agent: StarRouterRent/2.0'
        ];
        
        $url = $this->api_url . $endpoint;
        
        // Log request (without sensitive data)
        error_log("Binance Pay API Request: {$url}");
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // Handle cURL errors with retry
        if ($curl_error) {
            if ($retry_count < $this->max_retries) {
                sleep(pow(2, $retry_count));
                return $this->makeRequest($endpoint, $data, $retry_count + 1);
            }
            throw new Exception('Network error: ' . $curl_error);
        }
        
        // Log response
        error_log("Binance Pay API Response: HTTP {$http_code}");
        
        // Handle HTTP errors with retry for temporary failures
        if (($http_code >= 500 || $http_code == 451) && $retry_count < $this->max_retries) {
            sleep(pow(2, $retry_count));
            return $this->makeRequest($endpoint, $data, $retry_count + 1);
        }
        
        if ($http_code !== 200) {
            $error_messages = [
                400 => 'Bad request - check your parameters',
                401 => 'Unauthorized - invalid API credentials',
                403 => 'Forbidden - insufficient permissions',
                404 => 'Not found - invalid endpoint',
                451 => 'Service unavailable in your region',
                429 => 'Too many requests - rate limit exceeded',
                500 => 'Internal server error',
                502 => 'Bad gateway',
                503 => 'Service unavailable',
                504 => 'Gateway timeout'
            ];
            
            $error_message = $error_messages[$http_code] ?? "HTTP error {$http_code}";
            throw new Exception($error_message);
        }
        
        $result = json_decode($response, true);
        
        if (!$result) {
            throw new Exception('Invalid JSON response from Binance Pay API');
        }
        
        if (!isset($result['status']) || $result['status'] !== 'SUCCESS') {
            $error_message = $result['errorMessage'] ?? 'Unknown error';
            throw new Exception('Binance Pay API error: ' . $error_message);
        }
        
        return $result['data'] ?? $result;
    }
    
    /**
     * Test API connection
     */
    public function testConnection() {
        try {
            // Try a simple API call to test connection
            $data = ['env' => ['terminalType' => 'WEB']];
            $response = $this->makeRequest('/binancepay/openapi/v2/currencies', $data);
            if ($response) {
                return ['success' => true, 'message' => 'Connection successful'];
            } else {
                return ['success' => false, 'message' => 'Invalid response from API'];
            }
        } catch (Exception $e) {
            // Handle specific error cases
            $message = $e->getMessage();
            if (strpos($message, '451') !== false) {
                $message = 'Binance Pay is not available in your region';
            } elseif (strpos($message, 'authentication') !== false) {
                $message = 'Invalid API credentials - check your certificate SN and private key';
            }
            return ['success' => false, 'message' => $message];
        }
    }
}
?>