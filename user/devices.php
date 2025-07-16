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

// Get available devices
$stmt = $pdo->prepare("SELECT * FROM devices WHERE status = 'available' ORDER BY location, daily_rate");
$stmt->execute();
$devices = $stmt->fetchAll();

// Get user's rented devices
$stmt = $pdo->prepare("
    SELECT r.*, d.name as device_name, d.location, d.model 
    FROM rentals r 
    JOIN devices d ON r.device_id = d.id 
    WHERE r.user_id = ? 
    ORDER BY r.created_at DESC
");
$stmt->execute([$user_id]);
$rentals = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devices - <?php echo htmlspecialchars($site_name); ?></title>
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
        
        .devices-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .device-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
        .device-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .device-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        
        .device-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .device-location {
            color: #666;
            font-size: 0.9rem;
        }
        
        .device-rate {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .device-specs {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        .spec-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .spec-row:last-child {
            margin-bottom: 0;
        }
        
        .spec-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .spec-value {
            font-weight: 600;
            color: #333;
        }
        
        .uptime-bar {
            background: #e9ecef;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .uptime-fill {
            background: linear-gradient(90deg, #28a745, #20c997);
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        .btn-disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        .rentals-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .section-header {
            background: #f8f9fa;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .section-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
        }
        
        .rental-item {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #f1f3f4;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .rental-item:last-child {
            border-bottom: none;
        }
        
        .rental-info h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .rental-details {
            color: #666;
            font-size: 0.9rem;
        }
        
        .rental-stats {
            text-align: right;
        }
        
        .rental-profit {
            font-size: 1.2rem;
            font-weight: 700;
            color: #28a745;
            margin-bottom: 0.25rem;
        }
        
        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status.active { background: #d4edda; color: #155724; }
        .status.completed { background: #d1ecf1; color: #0c5460; }
        .status.pending { background: #fff3cd; color: #856404; }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .devices-grid {
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
            <li><a href="devices.php" class="active">🖥️ Devices</a></li>
            <li><a href="transactions.php">💳 Transactions</a></li>
            <li><a href="referrals.php">👥 Referrals</a></li>
            <li><a href="profile.php">👤 Profile</a></li>
            <li><a href="support.php">🎧 Support</a></li>
            <li><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>Premium Router Devices</h1>
            <p style="color: #666; margin-top: 0.5rem;">Rent premium routers worldwide and earn daily profits</p>
        </div>
        
        <div class="devices-grid">
            <?php foreach ($devices as $device): ?>
                <div class="device-card">
                    <div class="device-header">
                        <div>
                            <div class="device-name"><?php echo htmlspecialchars($device['name']); ?></div>
                            <div class="device-location">📍 <?php echo htmlspecialchars($device['location']); ?></div>
                        </div>
                        <div class="device-rate">$<?php echo number_format($device['daily_rate'], 2); ?>/day</div>
                    </div>
                    
                    <div class="device-specs">
                        <div class="spec-row">
                            <span class="spec-label">Model:</span>
                            <span class="spec-value"><?php echo htmlspecialchars($device['model']); ?></span>
                        </div>
                        <div class="spec-row">
                            <span class="spec-label">Download Speed:</span>
                            <span class="spec-value"><?php echo $device['max_speed_down']; ?> Mbps</span>
                        </div>
                        <div class="spec-row">
                            <span class="spec-label">Upload Speed:</span>
                            <span class="spec-value"><?php echo $device['max_speed_up']; ?> Mbps</span>
                        </div>
                        <div class="spec-row">
                            <span class="spec-label">Uptime:</span>
                            <span class="spec-value"><?php echo $device['uptime_percentage']; ?>%</span>
                        </div>
                        <div class="uptime-bar">
                            <div class="uptime-fill" style="width: <?php echo $device['uptime_percentage']; ?>%"></div>
                        </div>
                    </div>
                    
                    <?php if ($user['balance'] >= $device['daily_rate']): ?>
                        <a href="rent-device.php?id=<?php echo $device['id']; ?>" class="btn">
                            🚀 Rent Device
                        </a>
                    <?php else: ?>
                        <button class="btn btn-disabled" disabled>
                            💰 Insufficient Balance
                        </button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="rentals-section">
            <div class="section-header">
                <h3>My Device Rentals</h3>
            </div>
            <?php if (empty($rentals)): ?>
                <div style="padding: 3rem; text-align: center; color: #666;">
                    <p>No device rentals yet. Start renting to earn daily profits!</p>
                </div>
            <?php else: ?>
                <?php foreach ($rentals as $rental): ?>
                    <div class="rental-item">
                        <div class="rental-info">
                            <h4><?php echo htmlspecialchars($rental['device_name']); ?></h4>
                            <div class="rental-details">
                                📍 <?php echo htmlspecialchars($rental['location']); ?> • 
                                <?php echo htmlspecialchars($rental['model']); ?> • 
                                <?php echo date('M j, Y', strtotime($rental['start_date'])); ?> - 
                                <?php echo date('M j, Y', strtotime($rental['end_date'])); ?>
                            </div>
                        </div>
                        <div class="rental-stats">
                            <div class="rental-profit">$<?php echo number_format($rental['actual_total_profit'], 2); ?></div>
                            <div class="status <?php echo $rental['status']; ?>"><?php echo ucfirst($rental['status']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>