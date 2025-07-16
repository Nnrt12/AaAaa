<?php
/**
 * Enhanced Notification System
 * Handles in-app notifications and email notifications
 */

class NotificationSystem {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create a new notification
     */
    public function createNotification($user_id, $title, $message, $type = 'info', $send_email = false) {
        try {
            // Insert notification into database
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
            error_log("Failed to create notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email notification
     */
    private function sendEmailNotification($user_id, $title, $message, $type) {
        try {
            // Get user email
            $stmt = $this->pdo->prepare("SELECT email, username, first_name, last_name FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) return false;
            
            // Get email template
            $stmt = $this->pdo->prepare("SELECT * FROM email_templates WHERE template_key = 'notification' AND is_active = 1");
            $stmt->execute();
            $template = $stmt->fetch();
            
            if (!$template) return false;
            
            // Replace variables in template
            $variables = [
                '{{username}}' => $user['username'],
                '{{first_name}}' => $user['first_name'] ?? $user['username'],
                '{{last_name}}' => $user['last_name'] ?? '',
                '{{title}}' => $title,
                '{{message}}' => $message,
                '{{site_name}}' => getSetting('site_name', 'Star Router Rent'),
                '{{site_url}}' => getSetting('site_url', 'https://star-rent.vip'),
                '{{support_email}}' => getSetting('admin_email', 'support@star-rent.vip')
            ];
            
            $subject = str_replace(array_keys($variables), array_values($variables), $template['subject']);
            $body = str_replace(array_keys($variables), array_values($variables), $template['body']);
            
            // Send email (implement your email sending logic here)
            return $this->sendEmail($user['email'], $subject, $body);
            
        } catch (Exception $e) {
            error_log("Failed to send email notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email using configured SMTP settings
     */
    private function sendEmail($to, $subject, $body) {
        // Basic email sending - you can enhance this with PHPMailer or similar
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . getSetting('site_name', 'Star Router Rent') . ' <' . getSetting('admin_email', 'noreply@star-rent.vip') . '>',
            'Reply-To: ' . getSetting('admin_email', 'support@star-rent.vip'),
            'X-Mailer: PHP/' . phpversion()
        ];
        
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    /**
     * Get email template
     */
    private function getEmailTemplate($template_key, $variables = []) {
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
     * Send welcome email to new user
     */
    public function sendWelcomeEmail($user_id, $user_data) {
        try {
            $template_vars = [
                'first_name' => $user_data['first_name'],
                'last_name' => $user_data['last_name'],
                'username' => $user_data['username'],
                'site_name' => getSetting('site_name', 'Star Router Rent'),
                'site_url' => getSetting('site_url', 'https://star-rent.vip'),
                'support_email' => getSetting('admin_email', 'support@star-rent.vip')
            ];
            
            $template = $this->getEmailTemplate('welcome', $template_vars);
            return $this->sendEmail($user_data['email'], $template['subject'], $template);
            
        } catch (Exception $e) {
            error_log('Failed to send welcome email: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get notifications for a user
     */
    public function getUserNotifications($user_id, $limit = 10, $unread_only = false) {
        try {
            $where_clause = "WHERE user_id = ?";
            $params = [$user_id];
            
            if ($unread_only) {
                $where_clause .= " AND is_read = 0";
            }
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM notifications 
                $where_clause 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $params[] = $limit;
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Failed to get user notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id, $user_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE id = ? AND user_id = ?
            ");
            return $stmt->execute([$notification_id, $user_id]);
        } catch (Exception $e) {
            error_log("Failed to mark notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($user_id) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE user_id = ? AND is_read = 0
            ");
            return $stmt->execute([$user_id]);
        } catch (Exception $e) {
            error_log("Failed to mark all notifications as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount($user_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM notifications 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetch()['count'];
        } catch (Exception $e) {
            error_log("Failed to get unread notification count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Delete old notifications
     */
    public function cleanupOldNotifications($days = 30) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM notifications 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            return $stmt->execute([$days]);
        } catch (Exception $e) {
            error_log("Failed to cleanup old notifications: " . $e->getMessage());
            return false;
        }
    }
}
?>