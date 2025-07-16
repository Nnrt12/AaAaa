<?php
require_once '../includes/session_config.php';
require_once '../config.php';

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

$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $country = trim($_POST['country']);
    $telegram_username = trim($_POST['telegram_username']);
    
    if (empty($email)) {
        $error = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Check if email is already taken by another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $error = 'Email is already taken.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET email = ?, first_name = ?, last_name = ?, country = ?, telegram_username = ? WHERE id = ?");
                $stmt->execute([$email, $first_name, $last_name, $country, $telegram_username, $user_id]);
                $success = 'Profile updated successfully!';
                
                // Log profile update
                logActivity($user_id, 'profile_updated', 'User profile information updated');
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            }
        } catch (Exception $e) {
            $error = 'Update failed. Please try again.';
            error_log('Profile update error: ' . $e->getMessage());
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password)) {
        $error = 'All password fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = 'Current password is incorrect.';
    } else {
        try {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$password_hash, $user_id]);
            $success = 'Password changed successfully!';
            
            // Log password change
            logActivity($user_id, 'password_changed', 'User password changed successfully');
            
        } catch (Exception $e) {
            $error = 'Password change failed. Please try again.';
            error_log('Password change error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo htmlspecialchars($site_name); ?></title>
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
        
        .profile-grid {
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
        
        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fafbfc;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group input[readonly] {
            background: #e9ecef;
            color: #6c757d;
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
        
        .profile-stats {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .referral-section {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        
        .referral-code {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: 10px;
            font-family: monospace;
            font-size: 1.2rem;
            font-weight: 600;
            text-align: center;
            margin: 1rem 0;
        }
        
        .copy-btn {
            background: white;
            color: #667eea;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .copy-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .profile-grid {
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
            <li><a href="profile.php" class="active">👤 Profile</a></li>
            <li><a href="support.php">🎧 Support</a></li>
            <li><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>My Profile</h1>
            <p style="color: #666; margin-top: 0.5rem;">Manage your account settings and information</p>
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
        
        <div class="profile-stats">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value">$<?php echo number_format($user['balance'], 2); ?></div>
                    <div class="stat-label">Current Balance</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">$<?php echo number_format($user['total_earnings'], 2); ?></div>
                    <div class="stat-label">Total Earnings</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">$<?php echo number_format($user['total_invested'], 2); ?></div>
                    <div class="stat-label">Total Invested</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
                    <div class="stat-label">Member Since</div>
                </div>
            </div>
        </div>
        
        <div class="referral-section">
            <h3 style="margin-bottom: 1rem;">🎯 Your Referral Code</h3>
            <p style="margin-bottom: 1rem; opacity: 0.9;">Share your referral code and earn 10% commission on all referral investments!</p>
            <div class="referral-code"><?php echo htmlspecialchars($user['referral_code']); ?></div>
            <div style="text-align: center;">
                <button class="copy-btn" onclick="copyReferralCode()">📋 Copy Code</button>
            </div>
        </div>
        
        <div class="profile-grid">
            <div class="card">
                <div class="card-header">
                    <h3>Profile Information</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" placeholder="Enter your first name">
                        </div>
                        
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" placeholder="Enter your last name">
                        </div>
                        
                        <div class="form-group">
                            <label>Country</label>
                            <select name="country" style="width: 100%; padding: 1rem; border: 2px solid #e1e5e9; border-radius: 10px; font-size: 1rem; transition: all 0.3s ease; background: #fafbfc;">
                                <option value="">Select Country</option>
                                <!-- European Countries -->
                                <option value="AD" <?php echo ($user['country'] ?? '') === 'AD' ? 'selected' : ''; ?>>🇦🇩 Andorra</option>
                                <option value="AL" <?php echo ($user['country'] ?? '') === 'AL' ? 'selected' : ''; ?>>🇦🇱 Albania</option>
                                <option value="AT" <?php echo ($user['country'] ?? '') === 'AT' ? 'selected' : ''; ?>>🇦🇹 Austria</option>
                                <option value="BA" <?php echo ($user['country'] ?? '') === 'BA' ? 'selected' : ''; ?>>🇧🇦 Bosnia and Herzegovina</option>
                                <option value="BE" <?php echo ($user['country'] ?? '') === 'BE' ? 'selected' : ''; ?>>🇧🇪 Belgium</option>
                                <option value="BG" <?php echo ($user['country'] ?? '') === 'BG' ? 'selected' : ''; ?>>🇧🇬 Bulgaria</option>
                                <option value="BY" <?php echo ($user['country'] ?? '') === 'BY' ? 'selected' : ''; ?>>🇧🇾 Belarus</option>
                                <option value="CH" <?php echo ($user['country'] ?? '') === 'CH' ? 'selected' : ''; ?>>🇨🇭 Switzerland</option>
                                <option value="CY" <?php echo ($user['country'] ?? '') === 'CY' ? 'selected' : ''; ?>>🇨🇾 Cyprus</option>
                                <option value="CZ" <?php echo ($user['country'] ?? '') === 'CZ' ? 'selected' : ''; ?>>🇨🇿 Czech Republic</option>
                                <option value="DE" <?php echo ($user['country'] ?? '') === 'DE' ? 'selected' : ''; ?>>🇩🇪 Germany</option>
                                <option value="DK" <?php echo ($user['country'] ?? '') === 'DK' ? 'selected' : ''; ?>>🇩🇰 Denmark</option>
                                <option value="EE" <?php echo ($user['country'] ?? '') === 'EE' ? 'selected' : ''; ?>>🇪🇪 Estonia</option>
                                <option value="ES" <?php echo ($user['country'] ?? '') === 'ES' ? 'selected' : ''; ?>>🇪🇸 Spain</option>
                                <option value="FI" <?php echo ($user['country'] ?? '') === 'FI' ? 'selected' : ''; ?>>🇫🇮 Finland</option>
                                <option value="FR" <?php echo ($user['country'] ?? '') === 'FR' ? 'selected' : ''; ?>>🇫🇷 France</option>
                                <option value="GB" <?php echo ($user['country'] ?? '') === 'GB' ? 'selected' : ''; ?>>🇬🇧 United Kingdom</option>
                                <option value="GR" <?php echo ($user['country'] ?? '') === 'GR' ? 'selected' : ''; ?>>🇬🇷 Greece</option>
                                <option value="HR" <?php echo ($user['country'] ?? '') === 'HR' ? 'selected' : ''; ?>>🇭🇷 Croatia</option>
                                <option value="HU" <?php echo ($user['country'] ?? '') === 'HU' ? 'selected' : ''; ?>>🇭🇺 Hungary</option>
                                <option value="IE" <?php echo ($user['country'] ?? '') === 'IE' ? 'selected' : ''; ?>>🇮🇪 Ireland</option>
                                <option value="IS" <?php echo ($user['country'] ?? '') === 'IS' ? 'selected' : ''; ?>>🇮🇸 Iceland</option>
                                <option value="IT" <?php echo ($user['country'] ?? '') === 'IT' ? 'selected' : ''; ?>>🇮🇹 Italy</option>
                                <option value="XK" <?php echo ($user['country'] ?? '') === 'XK' ? 'selected' : ''; ?>>🇽🇰 Kosovo</option>
                                <option value="LI" <?php echo ($user['country'] ?? '') === 'LI' ? 'selected' : ''; ?>>🇱🇮 Liechtenstein</option>
                                <option value="LT" <?php echo ($user['country'] ?? '') === 'LT' ? 'selected' : ''; ?>>🇱🇹 Lithuania</option>
                                <option value="LU" <?php echo ($user['country'] ?? '') === 'LU' ? 'selected' : ''; ?>>🇱🇺 Luxembourg</option>
                                <option value="LV" <?php echo ($user['country'] ?? '') === 'LV' ? 'selected' : ''; ?>>🇱🇻 Latvia</option>
                                <option value="MC" <?php echo ($user['country'] ?? '') === 'MC' ? 'selected' : ''; ?>>🇲🇨 Monaco</option>
                                <option value="MD" <?php echo ($user['country'] ?? '') === 'MD' ? 'selected' : ''; ?>>🇲🇩 Moldova</option>
                                <option value="ME" <?php echo ($user['country'] ?? '') === 'ME' ? 'selected' : ''; ?>>🇲🇪 Montenegro</option>
                                <option value="MK" <?php echo ($user['country'] ?? '') === 'MK' ? 'selected' : ''; ?>>🇲🇰 North Macedonia</option>
                                <option value="MT" <?php echo ($user['country'] ?? '') === 'MT' ? 'selected' : ''; ?>>🇲🇹 Malta</option>
                                <option value="NL" <?php echo ($user['country'] ?? '') === 'NL' ? 'selected' : ''; ?>>🇳🇱 Netherlands</option>
                                <option value="NO" <?php echo ($user['country'] ?? '') === 'NO' ? 'selected' : ''; ?>>🇳🇴 Norway</option>
                                <option value="PL" <?php echo ($user['country'] ?? '') === 'PL' ? 'selected' : ''; ?>>🇵🇱 Poland</option>
                                <option value="PT" <?php echo ($user['country'] ?? '') === 'PT' ? 'selected' : ''; ?>>🇵🇹 Portugal</option>
                                <option value="RO" <?php echo ($user['country'] ?? '') === 'RO' ? 'selected' : ''; ?>>🇷🇴 Romania</option>
                                <option value="RS" <?php echo ($user['country'] ?? '') === 'RS' ? 'selected' : ''; ?>>🇷🇸 Serbia</option>
                                <option value="RU" <?php echo ($user['country'] ?? '') === 'RU' ? 'selected' : ''; ?>>🇷🇺 Russia</option>
                                <option value="SE" <?php echo ($user['country'] ?? '') === 'SE' ? 'selected' : ''; ?>>🇸🇪 Sweden</option>
                                <option value="SI" <?php echo ($user['country'] ?? '') === 'SI' ? 'selected' : ''; ?>>🇸🇮 Slovenia</option>
                                <option value="SK" <?php echo ($user['country'] ?? '') === 'SK' ? 'selected' : ''; ?>>🇸🇰 Slovakia</option>
                                <option value="SM" <?php echo ($user['country'] ?? '') === 'SM' ? 'selected' : ''; ?>>🇸🇲 San Marino</option>
                                <option value="UA" <?php echo ($user['country'] ?? '') === 'UA' ? 'selected' : ''; ?>>🇺🇦 Ukraine</option>
                                <option value="VA" <?php echo ($user['country'] ?? '') === 'VA' ? 'selected' : ''; ?>>🇻🇦 Vatican City</option>
                                
                                <!-- Other Major Countries -->
                                <option value="US" <?php echo ($user['country'] ?? '') === 'US' ? 'selected' : ''; ?>>United States</option>
                                <option value="CA" <?php echo ($user['country'] ?? '') === 'CA' ? 'selected' : ''; ?>>Canada</option>
                                <option value="AU" <?php echo ($user['country'] ?? '') === 'AU' ? 'selected' : ''; ?>>Australia</option>
                                <option value="JP" <?php echo ($user['country'] ?? '') === 'JP' ? 'selected' : ''; ?>>Japan</option>
                                <option value="IN" <?php echo ($user['country'] ?? '') === 'IN' ? 'selected' : ''; ?>>India</option>
                                <option value="BR" <?php echo ($user['country'] ?? '') === 'BR' ? 'selected' : ''; ?>>Brazil</option>
                                <option value="MX" <?php echo ($user['country'] ?? '') === 'MX' ? 'selected' : ''; ?>>Mexico</option>
                                <option value="CN" <?php echo ($user['country'] ?? '') === 'CN' ? 'selected' : ''; ?>>China</option>
                                <option value="KR" <?php echo ($user['country'] ?? '') === 'KR' ? 'selected' : ''; ?>>South Korea</option>
                                <option value="SG" <?php echo ($user['country'] ?? '') === 'SG' ? 'selected' : ''; ?>>Singapore</option>
                                <option value="AE" <?php echo ($user['country'] ?? '') === 'AE' ? 'selected' : ''; ?>>UAE</option>
                                <option value="ZA" <?php echo ($user['country'] ?? '') === 'ZA' ? 'selected' : ''; ?>>South Africa</option>
                                <option value="NG" <?php echo ($user['country'] ?? '') === 'NG' ? 'selected' : ''; ?>>Nigeria</option>
                                <option value="EG" <?php echo ($user['country'] ?? '') === 'EG' ? 'selected' : ''; ?>>Egypt</option>
                                <option value="OTHER" <?php echo ($user['country'] ?? '') === 'OTHER' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Telegram Username (Optional)</label>
                            <input type="text" name="telegram_username" value="<?php echo htmlspecialchars($user['telegram_username'] ?? ''); ?>" placeholder="@username">
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn">Update Profile</button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Change Password</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function copyReferralCode() {
            const code = '<?php echo $user['referral_code']; ?>';
            navigator.clipboard.writeText(code).then(function() {
                alert('Referral code copied to clipboard!');
            });
        }
    </script>
</body>
</html>