<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../config/config.php';
requireLogin('customer');

$db = new Database();
$conn = $db->getConnection();

// Initialize VPN handler for suspension logic
require_once __DIR__ . '/../includes/vpn_handler.php';
$vpnHandler = new VPNHandler($conn);

$message = '';
$messageType = '';

// Get customer info
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$_SESSION['customer_id']]);
$customer = $stmt->fetch();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // Check if customer has reached max client limit
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM client_accounts WHERE customer_id = ?");
        $stmt->execute([$_SESSION['customer_id']]);
        $currentClientCount = $stmt->fetch()['count'];
        
        // Only check limit if max_clients is set (not NULL = unlimited)
        if ($customer['max_clients'] !== null && $currentClientCount >= $customer['max_clients']) {
            $message = 'You have reached the maximum number of client accounts allowed (' . $customer['max_clients'] . ' clients).';
            $messageType = 'error';
        } else {
            $clientName = sanitize($_POST['client_name']);
            $clientEmail = sanitize($_POST['client_email'] ?? '');
            $clientPhone = sanitize($_POST['client_phone'] ?? '');
            $department = sanitize($_POST['department'] ?? '');
            $notes = sanitize($_POST['notes'] ?? '');
            $maxVpnAccounts = !empty($_POST['max_vpn_accounts']) ? intval($_POST['max_vpn_accounts']) : NULL;
            
            try {
                $stmt = $conn->prepare("INSERT INTO client_accounts (customer_id, staff_name, staff_email, staff_phone, department, notes, max_vpn_accounts) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$_SESSION['customer_id'], $clientName, $clientEmail, $clientPhone, $department, $notes, $maxVpnAccounts]);
                
                logActivity($conn, 'customer', $_SESSION['customer_id'], 'add_client', "Added client: $clientName");
                $message = 'Client member added successfully!';
                $messageType = 'success';
            } catch(Exception $e) {
                $message = 'Failed to add client member. Please try again.';
                $messageType = 'error';
                error_log($e->getMessage());
            }
        }
    } elseif ($action === 'edit') {
        $clientId = intval($_POST['client_id']);
        $clientName = sanitize($_POST['client_name']);
        $clientEmail = sanitize($_POST['client_email'] ?? '');
        $clientPhone = sanitize($_POST['client_phone'] ?? '');
        $department = sanitize($_POST['department'] ?? '');
        $newStatus = sanitize($_POST['status'] ?? 'active');
        $notes = sanitize($_POST['notes'] ?? '');
        $maxVpnAccounts = !empty($_POST['max_vpn_accounts']) ? intval($_POST['max_vpn_accounts']) : NULL;
        
        try {
            // Get current status before update
            $stmt = $conn->prepare("SELECT status, staff_name FROM client_accounts WHERE id = ? AND customer_id = ?");
            $stmt->execute([$clientId, $_SESSION['customer_id']]);
            $currentClient = $stmt->fetch();
            $oldStatus = $currentClient['status'] ?? 'active';
            
            // Update client
            $stmt = $conn->prepare("UPDATE client_accounts SET staff_name=?, staff_email=?, staff_phone=?, department=?, status=?, notes=?, max_vpn_accounts=? WHERE id=? AND customer_id=?");
            $stmt->execute([$clientName, $clientEmail, $clientPhone, $department, $newStatus, $notes, $maxVpnAccounts, $clientId, $_SESSION['customer_id']]);
            
            // If status changed, update all VPN account statuses
            if ($oldStatus !== $newStatus) {
                // Update all VPN accounts for this client to match client status
                $stmt = $conn->prepare("UPDATE vpn_accounts SET status = ? WHERE staff_id = ?");
                $stmt->execute([$newStatus, $clientId]);
            }
            
            // If status changed to suspended or disabled, handle VPN accounts
            if ($oldStatus === 'active' && ($newStatus === 'suspended' || $newStatus === 'disabled')) {
                // Get all VPN accounts for this client
                $stmt = $conn->prepare("SELECT va.*, vs.server_type, vs.server_name, vs.api_url 
                    FROM vpn_accounts va 
                    JOIN vpn_servers vs ON va.server_id = vs.id 
                    WHERE va.staff_id = ?");
                $stmt->execute([$clientId]);
                $vpnAccounts = $stmt->fetchAll();
                
                $outlineDeleted = 0;
                $sstpAccounts = [];
                $v2rayAccounts = [];
                
                foreach ($vpnAccounts as $account) {
                    if ($account['server_type'] === 'outline' && !empty($account['api_url'])) {
                        // Automatically delete Outline accounts
                        $configData = json_decode($account['config_data'], true);
                        if (isset($configData['id'])) {
                            try {
                                $vpnHandler->deleteOutlineAccessKey([
                                    'api_url' => $account['api_url']
                                ], $configData['id']);
                                $outlineDeleted++;
                                
                                // Return credential to pool if applicable
                                if ($account['pool_credential_id']) {
                                    $stmt = $conn->prepare("UPDATE vpn_credentials_pool 
                                        SET is_assigned = 0, assigned_to = NULL, assigned_at = NULL 
                                        WHERE id = ?");
                                    $stmt->execute([$account['pool_credential_id']]);
                                }
                                
                                // Delete from database
                                $stmt = $conn->prepare("DELETE FROM vpn_accounts WHERE id = ?");
                                $stmt->execute([$account['id']]);
                                
                                // Update server count
                                $stmt = $conn->prepare("UPDATE vpn_servers SET current_accounts = GREATEST(current_accounts - 1, 0) WHERE id = ?");
                                $stmt->execute([$account['server_id']]);
                            } catch(Exception $e) {
                                error_log("Failed to delete Outline key: " . $e->getMessage());
                            }
                        }
                    } elseif ($account['server_type'] === 'sstp') {
                        $sstpAccounts[] = [
                            'server' => $account['server_name'],
                            'username' => $account['account_username']
                        ];
                    } elseif ($account['server_type'] === 'v2ray') {
                        $v2rayAccounts[] = [
                            'server' => $account['server_name'],
                            'uuid' => $account['access_key']
                        ];
                    }
                }
                
                // Create admin notification for SSTP/V2Ray accounts
                if (!empty($sstpAccounts) || !empty($v2rayAccounts)) {
                    $actionRequired = "Client '{$clientName}' has been " . ($newStatus === 'suspended' ? 'suspended' : 'disabled') . ".\n\n";
                    
                    if (!empty($sstpAccounts)) {
                        $actionRequired .= "SSTP Accounts to disable manually:\n";
                        foreach ($sstpAccounts as $acc) {
                            $actionRequired .= "  - Server: {$acc['server']}, Username: {$acc['username']}\n";
                        }
                        $actionRequired .= "\n";
                    }
                    
                    if (!empty($v2rayAccounts)) {
                        $actionRequired .= "V2Ray Accounts to disable manually:\n";
                        foreach ($v2rayAccounts as $acc) {
                            $actionRequired .= "  - Server: {$acc['server']}, UUID: {$acc['uuid']}\n";
                        }
                    }
                    
                    // Get customer info
                    $stmt = $conn->prepare("SELECT company_name FROM customers WHERE id = ?");
                    $stmt->execute([$_SESSION['customer_id']]);
                    $customer = $stmt->fetch();
                    
                    // Check if notifications table exists
                    try {
                        $stmt = $conn->prepare("INSERT INTO admin_notifications 
                            (notification_type, severity, title, message, related_customer_id, related_client_id, action_required) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            'client_' . $newStatus,
                            'warning',
                            'Manual VPN Account Deactivation Required',
                            "Client '{$clientName}' from company '{$customer['company_name']}' has been {$newStatus}. Manual server-side deactivation required for SSTP/V2Ray accounts.",
                            $_SESSION['customer_id'],
                            $clientId,
                            $actionRequired
                        ]);
                    } catch(Exception $e) {
                        error_log("Failed to create notification (table may not exist): " . $e->getMessage());
                    }
                }
                
                $statusMsg = "";
                if ($outlineDeleted > 0) {
                    $statusMsg .= " {$outlineDeleted} Outline account(s) automatically deleted.";
                }
                if (!empty($sstpAccounts) || !empty($v2rayAccounts)) {
                    $statusMsg .= " Admin notified to manually disable " . count($sstpAccounts) . " SSTP and " . count($v2rayAccounts) . " V2Ray accounts.";
                }
                
                $message = "Client {$newStatus} successfully!{$statusMsg}";
            } else {
                $message = 'Client member updated successfully!';
            }
            
            logActivity($conn, 'customer', $_SESSION['customer_id'], 'edit_client', "Edited client ID: $clientId, Status: $newStatus");
            $messageType = 'success';
        } catch(Exception $e) {
            $message = 'Failed to update client member. Please try again.';
            $messageType = 'error';
            error_log($e->getMessage());
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $clientId = intval($_GET['delete']);
    try {
        // Get all VPN accounts for this client
        $stmt = $conn->prepare("SELECT va.id, va.account_username, va.pool_credential_id, va.server_id, vs.server_type, vs.api_url, va.config_data 
            FROM vpn_accounts va 
            JOIN vpn_servers vs ON va.server_id = vs.id 
            WHERE va.staff_id = ?");
        $stmt->execute([$clientId]);
        $vpnAccounts = $stmt->fetchAll();
        
        // Delete each VPN account and return pool credentials
        foreach ($vpnAccounts as $account) {
            // Try to delete from Outline server
            if ($account['server_type'] === 'outline' && !empty($account['api_url'])) {
                $configData = json_decode($account['config_data'], true);
                if (isset($configData['id'])) {
                    $vpnHandler->deleteOutlineAccessKey([
                        'api_url' => $account['api_url']
                    ], $configData['id']);
                }
            }
            
            // Delete from RADIUS if SSTP or V2Ray with RADIUS credentials
            if (($account['server_type'] === 'sstp' || $account['server_type'] === 'v2ray') && 
                !empty($account['account_username'])) {
                if (defined('RADIUS_ENABLED') && RADIUS_ENABLED === true) {
                    require_once __DIR__ . '/../includes/radius_handler.php';
                    $radiusHandler = new RadiusHandler();
                    $radiusHandler->deleteUser($account['account_username']);
                    error_log("Deleted RADIUS user on client delete: " . $account['account_username']);
                }
            }
            
            // Return pool credential to available
            if ($account['pool_credential_id']) {
                $stmt = $conn->prepare("UPDATE vpn_credentials_pool 
                    SET is_assigned = 0, assigned_to = NULL, assigned_at = NULL 
                    WHERE id = ?");
                $stmt->execute([$account['pool_credential_id']]);
            }
            
            // Update server count
            $stmt = $conn->prepare("UPDATE vpn_servers SET current_accounts = GREATEST(current_accounts - 1, 0) WHERE id = ?");
            $stmt->execute([$account['server_id']]);
        }
        
        // Delete all VPN accounts for this client
        $stmt = $conn->prepare("DELETE FROM vpn_accounts WHERE staff_id = ?");
        $stmt->execute([$clientId]);
        
        // Delete the client
        $stmt = $conn->prepare("DELETE FROM client_accounts WHERE id = ? AND customer_id = ?");
        $stmt->execute([$clientId, $_SESSION['customer_id']]);
        
        logActivity($conn, 'customer', $_SESSION['customer_id'], 'delete_client', "Deleted client ID: $clientId and " . count($vpnAccounts) . " VPN accounts");
        $message = 'Client deleted successfully! ' . count($vpnAccounts) . ' VPN account(s) removed and credentials returned to pool.';
        $messageType = 'success';
    } catch(Exception $e) {
        $message = 'Failed to delete client member. Please try again.';
        $messageType = 'error';
        error_log($e->getMessage());
    }
}

// Get all client with their VPN account count
$stmt = $conn->prepare("SELECT s.*, 
    (SELECT COUNT(*) FROM vpn_accounts WHERE staff_id = s.id) as vpn_count
    FROM client_accounts s WHERE s.customer_id = ? ORDER BY s.created_at DESC");
$stmt->execute([$_SESSION['customer_id']]);
$client = $stmt->fetchAll();

$pageTitle = 'My Client - ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include __DIR__ . '/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div>
                    <div class="hamburger">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <h1 class="page-title">My Client</h1>
                </div>
                <button class="btn btn-primary" onclick="openModal('addClientModal')">+ Add Client</button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Client Members (<?php echo count($client); ?><?php echo $customer['max_clients'] !== null ? ' / ' . $customer['max_clients'] : ' / unlimited'; ?>)</h2>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Department</th>
                                <th>VPN Accounts</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($client) > 0): ?>
                                <?php foreach ($client as $member): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($member['staff_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($member['staff_email']); ?></td>
                                        <td><?php echo htmlspecialchars($member['staff_phone']); ?></td>
                                        <td><?php echo htmlspecialchars($member['department']); ?></td>
                                        <td><span class="badge badge-info"><?php echo $member['vpn_count']; ?></span></td>
                                        <td>
                                            <?php
                                            $badgeClass = 'badge-success';
                                            if ($member['status'] === 'suspended') $badgeClass = 'badge-warning';
                                            if ($member['status'] === 'inactive') $badgeClass = 'badge-danger';
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>">
                                                <?php echo ucfirst($member['status']); ?>
                                            </span>
                                        </td>
                                        <td class="action-buttons">
                                            <button class="btn btn-small btn-secondary" onclick='editClient(<?php echo json_encode($member); ?>)'>Edit</button>
                                            <a href="?delete=<?php echo $member['id']; ?>" class="btn btn-small btn-danger" onclick="return confirmDelete('Are you sure you want to delete this client member?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <div class="empty-state-icon">👤</div>
                                        <p>No client members yet. Click "Add Client" to get started.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Client Modal -->
    <div id="addClientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Client Member</h2>
                <span class="modal-close" onclick="closeModal('addClientModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label for="client_name">Client Name *</label>
                    <input type="text" name="client_name" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="client_email">Email</label>
                        <input type="email" name="client_email">
                    </div>
                    <div class="form-group">
                        <label for="client_phone">Phone</label>
                        <input type="text" name="client_phone">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="department">Department</label>
                    <input type="text" name="department" placeholder="e.g., IT, Sales, Marketing">
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea name="notes" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="max_vpn_accounts">Max VPN Accounts for This Client</label>
                    <input type="number" name="max_vpn_accounts" min="1" max="100" placeholder="Leave empty to use default (<?php echo $customer['max_vpn_per_client'] ?? 'unlimited'; ?>)">
                    <small style="color: #64748b; margin-top: 5px; display: block;">
                        Custom limit for this client only. Leave empty to use company default: <?php echo $customer['max_vpn_per_client'] ?? 'unlimited'; ?> VPN accounts
                    </small>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Add Client</button>
            </form>
        </div>
    </div>
    
    <!-- Edit Client Modal -->
    <div id="editClientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Client Member</h2>
                <span class="modal-close" onclick="closeModal('editClientModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="client_id" id="edit_client_id">
                
                <div class="form-group">
                    <label for="edit_client_name">Client Name *</label>
                    <input type="text" name="client_name" id="edit_client_name" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_client_email">Email</label>
                        <input type="email" name="client_email" id="edit_client_email">
                    </div>
                    <div class="form-group">
                        <label for="edit_client_phone">Phone</label>
                        <input type="text" name="client_phone" id="edit_client_phone">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_department">Department</label>
                        <input type="text" name="department" id="edit_department">
                    </div>
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select name="status" id="edit_status">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="disabled">Disabled</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_notes">Notes</label>
                    <textarea name="notes" id="edit_notes" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_max_vpn_accounts">Max VPN Accounts for This Client</label>
                    <input type="number" name="max_vpn_accounts" id="edit_max_vpn_accounts" min="1" max="100" placeholder="Leave empty for default">
                    <small style="color: #64748b; margin-top: 5px; display: block;">
                        Custom limit for this client. Leave empty to use company default: <?php echo $customer['max_vpn_per_client'] ?? 'unlimited'; ?> VPN accounts
                    </small>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Update Client</button>
            </form>
        </div>
    </div>
    
    <script src="/assets/js/main.js"></script>
    <script>
    function editClient(client) {
        document.getElementById('edit_client_id').value = client.id;
        document.getElementById('edit_client_name').value = client.staff_name;
        document.getElementById('edit_client_email').value = client.staff_email || '';
        document.getElementById('edit_client_phone').value = client.staff_phone || '';
        document.getElementById('edit_department').value = client.department || '';
        document.getElementById('edit_status').value = client.status;
        document.getElementById('edit_notes').value = client.notes || '';
        document.getElementById('edit_max_vpn_accounts').value = client.max_vpn_accounts || '';
        openModal('editClientModal');
    }
    </script>
</body>
</html>

