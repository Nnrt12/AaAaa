<?php
require_once '../includes/session_config.php';
require_once '../config.php';
require_once '../includes/PaymentManager.php';
require_once '../includes/PlisioWHMCSGateway.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get system settings
$min_deposit = getSetting('min_deposit', 100);
$max_deposit = getSetting('max_deposit', 50000);
$plisio_api_key = getSetting('plisio_api_key');
$site_name = getSetting('site_name', 'Star Router Rent');
$site_url = getSetting('site_url', 'https://star-rent.vip');

$error = '';
$success = '';

// Handle crypto deposit creation
if (isset($_POST['create_crypto_deposit'])) {
    $amount = floatval($_POST['amount']);
    $crypto_currency = 'BTC'; // Fixed to BTC only
    
    if ($amount < $min_deposit) {
        $error = "Minimum deposit amount is $" . number_format($min_deposit, 2);
    } elseif ($amount > $max_deposit) {
        $error = "Maximum deposit amount is $" . number_format($max_deposit, 2);
    } else {
        try {
            if (empty($plisio_api_key)) {
                throw new Exception('Payment gateway not configured. Please contact support.');
            }
            
            $pdo->beginTransaction();
            
            // Create payment record
            $payment_id = generateSecureToken(16);
            $stmt = $pdo->prepare("
                INSERT INTO payments (id, user_id, amount, crypto_currency, payment_method, status, type, description, created_at) 
                VALUES (?, ?, ?, ?, 'plisio', 'pending', 'deposit', 'Cryptocurrency deposit', NOW())
            ");
            $stmt->execute([$payment_id, $user_id, $amount, $crypto_currency]);
            
            // Create Plisio payment using WHMCS gateway
            $plisio_gateway = new PlisioWHMCSGateway($plisio_api_key);
            
            $payment_params = [
                'amount' => $amount,
                'currency' => $crypto_currency,
                'order_id' => $payment_id,
                'description' => 'Deposit to ' . $site_name,
                'email' => $user['email'],
                'callback_url' => $site_url . '/api/webhook/plisio.php',
                'success_callback_url' => $site_url . '/user/deposit-success.php?id=' . $payment_id,
                'fail_callback_url' => $site_url . '/user/deposit-failed.php?id=' . $payment_id
            ];
            
            $plisio_payment = $plisio_gateway->createInvoice($payment_params);
            
            if ($plisio_payment && isset($plisio_payment['invoice_url'])) {
                // Update payment with Plisio data
                $stmt = $pdo->prepare("
                    UPDATE payments 
                    SET transaction_id = ?, gateway_data = ? 
                    WHERE id = ?
                ");
                $stmt->execute([
                    $plisio_payment['txn_id'],
                    json_encode($plisio_payment),
                    $payment_id
                ]);
                
                $pdo->commit();
                
                // Redirect to Plisio payment page
                header('Location: ' . $plisio_payment['invoice_url']);
                exit;
                
            } else {
                throw new Exception('Failed to create payment invoice');
            }
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error = "Failed to create deposit: " . $e->getMessage();
            error_log("Deposit creation error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit - <?php echo htmlspecialchars($site_name); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .content {
            padding: 2rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .alert.error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert.success {
            background: #efe;
            color: #363;
            border: 1px solid #cfc;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .payment-method {
            padding: 1.5rem;
            border: 2px solid #e1e5e9;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .payment-method:hover {
            border-color: #3498db;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.15);
        }
        
        .payment-method.selected {
            border-color: #3498db;
            background: linear-gradient(135deg, #e3f2fd, #f8f9fa);
        }
        
        .crypto-note {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        
        .crypto-note p {
            margin: 0;
            color: #1976d2;
            font-size: 14px;
        }
        
        .method-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .method-info h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .method-info p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .crypto-options {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .crypto-option {
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .crypto-option:hover,
        .crypto-option.selected {
            border-color: #3498db;
            background: #f8f9fa;
        }
        
        .crypto-option img {
            width: 32px;
            height: 32px;
        }
        
        .crypto-option span {
            font-weight: 600;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            width: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
        }
        
        .payment-form {
            display: none;
        }
        
        .payment-form.active {
            display: block;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 2rem;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: #2980b9;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 1rem;
                border-radius: 15px;
            }
            
            .header {
                padding: 1.5rem;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .content {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-wallet"></i> Make a Deposit</h1>
            <p>Add funds to your account securely</p>
        </div>
        
        <div class="content">
            <a href="dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            
            <?php if ($error): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Select Payment Method</label>
                    <div class="payment-methods">
                        <div class="payment-method" data-method="crypto">
                            <div class="method-icon">₿</div>
                            <div class="method-info">
                                <h3>Cryptocurrency</h3>
                                <p>Bitcoin (BTC)</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="crypto-note">
                        <p><strong>Note:</strong> Please select BTC, on the next step You can change to other Currency's</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Deposit Amount ($)</label>
                        <input type="number" id="amount" name="amount" min="<?php echo $min_deposit; ?>" step="0.01" required>
                        <small>Minimum: $<?php echo number_format($min_deposit, 2); ?> | Maximum: $<?php echo number_format($max_deposit, 2); ?></small>
                    </div>
                </div>
                
                <!-- Crypto Payment Form -->
                <div id="crypto-form" class="payment-form">
                    <h3>Cryptocurrency Deposit</h3>
                    
                    <div class="crypto-options">
                        <div class="crypto-option selected" data-crypto="BTC">
                            <img src="https://cryptoicons.org/api/icon/btc/32" alt="Bitcoin">
                            <span>Bitcoin (BTC)</span>
                        </div>
                    </div>
                    
                    <div class="crypto-note">
                        <p><strong>Note:</strong> Please select BTC, on the next step You can change to other Currency's</p>
                    </div>
                    
                    <input type="hidden" id="selected-crypto" name="crypto_currency" value="BTC">
                    
                    <button type="submit" name="create_crypto_deposit" class="btn btn-primary">
                        Continue to Payment
                    </button>
                </div>
                
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentMethods = document.querySelectorAll('.payment-method');
            const paymentForms = document.querySelectorAll('.payment-form');
            
            // Payment method selection
            paymentMethods.forEach(method => {
                method.addEventListener('click', function() {
                    const selectedMethod = this.dataset.method;
                    
                    // Update UI
                    paymentMethods.forEach(m => m.classList.remove('selected'));
                    this.classList.add('selected');
                    
                    // Show corresponding form
                    paymentForms.forEach(form => {
                        form.style.display = form.id === selectedMethod + '-form' ? 'block' : 'none';
                    });
                });
            });
        });
    </script>
</body>
</html>