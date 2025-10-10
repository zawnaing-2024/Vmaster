<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/radius.php';
require_once __DIR__ . '/../includes/vpn_handler.php';
requireLogin('admin');

$db = new Database();
$conn = $db->getConnection();
$vpnHandler = new VPNHandler($conn);

$message = '';
$messageType = '';

// Handle create VPN account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $customerId = intval($_POST['customer_id']);
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
    
    $result = $vpnHandler->createVPNAccount($customerId, $clientId, $serverId, $planDuration, $customExpiryDate);
    
    if ($result['success']) {
        logActivity($conn, 'admin', $_SESSION['admin_id'], 'create_vpn_account', "Created VPN account for customer ID: $customerId, client ID: $clientId");
        $message = $result['message'];
        $messageType = 'success';
    } else {
        $message = $result['message'];
        $messageType = 'error';
    }
}

// Get all VPN accounts with full details
$stmt = $conn->query("SELECT va.*, vs.server_name, vs.server_type, c.company_name, s.staff_name,
    CASE 
        WHEN va.expires_at IS NULL THEN 'unlimited'
        WHEN va.expires_at > NOW() THEN 'active'
        ELSE 'expired'
    END as expiration_status
    FROM vpn_accounts va 
    JOIN vpn_servers vs ON va.server_id = vs.id 
    JOIN customers c ON va.customer_id = c.id
    JOIN client_accounts s ON va.staff_id = s.id 
    ORDER BY va.created_at DESC");
$vpnAccounts = $stmt->fetchAll();

// Get all active customers
$stmt = $conn->query("SELECT id, company_name FROM customers WHERE status = 'active' ORDER BY company_name");
$customers = $stmt->fetchAll();

// Get all active servers
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
                                <th>Company</th>
                                <th>Staff</th>
                                <th>Server</th>
                                <th>Type</th>
                                <th>Plan</th>
                                <th>Expires</th>
                                <th>Created</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($vpnAccounts) > 0): ?>
                                <?php foreach ($vpnAccounts as $account): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($account['company_name']); ?></td>
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
                                                echo '<span style="color: #10b981;">Unlimited</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($account['expiration_status'] === 'unlimited') {
                                                echo '<span class="badge badge-success">Never</span>';
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
                                        <td><?php echo formatDate($account['created_at']); ?></td>
                                        <td>
                                            <?php
                                            $badgeClass = 'badge-success';
                                            if ($account['status'] === 'suspended') $badgeClass = 'badge-warning';
                                            if ($account['status'] === 'inactive') $badgeClass = 'badge-danger';
                                            if ($account['expiration_status'] === 'expired') $badgeClass = 'badge-danger';
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>">
                                                <?php echo $account['expiration_status'] === 'expired' ? 'Expired' : ucfirst($account['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="empty-state">
                                        <div class="empty-state-icon">🔑</div>
                                        <p>No VPN accounts yet.</p>
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
            
            <?php if (count($customers) === 0): ?>
                <div class="alert alert-warning">
                    No active customers available.
                    <br><br>
                    <a href="/admin/customers.php" class="btn btn-primary">Go to Customer Management</a>
                </div>
            <?php elseif (count($servers) === 0): ?>
                <div class="alert alert-warning">
                    No active VPN servers available.
                    <br><br>
                    <a href="/admin/servers.php" class="btn btn-primary">Go to Server Management</a>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label for="customer_id">Select Customer *</label>
                        <select name="customer_id" id="customer_id" required onchange="loadCustomerClients(this.value)">
                            <option value="">-- Choose Customer --</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>">
                                    <?php echo htmlspecialchars($customer['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="client_id">Select Client *</label>
                        <select name="client_id" id="client_id" required disabled>
                            <option value="">-- Choose Customer First --</option>
                        </select>
                        <small style="color: #64748b; margin-top: 5px; display: block;">
                            Select a customer first to load their clients
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
                    
                    <div class="form-group">
                        <label for="plan_duration">Plan Duration</label>
                        <select name="plan_type" id="plan_type" onchange="toggleCustomPlan(this)">
                            <option value="unlimited">Unlimited (No Expiration)</option>
                            <option value="preset">Preset Plans</option>
                            <option value="custom">Custom Date Range</option>
                        </select>
                    </div>
                    
                    <!-- Preset Plans -->
                    <div id="presetPlans" style="display: none;">
                        <div class="form-group">
                            <label for="plan_duration">Select Plan</label>
                            <select name="plan_duration" id="plan_duration" onchange="updatePlanInfo(this)">
                                <option value="">-- Choose Duration --</option>
                                <option value="1">1 Month Plan</option>
                                <option value="2">2 Months Plan</option>
                                <option value="3">3 Months Plan</option>
                                <option value="6">6 Months Plan</option>
                                <option value="12">1 Year Plan</option>
                            </select>
                            <small style="color: #64748b; margin-top: 5px; display: block;">
                                Account will expire after the selected duration from creation date
                            </small>
                            <div id="planInfo" style="display: none; margin-top: 10px; padding: 10px; background: #e0f2fe; border-left: 3px solid #0284c7; border-radius: 4px;">
                                <strong>📅 Expiration Date:</strong> <span id="expiryDate"></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Custom Date Range -->
                    <div id="customPlan" style="display: none;">
                        <div class="form-group">
                            <label for="custom_start_date">Start Date</label>
                            <input type="date" name="custom_start_date" id="custom_start_date" onchange="updateCustomPlan()">
                            <small style="color: #64748b; margin-top: 5px; display: block;">
                                Leave empty to start from today
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="custom_end_date">End Date *</label>
                            <input type="date" name="custom_end_date" id="custom_end_date" onchange="updateCustomPlan()">
                            <small style="color: #64748b; margin-top: 5px; display: block;">
                                Choose when the account should expire
                            </small>
                        </div>
                        <div id="customPlanInfo" style="display: none; margin-top: 10px; padding: 10px; background: #fef3c7; border-left: 3px solid #f59e0b; border-radius: 4px;">
                            <strong>📅 Duration:</strong> <span id="customDuration"></span><br>
                            <strong>🗓️ Expires:</strong> <span id="customExpiry"></span>
                        </div>
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
    
    <script src="/assets/js/main.js"></script>
    <script>
    function loadCustomerClients(customerId) {
        const clientSelect = document.getElementById('client_id');
        
        if (!customerId) {
            clientSelect.innerHTML = '<option value="">-- Choose Customer First --</option>';
            clientSelect.disabled = true;
            return;
        }
        
        // Show loading state
        clientSelect.innerHTML = '<option value="">Loading clients...</option>';
        clientSelect.disabled = true;
        
        // Fetch clients for this customer
        fetch('/admin/get-customer-clients.php?customer_id=' + customerId)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.clients.length > 0) {
                    clientSelect.innerHTML = '<option value="">-- Choose Client --</option>';
                    data.clients.forEach(client => {
                        const option = document.createElement('option');
                        option.value = client.id;
                        option.textContent = client.staff_name;
                        clientSelect.appendChild(option);
                    });
                    clientSelect.disabled = false;
                } else {
                    clientSelect.innerHTML = '<option value="">No active clients found</option>';
                    clientSelect.disabled = true;
                }
            })
            .catch(error => {
                console.error('Error loading clients:', error);
                clientSelect.innerHTML = '<option value="">Error loading clients</option>';
                clientSelect.disabled = true;
            });
    }
    
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
    </script>
</body>
</html>

