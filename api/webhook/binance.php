<?php
/**
 * Binance Pay Webhook Handler - DEPRECATED
 * This file is kept for compatibility but Binance Pay has been removed
 */

// Return 404 for any Binance webhook requests
http_response_code(404);
echo json_encode([
    'error' => 'Binance Pay integration has been removed',
    'status' => 'not_found'
]);
?>