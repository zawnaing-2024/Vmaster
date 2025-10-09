<?php
/**
 * Get VPN Accounts API for Mobile App
 * GET /api/client/vpn-accounts
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../config/config.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
    
    // Connect to database
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get all VPN accounts for this client
    $stmt = $conn->prepare("SELECT 
        va.id,
        va.account_username,
        va.account_password,
        va.access_key,
        va.config_data,
        va.status,
        va.plan_duration,
        va.expires_at,
        va.created_at,
        vs.server_name,
        vs.server_type,
        vs.server_host,
        vs.server_port,
        vs.location,
        CASE 
            WHEN va.expires_at IS NULL THEN 'unlimited'
            WHEN va.expires_at > NOW() THEN 'active'
            ELSE 'expired'
        END as expiration_status,
        DATEDIFF(va.expires_at, NOW()) as days_remaining
        FROM vpn_accounts va 
        JOIN vpn_servers vs ON va.server_id = vs.id 
        WHERE va.staff_id = ? AND va.status = 'active'
        ORDER BY va.created_at DESC");
    $stmt->execute([$clientId]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format response
    $formattedAccounts = [];
    foreach ($accounts as $account) {
        $formattedAccount = [
            'id' => (int)$account['id'],
            'server_name' => $account['server_name'],
            'server_type' => $account['server_type'],
            'server_host' => $account['server_host'],
            'server_port' => (int)$account['server_port'],
            'location' => $account['location'] ?? 'Unknown',
            'status' => $account['status'],
            'expiration_status' => $account['expiration_status'],
            'plan_duration' => $account['plan_duration'] ? (int)$account['plan_duration'] : null,
            'expires_at' => $account['expires_at'],
            'days_remaining' => $account['days_remaining'] ? (int)$account['days_remaining'] : null,
            'created_at' => $account['created_at']
        ];
        
        // Add protocol-specific credentials
        switch ($account['server_type']) {
            case 'outline':
                $formattedAccount['access_key'] = $account['access_key'];
                $formattedAccount['protocol'] = 'shadowsocks';
                break;
                
            case 'sstp':
                $formattedAccount['username'] = $account['account_username'];
                $formattedAccount['password'] = $account['account_password'];
                $formattedAccount['protocol'] = 'sstp';
                break;
                
            case 'v2ray':
                $formattedAccount['access_key'] = $account['access_key'];
                $formattedAccount['protocol'] = 'vmess';
                // Parse V2Ray config if needed
                $configData = json_decode($account['config_data'], true);
                if ($configData) {
                    $formattedAccount['v2ray_config'] = $configData;
                }
                break;
        }
        
        $formattedAccounts[] = $formattedAccount;
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'count' => count($formattedAccounts),
        'accounts' => $formattedAccounts
    ]);
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

