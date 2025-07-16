<?php
/**
 * Enhanced Payment Manager
 * Handles all payment operations including Plisio integration
 */

class PaymentManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Process webhook from payment gateways
     */
    public function processWebhook($gateway, $data) {
        try {
            switch ($gateway) {
                case 'plisio':
                    return $this->processPlisioWebhook($data);
                default:
                    throw new Exception('Unknown gateway: ' . $gateway);
            }
        } catch (Exception $e) {
            error_log("Webhook processing error ({$gateway}): " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Process Plisio webhook
     */
    private function processPlisioWebhook($data) {
        // Validate required fields
        $required_fields = ['txn_id', 'status', 'order_number'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        $txn_id = $data['txn_id'];
        $status = $data['status'];
        $order_number = $data['order_number'];
        $amount = floatval($data['source_amount'] ?? 0);
        
        // Find payment record
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE id = ? OR transaction_id = ?");
        $stmt->execute([$order_number, $txn_id]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            throw new Exception("Payment not found for order: {$order_number}");
        }
        
        // Update payment status based on Plisio status
        $new_status = $this->mapPlisioStatus($status);
        
        if ($payment['status'] !== $new_status) {
            $this->pdo->beginTransaction();
            
            try {
                // Update payment record
                $stmt = $this->pdo->prepare("
                    UPDATE payments 
                    SET status = ?, transaction_id = ?, gateway_data = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $new_status,
                    $txn_id,
                    json_encode($data),
                    $payment['id']
                ]);
                
                // If payment is completed, credit user balance
                if ($new_status === 'completed' && $payment['type'] === 'deposit') {
                    $this->creditUserBalance($payment['user_id'], $amount);
                    
                    // Send notification
                    $this->sendPaymentNotification($payment['user_id'], 'deposit_completed', $amount);
                }
                
                $this->pdo->commit();
                
                return [
                    'success' => true,
                    'payment_id' => $payment['id'],
                    'status' => $new_status
                ];
                
            } catch (Exception $e) {
                $this->pdo->rollback();
                throw $e;
            }
        }
        
        return [
            'success' => true,
            'payment_id' => $payment['id'],
            'status' => $payment['status'],
            'message' => 'No status change required'
        ];
    }
    
    /**
     * Map Plisio status to internal status
     */
    private function mapPlisioStatus($plisio_status) {
        $status_map = [
            'new' => 'pending',
            'pending' => 'pending',
            'expired' => 'failed',
            'completed' => 'completed',
            'error' => 'failed',
            'cancelled' => 'failed'
        ];
        
        return $status_map[$plisio_status] ?? 'pending';
    }
    
    /**
     * Credit user balance
     */
    private function creditUserBalance($user_id, $amount) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET balance = balance + ?, total_deposited = total_deposited + ? 
            WHERE id = ?
        ");
        $stmt->execute([$amount, $amount, $user_id]);
        
        // Log activity
        logActivity($user_id, 'deposit_completed', "Deposit of $" . number_format($amount, 2) . " completed");
    }
    
    /**
     * Send payment notification
     */
    private function sendPaymentNotification($user_id, $type, $amount) {
        try {
            require_once __DIR__ . '/NotificationSystem.php';
            $notifications = new NotificationSystem($this->pdo);
            
            $messages = [
                'deposit_completed' => [
                    'title' => 'Deposit Completed',
                    'message' => 'Your deposit of $' . number_format($amount, 2) . ' has been completed successfully!'
                ]
            ];
            
            if (isset($messages[$type])) {
                $notifications->createNotification(
                    $user_id,
                    $messages[$type]['title'],
                    $messages[$type]['message'],
                    'success',
                    true // Send email
                );
            }
        } catch (Exception $e) {
            error_log("Failed to send payment notification: " . $e->getMessage());
        }
    }
    
    /**
     * Get currency manager instance
     */
    public function getCurrencyManager() {
        require_once __DIR__ . '/CurrencyManager.php';
        return new CurrencyManager($this->pdo);
    }
    
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

// Helper functions for backward compatibility
function createPlisioPayment($amount, $currency, $order_id, $callback_url) {
    global $pdo;
    $paymentManager = new PaymentManager($pdo);
    return $paymentManager->createPlisioPayment($amount, $currency, $order_id, $callback_url);
}
?>