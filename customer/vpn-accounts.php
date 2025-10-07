<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/radius.php';
require_once __DIR__ . '/../includes/vpn_handler.php';
requireLogin('customer');

$db = new Database();
$conn = $db->getConnection();
$vpnHandler = new VPNHandler($conn);

$message = '';
$messageType = '';

// Get customer info for limits
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$_SESSION['customer_id']]);
$customer = $stmt->fetch();

// Handle create VPN account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $clientId = intval($_POST['client_id']);
    $serverId = intval($_POST['server_id']);
    
    // Check total VPN accounts for this customer (if limit set)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM vpn_accounts WHERE customer_id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    $totalVpnAccounts = $stmt->fetch()['total'];
    
    // Check customer's total VPN limit
    if ($customer['max_total_vpn_accounts'] !== null && $totalVpnAccounts >= $customer['max_total_vpn_accounts']) {
        $message = 'Your company has reached the maximum total VPN accounts allowed (' . $customer['max_total_vpn_accounts'] . ' total VPN accounts).';
        $messageType = 'error';
    } else {
    
    // Check VPN accounts for this specific client
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM vpn_accounts WHERE staff_id = ?");
    $stmt->execute([$clientId]);
    $clientVpnAccounts = $stmt->fetch()['total'];
    
    // Get client's custom limit or use customer default
    $stmt = $conn->prepare("SELECT max_vpn_accounts FROM client_accounts WHERE id = ?");
    $stmt->execute([$clientId]);
    $clientData = $stmt->fetch();
    $clientMaxVpn = $clientData['max_vpn_accounts'] ?? $customer['max_vpn_per_client'];
    
    // Check limits (only if a limit is set)
    if ($clientMaxVpn !== null && $clientVpnAccounts >= $clientMaxVpn) {
        $message = 'This client has reached the maximum VPN accounts allowed (' . $clientMaxVpn . ' VPN accounts).';
        $messageType = 'error';
    } else {
        $result = $vpnHandler->createVPNAccount($_SESSION['customer_id'], $clientId, $serverId);
        
        if ($result['success']) {
            logActivity($conn, 'customer', $_SESSION['customer_id'], 'create_vpn_account', "Created VPN account for client ID: $clientId");
            $message = $result['message'];
            $messageType = 'success';
        } else {
            $message = $result['message'];
            $messageType = 'error';
        }
    }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $accountId = intval($_GET['delete']);
    try {
        // Get account details before deleting
        $stmt = $conn->prepare("SELECT va.*, vs.server_type, vs.api_url 
            FROM vpn_accounts va 
            JOIN vpn_servers vs ON va.server_id = vs.id 
            WHERE va.id = ? AND va.customer_id = ?");
        $stmt->execute([$accountId, $_SESSION['customer_id']]);
        $account = $stmt->fetch();
        
        if ($account) {
            // Try to delete from actual VPN server
            if ($account['server_type'] === 'outline' && !empty($account['api_url'])) {
                // Delete from Outline server via API
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
                    error_log("Deleted RADIUS user: " . $account['account_username']);
                }
            }
            
            // If credential was from pool, mark as available again
            if ($account['pool_credential_id']) {
                $stmt = $conn->prepare("UPDATE vpn_credentials_pool 
                    SET is_assigned = 0, assigned_to = NULL, assigned_at = NULL 
                    WHERE id = ?");
                $stmt->execute([$account['pool_credential_id']]);
            }
            
            // Delete account from database
            $stmt = $conn->prepare("DELETE FROM vpn_accounts WHERE id = ? AND customer_id = ?");
            $stmt->execute([$accountId, $_SESSION['customer_id']]);
            
            // Update server count
            $stmt = $conn->prepare("UPDATE vpn_servers SET current_accounts = GREATEST(current_accounts - 1, 0) WHERE id = ?");
            $stmt->execute([$account['server_id']]);
            
            logActivity($conn, 'customer', $_SESSION['customer_id'], 'delete_vpn_account', "Deleted VPN account ID: $accountId");
            $message = 'VPN account deleted successfully! Credential returned to pool.';
            $messageType = 'success';
        }
    } catch(Exception $e) {
        $message = 'Failed to delete VPN account.';
        $messageType = 'error';
        error_log($e->getMessage());
    }
}

// Get all VPN accounts for this customer with status
$stmt = $conn->prepare("SELECT va.*, vs.server_name, vs.server_type, vs.server_host, vs.server_port, s.staff_name, s.status as client_status 
    FROM vpn_accounts va 
    JOIN vpn_servers vs ON va.server_id = vs.id 
    JOIN client_accounts s ON va.staff_id = s.id 
    WHERE va.customer_id = ? 
    ORDER BY va.created_at DESC");
$stmt->execute([$_SESSION['customer_id']]);
$vpnAccounts = $stmt->fetchAll();

// Get client list for dropdown with VPN count and custom limits
$stmt = $conn->prepare("SELECT c.id, c.staff_name, c.max_vpn_accounts,
    (SELECT COUNT(*) FROM vpn_accounts WHERE staff_id = c.id) as vpn_count 
    FROM client_accounts c 
    WHERE c.customer_id = ? AND c.status = 'active' 
    ORDER BY c.staff_name");
$stmt->execute([$_SESSION['customer_id']]);
$clientList = $stmt->fetchAll();

// Get active servers
$stmt = $conn->query("SELECT * FROM vpn_servers WHERE status = 'active' ORDER BY server_name");
$servers = $stmt->fetchAll();

$pageTitle = 'VPN Accounts - ' . SITE_NAME;
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
                    <h1 class="page-title">VPN Accounts</h1>
                </div>
                <button class="btn btn-primary" onclick="openModal('createVPNModal')">🔑 Create VPN Account</button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">All VPN Accounts (<?php echo count($vpnAccounts); ?>)</h2>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Staff Name</th>
                                <th>Server</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($vpnAccounts) > 0): ?>
                                <?php foreach ($vpnAccounts as $account): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($account['staff_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($account['server_name']); ?></td>
                                        <td>
                                            <span class="server-type-icon server-type-<?php echo $account['server_type']; ?>">
                                                <?php echo strtoupper($account['server_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($account['created_at']); ?></td>
                                        <td>
                                            <?php
                                            $badgeClass = 'badge-success';
                                            if ($account['status'] === 'suspended') $badgeClass = 'badge-warning';
                                            if ($account['status'] === 'inactive') $badgeClass = 'badge-danger';
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>">
                                                <?php echo ucfirst($account['status']); ?>
                                            </span>
                                        </td>
                                        <td class="action-buttons">
                                            <button class="btn btn-small btn-success" onclick="viewCredentials(<?php echo $account['id']; ?>)">📋 View</button>
                                            <button class="btn btn-small btn-info" onclick="shareAccount(<?php echo $account['id']; ?>)">📤 Share</button>
                                            <a href="?delete=<?php echo $account['id']; ?>" class="btn btn-small btn-danger" onclick="return confirmDelete('Are you sure you want to delete this VPN account?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <div class="empty-state-icon">🔑</div>
                                        <p>No VPN accounts yet. Click "Create VPN Account" to get started.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create VPN Account Modal -->
    <div id="createVPNModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create VPN Account</h2>
                <span class="modal-close" onclick="closeModal('createVPNModal')">&times;</span>
            </div>
            
            <?php if (count($clientList) === 0): ?>
                <div class="alert alert-warning">
                    You need to add clients first before creating VPN accounts.
                    <br><br>
                    <a href="/customer/clients.php" class="btn btn-primary">Go to Client Management</a>
                </div>
            <?php elseif (count($servers) === 0): ?>
                <div class="alert alert-warning">
                    No active VPN servers available. Please contact administrator.
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label for="client_id">Select Client *</label>
                        <select name="client_id" required>
                            <option value="">-- Choose Client --</option>
                            <?php foreach ($clientList as $client): ?>
                                <?php 
                                // Use client's custom limit or customer default
                                $clientLimit = $client['max_vpn_accounts'] ?? $customer['max_vpn_per_client'];
                                $isLimitReached = ($clientLimit !== null) && ($client['vpn_count'] >= $clientLimit);
                                
                                if ($clientLimit === null) {
                                    $limitText = ' (' . $client['vpn_count'] . ' / unlimited)';
                                } else {
                                    $limitText = $isLimitReached ? ' (Limit Reached: ' . $client['vpn_count'] . '/' . $clientLimit . ')' : ' (' . $client['vpn_count'] . '/' . $clientLimit . ')';
                                }
                                ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo $isLimitReached ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($client['staff_name']) . $limitText; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #64748b; margin-top: 5px; display: block;">
                            <?php if ($customer['max_vpn_per_client']): ?>
                                Default: <?php echo $customer['max_vpn_per_client']; ?> VPN account(s) per client (can be customized per client)
                            <?php else: ?>
                                No default limit (can be set per client)
                            <?php endif; ?>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="server_id">Select VPN Server *</label>
                        <select name="server_id" required onchange="showServerInfo(this)">
                            <option value="">-- Choose Server --</option>
                            <?php foreach ($servers as $server): ?>
                                <option value="<?php echo $server['id']; ?>" 
                                    data-type="<?php echo $server['server_type']; ?>"
                                    data-name="<?php echo htmlspecialchars($server['server_name']); ?>"
                                    data-location="<?php echo htmlspecialchars($server['location']); ?>"
                                    data-capacity="<?php echo $server['current_accounts'] . '/' . $server['max_accounts']; ?>">
                                    <?php echo htmlspecialchars($server['server_name']); ?> 
                                    (<?php echo strtoupper($server['server_type']); ?>) - 
                                    <?php echo $server['location']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="serverInfo" style="display:none; margin-top: 15px; padding: 15px; background: #f8fafc; border-radius: 8px;">
                        <h4 style="margin-bottom: 10px;">Server Information</h4>
                        <p><strong>Type:</strong> <span id="infoType"></span></p>
                        <p><strong>Location:</strong> <span id="infoLocation"></span></p>
                        <p><strong>Capacity:</strong> <span id="infoCapacity"></span></p>
                    </div>
                    
                    <div class="alert alert-info" style="margin-top: 20px;">
                        <strong>Note:</strong> VPN credentials will be automatically generated based on the server type selected.
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Create VPN Account</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- View Credentials Modal -->
    <div id="viewCredentialsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>VPN Account Credentials</h2>
                <span class="modal-close" onclick="closeModal('viewCredentialsModal')">&times;</span>
            </div>
            <div id="credentialsContent">
                <!-- Content will be loaded via JavaScript -->
            </div>
        </div>
    </div>
    
    <script src="/assets/js/main.js"></script>
    <script>
    function showServerInfo(select) {
        const option = select.options[select.selectedIndex];
        if (option.value) {
            document.getElementById('serverInfo').style.display = 'block';
            document.getElementById('infoType').textContent = option.dataset.type.toUpperCase();
            document.getElementById('infoLocation').textContent = option.dataset.location;
            document.getElementById('infoCapacity').textContent = option.dataset.capacity;
        } else {
            document.getElementById('serverInfo').style.display = 'none';
        }
    }
    
    function viewCredentials(accountId) {
        fetch('view-credentials.php?id=' + accountId)
            .then(response => response.text())
            .then(html => {
                document.getElementById('credentialsContent').innerHTML = html;
                openModal('viewCredentialsModal');
            })
            .catch(error => {
                alert('Failed to load credentials');
                console.error(error);
            });
    }
    
    function shareAccount(accountId) {
        viewCredentials(accountId);
    }
    
    function copyCredential(text) {
        copyToClipboard(text);
    }
    </script>
</body>
</html>

