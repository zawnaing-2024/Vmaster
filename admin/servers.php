<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../config/config.php';
requireLogin('admin');
require_once __DIR__ . '/../config/radius.php';

$db = new Database();
$conn = $db->getConnection();
$radiusConn = getRadiusConnection();

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $serverName = sanitize($_POST['server_name']);
        $serverType = sanitize($_POST['server_type']);
        $serverHost = sanitize($_POST['server_host']);
        $serverPort = intval($_POST['server_port']);
        $apiUrl = sanitize($_POST['api_url'] ?? '');
        $apiKey = sanitize($_POST['api_key'] ?? '');
        $apiSecret = sanitize($_POST['api_secret'] ?? '');
        $adminUsername = sanitize($_POST['admin_username'] ?? '');
        $adminPassword = $_POST['admin_password'] ?? '';
        $maxAccounts = intval($_POST['max_accounts']);
        $location = sanitize($_POST['location'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $radiusSecret = sanitize($_POST['radius_secret'] ?? 'testing123');
        $addToRadius = isset($_POST['add_to_radius']) && $_POST['add_to_radius'] === '1';
        
        try {
            $stmt = $conn->prepare("INSERT INTO vpn_servers (server_name, server_type, server_host, server_port, api_url, api_key, api_secret, admin_username, admin_password, max_accounts, location, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$serverName, $serverType, $serverHost, $serverPort, $apiUrl, $apiKey, $apiSecret, $adminUsername, $adminPassword, $maxAccounts, $location, $description, $_SESSION['admin_id']]);
            
            // If SSTP server and RADIUS enabled, add to RADIUS clients
            if ($serverType === 'sstp' && $addToRadius && RADIUS_ENABLED && $radiusConn) {
                try {
                    // Check if already exists
                    $stmt = $radiusConn->prepare("SELECT COUNT(*) as count FROM nas WHERE nasname = ?");
                    $stmt->execute([$serverHost]);
                    
                    if ($stmt->fetch()['count'] == 0) {
                        // Add to RADIUS clients
                        $stmt = $radiusConn->prepare("INSERT INTO nas (nasname, shortname, type, secret, description) VALUES (?, ?, 'other', ?, ?)");
                        $stmt->execute([
                            $serverHost,
                            strtolower(str_replace(' ', '-', $serverName)),
                            $radiusSecret,
                            "SSTP Server: $serverName"
                        ]);
                        $message = "VPN server added successfully! Also added to RADIUS clients. Remember to restart FreeRADIUS: docker-compose -f /var/www/vmaster/docker-compose.prod.yml restart freeradius";
                    } else {
                        $message = 'VPN server added successfully! (Already exists in RADIUS clients)';
                    }
                } catch(Exception $e) {
                    error_log("Failed to add to RADIUS: " . $e->getMessage());
                    $message = 'VPN server added successfully! But failed to add to RADIUS clients. You can add it manually in RADIUS Clients page.';
                }
            } else {
                $message = 'VPN server added successfully!';
            }
            
            logActivity($conn, 'admin', $_SESSION['admin_id'], 'add_server', "Added VPN server: $serverName");
            $messageType = 'success';
        } catch(Exception $e) {
            $message = 'Failed to add server. Please try again.';
            $messageType = 'error';
            error_log($e->getMessage());
        }
    } elseif ($action === 'edit') {
        $serverId = intval($_POST['server_id']);
        $serverName = sanitize($_POST['server_name']);
        $serverType = sanitize($_POST['server_type']);
        $serverHost = sanitize($_POST['server_host']);
        $serverPort = intval($_POST['server_port']);
        $apiUrl = sanitize($_POST['api_url'] ?? '');
        $apiKey = sanitize($_POST['api_key'] ?? '');
        $apiSecret = sanitize($_POST['api_secret'] ?? '');
        $adminUsername = sanitize($_POST['admin_username'] ?? '');
        $adminPassword = $_POST['admin_password'] ?? '';
        $maxAccounts = intval($_POST['max_accounts']);
        $status = sanitize($_POST['status']);
        $location = sanitize($_POST['location'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        
        try {
            if (!empty($adminPassword)) {
                $stmt = $conn->prepare("UPDATE vpn_servers SET server_name=?, server_type=?, server_host=?, server_port=?, api_url=?, api_key=?, api_secret=?, admin_username=?, admin_password=?, max_accounts=?, status=?, location=?, description=? WHERE id=?");
                $stmt->execute([$serverName, $serverType, $serverHost, $serverPort, $apiUrl, $apiKey, $apiSecret, $adminUsername, $adminPassword, $maxAccounts, $status, $location, $description, $serverId]);
            } else {
                $stmt = $conn->prepare("UPDATE vpn_servers SET server_name=?, server_type=?, server_host=?, server_port=?, api_url=?, api_key=?, api_secret=?, admin_username=?, max_accounts=?, status=?, location=?, description=? WHERE id=?");
                $stmt->execute([$serverName, $serverType, $serverHost, $serverPort, $apiUrl, $apiKey, $apiSecret, $adminUsername, $maxAccounts, $status, $location, $description, $serverId]);
            }
            
            logActivity($conn, 'admin', $_SESSION['admin_id'], 'edit_server', "Updated VPN server: $serverName");
            $message = 'VPN server updated successfully!';
            $messageType = 'success';
        } catch(Exception $e) {
            $message = 'Failed to update server. Please try again.';
            $messageType = 'error';
            error_log($e->getMessage());
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $serverId = intval($_GET['delete']);
    try {
        $stmt = $conn->prepare("DELETE FROM vpn_servers WHERE id = ?");
        $stmt->execute([$serverId]);
        logActivity($conn, 'admin', $_SESSION['admin_id'], 'delete_server', "Deleted VPN server ID: $serverId");
        $message = 'VPN server deleted successfully!';
        $messageType = 'success';
    } catch(Exception $e) {
        $message = 'Failed to delete server. It may have associated accounts.';
        $messageType = 'error';
        error_log($e->getMessage());
    }
}

// Get all servers
$stmt = $conn->query("SELECT * FROM vpn_servers ORDER BY created_at DESC");
$servers = $stmt->fetchAll();

$pageTitle = 'VPN Servers - ' . SITE_NAME;
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
                    <h1 class="page-title">VPN Servers</h1>
                </div>
                <button class="btn btn-primary" onclick="openModal('addServerModal')">+ Add Server</button>
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
                                <th>Server Name</th>
                                <th>Type</th>
                                <th>Host:Port</th>
                                <th>Location</th>
                                <th>Accounts</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($servers) > 0): ?>
                                <?php foreach ($servers as $server): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($server['server_name']); ?></strong></td>
                                        <td>
                                            <span class="server-type-icon server-type-<?php echo $server['server_type']; ?>">
                                                <?php echo strtoupper($server['server_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($server['server_host']) . ':' . $server['server_port']; ?></td>
                                        <td><?php echo htmlspecialchars($server['location']); ?></td>
                                        <td><?php echo $server['current_accounts'] . '/' . $server['max_accounts']; ?></td>
                                        <td>
                                            <?php
                                            $badgeClass = 'badge-success';
                                            if ($server['status'] === 'maintenance') $badgeClass = 'badge-warning';
                                            if ($server['status'] === 'inactive') $badgeClass = 'badge-danger';
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>">
                                                <?php echo ucfirst($server['status']); ?>
                                            </span>
                                        </td>
                                        <td class="action-buttons">
                                            <button class="btn btn-small btn-secondary" onclick='editServer(<?php echo json_encode($server); ?>)'>Edit</button>
                                            <a href="?delete=<?php echo $server['id']; ?>" class="btn btn-small btn-danger" onclick="return confirmDelete('Are you sure you want to delete this server?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <div class="empty-state-icon">🖥️</div>
                                        <p>No VPN servers yet. Click "Add Server" to get started.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Server Modal -->
    <div id="addServerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add VPN Server</h2>
                <span class="modal-close" onclick="closeModal('addServerModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="server_name">Server Name *</label>
                        <input type="text" name="server_name" required>
                    </div>
                    <div class="form-group">
                        <label for="server_type">Server Type *</label>
                        <select name="server_type" required>
                            <option value="outline">Outline</option>
                            <option value="v2ray">V2Ray</option>
                            <option value="sstp">SSTP</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="server_host">Server Host *</label>
                        <input type="text" name="server_host" placeholder="192.168.1.1 or domain.com" required>
                    </div>
                    <div class="form-group">
                        <label for="server_port">Server Port *</label>
                        <input type="number" name="server_port" placeholder="443" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" name="location" placeholder="Singapore, USA, etc.">
                    </div>
                    <div class="form-group">
                        <label for="max_accounts">Max Accounts *</label>
                        <input type="number" name="max_accounts" value="100" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="api_url">API URL</label>
                    <input type="text" name="api_url" placeholder="https://api.example.com">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="api_key">API Key</label>
                        <input type="text" name="api_key">
                    </div>
                    <div class="form-group">
                        <label for="api_secret">API Secret</label>
                        <input type="password" name="api_secret">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="admin_username">Admin Username</label>
                        <input type="text" name="admin_username">
                    </div>
                    <div class="form-group">
                        <label for="admin_password">Admin Password</label>
                        <input type="password" name="admin_password">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                
                <!-- RADIUS Settings (for SSTP servers) -->
                <div id="radiusSettings" style="display: none; border-top: 2px solid #e0e0e0; padding-top: 20px; margin-top: 20px;">
                    <h3 style="color: #007bff; margin-bottom: 15px;">🔐 RADIUS Settings (Optional)</h3>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" name="add_to_radius" value="1" id="addToRadiusCheckbox" style="margin-right: 10px; width: auto;">
                            <span>Automatically add this SSTP server to RADIUS clients</span>
                        </label>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            ✅ Enable this to allow RADIUS authentication for this SSTP server
                        </small>
                    </div>
                    
                    <div id="radiusSecretField" style="display: none;">
                        <div class="form-group">
                            <label for="radius_secret">RADIUS Shared Secret *</label>
                            <input type="text" name="radius_secret" id="radiusSecretInput" value="testing123" placeholder="Enter shared secret">
                            <small style="color: #666;">
                                This secret must match the one configured on your SSTP server's accel-ppp.conf
                            </small>
                        </div>
                        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; border-radius: 4px; margin-top: 10px;">
                            <strong>⚠️ Important:</strong> After adding, you must:
                            <ol style="margin: 10px 0 0 20px; padding: 0;">
                                <li>Restart FreeRADIUS: <code style="background: #fff; padding: 2px 6px; border-radius: 3px;">docker-compose restart freeradius</code></li>
                                <li>Configure your SSTP server's <code>/etc/accel-ppp.conf</code> with this secret</li>
                            </ol>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Add Server</button>
            </form>
        </div>
    </div>
    
    <script>
    // Show/hide RADIUS settings based on server type
    document.querySelector('select[name="server_type"]').addEventListener('change', function() {
        const radiusSettings = document.getElementById('radiusSettings');
        if (this.value === 'sstp') {
            radiusSettings.style.display = 'block';
        } else {
            radiusSettings.style.display = 'none';
            document.getElementById('addToRadiusCheckbox').checked = false;
            document.getElementById('radiusSecretField').style.display = 'none';
        }
    });
    
    // Show/hide secret field based on checkbox
    document.getElementById('addToRadiusCheckbox').addEventListener('change', function() {
        const secretField = document.getElementById('radiusSecretField');
        if (this.checked) {
            secretField.style.display = 'block';
            document.getElementById('radiusSecretInput').required = true;
        } else {
            secretField.style.display = 'none';
            document.getElementById('radiusSecretInput').required = false;
        }
    });
    </script>
    
    <!-- Edit Server Modal -->
    <div id="editServerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit VPN Server</h2>
                <span class="modal-close" onclick="closeModal('editServerModal')">&times;</span>
            </div>
            <form method="POST" action="" id="editServerForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="server_id" id="edit_server_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_server_name">Server Name *</label>
                        <input type="text" name="server_name" id="edit_server_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_server_type">Server Type *</label>
                        <select name="server_type" id="edit_server_type" required>
                            <option value="outline">Outline</option>
                            <option value="v2ray">V2Ray</option>
                            <option value="sstp">SSTP</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_server_host">Server Host *</label>
                        <input type="text" name="server_host" id="edit_server_host" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_server_port">Server Port *</label>
                        <input type="number" name="server_port" id="edit_server_port" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_location">Location</label>
                        <input type="text" name="location" id="edit_location">
                    </div>
                    <div class="form-group">
                        <label for="edit_max_accounts">Max Accounts *</label>
                        <input type="number" name="max_accounts" id="edit_max_accounts" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_status">Status</label>
                    <select name="status" id="edit_status">
                        <option value="active">Active</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="disabled">Disabled</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_api_url">API URL</label>
                    <input type="text" name="api_url" id="edit_api_url">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_api_key">API Key</label>
                        <input type="text" name="api_key" id="edit_api_key">
                    </div>
                    <div class="form-group">
                        <label for="edit_api_secret">API Secret</label>
                        <input type="password" name="api_secret" id="edit_api_secret">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_admin_username">Admin Username</label>
                        <input type="text" name="admin_username" id="edit_admin_username">
                    </div>
                    <div class="form-group">
                        <label for="edit_admin_password">Admin Password (leave blank to keep current)</label>
                        <input type="password" name="admin_password" id="edit_admin_password">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea name="description" id="edit_description" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Update Server</button>
            </form>
        </div>
    </div>
    
    <script src="/assets/js/main.js"></script>
    <script>
    function editServer(server) {
        document.getElementById('edit_server_id').value = server.id;
        document.getElementById('edit_server_name').value = server.server_name;
        document.getElementById('edit_server_type').value = server.server_type;
        document.getElementById('edit_server_host').value = server.server_host;
        document.getElementById('edit_server_port').value = server.server_port;
        document.getElementById('edit_location').value = server.location || '';
        document.getElementById('edit_max_accounts').value = server.max_accounts;
        document.getElementById('edit_status').value = server.status;
        document.getElementById('edit_api_url').value = server.api_url || '';
        document.getElementById('edit_api_key').value = server.api_key || '';
        document.getElementById('edit_api_secret').value = server.api_secret || '';
        document.getElementById('edit_admin_username').value = server.admin_username || '';
        document.getElementById('edit_description').value = server.description || '';
        openModal('editServerModal');
    }
    </script>
</body>
</html>

