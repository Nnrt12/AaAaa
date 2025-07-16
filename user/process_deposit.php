                'success_callback_url' => $site_url . '/user/deposit-success.php?id=' . $payment_id,
                'fail_callback_url' => $site_url . '/user/deposit-failed.php?id=' . $payment_id
            ];
            
            $plisio_payment = $plisio_gateway->createInvoice($payment_params);
@@ .. @@
 // Handle crypto deposit creation
 if (isset($_POST['create_crypto_deposit'])) {
     $amount = floatval($_POST['amount']);
-    $crypto_currency = $_POST['crypto_currency'] ?? 'BTC';
+    $crypto_currency = 'BTC'; // Fixed to BTC only
     
require_once '../includes/PaymentManager.php';
require_once '../includes/PlisioWHMCSGateway.php';
     if ($amount < $min_deposit) {
         $error = "Minimum deposit amount is $" . number_format($min_deposit, 2);
@@ .. @@
         }
     }
 }
-
-// Handle Binance Pay deposit creation
-if (isset($_POST['create_binance_deposit'])) {
-    $amount = floatval($_POST['amount']);
-    
-    if ($amount < $min_deposit) {
-        $error = "Minimum deposit amount is $" . number_format($min_deposit, 2);
-    } elseif ($amount > $max_deposit) {
-        $error = "Maximum deposit amount is $" . number_format($max_deposit, 2);
// Get system settings
-    } else {
$min_deposit = getSetting('min_deposit', 100);
            error_log("Deposit creation error: " . $e->getMessage());
-        try {
$max_deposit = getSetting('max_deposit', 50000);
-            $pdo->beginTransaction();
$plisio_api_key = getSetting('plisio_api_key');
-            
$site_name = getSetting('site_name', 'Star Router Rent');
-            // Create payment record
$site_url = getSetting('site_url', 'https://star-rent.vip');
-            $payment_id = generateSecureToken(16);

-            $stmt = $pdo->prepare("
-                INSERT INTO payments (id, user_id, amount, payment_method, status, type, description, created_at) 
-                VALUES (?, ?, ?, 'binance', 'pending', 'deposit', 'Binance Pay deposit', NOW())
-            ");
-            $stmt->execute([$payment_id, $user_id, $amount]);
-            
-            // Create Binance Pay order
-            $binance_order = createBinancePayOrder($amount, $payment_id);
-            
-            if ($binance_order && isset($binance_order['data']['prepayId'])) {
-                // Update payment with Binance data
    } elseif ($amount > $max_deposit) {
-                $stmt = $pdo->prepare("
        $error = "Maximum deposit amount is $" . number_format($max_deposit, 2);
-                    UPDATE payments 
-                    SET transaction_id = ?, gateway_data = ? 
-                    WHERE id = ?
            if (empty($plisio_api_key)) {
-                ");
                throw new Exception('Payment gateway not configured. Please contact support.');
-                $stmt->execute([
            }
-                    $binance_order['data']['prepayId'],
            
-                    json_encode($binance_order['data']),
-                    $payment_id
-                ]);
-                
            // Create Plisio payment using WHMCS gateway
-                $pdo->commit();
            $plisio_gateway = new PlisioWHMCSGateway($plisio_api_key);
-            }
            
-            
            $payment_params = [
-        } catch (Exception $e) {
                'amount' => $amount,
-            $pdo->rollback();
                'currency' => $crypto_currency,
-            $error = "Failed to create Binance Pay deposit: " . $e->getMessage();
                'order_id' => $payment_id,
-        }
                'description' => 'Deposit to ' . $site_name,
-    }
                'callback_url' => $site_url . '/api/webhook/plisio.php',
-}