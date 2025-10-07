<?php
require_once __DIR__ . '/../config/config.php';
requireLogin('customer');

$db = new Database();
$conn = $db->getConnection();

require_once __DIR__ . '/../includes/vpn_handler.php';
$vpnHandler = new VPNHandler($conn);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$accountId = intval($_POST['account_id'] ?? 0);
$newStatus = $_POST['status'] ?? '';

if (!in_array($newStatus, ['active', 'suspended', 'disabled'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // Get VPN account details
    $stmt = $conn->prepare("SELECT va.*, vs.server_type, vs.api_url 
        FROM vpn_accounts va 
        JOIN vpn_servers vs ON va.server_id = vs.id 
        WHERE va.id = ? AND va.customer_id = ?");
    $stmt->execute([$accountId, $_SESSION['customer_id']]);
    $account = $stmt->fetch();
    
    if (!$account) {
        echo json_encode(['success' => false, 'message' => 'VPN account not found']);
        exit;
    }
    
    $oldStatus = $account['status'] ?? 'active';
    
    // If disabling/suspending Outline account, delete from server
    if ($account['server_type'] === 'outline' && 
        $oldStatus === 'active' && 
        ($newStatus === 'suspended' || $newStatus === 'disabled')) {
        
        $configData = json_decode($account['config_data'], true);
        if (isset($configData['id']) && !empty($account['api_url'])) {
            try {
                $vpnHandler->deleteOutlineAccessKey([
                    'api_url' => $account['api_url']
                ], $configData['id']);
                
                // Return pool credential if applicable
                if ($account['pool_credential_id']) {
                    $stmt = $conn->prepare("UPDATE vpn_credentials_pool 
                        SET is_assigned = 0, assigned_to = NULL, assigned_at = NULL 
                        WHERE id = ?");
                    $stmt->execute([$account['pool_credential_id']]);
                }
                
                // Delete from database since Outline key no longer exists
                $stmt = $conn->prepare("DELETE FROM vpn_accounts WHERE id = ?");
                $stmt->execute([$accountId]);
                
                // Update server count
                $stmt = $conn->prepare("UPDATE vpn_servers SET current_accounts = GREATEST(current_accounts - 1, 0) WHERE id = ?");
                $stmt->execute([$account['server_id']]);
                
                logActivity($conn, 'customer', $_SESSION['customer_id'], 'disable_outline_vpn', "Disabled and deleted Outline VPN account ID: $accountId");
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Outline VPN account disabled and deleted from server',
                    'deleted' => true
                ]);
                exit;
            } catch(Exception $e) {
                error_log("Failed to delete Outline key: " . $e->getMessage());
            }
        }
    }
    
    // For SSTP/V2Ray or Outline re-activation, just update status
    $stmt = $conn->prepare("UPDATE vpn_accounts SET status = ? WHERE id = ? AND customer_id = ?");
    $stmt->execute([$newStatus, $accountId, $_SESSION['customer_id']]);
    
    logActivity($conn, 'customer', $_SESSION['customer_id'], 'toggle_vpn_status', "Changed VPN account ID: $accountId status to: $newStatus");
    
    // If suspending/disabling SSTP/V2Ray, notify admin
    if ($oldStatus === 'active' && ($newStatus === 'suspended' || $newStatus === 'disabled') && 
        in_array($account['server_type'], ['sstp', 'v2ray'])) {
        
        // Get customer and client info
        $stmt = $conn->prepare("SELECT c.company_name, cl.staff_name 
            FROM customers c 
            LEFT JOIN client_accounts cl ON cl.id = ? 
            WHERE c.id = ?");
        $stmt->execute([$account['staff_id'], $_SESSION['customer_id']]);
        $info = $stmt->fetch();
        
        try {
            $stmt = $conn->prepare("INSERT INTO admin_notifications 
                (notification_type, severity, title, message, related_customer_id, action_required) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                'vpn_manual_action',
                'info',
                'VPN Account Manually Disabled',
                "Customer '{$info['company_name']}' manually disabled a {$account['server_type']} VPN account for client '{$info['staff_name']}'.",
                $_SESSION['customer_id'],
                "Server: {$account['server_name']}\nType: {$account['server_type']}\nUsername: {$account['account_username']}\nAction: Please disable on server if needed."
            ]);
        } catch(Exception $e) {
            error_log("Failed to create notification: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'VPN account status updated successfully',
        'new_status' => $newStatus
    ]);
    
} catch(Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
}

