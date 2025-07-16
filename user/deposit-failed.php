<?php
require_once '../includes/session_config.php';
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$payment_id = $_GET['id'] ?? '';
$site_name = getSetting('site_name', 'Star Router Rent');

// Get payment details if ID provided
$payment = null;
if ($payment_id) {
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND user_id = ?");
    $stmt->execute([$payment_id, $_SESSION['user_id']]);
    $payment = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit Failed - <?php echo htmlspecialchars($site_name); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .error-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            max-width: 500px;
            width: 100%;
            padding: 3rem;
            text-align: center;
        }
        
        .error-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin: 0 auto 2rem;
        }
        
        .error-title {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 1rem;
        }
        
        .error-message {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .payment-details {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .detail-row:last-child {
            margin-bottom: 0;
        }
        
        .btn {
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 0 0.5rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">✗</div>
        <h1 class="error-title">Payment Failed</h1>
        <p class="error-message">
            Unfortunately, your deposit could not be processed. This may be due to insufficient funds, network issues, or payment cancellation. Please try again or contact support if the problem persists.
        </p>
        
        <?php if ($payment): ?>
            <div class="payment-details">
                <div class="detail-row">
                    <span><strong>Amount:</strong></span>
                    <span>$<?php echo number_format($payment['amount'], 2); ?></span>
                </div>
                <div class="detail-row">
                    <span><strong>Currency:</strong></span>
                    <span><?php echo htmlspecialchars($payment['crypto_currency'] ?? 'USDT'); ?></span>
                </div>
                <div class="detail-row">
                    <span><strong>Status:</strong></span>
                    <span><?php echo ucfirst($payment['status']); ?></span>
                </div>
                <div class="detail-row">
                    <span><strong>Date:</strong></span>
                    <span><?php echo date('M j, Y H:i', strtotime($payment['created_at'])); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <div>
            <a href="deposit.php" class="btn">Try Again</a>
            <a href="support.php" class="btn btn-secondary">Contact Support</a>
        </div>
    </div>
</body>
</html>