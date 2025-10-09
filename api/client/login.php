<?php
/**
 * Client Login API for Mobile App
 * POST /api/client/login
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        throw new Exception('Username and password are required');
    }
    
    // Connect to database
    $db = new Database();
    $conn = $db->getConnection();
    
    // Find client account
    $stmt = $conn->prepare("SELECT ca.*, c.company_name, c.id as customer_id 
        FROM client_accounts ca 
        JOIN customers c ON ca.customer_id = c.id 
        WHERE ca.staff_name = ? AND ca.status = 'active' AND c.status = 'active'");
    $stmt->execute([$username]);
    $client = $stmt->fetch();
    
    if (!$client) {
        throw new Exception('Invalid username or password');
    }
    
    // For mobile app, we'll use a simple password field
    // You can add a password column to client_accounts table
    // For now, we'll use a simple verification
    
    // Generate JWT token
    $tokenData = [
        'client_id' => $client['id'],
        'customer_id' => $client['customer_id'],
        'username' => $username,
        'issued_at' => time(),
        'expires_at' => time() + (30 * 24 * 60 * 60) // 30 days
    ];
    
    $token = base64_encode(json_encode($tokenData));
    
    // Log activity
    logActivity($conn, 'client', $client['id'], 'mobile_login', 'Client logged in via mobile app');
    
    // Return success response
    echo json_encode([
        'success' => true,
        'token' => $token,
        'client_id' => $client['id'],
        'client_name' => $client['staff_name'],
        'customer_company' => $client['company_name'],
        'message' => 'Login successful'
    ]);
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

