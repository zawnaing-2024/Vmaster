<?php
session_start();
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../config/radius.php';
require_once '../includes/functions.php';
require_once '../includes/radius_handler.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$radiusHandler = new RadiusHandler();
$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_user') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        if ($radiusHandler->createUser($username, $password)) {
            $message = "✅ RADIUS user '$username' created successfully!";
            $messageType = 'success';
            logActivity($conn, 'admin', $_SESSION['admin_id'], 'create_radius_user', "Created RADIUS user: $username");
        } else {
            $message = "❌ Failed to create RADIUS user. Check if user already exists.";
            $messageType = 'error';
        }
    }
    elseif ($action === 'delete_user') {
        $username = trim($_POST['username']);
        
        if ($radiusHandler->deleteUser($username)) {
            $message = "✅ RADIUS user '$username' deleted successfully!";
            $messageType = 'success';
            logActivity($conn, 'admin', $_SESSION['admin_id'], 'delete_radius_user', "Deleted RADIUS user: $username");
        } else {
            $message = "❌ Failed to delete RADIUS user.";
            $messageType = 'error';
        }
    }
    elseif ($action === 'suspend_user') {
        $username = trim($_POST['username']);
        
        if ($radiusHandler->suspendUser($username)) {
            $message = "✅ RADIUS user '$username' suspended successfully!";
            $messageType = 'success';
            logActivity($conn, 'admin', $_SESSION['admin_id'], 'suspend_radius_user', "Suspended RADIUS user: $username");
        } else {
            $message = "❌ Failed to suspend RADIUS user.";
            $messageType = 'error';
        }
    }
    elseif ($action === 'reactivate_user') {
        $username = trim($_POST['username']);
        
        if ($radiusHandler->reactivateUser($username)) {
            $message = "✅ RADIUS user '$username' reactivated successfully!";
            $messageType = 'success';
            logActivity($conn, 'admin', $_SESSION['admin_id'], 'reactivate_radius_user', "Reactivated RADIUS user: $username");
        } else {
            $message = "❌ Failed to reactivate RADIUS user.";
            $messageType = 'error';
        }
    }
    elseif ($action === 'change_password') {
        $username = trim($_POST['username']);
        $newPassword = trim($_POST['new_password']);
        
        if ($radiusHandler->changePassword($username, $newPassword)) {
            $message = "✅ Password changed for RADIUS user '$username'!";
            $messageType = 'success';
            logActivity($conn, 'admin', $_SESSION['admin_id'], 'change_radius_password', "Changed password for RADIUS user: $username");
        } else {
            $message = "❌ Failed to change password.";
            $messageType = 'error';
        }
    }
}

// Get all RADIUS users
$radiusUsers = RADIUS_ENABLED ? $radiusHandler->getAllUsers() : [];

// Get RADIUS connection status
$radiusConnected = RADIUS_ENABLED ? $radiusHandler->testConnection() : false;

// Get statistics
$stats = [
    'total_users' => count($radiusUsers),
    'active_users' => 0,
    'suspended_users' => 0
];

if (RADIUS_ENABLED) {
    foreach ($radiusUsers as $user) {
        $status = $radiusHandler->getUserStatus($user);
        if ($status === 'active') $stats['active_users']++;
        if ($status === 'suspended') $stats['suspended_users']++;
    }
}

