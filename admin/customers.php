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
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $conn->prepare("INSERT INTO customers (username, password, company_name, full_name, email, phone, max_clients, max_vpn_per_client, max_total_vpn_accounts, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hashedPassword, $companyName, $fullName, $email, $phone, $maxClients, $maxVpnPerClient, $maxTotalVpn, $_SESSION['admin_id']]);
            
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
                $stmt = $conn->prepare("UPDATE customers SET username=?, password=?, company_name=?, full_name=?, email=?, phone=?, status=?, max_clients=?, max_vpn_per_client=?, max_total_vpn_accounts=? WHERE id=?");
                $stmt->execute([$username, $hashedPassword, $companyName, $fullName, $email, $phone, $status, $maxClients, $maxVpnPerClient, $maxTotalVpn, $customerId]);
            } else {
                $stmt = $conn->prepare("UPDATE customers SET username=?, company_name=?, full_name=?, email=?, phone=?, status=?, max_clients=?, max_vpn_per_client=?, max_total_vpn_accounts=? WHERE id=?");
                $stmt->execute([$username, $companyName, $fullName, $email, $phone, $status, $maxClients, $maxVpnPerClient, $maxTotalVpn, $customerId]);
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
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Update Customer</button>
            </form>
        </div>
    </div>
    
    <script src="/assets/js/main.js"></script>
    <script>
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
        openModal('editCustomerModal');
    }
    </script>
</body>
</html>

