@@ .. @@
     /**
      * Create Plisio payment
      */
     public function createPlisioPayment($amount, $currency, $order_id, $callback_url) {
         $api_key = getSetting('plisio_api_key');
         if (!$api_key) {
             throw new Exception('Plisio API key not configured');
         }
         
         $params = [
             'source_currency' => 'USD',
             'source_amount' => $amount,
             'order_number' => $order_id,
             'currency' => $currency,
             'callback_url' => $callback_url,
             'api_key' => $api_key
         ];
         
         $response = $this->makeRequest('https://plisio.net/api/v1/invoices/new', $params);
         
         if ($response && $response['status'] === 'success') {
             return $response['data'];
         }
         
         throw new Exception('Failed to create Plisio payment: ' . ($response['data']['message'] ?? 'Unknown error'));
     }
     
-    /**
-     * Create Binance Pay order
-     */
-    public function createBinancePayOrder($amount, $merchant_trade_no) {
-        $api_key = getSetting('binance_api_key');
-        $secret_key = getSetting('binance_secret_key');
-        
-        if (!$api_key || !$secret_key) {
-            throw new Exception('Binance Pay credentials not configured');
-        }
-        
-        $timestamp = time() * 1000;
-        $nonce = $this->generateNonce();
-        
-        $request_body = [
-            'env' => [
-                'terminalType' => 'WEB'
-            ],
-            'merchantTradeNo' => $merchant_trade_no,
-            'orderAmount' => $amount,
-            'currency' => 'USDT',
-            'goods' => [
-                'goodsType' => '02',
-                'goodsCategory' => 'Z000',
-                'referenceGoodsId' => 'deposit_' . $merchant_trade_no,
-                'goodsName' => 'Star Router Rent Deposit',
-                'goodsDetail' => 'Deposit to Star Router Rent account'
-            ]
-        ];
-        
-        $payload = json_encode($request_body);
-        $signature = $this->generateBinanceSignature($timestamp, $nonce, $payload, $secret_key);
-        
-        $headers = [
-            'Content-Type: application/json',
-            'BinancePay-Timestamp: ' . $timestamp,
-            'BinancePay-Nonce: ' . $nonce,
-            'BinancePay-Certificate-SN: ' . $api_key,
-            'BinancePay-Signature: ' . $signature
-        ];
-        
-        $ch = curl_init();
-        curl_setopt($ch, CURLOPT_URL, 'https://bpay.binanceapi.com/binancepay/openapi/v2/order');
-        curl_setopt($ch, CURLOPT_POST, true);
-        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
-        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
-        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
-        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
-        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
-        
-        $response = curl_exec($ch);
-        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
-        curl_close($ch);
-        
-        if ($http_code === 200) {
-            $data = json_decode($response, true);
-            if ($data && $data['status'] === 'SUCCESS') {
-                return $data;
-            }
-        }
-        
-        throw new Exception('Binance Pay API error: ' . $response);
-    }
-    
-    /**
-     * Generate Binance Pay signature
-     */
-    private function generateBinanceSignature($timestamp, $nonce, $body, $secret_key) {
-        $payload = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
-        return strtoupper(hash_hmac('sha512', $payload, $secret_key));
-    }
-    
-    /**
-     * Generate random nonce
-     */
-    private function generateNonce($length = 32) {
-        return bin2hex(random_bytes($length / 2));
-    }
-    
     /**
      * Test all payment gateways
      */
     public function testAllGateways() {
         $results = [];
         
         // Test Plisio
         try {
             $api_key = getSetting('plisio_api_key');
             if ($api_key) {
                 $response = $this->makeRequest('https://plisio.net/api/v1/currencies', ['api_key' => $api_key]);
                 $results['plisio'] = [
                     'success' => $response && $response['status'] === 'success',
                     'message' => $response ? 'Connected successfully' : 'Connection failed'
                 ];
             } else {
                 $results['plisio'] = [
                     'success' => false,
                     'message' => 'API key not configured'
                 ];
             }
         } catch (Exception $e) {
             $results['plisio'] = [
                 'success' => false,
                 'message' => $e->getMessage()
             ];
         }
         
-        // Test Binance Pay
-        try {
-            $api_key = getSetting('binance_api_key');
-            $secret_key = getSetting('binance_secret_key');
-            
-            if ($api_key && $secret_key) {
-                // Simple test - try to generate signature
-                $timestamp = time() * 1000;
-                $nonce = $this->generateNonce();
-                $signature = $this->generateBinanceSignature($timestamp, $nonce, '{}', $secret_key);
-                
-                $results['binance'] = [
-                    'success' => !empty($signature),
-                    'message' => 'Credentials configured'
-                ];
-            } else {
-                $results['binance'] = [
-                    'success' => false,
-                    'message' => 'API credentials not configured'
-                ];
-            }
-        } catch (Exception $e) {
-            $results['binance'] = [
-                'success' => false,
-                'message' => $e->getMessage()
-            ];
-        }
-        
         return $results;
     }
     
     /**
      * Make HTTP request
      */
     private function makeRequest($url, $params = []) {
         $ch = curl_init();
         
         if (!empty($params)) {
             $url .= '?' . http_build_query($params);
         }
         
         curl_setopt($ch, CURLOPT_URL, $url);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
         curl_setopt($ch, CURLOPT_TIMEOUT, 30);
         curl_setopt($ch, CURLOPT_USERAGENT, 'Star Router Rent/1.0');
         
         $response = curl_exec($ch);
         $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
         curl_close($ch);
         
         if ($http_code === 200) {
             return json_decode($response, true);
         }
         
         return false;
     }
 }
 
-// Helper functions for backward compatibility
-function createBinancePayOrder($amount, $merchant_trade_no) {
-    global $pdo;
-    $paymentManager = new PaymentManager($pdo);
-    return $paymentManager->createBinancePayOrder($amount, $merchant_trade_no);
-}
-
 function createPlisioPayment($amount, $currency, $order_id, $callback_url) {
     global $pdo;
     $paymentManager = new PaymentManager($pdo);
     return $paymentManager->createPlisioPayment($amount, $currency, $order_id, $callback_url);
 }
 ?>