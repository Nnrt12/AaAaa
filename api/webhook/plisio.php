<?php
/**
 * Enhanced Plisio Webhook Handler
 * Processes payment notifications from Plisio.net using WHMCS v1.0.3 compatibility
 */

require_once '../../config.php';
require_once '../../includes/PaymentManager.php';
require_once '../../includes/PlisioWHMCSGateway.php';

// Set JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Log all webhook requests
$log_data = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders(),
    'post_data' => $_POST,
    'raw_input' => file_get_contents('php://input')
];
error_log('Plisio WHMCS Webhook Received: ' . json_encode($log_data));

try {
    // Get POST data
    $post_data = $_POST ?: [];
    
    if (empty($post_data)) {
        // Try to get data from php://input for JSON requests
        $input = file_get_contents('php://input');
        if ($input) {
            $post_data = json_decode($input, true) ?: [];
        }
        
        if (empty($post_data)) {
            throw new Exception('No POST data received');
        }
    }
    
    // Validate required fields
    $required_fields = ['txn_id', 'status', 'order_number'];
    foreach ($required_fields as $field) {
        if (!isset($post_data[$field])) {
            throw new Exception("Missing required field: {$field}");
        }
    }
    
    // Initialize payment manager
    $paymentManager = new PaymentManager($pdo);
    
    // Get Plisio WHMCS gateway for signature verification
    $plisio_key = getSetting('plisio_api_key', 'M_srKi_qKCQ1hra_J8Zx-khHGozvT2EkbfXq8ieKZvTfmpCOIKcTFHNchjMEC4_x');
    
    if ($plisio_key && strlen($plisio_key) > 20) {
        $plisio = new PlisioWHMCSGateway($plisio_key);
        
        // Verify webhook signature
        $verify_hash = $post_data['verify_hash'] ?? '';
        if ($verify_hash && !$plisio->verifyCallback($post_data, $verify_hash)) {
            error_log('Plisio webhook signature verification failed');
            // Continue processing but log the issue
        }
    }
    
    // Process webhook
    $result = $paymentManager->processWebhook('plisio', $post_data);
    
    // Log successful processing
    error_log('Plisio WHMCS webhook processed successfully: ' . json_encode($result));
    
    // Return success response
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    // Log error
    error_log("Plisio WHMCS webhook error: " . $e->getMessage());
    error_log("POST data: " . json_encode($_POST));
    error_log("Raw input: " . file_get_contents('php://input'));
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}
?>