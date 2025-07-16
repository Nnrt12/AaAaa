<?php
/**
 * Session Configuration and Management
 * Secure session handling for Star Router Rent
 */

// Prevent direct access
if (!defined('SESSION_CONFIG_LOADED')) {
    define('SESSION_CONFIG_LOADED', true);
}

/**
 * Configure secure session settings
 */
function configureSession() {
    // Only configure if session hasn't started yet
    if (session_status() === PHP_SESSION_NONE) {
        // Session security settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        // Session name and lifetime
        session_name('STAR_RENT_SESSION');
        ini_set('session.gc_maxlifetime', 7200); // 2 hours
        ini_set('session.cookie_lifetime', 0); // Until browser closes
        
        // Session storage settings
        ini_set('session.entropy_length', 32);
        ini_set('session.hash_function', 'sha256');
        
        // Regenerate session ID periodically
        if (isset($_SESSION['last_regeneration'])) {
            if (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
        }
    }
}

/**
 * Safely start session with proper configuration
 */
function safeSessionStart() {
    if (session_status() === PHP_SESSION_NONE) {
        try {
            configureSession();
            session_start();
            
            // Set regeneration timestamp on first start
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
            }
            
            // Basic session hijacking protection
            if (!isset($_SESSION['user_ip'])) {
                $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            } elseif ($_SESSION['user_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')) {
                // IP changed, destroy session for security
                session_destroy();
                session_start();
                $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $_SESSION['last_regeneration'] = time();
            }
            
            return true;
        } catch (Exception $e) {
            error_log('Session start failed: ' . $e->getMessage());
            return false;
        }
    }
    return true;
}

/**
 * Destroy session securely
 */
function destroySession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        
        // Delete session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        session_destroy();
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if admin is logged in
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Require user login
 */
function requireLogin($redirect_to = 'login.php') {
    if (!isLoggedIn()) {
        header('Location: ' . $redirect_to);
        exit;
    }
}

/**
 * Require admin login
 */
function requireAdminLogin($redirect_to = 'login.php') {
    if (!isAdminLoggedIn()) {
        header('Location: ' . $redirect_to);
        exit;
    }
    
    // Additional security check - verify admin exists in database
    global $pdo;
    if (isset($pdo) && isset($_SESSION['admin_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE id = ? AND status = 'active'");
            $stmt->execute([$_SESSION['admin_id']]);
            if (!$stmt->fetch()) {
                destroySession();
                header('Location: ' . $redirect_to);
                exit;
            }
        } catch (Exception $e) {
            error_log('Admin verification failed: ' . $e->getMessage());
        }
    }
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Auto-start session when this file is included
safeSessionStart();
?>