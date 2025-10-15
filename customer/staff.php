<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../config/config.php';
requireLogin('customer');

$db = new Database();
$conn = $db->getConnection();

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
        // Check if customer has reached max staff limit
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM staff_accounts WHERE customer_id = ?");
        $stmt->execute([$_SESSION['customer_id']]);
        $currentStaffCount = $stmt->fetch()['count'];
        
        if ($currentStaffCount >= $customer['max_staff_accounts']) {
            $message = 'You have reached the maximum number of staff accounts allowed.';
            $messageType = 'error';
        } else {
            $staffName = sanitize($_POST['staff_name']);
            $staffEmail = sanitize($_POST['staff_email'] ?? '');
            $staffPhone = sanitize($_POST['staff_phone'] ?? '');
            $department = sanitize($_POST['department'] ?? '');
            $notes = sanitize($_POST['notes'] ?? '');
            
            try {
                $stmt = $conn->prepare("INSERT INTO staff_accounts (customer_id, staff_name, staff_email, staff_phone, department, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$_SESSION['customer_id'], $staffName, $staffEmail, $staffPhone, $department, $notes]);
                
                logActivity($conn, 'customer', $_SESSION['customer_id'], 'add_staff', "Added staff: $staffName");
                $message = 'Staff member added successfully!';
                $messageType = 'success';
            } catch(Exception $e) {
                $message = 'Failed to add staff member. Please try again.';
                $messageType = 'error';
                error_log($e->getMessage());
            }
        }
    } elseif ($action === 'edit') {
        $staffId = intval($_POST['staff_id']);
        $staffName = sanitize($_POST['staff_name']);
        $staffEmail = sanitize($_POST['staff_email'] ?? '');
        $staffPhone = sanitize($_POST['staff_phone'] ?? '');
        $department = sanitize($_POST['department'] ?? '');
        $status = sanitize($_POST['status']);
        $notes = sanitize($_POST['notes'] ?? '');
        
        try {
            $stmt = $conn->prepare("UPDATE staff_accounts SET staff_name=?, staff_email=?, staff_phone=?, department=?, status=?, notes=? WHERE id=? AND customer_id=?");
            $stmt->execute([$staffName, $staffEmail, $staffPhone, $department, $status, $notes, $staffId, $_SESSION['customer_id']]);
            
            logActivity($conn, 'customer', $_SESSION['customer_id'], 'edit_staff', "Updated staff: $staffName");
            $message = 'Staff member updated successfully!';
            $messageType = 'success';
        } catch(Exception $e) {
            $message = 'Failed to update staff member. Please try again.';
            $messageType = 'error';
            error_log($e->getMessage());
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $staffId = intval($_GET['delete']);
    try {
        $stmt = $conn->prepare("DELETE FROM staff_accounts WHERE id = ? AND customer_id = ?");
        $stmt->execute([$staffId, $_SESSION['customer_id']]);
        logActivity($conn, 'customer', $_SESSION['customer_id'], 'delete_staff', "Deleted staff ID: $staffId");
        $message = 'Staff member deleted successfully!';
        $messageType = 'success';
    } catch(Exception $e) {
        $message = 'Failed to delete staff member. They may have associated VPN accounts.';
        $messageType = 'error';
        error_log($e->getMessage());
    }
}

// Get all staff with their VPN account count
$stmt = $conn->prepare("SELECT s.*, 
    (SELECT COUNT(*) FROM vpn_accounts WHERE staff_id = s.id) as vpn_count
    FROM staff_accounts s WHERE s.customer_id = ? ORDER BY s.created_at DESC");
$stmt->execute([$_SESSION['customer_id']]);
$staff = $stmt->fetchAll();

$pageTitle = 'My Staff - ' . SITE_NAME;
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
                    <h1 class="page-title">My Staff</h1>
                </div>
                <button class="btn btn-primary" onclick="openModal('addStaffModal')">+ Add Staff</button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Staff Members (<?php echo count($staff); ?> / <?php echo $customer['max_staff_accounts']; ?>)</h2>
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
                            <?php if (count($staff) > 0): ?>
                                <?php foreach ($staff as $member): ?>
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
                                            <button class="btn btn-small btn-secondary" onclick='editStaff(<?php echo json_encode($member); ?>)'>Edit</button>
                                            <a href="?delete=<?php echo $member['id']; ?>" class="btn btn-small btn-danger" onclick="return confirmDelete('Are you sure you want to delete this staff member?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <div class="empty-state-icon">👤</div>
                                        <p>No staff members yet. Click "Add Staff" to get started.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Staff Modal -->
    <div id="addStaffModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Staff Member</h2>
                <span class="modal-close" onclick="closeModal('addStaffModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label for="staff_name">Staff Name *</label>
                    <input type="text" name="staff_name" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="staff_email">Email</label>
                        <input type="email" name="staff_email">
                    </div>
                    <div class="form-group">
                        <label for="staff_phone">Phone</label>
                        <input type="text" name="staff_phone">
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
                
                <button type="submit" class="btn btn-primary btn-block">Add Staff</button>
            </form>
        </div>
    </div>
    
    <!-- Edit Staff Modal -->
    <div id="editStaffModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Staff Member</h2>
                <span class="modal-close" onclick="closeModal('editStaffModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="staff_id" id="edit_staff_id">
                
                <div class="form-group">
                    <label for="edit_staff_name">Staff Name *</label>
                    <input type="text" name="staff_name" id="edit_staff_name" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_staff_email">Email</label>
                        <input type="email" name="staff_email" id="edit_staff_email">
                    </div>
                    <div class="form-group">
                        <label for="edit_staff_phone">Phone</label>
                        <input type="text" name="staff_phone" id="edit_staff_phone">
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
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_notes">Notes</label>
                    <textarea name="notes" id="edit_notes" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Update Staff</button>
            </form>
        </div>
    </div>
    
    <script src="/assets/js/main.js"></script>
    <script>
    function editStaff(staff) {
        document.getElementById('edit_staff_id').value = staff.id;
        document.getElementById('edit_staff_name').value = staff.staff_name;
        document.getElementById('edit_staff_email').value = staff.staff_email || '';
        document.getElementById('edit_staff_phone').value = staff.staff_phone || '';
        document.getElementById('edit_department').value = staff.department || '';
        document.getElementById('edit_status').value = staff.status;
        document.getElementById('edit_notes').value = staff.notes || '';
        openModal('editStaffModal');
    }
    </script>
</body>
</html>