$pageTitle = 'RADIUS Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1>🔐 RADIUS Management</h1>
            <?php if (!RADIUS_ENABLED): ?>
                <span class="badge badge-warning">RADIUS Disabled</span>
            <?php elseif ($radiusConnected): ?>
                <span class="badge badge-success">✅ Connected</span>
            <?php else: ?>
                <span class="badge badge-danger">❌ Connection Failed</span>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!RADIUS_ENABLED): ?>
            <div class="alert alert-warning">
                <h3>⚠️ RADIUS is Disabled</h3>
                <p>To enable RADIUS integration:</p>
                <ol>
                    <li>Setup FreeRADIUS server (or use Docker: <code>docker-compose -f docker-compose-radius.yml up -d</code>)</li>
                    <li>Edit <code>config/radius.php</code></li>
                    <li>Set <code>RADIUS_ENABLED = true</code></li>
                    <li>Configure database connection settings</li>
                </ol>
                <p><a href="../RADIUS_INTEGRATION.md" target="_blank">📚 View Complete Setup Guide</a></p>
            </div>
        <?php endif; ?>

        <?php if (RADIUS_ENABLED): ?>
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['active_users']; ?></div>
                    <div class="stat-label">Active Users</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏸️</div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $stats['suspended_users']; ?></div>
                    <div class="stat-label">Suspended Users</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔌</div>
                <div class="stat-details">
                    <div class="stat-value"><?php echo $radiusConnected ? 'Connected' : 'Offline'; ?></div>
                    <div class="stat-label">RADIUS Status</div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="card">
            <div class="card-header">
                <h2>Quick Actions</h2>
            </div>
            <div class="card-body">
                <button class="btn btn-primary" onclick="openModal('createUserModal')">➕ Create RADIUS User</button>
                <a href="http://localhost:8081" target="_blank" class="btn btn-info">🌐 Open daloRADIUS (External GUI)</a>
            </div>
        </div>

        <!-- Users List -->
        <div class="card">
            <div class="card-header">
                <h2>RADIUS Users</h2>
            </div>
            <div class="card-body">
                <?php if (empty($radiusUsers)): ?>
                    <p class="text-muted">No RADIUS users found. Create one to get started!</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($radiusUsers as $user): ?>
                                <?php $status = $radiusHandler->getUserStatus($user); ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($user); ?></strong></td>
                                    <td>
                                        <?php if ($status === 'active'): ?>
                                            <span class="badge badge-success">✅ Active</span>
                                        <?php elseif ($status === 'suspended'): ?>
                                            <span class="badge badge-warning">⏸️ Suspended</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary"><?php echo ucfirst($status); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="actions">
                                        <?php if ($status === 'active'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="suspend_user">
                                                <input type="hidden" name="username" value="<?php echo htmlspecialchars($user); ?>">
                                                <button type="submit" class="btn-icon" title="Suspend">⏸️</button>
                                            </form>
                                        <?php elseif ($status === 'suspended'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="reactivate_user">
                                                <input type="hidden" name="username" value="<?php echo htmlspecialchars($user); ?>">
                                                <button type="submit" class="btn-icon" title="Reactivate">✅</button>
                                            </form>
                                        <?php endif; ?>
                                        <button class="btn-icon" onclick="changePassword('<?php echo htmlspecialchars($user); ?>')" title="Change Password">🔑</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete user <?php echo htmlspecialchars($user); ?>?');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($user); ?>">
                                            <button type="submit" class="btn-icon btn-danger" title="Delete">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Configuration -->
        <div class="card">
            <div class="card-header">
                <h2>Configuration</h2>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <td><strong>RADIUS Enabled:</strong></td>
                        <td><?php echo RADIUS_ENABLED ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-danger">No</span>'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>RADIUS Server:</strong></td>
                        <td><?php echo defined('RADIUS_SERVER_IP') ? RADIUS_SERVER_IP : 'Not configured'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>RADIUS Port:</strong></td>
                        <td><?php echo defined('RADIUS_SERVER_PORT') ? RADIUS_SERVER_PORT : 'Not configured'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Database Host:</strong></td>
                        <td><?php echo defined('RADIUS_DB_HOST') ? RADIUS_DB_HOST : 'Not configured'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Connection Status:</strong></td>
                        <td><?php echo $radiusConnected ? '<span class="badge badge-success">✅ Connected</span>' : '<span class="badge badge-danger">❌ Failed</span>'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div id="createUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create RADIUS User</h2>
                <span class="close" onclick="closeModal('createUserModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_user">
                <div class="form-group">
                    <label for="username">Username *</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required>
                    <small>Minimum 8 characters recommended</small>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('createUserModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Change Password</h2>
                <span class="close" onclick="closeModal('changePasswordModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" id="change_password_username" name="username">
                <div class="form-group">
                    <label for="new_password">New Password *</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('changePasswordModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
    function changePassword(username) {
        document.getElementById('change_password_username').value = username;
        openModal('changePasswordModal');
    }
    </script>
</body>
</html>

