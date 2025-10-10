<?php
require_once __DIR__ . '/../config/config.php';
requireLogin('admin');

$db = new Database();
$conn = $db->getConnection();

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $username = sanitize($_POST['username']);
        $password = $_POST['password'];
        $companyName = sanitize($_POST['company_name']);
        $fullName = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone'] ?? '');
        $maxClients = !empty($_POST['max_clients']) ? intval($_POST['max_clients']) : NULL;
        $maxVpnPerClient = !empty($_POST['max_vpn_per_client']) ? intval($_POST['max_vpn_per_client']) : NULL;
        $maxTotalVpn = !empty($_POST['max_total_vpn_accounts']) ? intval($_POST['max_total_vpn_accounts']) : NULL;
        
        // Handle customer account expiration
        $planType = $_POST['account_plan_type'] ?? 'unlimited';
        $planDuration = null;
        $expiresAt = null;
        
        if ($planType === 'preset' && isset($_POST['account_plan_duration']) && $_POST['account_plan_duration'] !== '') {
            $planDuration = intval($_POST['account_plan_duration']);
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$planDuration} months"));
        } elseif ($planType === 'custom' && isset($_POST['account_expiry_date']) && $_POST['account_expiry_date'] !== '') {
            $expiresAt = $_POST['account_expiry_date'] . ' 23:59:59';
            // Calculate duration in months for display
            $startDate = new DateTime();
            $endDate = new DateTime($expiresAt);
            $interval = $startDate->diff($endDate);
            $planDuration = ($interval->y * 12) + $interval->m;
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $conn->prepare("INSERT INTO customers (username, password, company_name, full_name, email, phone, max_clients, max_vpn_per_client, max_total_vpn_accounts, plan_duration, expires_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hashedPassword, $companyName, $fullName, $email, $phone, $maxClients, $maxVpnPerClient, $maxTotalVpn, $planDuration, $expiresAt, $_SESSION['admin_id']]);
            
            logActivity($conn, 'admin', $_SESSION['admin_id'], 'add_customer', "Added customer: $companyName");
            $message = 'Customer added successfully!';
            $messageType = 'success';
        } catch(Exception $e) {
            $message = 'Failed to add customer. Username or email may already exist.';
            $messageType = 'error';
            error_log($e->getMessage());
        }
    } elseif ($action === 'edit') {
        $customerId = intval($_POST['customer_id']);
        $username = sanitize($_POST['username']);
        $companyName = sanitize($_POST['company_name']);
        $fullName = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $phone = sanitize($_POST['phone'] ?? '');
        $status = sanitize($_POST['status']);
        $maxClients = !empty($_POST['max_clients']) ? intval($_POST['max_clients']) : NULL;
        $maxVpnPerClient = !empty($_POST['max_vpn_per_client']) ? intval($_POST['max_vpn_per_client']) : NULL;
        $maxTotalVpn = !empty($_POST['max_total_vpn_accounts']) ? intval($_POST['max_total_vpn_accounts']) : NULL;
        $password = $_POST['password'] ?? '';
        
        // Handle customer account expiration for edit
        $planType = $_POST['account_plan_type'] ?? 'unlimited';
        $planDuration = null;
        $expiresAt = null;
        
        if ($planType === 'preset' && isset($_POST['account_plan_duration']) && $_POST['account_plan_duration'] !== '') {
            $planDuration = intval($_POST['account_plan_duration']);
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$planDuration} months"));
        } elseif ($planType === 'custom' && isset($_POST['account_expiry_date']) && $_POST['account_expiry_date'] !== '') {
            $expiresAt = $_POST['account_expiry_date'] . ' 23:59:59';
            // Calculate duration in months for display
            $startDate = new DateTime();
            $endDate = new DateTime($expiresAt);
            $interval = $startDate->diff($endDate);
            $planDuration = ($interval->y * 12) + $interval->m;
        }
        
        try {
            // Check if username is taken by another customer
            $stmt = $conn->prepare("SELECT id FROM customers WHERE username = ? AND id != ?");
            $stmt->execute([$username, $customerId]);
            if ($stmt->fetch()) {
                throw new Exception("Username already exists");
            }
            
            // Check if email is taken by another customer
            $stmt = $conn->prepare("SELECT id FROM customers WHERE email = ? AND id != ?");
            $stmt->execute([$email, $customerId]);
            if ($stmt->fetch()) {
                throw new Exception("Email already exists");
            }
            
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE customers SET username=?, password=?, company_name=?, full_name=?, email=?, phone=?, status=?, max_clients=?, max_vpn_per_client=?, max_total_vpn_accounts=?, plan_duration=?, expires_at=? WHERE id=?");
                $stmt->execute([$username, $hashedPassword, $companyName, $fullName, $email, $phone, $status, $maxClients, $maxVpnPerClient, $maxTotalVpn, $planDuration, $expiresAt, $customerId]);
            } else {
                $stmt = $conn->prepare("UPDATE customers SET username=?, company_name=?, full_name=?, email=?, phone=?, status=?, max_clients=?, max_vpn_per_client=?, max_total_vpn_accounts=?, plan_duration=?, expires_at=? WHERE id=?");
                $stmt->execute([$username, $companyName, $fullName, $email, $phone, $status, $maxClients, $maxVpnPerClient, $maxTotalVpn, $planDuration, $expiresAt, $customerId]);
            }
            
            logActivity($conn, 'admin', $_SESSION['admin_id'], 'edit_customer', "Updated customer: $companyName (Status: $status)");
            $message = 'Customer updated successfully!';
            $messageType = 'success';
        } catch(Exception $e) {
            $message = 'Failed to update customer. ' . $e->getMessage();
            $messageType = 'error';
            error_log("Customer update error: " . $e->getMessage());
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $customerId = intval($_GET['delete']);
    try {
        $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$customerId]);
        logActivity($conn, 'admin', $_SESSION['admin_id'], 'delete_customer', "Deleted customer ID: $customerId");
        $message = 'Customer deleted successfully!';
        $messageType = 'success';
    } catch(Exception $e) {
        $message = 'Failed to delete customer. They may have associated data.';
        $messageType = 'error';
        error_log($e->getMessage());
    }
}

