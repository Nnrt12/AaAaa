<?php
require_once '../includes/session_config.php';
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get system settings
$site_name = getSetting('site_name', 'Star Router Rent');
$admin_email = getSetting('admin_email', 'support@star-rent.vip');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support - <?php echo htmlspecialchars($site_name); ?></title>
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
        
        .support-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .support-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .support-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .support-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.5rem;
        }
        
        .support-icon.email { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .support-icon.telegram { background: linear-gradient(135deg, #0088cc, #006699); color: white; }
        .support-icon.faq { background: linear-gradient(135deg, #51cf66, #40c057); color: white; }
        
        .support-card h3 {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .support-card p {
            color: #666;
            margin-bottom: 1.5rem;
            line-height: 1.6;
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
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .faq-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .faq-header {
            background: #f8f9fa;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .faq-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
        }
        
        .faq-item {
            border-bottom: 1px solid #f1f3f4;
        }
        
        .faq-question {
            padding: 1.5rem 2rem;
            background: white;
            border: none;
            width: 100%;
            text-align: left;
            font-size: 1rem;
            font-weight: 600;
            color: #333;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .faq-question:hover {
            background: #f8f9fa;
        }
        
        .faq-answer {
            padding: 0 2rem 1.5rem;
            color: #666;
            line-height: 1.6;
            display: none;
        }
        
        .faq-answer.active {
            display: block;
        }
        
        .faq-toggle {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }
        
        .faq-toggle.active {
            transform: rotate(180deg);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .support-grid {
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
            <li><a href="support.php" class="active">🎧 Support</a></li>
            <li><a href="logout.php">🚪 Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1>Support Center</h1>
            <p style="color: #666; margin-top: 0.5rem;">Get help and support for your account</p>
        </div>
        
        <div class="support-grid">
            <div class="support-card">
                <div class="support-icon email">📧</div>
                <h3>Email Support</h3>
                <p>Send us an email and we'll get back to you within 24 hours. Perfect for detailed questions or account issues.</p>
                <a href="mailto:<?php echo htmlspecialchars($admin_email); ?>" class="btn">Send Email</a>
            </div>
            
            <div class="support-card">
                <div class="support-icon telegram">📱</div>
                <h3>Telegram Support</h3>
                <p>Join our Telegram support channel for instant help and community discussions with other investors.</p>
                <a href="https://t.me/starrouter_support" target="_blank" class="btn">Join Telegram</a>
            </div>
            
            <div class="support-card">
                <div class="support-icon faq">❓</div>
                <h3>FAQ & Help</h3>
                <p>Browse our frequently asked questions and help articles to find quick answers to common questions.</p>
                <a href="#faq" class="btn">View FAQ</a>
            </div>
        </div>
        
        <div class="faq-section" id="faq">
            <div class="faq-header">
                <h3>Frequently Asked Questions</h3>
            </div>
            
            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    How do I start investing?
                    <span class="faq-toggle">▼</span>
                </button>
                <div class="faq-answer">
                    To start investing, first make a deposit to your account, then go to the Investments page and choose a plan that suits your budget. The minimum investment is $100.
                </div>
            </div>
            
            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    How are daily profits calculated?
                    <span class="faq-toggle">▼</span>
                </button>
                <div class="faq-answer">
                    Daily profits are calculated based on your investment amount and the plan's daily rate. For example, a $1000 investment in a 1.5% daily plan earns $15 per day.
                </div>
            </div>
            
            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    When can I withdraw my earnings?
                    <span class="faq-toggle">▼</span>
                </button>
                <div class="faq-answer">
                    You can withdraw your earnings anytime once they're credited to your account balance. Withdrawals are processed within 24-48 hours.
                </div>
            </div>
            
            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    What payment methods do you accept?
                    <span class="faq-toggle">▼</span>
                </button>
                <div class="faq-answer">
                    We accept various cryptocurrencies including Bitcoin, Ethereum, USDT, and more through our secure payment processor.
                </div>
            </div>
            
            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    How does the referral program work?
                    <span class="faq-toggle">▼</span>
                </button>
                <div class="faq-answer">
                    Share your referral code with friends. When they invest, you earn 10% commission on their investment amount. Commissions are paid instantly to your account.
                </div>
            </div>
            
            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    Are there any fees?
                    <span class="faq-toggle">▼</span>
                </button>
                <div class="faq-answer">
                    There are no fees for deposits or investments. Withdrawals have a small processing fee of 2.5% to cover transaction costs.
                </div>
            </div>
            
            <div class="faq-item">
                <button class="faq-question" onclick="toggleFaq(this)">
                    Is my investment secure?
                    <span class="faq-toggle">▼</span>
                </button>
                <div class="faq-answer">
                    Yes, we use bank-level security, SSL encryption, and secure cryptocurrency payments. Our platform is regularly audited for security.
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function toggleFaq(button) {
            const answer = button.nextElementSibling;
            const toggle = button.querySelector('.faq-toggle');
            
            // Close all other FAQ items
            document.querySelectorAll('.faq-answer').forEach(item => {
                if (item !== answer) {
                    item.classList.remove('active');
                }
            });
            
            document.querySelectorAll('.faq-toggle').forEach(item => {
                if (item !== toggle) {
                    item.classList.remove('active');
                }
            });
            
            // Toggle current item
            answer.classList.toggle('active');
            toggle.classList.toggle('active');
        }
    </script>
</body>
</html>