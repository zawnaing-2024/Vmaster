<?php
require_once __DIR__ . '/../includes/language.php';
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
    $planType = $_POST['plan_type'] ?? 'unlimited';
    $planDuration = null;
    $customExpiryDate = null;
    
    // Handle different plan types
    if ($planType === 'preset' && isset($_POST['plan_duration']) && $_POST['plan_duration'] !== '') {
        $planDuration = intval($_POST['plan_duration']);
    } elseif ($planType === 'custom' && isset($_POST['custom_end_date']) && $_POST['custom_end_date'] !== '') {
        $customExpiryDate = $_POST['custom_end_date'];
        // Calculate duration in months for display purposes
        $startDate = isset($_POST['custom_start_date']) && $_POST['custom_start_date'] !== '' 
            ? new DateTime($_POST['custom_start_date']) 
            : new DateTime();
        $endDate = new DateTime($customExpiryDate);
        $interval = $startDate->diff($endDate);
        $planDuration = ($interval->y * 12) + $interval->m; // Convert to months for storage
    }
    
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
        $result = $vpnHandler->createVPNAccount($_SESSION['customer_id'], $clientId, $serverId, $planDuration, $customExpiryDate);
        
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
$stmt = $conn->prepare("SELECT va.*, vs.server_name, vs.server_type, vs.server_host, vs.server_port, s.staff_name, s.status as client_status,
    CASE 
        WHEN va.expires_at IS NULL THEN 'unlimited'
        WHEN va.expires_at > NOW() THEN 'active'
        ELSE 'expired'
    END as expiration_status
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

$pageTitle = t('vpn_accounts', 'customer') . ' - ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
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
                    <h1 class="page-title"><?php echo t('vpn_accounts', 'customer'); ?></h1>
                </div>
                <button class="btn btn-primary" onclick="openModal('createVPNModal')">🔑 <?php echo t('create_vpn_account', 'customer'); ?></button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?php echo t('all_vpn_accounts', 'customer'); ?> (<?php echo count($vpnAccounts); ?>)</h2>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th><?php echo t('staff_name', 'customer'); ?></th>
                                <th><?php echo t('server', 'customer'); ?></th>
                                <th><?php echo t('server_type', 'vpn'); ?></th>
                                <th><?php echo t('plan', 'vpn'); ?></th>
                                <th><?php echo t('expires', 'vpn'); ?></th>
                                <th><?php echo t('status', 'common'); ?></th>
                                <th><?php echo t('created', 'vpn'); ?></th>
                                <th><?php echo t('actions', 'common'); ?></th>
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
                                        <td>
                                            <?php 
                                            if (isset($account['plan_duration']) && $account['plan_duration']) {
                                                echo $account['plan_duration'] . ' month' . ($account['plan_duration'] > 1 ? 's' : '');
                                            } else {
                                                echo '<span style="color: #10b981;">' . t('unlimited', 'vpn') . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($account['expiration_status'] === 'unlimited') {
                                                echo '<span class="badge badge-success">' . t('never', 'vpn') . '</span>';
                                            } elseif ($account['expiration_status'] === 'expired') {
                                                echo '<span class="badge badge-danger">' . formatDate($account['expires_at']) . '</span>';
                                            } else {
                                                $daysLeft = ceil((strtotime($account['expires_at']) - time()) / 86400);
                                                $expiryBadge = $daysLeft <= 7 ? 'badge-warning' : 'badge-info';
                                                echo '<span class="badge ' . $expiryBadge . '">' . formatDate($account['expires_at']) . '</span>';
                                                echo '<br><small style="color: #64748b;">' . $daysLeft . ' days left</small>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $badgeClass = 'badge-success';
                                            if ($account['status'] === 'suspended') $badgeClass = 'badge-warning';
                                            if ($account['status'] === 'inactive') $badgeClass = 'badge-danger';
                                            if ($account['expiration_status'] === 'expired') $badgeClass = 'badge-danger';
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>">
                                                <?php echo $account['expiration_status'] === 'expired' ? t('expired', 'vpn') : t($account['status'], 'common'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatDate($account['created_at']); ?></td>
                                        <td class="action-buttons">
                                            <button class="btn btn-small btn-success" onclick="viewCredentials(<?php echo $account['id']; ?>)">📋 <?php echo t('view', 'vpn'); ?></button>
                                            <button class="btn btn-small btn-info" onclick="shareAccount(<?php echo $account['id']; ?>)">📤 <?php echo t('share', 'vpn'); ?></button>
                                            <a href="?delete=<?php echo $account['id']; ?>" class="btn btn-small btn-danger" onclick="return confirmDelete('Are you sure you want to delete this VPN account?')"><?php echo t('delete', 'common'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="empty-state">
                                        <div class="empty-state-icon">🔑</div>
                                        <p><?php echo t('no_vpn_accounts', 'vpn'); ?></p>
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
                <h2><?php echo t('create_vpn_account', 'customer'); ?></h2>
                <span class="modal-close" onclick="closeModal('createVPNModal')">&times;</span>
            </div>
            
            <?php if (count($clientList) === 0): ?>
                <div class="alert alert-warning">
                    <?php echo t('need_to_add_clients_first', 'customer'); ?>
                    <br><br>
                    <a href="/customer/clients.php" class="btn btn-primary"><?php echo t('go_to_client_management', 'customer'); ?></a>
                </div>
            <?php elseif (count($servers) === 0): ?>
                <div class="alert alert-warning">
                    <?php echo t('no_active_vpn_servers', 'vpn'); ?>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label for="client_id"><?php echo t('select_client', 'customer'); ?> *</label>
                        <select name="client_id" required>
                            <option value="">-- <?php echo t('choose_client', 'customer'); ?> --</option>
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
                                <?php echo t('default_limit', 'customer'); ?>: <?php echo $customer['max_vpn_per_client']; ?> <?php echo t('vpn_accounts_per_client', 'customer'); ?> (<?php echo t('can_be_customized', 'customer'); ?>)
                            <?php else: ?>
                                <?php echo t('no_default_limit', 'customer'); ?> (<?php echo t('can_be_set_per_client', 'customer'); ?>)
                            <?php endif; ?>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="server_id"><?php echo t('select_vpn_server', 'customer'); ?> *</label>
                        <select name="server_id" required onchange="showServerInfo(this)">
                            <option value="">-- <?php echo t('choose_server', 'customer'); ?> --</option>
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
                    
                    <div class="form-group">
                        <label for="plan_duration"><?php echo t('plan_duration', 'vpn'); ?></label>
                        <select name="plan_type" id="plan_type" onchange="toggleCustomPlan(this)">
                            <option value="unlimited"><?php echo t('unlimited_no_expiration', 'vpn'); ?></option>
                            <option value="preset"><?php echo t('preset_plans', 'vpn'); ?></option>
                            <option value="custom"><?php echo t('custom_date_range', 'vpn'); ?></option>
                        </select>
                    </div>
                    
                    <!-- Preset Plans -->
                    <div id="presetPlans" style="display: none;">
                        <div class="form-group">
                            <label for="plan_duration"><?php echo t('select_plan', 'vpn'); ?></label>
                            <select name="plan_duration" id="plan_duration" onchange="updatePlanInfo(this)">
                                <option value="">-- <?php echo t('choose_duration', 'vpn'); ?> --</option>
                                <option value="1"><?php echo t('1_month_plan', 'vpn'); ?></option>
                                <option value="2"><?php echo t('2_months_plan', 'vpn'); ?></option>
                                <option value="3"><?php echo t('3_months_plan', 'vpn'); ?></option>
                                <option value="6"><?php echo t('6_months_plan', 'vpn'); ?></option>
                                <option value="12"><?php echo t('1_year_plan', 'vpn'); ?></option>
                            </select>
                            <small style="color: #64748b; margin-top: 5px; display: block;">
                                <?php echo t('account_expires_after_duration', 'vpn'); ?>
                            </small>
                            <div id="planInfo" style="display: none; margin-top: 10px; padding: 10px; background: #e0f2fe; border-left: 3px solid #0284c7; border-radius: 4px;">
                                <strong>📅 <?php echo t('expiration_date', 'vpn'); ?>:</strong> <span id="expiryDate"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Custom Date Range -->
                    <div id="customPlan" style="display: none;">
                        <div class="form-group">
                            <label for="custom_start_date"><?php echo t('start_date', 'vpn'); ?></label>
                            <input type="date" name="custom_start_date" id="custom_start_date" onchange="updateCustomPlan()">
                            <small style="color: #64748b; margin-top: 5px; display: block;">
                                <?php echo t('leave_empty_to_start_today', 'vpn'); ?>
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="custom_end_date"><?php echo t('end_date', 'vpn'); ?> *</label>
                            <input type="date" name="custom_end_date" id="custom_end_date" onchange="updateCustomPlan()">
                            <small style="color: #64748b; margin-top: 5px; display: block;">
                                <?php echo t('choose_when_account_expires', 'vpn'); ?>
                            </small>
                        </div>
                        <div id="customPlanInfo" style="display: none; margin-top: 10px; padding: 10px; background: #fef3c7; border-left: 3px solid #f59e0b; border-radius: 4px;">
                            <strong>📅 <?php echo t('duration', 'vpn'); ?>:</strong> <span id="customDuration"></span><br>
                            <strong>🗓️ <?php echo t('expires', 'vpn'); ?>:</strong> <span id="customExpiry"></span>
                        </div>
                    </div>
                    
                    <div id="serverInfo" style="display:none; margin-top: 15px; padding: 15px; background: #f8fafc; border-radius: 8px;">
                        <h4 style="margin-bottom: 10px;"><?php echo t('server_information', 'vpn'); ?></h4>
                        <p><strong><?php echo t('type', 'vpn'); ?>:</strong> <span id="infoType"></span></p>
                        <p><strong><?php echo t('location', 'vpn'); ?>:</strong> <span id="infoLocation"></span></p>
                        <p><strong><?php echo t('capacity', 'vpn'); ?>:</strong> <span id="infoCapacity"></span></p>
                    </div>
                    
                    <div class="alert alert-info" style="margin-top: 20px;">
                        <strong><?php echo t('note', 'common'); ?>:</strong> <?php echo t('vpn_credentials_auto_generated', 'vpn'); ?>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block"><?php echo t('create_vpn_account', 'customer'); ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- View Credentials Modal -->
    <div id="viewCredentialsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?php echo t('vpn_account_credentials', 'vpn'); ?></h2>
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
    
    function toggleCustomPlan(select) {
        const planType = select.value;
        const presetPlans = document.getElementById('presetPlans');
        const customPlan = document.getElementById('customPlan');
        
        // Hide all sections first
        presetPlans.style.display = 'none';
        customPlan.style.display = 'none';
        
        // Show selected section
        if (planType === 'preset') {
            presetPlans.style.display = 'block';
        } else if (planType === 'custom') {
            customPlan.style.display = 'block';
            // Set min date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('custom_start_date').setAttribute('min', today);
            document.getElementById('custom_end_date').setAttribute('min', today);
        }
    }
    
    function updatePlanInfo(select) {
        const months = parseInt(select.value);
        const planInfo = document.getElementById('planInfo');
        const expiryDate = document.getElementById('expiryDate');
        
        if (months > 0) {
            const expiry = new Date();
            expiry.setMonth(expiry.getMonth() + months);
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            expiryDate.textContent = expiry.toLocaleDateString('en-US', options);
            planInfo.style.display = 'block';
        } else {
            planInfo.style.display = 'none';
        }
    }
    
    function updateCustomPlan() {
        const startDate = document.getElementById('custom_start_date').value;
        const endDate = document.getElementById('custom_end_date').value;
        const customPlanInfo = document.getElementById('customPlanInfo');
        const customDuration = document.getElementById('customDuration');
        const customExpiry = document.getElementById('customExpiry');
        
        if (endDate) {
            const start = startDate ? new Date(startDate) : new Date();
            const end = new Date(endDate);
            
            // Calculate duration in days
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            const diffMonths = Math.floor(diffDays / 30);
            const remainingDays = diffDays % 30;
            
            let durationText = '';
            if (diffMonths > 0) {
                durationText = diffMonths + ' month' + (diffMonths > 1 ? 's' : '');
                if (remainingDays > 0) {
                    durationText += ' and ' + remainingDays + ' day' + (remainingDays > 1 ? 's' : '');
                }
            } else {
                durationText = diffDays + ' day' + (diffDays > 1 ? 's' : '');
            }
            
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            customDuration.textContent = durationText + ' (' + diffDays + ' days total)';
            customExpiry.textContent = end.toLocaleDateString('en-US', options);
            customPlanInfo.style.display = 'block';
        } else {
            customPlanInfo.style.display = 'none';
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

