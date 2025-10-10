<?php
require_once __DIR__ . '/../config/config.php';
requireLogin('admin');

header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();

$customerId = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

if ($customerId > 0) {
    try {
        $stmt = $conn->prepare("SELECT id, staff_name, status FROM client_accounts WHERE customer_id = ? AND status = 'active' ORDER BY staff_name");
        $stmt->execute([$customerId]);
        $clients = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'clients' => $clients
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error loading clients: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid customer ID'
    ]);
}