// Get all customers with their stats
$stmt = $conn->query("SELECT c.*, 
    (SELECT COUNT(*) FROM client_accounts WHERE customer_id = c.id) as client_count,
    (SELECT COUNT(*) FROM vpn_accounts WHERE customer_id = c.id) as vpn_account_count
    FROM customers c ORDER BY c.created_at DESC");
$customers = $stmt->fetchAll();

$pageTitle = 'Customers - ' . SITE_NAME;
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
                    <h1 class="page-title">Customers</h1>
                </div>
                <button class="btn btn-primary" onclick="openModal('addCustomerModal')">+ Add Customer</button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Contact Person</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Staff/VPN Accounts</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($customers) > 0): ?>
                                <?php foreach ($customers as $customer): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($customer['company_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['username']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                        <td>
                            <span class="badge badge-info"><?php echo $customer['client_count']; ?> Clients</span>
                            <span class="badge badge-success"><?php echo $customer['vpn_account_count']; ?> VPN</span>
                        </td>
                                        <td>
                                            <?php
                                            $badgeClass = 'badge-success';
                                            if ($customer['status'] === 'suspended') $badgeClass = 'badge-warning';
                                            if ($customer['status'] === 'inactive') $badgeClass = 'badge-danger';
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>">
                                                <?php echo ucfirst($customer['status']); ?>
                                            </span>
                                        </td>
                                        <td class="action-buttons">
                                            <button class="btn btn-small btn-secondary" onclick='editCustomer(<?php echo json_encode($customer); ?>)'>Edit</button>
                                            <a href="?delete=<?php echo $customer['id']; ?>" class="btn btn-small btn-danger" onclick="return confirmDelete('Are you sure you want to delete this customer?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <div class="empty-state-icon">👥</div>
                                        <p>No customers yet. Click "Add Customer" to get started.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Customer Modal -->
    <div id="addCustomerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Customer</h2>
                <span class="modal-close" onclick="closeModal('addCustomerModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label for="company_name">Company Name *</label>
                    <input type="text" name="company_name" required>
                </div>
                
                <div class="form-group">
                    <label for="full_name">Contact Person *</label>
                    <input type="text" name="full_name" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" name="password" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" name="phone">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="max_clients">Max Clients</label>
                        <input type="number" name="max_clients" min="1" placeholder="Leave empty for unlimited">
                        <small style="color: #64748b;">Leave empty = unlimited clients</small>
                    </div>
                    <div class="form-group">
                        <label for="max_vpn_per_client">Max VPN per Client</label>
                        <input type="number" name="max_vpn_per_client" min="1" placeholder="Leave empty for unlimited">
                        <small style="color: #64748b;">Default VPN limit per client</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="max_total_vpn_accounts">Max Total VPN Accounts</label>
                    <input type="number" name="max_total_vpn_accounts" min="1" placeholder="Leave empty for unlimited">
                    <small style="color: #64748b;">Total VPN accounts this customer can create (across all clients)</small>
                </div>
                
                <hr style="margin: 20px 0; border: none; border-top: 1px solid #e5e7eb;">
                
                <div class="form-group">
                    <label for="account_plan_type">Customer Account Expiration</label>
                    <select name="account_plan_type" id="account_plan_type" onchange="toggleAccountExpiry(this)">
                        <option value="unlimited">Never Expires (Lifetime)</option>
                        <option value="preset">Preset Plan Duration</option>
                        <option value="custom">Custom Expiry Date</option>
                    </select>
                    <small style="color: #64748b;">Set when this customer account expires</small>
                </div>
                
                <!-- Preset Plans -->
                <div id="accountPresetPlans" style="display: none;">
                    <div class="form-group">
                        <label for="account_plan_duration">Account Duration</label>
                        <select name="account_plan_duration" id="account_plan_duration" onchange="updateAccountExpiry(this)">
                            <option value="">-- Choose Duration --</option>
                            <option value="1">1 Month</option>
                            <option value="2">2 Months</option>
                            <option value="3">3 Months</option>
                            <option value="6">6 Months</option>
                            <option value="12">1 Year</option>
                            <option value="24">2 Years</option>
                            <option value="36">3 Years</option>
                        </select>
                        <div id="accountExpiryInfo" style="display: none; margin-top: 10px; padding: 10px; background: #fef3c7; border-left: 3px solid #f59e0b; border-radius: 4px;">
                            <strong>📅 Account Expires:</strong> <span id="accountExpiryDate"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Custom Date -->
                <div id="accountCustomExpiry" style="display: none;">
                    <div class="form-group">
                        <label for="account_expiry_date">Expiry Date *</label>
                        <input type="date" name="account_expiry_date" id="account_expiry_date" onchange="showCustomExpiryInfo()">
                        <div id="customExpiryInfo" style="display: none; margin-top: 10px; padding: 10px; background: #fee2e2; border-left: 3px solid #ef4444; border-radius: 4px;">
                            <strong>📅 Account Expires:</strong> <span id="customExpiryDisplay"></span>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Add Customer</button>
            </form>
        </div>
    </div>
    
    <!-- Edit Customer Modal -->
    <div id="editCustomerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Customer</h2>
                <span class="modal-close" onclick="closeModal('editCustomerModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="customer_id" id="edit_customer_id">
                
                <div class="form-group">
                    <label for="edit_company_name">Company Name *</label>
                    <input type="text" name="company_name" id="edit_company_name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_full_name">Contact Person *</label>
                    <input type="text" name="full_name" id="edit_full_name" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_username">Username *</label>
                        <input type="text" name="username" id="edit_username" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_password">Password (leave blank to keep current)</label>
                        <input type="password" name="password" id="edit_password">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_email">Email *</label>
                        <input type="email" name="email" id="edit_email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_phone">Phone</label>
                        <input type="text" name="phone" id="edit_phone">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select name="status" id="edit_status">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="disabled">Disabled</option>
                        </select>
                    </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_max_clients">Max Clients</label>
                        <input type="number" name="max_clients" id="edit_max_clients" min="1" placeholder="Empty = unlimited">
                    </div>
                    <div class="form-group">
                        <label for="edit_max_vpn_per_client">Max VPN per Client</label>
                        <input type="number" name="max_vpn_per_client" id="edit_max_vpn_per_client" min="1" placeholder="Empty = unlimited">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_max_total_vpn_accounts">Max Total VPN Accounts</label>
                    <input type="number" name="max_total_vpn_accounts" id="edit_max_total_vpn_accounts" min="1" placeholder="Empty = unlimited">
                    <small style="color: #64748b;">Total VPN limit for this customer</small>
                </div>
                
                <hr style="margin: 20px 0; border: none; border-top: 1px solid #e5e7eb;">
                
                <div class="form-group">
                    <label for="edit_account_plan_type">Customer Account Expiration</label>
                    <select name="account_plan_type" id="edit_account_plan_type" onchange="toggleEditAccountExpiry(this)">
                        <option value="unlimited">Never Expires (Lifetime)</option>
                        <option value="preset">Preset Plan Duration</option>
                        <option value="custom">Custom Expiry Date</option>
                    </select>
                    <small style="color: #64748b;">Set when this customer account expires</small>
                </div>
                
                <!-- Preset Plans -->
                <div id="editAccountPresetPlans" style="display: none;">
                    <div class="form-group">
                        <label for="edit_account_plan_duration">Account Duration</label>
                        <select name="account_plan_duration" id="edit_account_plan_duration" onchange="updateEditAccountExpiry(this)">
                            <option value="">-- Choose Duration --</option>
                            <option value="1">1 Month</option>
                            <option value="2">2 Months</option>
                            <option value="3">3 Months</option>
                            <option value="6">6 Months</option>
                            <option value="12">1 Year</option>
                            <option value="24">2 Years</option>
                            <option value="36">3 Years</option>
                        </select>
                        <div id="editAccountExpiryInfo" style="display: none; margin-top: 10px; padding: 10px; background: #fef3c7; border-left: 3px solid #f59e0b; border-radius: 4px;">
                            <strong>📅 Account Expires:</strong> <span id="editAccountExpiryDate"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Custom Date -->
                <div id="editAccountCustomExpiry" style="display: none;">
                    <div class="form-group">
                        <label for="edit_account_expiry_date">Expiry Date *</label>
                        <input type="date" name="account_expiry_date" id="edit_account_expiry_date" onchange="showEditCustomExpiryInfo()">
                        <div id="editCustomExpiryInfo" style="display: none; margin-top: 10px; padding: 10px; background: #fee2e2; border-left: 3px solid #ef4444; border-radius: 4px;">
                            <strong>📅 Account Expires:</strong> <span id="editCustomExpiryDisplay"></span>
                        </div>
                    </div>
                </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Update Customer</button>
            </form>
        </div>
    </div>
    
    <script src="/assets/js/main.js"></script>
    <script>
    // Add Customer - Toggle account expiry options
    function toggleAccountExpiry(select) {
        const planType = select.value;
        document.getElementById('accountPresetPlans').style.display = planType === 'preset' ? 'block' : 'none';
        document.getElementById('accountCustomExpiry').style.display = planType === 'custom' ? 'block' : 'none';
        
        if (planType === 'custom') {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('account_expiry_date').setAttribute('min', today);
        }
    }
    
    function updateAccountExpiry(select) {
        const months = parseInt(select.value);
        if (months > 0) {
            const expiry = new Date();
            expiry.setMonth(expiry.getMonth() + months);
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('accountExpiryDate').textContent = expiry.toLocaleDateString('en-US', options);
            document.getElementById('accountExpiryInfo').style.display = 'block';
        } else {
            document.getElementById('accountExpiryInfo').style.display = 'none';
        }
    }
    
    function showCustomExpiryInfo() {
        const date = document.getElementById('account_expiry_date').value;
        if (date) {
            const expiry = new Date(date);
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('customExpiryDisplay').textContent = expiry.toLocaleDateString('en-US', options);
            document.getElementById('customExpiryInfo').style.display = 'block';
        } else {
            document.getElementById('customExpiryInfo').style.display = 'none';
        }
    }
    
    // Edit Customer - Toggle account expiry options
    function toggleEditAccountExpiry(select) {
        const planType = select.value;
        document.getElementById('editAccountPresetPlans').style.display = planType === 'preset' ? 'block' : 'none';
        document.getElementById('editAccountCustomExpiry').style.display = planType === 'custom' ? 'block' : 'none';
        
        if (planType === 'custom') {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('edit_account_expiry_date').setAttribute('min', today);
        }
    }
    
    function updateEditAccountExpiry(select) {
        const months = parseInt(select.value);
        if (months > 0) {
            const expiry = new Date();
            expiry.setMonth(expiry.getMonth() + months);
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('editAccountExpiryDate').textContent = expiry.toLocaleDateString('en-US', options);
            document.getElementById('editAccountExpiryInfo').style.display = 'block';
        } else {
            document.getElementById('editAccountExpiryInfo').style.display = 'none';
        }
    }
    
    function showEditCustomExpiryInfo() {
        const date = document.getElementById('edit_account_expiry_date').value;
        if (date) {
            const expiry = new Date(date);
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('editCustomExpiryDisplay').textContent = expiry.toLocaleDateString('en-US', options);
            document.getElementById('editCustomExpiryInfo').style.display = 'block';
        } else {
            document.getElementById('editCustomExpiryInfo').style.display = 'none';
        }
    }
    
    function editCustomer(customer) {
        document.getElementById('edit_customer_id').value = customer.id;
        document.getElementById('edit_company_name').value = customer.company_name;
        document.getElementById('edit_full_name').value = customer.full_name;
        document.getElementById('edit_username').value = customer.username;
        document.getElementById('edit_email').value = customer.email;
        document.getElementById('edit_phone').value = customer.phone || '';
        document.getElementById('edit_status').value = customer.status;
        document.getElementById('edit_max_clients').value = customer.max_clients || '';
        document.getElementById('edit_max_vpn_per_client').value = customer.max_vpn_per_client || '';
        document.getElementById('edit_max_total_vpn_accounts').value = customer.max_total_vpn_accounts || '';
        
        // Set account expiration fields
        if (customer.expires_at) {
            if (customer.plan_duration) {
                document.getElementById('edit_account_plan_type').value = 'preset';
                document.getElementById('edit_account_plan_duration').value = customer.plan_duration;
                toggleEditAccountExpiry(document.getElementById('edit_account_plan_type'));
                updateEditAccountExpiry(document.getElementById('edit_account_plan_duration'));
            } else {
                document.getElementById('edit_account_plan_type').value = 'custom';
                const expiryDate = customer.expires_at.split(' ')[0];
                document.getElementById('edit_account_expiry_date').value = expiryDate;
                toggleEditAccountExpiry(document.getElementById('edit_account_plan_type'));
                showEditCustomExpiryInfo();
            }
        } else {
            document.getElementById('edit_account_plan_type').value = 'unlimited';
            toggleEditAccountExpiry(document.getElementById('edit_account_plan_type'));
        }
        
        openModal('editCustomerModal');
    }
    </script>
</body>
</html>

