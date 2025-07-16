<?php
/**
 * Binance Pay Integration
 * Official API documentation: https://developers.binance.com/docs/binance-pay
 */

class BinancePayment {
    private $api_key;
    private $secret_key;
    private $api_url = 'https://bpay.binanceapi.com';
    
    public function __construct($api_key, $secret_key) {
        $this->api_key = $api_key;
        $this->secret_key = $secret_key;
    }
    
    /**
     * Create payment order
     */
    public function createOrder($params) {
        $data = [
            'env' => [
                'terminalType' => 'WEB'
            ],
            'merchantTradeNo' => $params['order_id'],
            'orderAmount' => $params['amount'],
            'currency' => 'USDT',
            'goods' => [
                'goodsType' => '02',
                'goodsCategory' => 'Z000',
                'referenceGoodsId' => $params['order_id'],
                'goodsName' => $params['description'],
                'goodsDetail' => $params['description']
            ],
            'returnUrl' => $params['return_url'],
            'cancelUrl' => $params['cancel_url']
        ];
        
        return $this->makeRequest('/binancepay/openapi/v2/order', $data);
    }
    
    /**
     * Query order status
     */
    public function queryOrder($merchant_trade_no) {
        $data = [
            'merchantTradeNo' => $merchant_trade_no
        ];
        
        return $this->makeRequest('/binancepay/openapi/v2/order/query', $data);
    }
    
    /**
     * Create payout (withdrawal)
     */
    public function createPayout($params) {
        $data = [
            'requestId' => $params['request_id'],
            'batchName' => $params['batch_name'],
            'currency' => $params['currency'],
            'totalAmount' => $params['amount'],
            'totalNumber' => 1,
            'bizScene' => 'CRYPTO_BOX',
            'transferDetailList' => [
                [
                    'merchantSendId' => $params['merchant_send_id'],
                    'transferAmount' => $params['amount'],
                    'receiveType' => 'EMAIL',
                    'receiver' => $params['receiver_email']
                ]
            ]
        ];
        
        return $this->makeRequest('/binancepay/openapi/payout/transfer', $data);
    }
    
    /**
     * Verify webhook signature
     */
    public function verifyWebhook($payload, $signature, $timestamp, $nonce) {
        $string_to_sign = $timestamp . "\n" . $nonce . "\n" . $payload . "\n";
        $expected_signature = strtoupper(hash_hmac('sha512', $string_to_sign, $this->secret_key));
        
        return hash_equals($expected_signature, strtoupper($signature));
    }
    
    /**
     * Make authenticated API request
     */
    private function makeRequest($endpoint, $data) {
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
            'BinancePay-Signature: ' . $signature
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url . $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception('Binance Pay API request failed with HTTP code: ' . $http_code);
        }
        
        $result = json_decode($response, true);
        
        if (!$result || $result['status'] !== 'SUCCESS') {
            throw new Exception('Binance Pay API error: ' . ($result['errorMessage'] ?? 'Unknown error'));
        }
        
        return $result['data'];
    }
}