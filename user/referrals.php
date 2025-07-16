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
$referral_rate = getSetting('referral_level_1_rate', '10.0');

// Get referral statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_referrals,
        SUM(total_commission_earned) as total_commission
    FROM referrals 
    WHERE referrer_id = ?
");
$stmt->execute([$user_id]);
$referral_stats = $stmt->fetch();

// Get referred users
$stmt = $pdo->prepare("
    SELECT u.username, u.email, u.created_at, u.total_invested, r.total_commission_earned
    FROM referrals r
    JOIN users u ON r.referred_id = u.id
    WHERE r.referrer_id = ?
    ORDER BY u.created_at DESC
");
$stmt->execute([$user_id]);
$referred_users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referrals - <?php echo htmlspecialchars($site_name); ?></title>
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
        
        .referral-hero {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 3rem 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .referral-code {
            background: rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
            border-radius: 10px;
            font-family: monospace;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 2rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .copy-btn {
            background: white;
            color: #667eea;
            padding: 0.75rem 1.5rem;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 1rem;
        }
        
        .stat-icon.referrals { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .stat-icon.commission { background: linear-gradient(135deg, #51cf66, #40c057); color: white; }
        .stat-icon.rate { background: linear-gradient(135deg, #ffd43b, #fab005); color: white; }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .referrals-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .commission-amount {
            font-weight: 600;
            color: #28a745;
        }
        
        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
            color: #666;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .share-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .share-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .share-btn.telegram {
            background: #0088cc;
            color: white;
        }
        
        .share-btn.whatsapp {
            background: #25d366;
            color: white;
        }
        
        .share-btn:hover {
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
            
            .referral-code {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .share-buttons {
                flex-direction: column;
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
            <li><a href="referrals.php" class="active">👥 Referrals</a></li>
            <li><a href="profile.php">👤 Profile</a></li>
            <li><a href="support.php">🎧 Support</a></li>
            <li><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>Referral Program</h1>
            <p style="color: #666; margin-top: 0.5rem;">Earn <?php echo $referral_rate; ?>% commission on all referral investments</p>
        </div>
        
        <div class="referral-hero">
            <h2 style="margin-bottom: 1rem;">🎯 Your Referral Code</h2>
            <p style="opacity: 0.9; margin-bottom: 2rem;">Share your unique referral code and earn <?php echo $referral_rate; ?>% commission on every investment made by your referrals!</p>
            
            <div class="referral-code">
                <span><?php echo htmlspecialchars($user['referral_code']); ?></span>
                <button class="copy-btn" onclick="copyReferralCode()">📋 Copy</button>
            </div>
            
            <div class="share-buttons">
                <a href="https://t.me/share/url?url=<?php echo urlencode('Join ' . $site_name . ' and start earning daily profits! Use my referral code: ' . $user['referral_code'] . ' - ' . getSetting('site_url', '')); ?>" target="_blank" class="share-btn telegram">
                    📱 Share on Telegram
                </a>
                <a href="https://wa.me/?text=<?php echo urlencode('Join ' . $site_name . ' and start earning daily profits! Use my referral code: ' . $user['referral_code'] . ' - ' . getSetting('site_url', '')); ?>" target="_blank" class="share-btn whatsapp">
                    💬 Share on WhatsApp
                </a>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon referrals">👥</div>
                <div class="stat-value"><?php echo $referral_stats['total_referrals'] ?? 0; ?></div>
                <div class="stat-label">Total Referrals</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon commission">💰</div>
                <div class="stat-value">$<?php echo number_format($referral_stats['total_commission'] ?? 0, 2); ?></div>
                <div class="stat-label">Total Commission Earned</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon rate">📈</div>
                <div class="stat-value"><?php echo $referral_rate; ?>%</div>
                <div class="stat-label">Commission Rate</div>
            </div>
        </div>
        
        <div class="referrals-table">
            <div class="table-header">
                <h3>My Referrals</h3>
            </div>
            <?php if (empty($referred_users)): ?>
                <div class="empty-state">
                    <h3>No Referrals Yet</h3>
                    <p>Start sharing your referral code to earn commissions on investments!</p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Joined Date</th>
                            <th>Total Invested</th>
                            <th>Your Commission</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referred_users as $referral): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($referral['username']); ?></td>
                                <td><?php echo htmlspecialchars($referral['email']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($referral['created_at'])); ?></td>
                                <td>$<?php echo number_format($referral['total_invested'], 2); ?></td>
                                <td class="commission-amount">$<?php echo number_format($referral['total_commission_earned'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
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