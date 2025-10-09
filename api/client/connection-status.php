<?php
/**
 * Report Connection Status API for Mobile App
 * POST /api/client/connection-status
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../config/config.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get authorization header
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($authHeader)) {
        throw new Exception('Authorization token required');
    }
    
    // Extract token
    $token = str_replace('Bearer ', '', $authHeader);
    $tokenData = json_decode(base64_decode($token), true);
    
    if (!$tokenData || !isset($tokenData['client_id'])) {
        throw new Exception('Invalid token');
    }
    
    // Check token expiration
    if ($tokenData['expires_at'] < time()) {
        throw new Exception('Token expired');
    }
    
    $clientId = $tokenData['client_id'];
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $accountId = $input['account_id'] ?? 0;
    $status = $input['status'] ?? '';
    $connectedAt = $input['connected_at'] ?? date('Y-m-d H:i:s');
    $ipAddress = $input['ip_address'] ?? $_SERVER['REMOTE_ADDR'];
    
    if (empty($accountId) || empty($status)) {
        throw new Exception('account_id and status are required');
    }
    
    // Connect to database
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verify account belongs to this client
    $stmt = $conn->prepare("SELECT id FROM vpn_accounts WHERE id = ? AND staff_id = ?");
    $stmt->execute([$accountId, $clientId]);
    $account = $stmt->fetch();
    
    if (!$account) {
        throw new Exception('VPN account not found or access denied');
    }
    
    // Update last_used_at timestamp
    if ($status === 'connected') {
        $stmt = $conn->prepare("UPDATE vpn_accounts SET last_used_at = NOW() WHERE id = ?");
        $stmt->execute([$accountId]);
    }
    
    // Log activity
    logActivity($conn, 'client', $clientId, 'vpn_connection', "VPN account $accountId status: $status from mobile app");
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Status reported successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

