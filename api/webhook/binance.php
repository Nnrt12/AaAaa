<?php
/**
 * Enhanced Binance Pay Webhook Handler
 * Processes payment notifications from Binance Pay
 */

require_once '../../config.php';
require_once '../../includes/PaymentManager.php';

// Set JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, BinancePay-Signature, BinancePay-Timestamp, BinancePay-Nonce');

// Log all webhook requests
$log_data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'raw_input' => substr(file_get_contents('php://input'), 0, 1000) // Limit log size
];
error_log('Binance Webhook Received: ' . json_encode($log_data));

try {
    // Get headers
    $headers = getallheaders();
    $signature = $headers['BinancePay-Signature'] ?? $headers['binancepay-signature'] ?? '';
    $timestamp = $headers['BinancePay-Timestamp'] ?? $headers['binancepay-timestamp'] ?? '';
    $nonce = $headers['BinancePay-Nonce'] ?? $headers['binancepay-nonce'] ?? '';
    
    // Get raw POST data
    $payload = file_get_contents('php://input');
    
    if (empty($payload)) {
        throw new Exception('No payload received');
    }
    
    // Parse JSON data
    $data = json_decode($payload, true);
    if (!$data) {
        throw new Exception('Invalid JSON payload');
    }
    
    // Validate required fields
    $required_fields = ['merchantTradeNo', 'status'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }
    
    // Initialize payment manager and verify webhook
    $paymentManager = new PaymentManager($pdo);
    
    // Get Binance gateway for signature verification
    $binance_key = getSetting('binance_api_key');
    $binance_secret = getSetting('binance_secret_key');
    
    if ($binance_key && $binance_secret) {
        require_once '../../includes/BinancePaymentGateway.php';
        $binance = new BinancePaymentGateway($binance_key, $binance_secret);
        
        // Verify webhook signature
        if (!$binance->verifyWebhook($payload, $signature, $timestamp, $nonce)) {
            throw new Exception('Invalid webhook signature');
        }
    }
    
    // Process webhook
    $result = $paymentManager->processWebhook('binance', $data);
    
    // Log successful processing
    error_log('Binance webhook processed successfully: ' . json_encode($result));
    
    // Return success response
    echo json_encode(['returnCode' => 'SUCCESS', 'returnMessage' => null]);
    
} catch (Exception $e) {
    // Log error
    error_log("Binance webhook error: " . $e->getMessage());
    error_log("Headers: " . json_encode(getallheaders()));
    error_log("Raw input: " . substr(file_get_contents('php://input'), 0, 500));
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'returnCode' => 'FAIL', 
        'returnMessage' => $e->getMessage()
    ]);
}
?>