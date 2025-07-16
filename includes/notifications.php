<?php
/**
 * Notification System
 * Handle in-app notifications and email notifications
 */

class NotificationSystem {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create notification
     */
    public function createNotification($user_id, $title, $message, $type = 'info', $send_email = false) {
        try {
            // Insert notification
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
                VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$user_id, $title, $message, $type]);
            
            // Send email if requested
            if ($send_email) {
                $this->sendEmailNotification($user_id, $title, $message, $type);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Notification creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email notification
     */
    public function sendEmailNotification($user_id, $title, $message, $type = 'info') {
        try {
            // Get user email
            $stmt = $this->pdo->prepare("SELECT email, username FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return false;
            }
            
            // Get email template
            $template = $this->getEmailTemplate('notification', [
                'username' => $user['username'],
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'site_name' => getSetting('site_name', 'Star Router Rent'),
                'site_url' => getSetting('site_url', '')
            ]);
            
            return $this->sendEmail($user['email'], $title, $template);
            
        } catch (Exception $e) {
            error_log("Email notification failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get email template
     */
    public function getEmailTemplate($template_key, $variables = []) {
        // Get template from database
        $stmt = $this->pdo->prepare("SELECT * FROM email_templates WHERE template_key = ? AND is_active = 1");
        $stmt->execute([$template_key]);
        $template = $stmt->fetch();
        
        if (!$template) {
            // Use default template
            $template = $this->getDefaultTemplate($template_key);
        }
        
        // Replace variables
        $subject = $template['subject'];
        $body = $template['body'];
        
        foreach ($variables as $key => $value) {
            $subject = str_replace('{{' . $key . '}}', $value, $subject);
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }
        
        return [
            'subject' => $subject,
            'body' => $body
        ];
    }
    
    /**
     * Send email using SMTP
     */
    public function sendEmail($to, $subject, $template) {
        $smtp_host = getSetting('smtp_host');
        $smtp_port = getSetting('smtp_port', 587);
        $smtp_username = getSetting('smtp_username');
        $smtp_password = getSetting('smtp_password');
        $from_email = getSetting('admin_email', 'noreply@star-rent.vip');
        $site_name = getSetting('site_name', 'Star Router Rent');
        
        if (!$smtp_host || !$smtp_username) {
            // Use PHP mail() as fallback
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . $site_name . ' <' . $from_email . '>',
                'Reply-To: ' . $from_email,
                'X-Mailer: PHP/' . phpversion()
            ];
            
            return mail($to, $subject, $template['body'], implode("\r\n", $headers));
        }
        
        // Use SMTP
        try {
            $mail_content = "Subject: " . $subject . "\r\n";
            $mail_content .= "From: " . $site_name . " <" . $from_email . ">\r\n";
            $mail_content .= "To: " . $to . "\r\n";
            $mail_content .= "MIME-Version: 1.0\r\n";
            $mail_content .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $mail_content .= $template['body'];
            
            // Simple SMTP implementation
            $socket = fsockopen($smtp_host, $smtp_port, $errno, $errstr, 30);
            if (!$socket) {
                throw new Exception("Failed to connect to SMTP server");
            }
            
            // SMTP commands would go here
            // For simplicity, using mail() function
            fclose($socket);
            
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . $site_name . ' <' . $from_email . '>',
                'Reply-To: ' . $from_email
            ];
            
            return mail($to, $subject, $template['body'], implode("\r\n", $headers));
            
        } catch (Exception $e) {
            error_log("SMTP send failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get default email templates
     */
    private function getDefaultTemplate($template_key) {
        $templates = [
            'notification' => [
                'subject' => '{{title}} - {{site_name}}',
                'body' => '
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>{{title}}</title>
                </head>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                        <h1 style="margin: 0;">{{site_name}}</h1>
                    </div>
                    <div style="background: white; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 10px 10px;">
                        <h2 style="color: #333; margin-bottom: 20px;">{{title}}</h2>
                        <p style="margin-bottom: 20px;">Hello {{username}},</p>
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;">
                            {{message}}
                        </div>
                        <p>Best regards,<br>{{site_name}} Team</p>
                        <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">
                        <p style="font-size: 12px; color: #666; text-align: center;">
                            This email was sent from {{site_name}}. If you have any questions, please contact our support team.
                        </p>
                    </div>
                </body>
                </html>'
            ],
            'deposit_confirmed' => [
                'subject' => 'Deposit Confirmed - {{site_name}}',
                'body' => '
                <!DOCTYPE html>
                <html>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                        <h1 style="margin: 0;">{{site_name}}</h1>
                    </div>
                    <div style="background: white; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 10px 10px;">
                        <h2 style="color: #28a745;">✅ Deposit Confirmed</h2>
                        <p>Hello {{username}},</p>
                        <p>Your deposit of <strong>${{amount}}</strong> has been confirmed and added to your account.</p>
                        <div style="background: #d4edda; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #28a745;">
                            <p><strong>Transaction Details:</strong></p>
                            <p>Amount: ${{amount}}</p>
                            <p>Transaction ID: {{transaction_id}}</p>
                            <p>Date: {{date}}</p>
                        </div>
                        <p>You can now start investing and earning daily profits!</p>
                        <p><a href="{{site_url}}/user/dashboard.php" style="background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">View Dashboard</a></p>
                    </div>
                </body>
                </html>'
            ],
            'withdrawal_processed' => [
                'subject' => 'Withdrawal Processed - {{site_name}}',
                'body' => '
                <!DOCTYPE html>
                <html>
                <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                        <h1 style="margin: 0;">{{site_name}}</h1>
                    </div>
                    <div style="background: white; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 10px 10px;">
                        <h2 style="color: #17a2b8;">💰 Withdrawal Processed</h2>
                        <p>Hello {{username}},</p>
                        <p>Your withdrawal request has been processed successfully.</p>
                        <div style="background: #d1ecf1; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #17a2b8;">
                            <p><strong>Withdrawal Details:</strong></p>
                            <p>Amount: ${{amount}}</p>
                            <p>Fee: ${{fee}}</p>
                            <p>Net Amount: ${{net_amount}}</p>
                            <p>Method: {{method}}</p>
                            <p>Address: {{address}}</p>
                        </div>
                        <p>The funds should arrive in your wallet within 24-48 hours.</p>
                    </div>
                </body>
                </html>'
            ]
        ];
        
        return $templates[$template_key] ?? $templates['notification'];
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id, $user_id) {
        $stmt = $this->pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notification_id, $user_id]);
    }
    
    /**
     * Get user notifications
     */
    public function getUserNotifications($user_id, $limit = 20, $unread_only = false) {
        $where_clause = "WHERE user_id = ?";
        $params = [$user_id];
        
        if ($unread_only) {
            $where_clause .= " AND is_read = 0";
        }
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM notifications 
            $where_clause 
            ORDER BY created_at DESC 
            LIMIT $limit
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount($user_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return $stmt->fetch()['count'];
    }
}