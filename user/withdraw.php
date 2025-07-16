<?php
require_once '../includes/session_config.php';
require_once '../config.php';
require_once '../includes/PaymentManager.php';

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
$site_name = getSetting('site_name', 'Star Router Rent');
$min_withdrawal = getSetting('min_withdrawal', 20);
$withdrawal_fee = getSetting('withdrawal_fee', 2.5);

$error = '';
$success = '';

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_withdrawal'])) {
    $amount = floatval($_POST['amount']);
    $method = $_POST['withdrawal_method'];
    $address = trim($_POST['withdrawal_address']);
    
    $fee_amount = ($amount * $withdrawal_fee) / 100;
    $net_amount = $amount - $fee_amount;
    
    if (empty($address)) {
        $error = "Withdrawal address is required.";
    } elseif ($amount < $min_withdrawal) {
        $error = "Minimum withdrawal amount is $" . number_format($min_withdrawal, 2);
    } elseif ($amount > $user['balance']) {
        $error = "Insufficient balance. Available: $" . number_format($user['balance'], 2);
    } else {
        try {
            $pdo->beginTransaction();
            
            // Ensure withdrawal method is valid
            $valid_method = in_array($method, ['binance', 'plisio']) ? $method : 'crypto';
            
            // Deduct from user balance
            $stmt = $pdo->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            $stmt->execute([$amount, $user_id]);
            
            // Create withdrawal request
            $stmt = $pdo->prepare("
                INSERT INTO withdrawal_requests (user_id, amount, fee_amount, net_amount, withdrawal_method, withdrawal_address, status, requested_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$user_id, $amount, $fee_amount, $net_amount, $valid_method, $address]);
            
            $withdrawal_id = $pdo->lastInsertId();
            
            // Record transaction
            $stmt = $pdo->prepare("
                INSERT INTO payments (user_id, amount, payment_method, status, type, description, created_at) 
                VALUES (?, ?, ?, 'pending', 'withdrawal', ?, NOW())
            ");
            $stmt->execute([
                $user_id, 
                $amount, 
                $valid_method,
                "Withdrawal request to " . substr($address, 0, 10) . "..."
            ]);
            
            // Log activity
            logActivity(
                $user_id,
                'withdrawal_requested',
                "Withdrawal request for $" . number_format($amount, 2) . " (net: $" . number_format($net_amount, 2) . ")"
            );
            
            $pdo->commit();
            
            $success = "Withdrawal request submitted successfully! Request ID: #" . $withdrawal_id;
            
            // Refresh user data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
        } catch (Exception $e) {
            $pdo->rollback();
            $error = "Failed to create withdrawal request. Please try again.";
            error_log("Withdrawal creation error: " . $e->getMessage());
        }
    }
}

