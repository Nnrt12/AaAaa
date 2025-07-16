<?php
require_once '../config.php';

// Set JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = trim($_SERVER['REQUEST_URI'], '/');
$path = str_replace('api/', '', $path);
$segments = explode('/', $path);

// Basic authentication check for API endpoints
function authenticate() {
    global $pdo;
    
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? '';
    
    if (strpos($auth_header, 'Bearer ') === 0) {
        $token = substr($auth_header, 7);
        
        // Verify JWT token (simplified)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([base64_decode($token)]);
        return $stmt->fetch();
    }
    
    return false;
}

// Response helper
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Error handler
function apiError($message, $status = 400) {
    jsonResponse(['error' => $message], $status);
}

try {
    // Route handling
    switch ($segments[0]) {
        case 'auth':
            if ($method === 'POST' && $segments[1] === 'login') {
                $input = json_decode(file_get_contents('php://input'), true);
                $username = $input['username'] ?? '';
                $password = $input['password'] ?? '';
                
                if (empty($username) || empty($password)) {
                    apiError('Username and password required');
                }
                
                $stmt = $pdo->prepare("SELECT id, username, password, status FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] === 'active') {
                        $token = base64_encode($user['id']);
                        jsonResponse([
                            'success' => true,
                            'token' => $token,
                            'user' => [
                                'id' => $user['id'],
                                'username' => $user['username']
                            ]
                        ]);
                    } else {
                        apiError('Account suspended');
                    }
                } else {
                    apiError('Invalid credentials');
                }
            }
            break;
            
        case 'user':
            $user = authenticate();
            if (!$user) {
                apiError('Unauthorized', 401);
            }
            
            if ($method === 'GET' && $segments[1] === 'balance') {
                $stmt = $pdo->prepare("SELECT balance, total_earnings FROM users WHERE id = ?");
                $stmt->execute([$user['id']]);
                $data = $stmt->fetch();
                
                jsonResponse([
                    'balance' => $data['balance'],
                    'total_earnings' => $data['total_earnings']
                ]);
            }
            break;
            
        case 'investments':
            $user = authenticate();
            if (!$user) {
                apiError('Unauthorized', 401);
            }
            
            if ($method === 'GET') {
                $stmt = $pdo->prepare("SELECT * FROM investments WHERE user_id = ? ORDER BY created_at DESC");
                $stmt->execute([$user['id']]);
                $investments = $stmt->fetchAll();
                
                jsonResponse(['investments' => $investments]);
            }
            break;
            
        case 'plans':
            if ($method === 'GET') {
                $stmt = $pdo->prepare("SELECT * FROM investment_plans WHERE is_active = 1 ORDER BY sort_order");
                $stmt->execute();
                $plans = $stmt->fetchAll();
                
                jsonResponse(['plans' => $plans]);
            }
            break;
            
        case 'devices':
            if ($method === 'GET') {
                $stmt = $pdo->prepare("SELECT * FROM devices WHERE status = 'available' ORDER BY location");
                $stmt->execute();
                $devices = $stmt->fetchAll();
                
                jsonResponse(['devices' => $devices]);
            }
            break;
            
        default:
            apiError('Endpoint not found', 404);
    }
    
} catch (Exception $e) {
    apiError('Internal server error', 500);
}
?>