// Get recent withdrawals
$stmt = $pdo->prepare("
    SELECT * FROM withdrawal_requests 
    WHERE user_id = ? 
    ORDER BY requested_at DESC 
    LIMIT 10
");
$stmt->execute([$user_id]);
$recent_withdrawals = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw - <?php echo htmlspecialchars($site_name); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #333;
        }
        
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 0 2rem;
            margin-bottom: 2rem;
        }
        
        .sidebar-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .sidebar-nav {
            list-style: none;
        }
        
        .sidebar-nav li {
            margin-bottom: 0.5rem;
        }
        
        .sidebar-nav a {
            display: block;
            padding: 1rem 2rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255, 255, 255, 0.1);
            border-right: 4px solid white;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }
        
        .header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .withdrawal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .card-header {
            background: #f8f9fa;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .card-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fafbfc;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            font-weight: 500;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #51cf66, #40c057);
            color: white;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
        }
        
        .balance-info {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .balance-display {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .fee-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .calculation {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .calc-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .calc-row.total {
            border-top: 1px solid #dee2e6;
            padding-top: 0.5rem;
            font-weight: 600;
        }
        
        .withdrawal-item {
            padding: 1rem 0;
            border-bottom: 1px solid #f1f3f4;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .withdrawal-item:last-child {
            border-bottom: none;
        }
        
        .withdrawal-info h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .withdrawal-info p {
            font-size: 0.8rem;
            color: #666;
        }
        
        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status.pending { background: #fff3cd; color: #856404; }
        .status.approved { background: #d1ecf1; color: #0c5460; }
        .status.completed { background: #d4edda; color: #155724; }
        .status.rejected { background: #f8d7da; color: #721c24; }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .withdrawal-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>💫 <?php echo htmlspecialchars($site_name); ?></h2>
        </div>
        <ul class="sidebar-nav">
            <li><a href="dashboard.php">📊 Dashboard</a></li>
            <li><a href="investments.php">💰 Investments</a></li>
            <li><a href="devices.php">🖥️ Devices</a></li>
            <li><a href="transactions.php">💳 Transactions</a></li>
            <li><a href="referrals.php">👥 Referrals</a></li>
            <li><a href="profile.php">👤 Profile</a></li>
            <li><a href="support.php">🎧 Support</a></li>
            <li><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>Withdraw Funds</h1>
            <p style="color: #666; margin-top: 0.5rem;">Request withdrawal to your cryptocurrency wallet</p>
        </div>
        
        <div class="balance-info">
            <div class="balance-display">$<?php echo number_format($user['balance'], 2); ?></div>
            <div>Available Balance</div>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="withdrawal-grid">
            <div class="card">
                <div class="card-header">
                    <h3>Create Withdrawal</h3>
                </div>
                <div class="card-body">
                    <div class="fee-info">
                        <strong>Withdrawal Fee:</strong> <?php echo $withdrawal_fee; ?>% | 
                        <strong>Minimum:</strong> $<?php echo number_format($min_withdrawal, 2); ?>
                    </div>
                    
                    <form method="POST" id="withdrawalForm">
                        <div class="form-group">
                            <label>Withdrawal Method</label>
                            <select name="withdrawal_method" required>
                                <option value="">Select Method</option>
                                <option value="plisio">Plisio (USDT, BTC, ETH, LTC)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Select Cryptocurrency</label>
                            <select name="withdrawal_currency" required>
                                <option value="">Select Currency</option>
                                <?php 
                                require_once '../includes/PaymentManager.php';
                                $paymentManager = new PaymentManager($pdo);
                                $currencies = $paymentManager->getCurrencyManager()->getActiveCurrencies();
                                foreach ($currencies as $currency): 
                                ?>
                                    <option value="<?php echo $currency['code']; ?>">
                                        <?php echo $currency['icon']; ?> <?php echo $currency['display_name'] ?? $currency['name']; ?> (<?php echo $currency['network'] ?? $currency['code']; ?>) - 
                                        $<?php echo number_format($paymentManager->getCurrencyManager()->getExchangeRate($currency['code']), 2); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Cryptocurrency Wallet Address</label>
                            <input type="text" name="withdrawal_address" placeholder="Enter your cryptocurrency wallet address" required>
                            <small style="color: #666; font-size: 0.8rem;">
                                Make sure the address matches the selected cryptocurrency
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label>Withdrawal Amount (USD)</label>
                            <input type="number" name="amount" id="withdrawalAmount" placeholder="Enter amount" 
                                   min="<?php echo $min_withdrawal; ?>" 
                                   max="<?php echo $user['balance']; ?>" 
                                   step="0.01" required>
                        </div>
                        
                        <div class="calculation" id="calculationBox" style="display: none;">
                            <div class="calc-row">
                                <span>Withdrawal Amount:</span>
                                <span id="calcAmount">$0.00</span>
                            </div>
                            <div class="calc-row">
                                <span>Fee (<?php echo $withdrawal_fee; ?>%):</span>
                                <span id="calcFee">$0.00</span>
                            </div>
                            <div class="calc-row total">
                                <span>You will receive:</span>
                                <span id="calcNet">$0.00</span>
                            </div>
                        </div>
                        
                        <button type="submit" name="create_withdrawal" class="btn">Request Withdrawal</button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Recent Withdrawals</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_withdrawals)): ?>
                        <p style="text-align: center; color: #666; padding: 2rem;">No withdrawals yet</p>
                    <?php else: ?>
                        <?php foreach ($recent_withdrawals as $withdrawal): ?>
                            <div class="withdrawal-item">
                                <div class="withdrawal-info">
                                    <h4>$<?php echo number_format($withdrawal['amount'], 2); ?> (Net: $<?php echo number_format($withdrawal['net_amount'], 2); ?>)</h4>
                                    <p><?php echo date('M j, Y H:i', strtotime($withdrawal['requested_at'])); ?> | <?php echo ucfirst($withdrawal['withdrawal_method']); ?></p>
                                </div>
                                <div>
                                    <div class="status <?php echo $withdrawal['status']; ?>">
                                        <?php echo ucfirst($withdrawal['status']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const withdrawalAmount = document.getElementById('withdrawalAmount');
        const calculationBox = document.getElementById('calculationBox');
        const calcAmount = document.getElementById('calcAmount');
        const calcFee = document.getElementById('calcFee');
        const calcNet = document.getElementById('calcNet');
        const feePercent = <?php echo $withdrawal_fee; ?>;
        
        withdrawalAmount.addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            
            if (amount > 0) {
                const fee = (amount * feePercent) / 100;
                const net = amount - fee;
                
                calcAmount.textContent = '$' + amount.toFixed(2);
                calcFee.textContent = '$' + fee.toFixed(2);
                calcNet.textContent = '$' + net.toFixed(2);
                
                calculationBox.style.display = 'block';
            } else {
                calculationBox.style.display = 'none';
            }
        });
    </script>
</body>
</html>