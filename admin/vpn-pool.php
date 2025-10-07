<?php
require_once __DIR__ . '/../config/config.php';
requireLogin('admin');

$db = new Database();
$conn = $db->getConnection();

$message = '';
$messageType = '';

// Handle bulk add credentials
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_add') {
    $serverId = intval($_POST['server_id']);
    $serverType = sanitize($_POST['server_type']);
    $credentialsText = $_POST['credentials'];
    $notes = sanitize($_POST['notes'] ?? '');
    
    try {
        // Parse credentials based on server type
        $lines = array_filter(array_map('trim', explode("\n", $credentialsText)));
        $added = 0;
        
        foreach ($lines as $line) {
            if (empty($line)) continue;
            
            if ($serverType === 'sstp') {
                // Format: username:password
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $stmt = $conn->prepare("INSERT INTO vpn_credentials_pool (server_id, server_type, credential_username, credential_password, notes) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$serverId, $serverType, trim($parts[0]), trim($parts[1]), $notes]);
                    $added++;
                }
            } elseif ($serverType === 'v2ray') {
                // Format: UUID or full config JSON
                $trimmed = trim($line);
                if (strlen($trimmed) === 36 || strlen($trimmed) === 32) {
                    // It's a UUID
                    $stmt = $conn->prepare("INSERT INTO vpn_credentials_pool (server_id, server_type, credential_uuid, notes) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$serverId, $serverType, $trimmed, $notes]);
                    $added++;
                } else {
                    // Try as JSON config
                    $stmt = $conn->prepare("INSERT INTO vpn_credentials_pool (server_id, server_type, credential_config, notes) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$serverId, $serverType, $trimmed, $notes]);
                    $added++;
                }
            }
        }
        
        logActivity($conn, 'admin', $_SESSION['admin_id'], 'bulk_add_credentials', "Added $added credentials to pool for server ID: $serverId");
        $message = "Successfully added $added credentials to the pool!";
        $messageType = 'success';
    } catch(Exception $e) {
        $message = 'Failed to add credentials. Please check format.';
        $messageType = 'error';
        error_log($e->getMessage());
    }
}

// Handle delete credential
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        // Check if assigned
        $stmt = $conn->prepare("SELECT is_assigned FROM vpn_credentials_pool WHERE id = ?");
        $stmt->execute([$id]);
        $cred = $stmt->fetch();
        
        if ($cred && !$cred['is_assigned']) {
            $stmt = $conn->prepare("DELETE FROM vpn_credentials_pool WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Credential deleted successfully!';
            $messageType = 'success';
        } else {
            $message = 'Cannot delete: Credential is already assigned!';
            $messageType = 'error';
        }
    } catch(Exception $e) {
        $message = 'Failed to delete credential.';
        $messageType = 'error';
    }
}

// Get all servers for dropdown
$servers = $conn->query("SELECT * FROM vpn_servers WHERE server_type IN ('sstp', 'v2ray') ORDER BY server_name")->fetchAll();

// Get all pool credentials with stats
$stmt = $conn->query("SELECT vcp.*, vcp.vpn_type,
    CASE WHEN vcp.is_assigned THEN va.id ELSE NULL END as vpn_account_id,
    CASE WHEN vcp.is_assigned THEN c.company_name ELSE NULL END as assigned_company
    FROM vpn_credentials_pool vcp
    LEFT JOIN vpn_accounts va ON vcp.assigned_to = va.id
    LEFT JOIN customers c ON va.customer_id = c.id
    ORDER BY vcp.is_assigned ASC, vcp.created_at DESC");
$poolCredentials = $stmt->fetchAll();

// Get statistics
$stats = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_assigned = 0 THEN 1 ELSE 0 END) as available,
    SUM(CASE WHEN is_assigned = 1 THEN 1 ELSE 0 END) as assigned,
    SUM(CASE WHEN vpn_type = 'sstp' THEN 1 ELSE 0 END) as sstp_count,
    SUM(CASE WHEN vpn_type = 'v2ray' THEN 1 ELSE 0 END) as v2ray_count
    FROM vpn_credentials_pool")->fetch();

$pageTitle = 'VPN Credentials Pool - ' . SITE_NAME;
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
                    <h1 class="page-title">VPN Credentials Pool</h1>
                </div>
                <button class="btn btn-primary" onclick="openModal('bulkAddModal')">
                    ➕ Add Credentials Bulk
                </button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="stats-grid" style="grid-template-columns: repeat(5, 1fr);">
                <div class="stat-card">
                    <div class="stat-label">Total Credentials</div>
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                </div>
                <div class="stat-card success">
                    <div class="stat-label">Available</div>
                    <div class="stat-value"><?php echo $stats['available']; ?></div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-label">Assigned</div>
                    <div class="stat-value"><?php echo $stats['assigned']; ?></div>
                </div>
                <div class="stat-card info">
                    <div class="stat-label">SSTP</div>
                    <div class="stat-value"><?php echo $stats['sstp_count']; ?></div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-label">V2Ray</div>
                    <div class="stat-value"><?php echo $stats['v2ray_count']; ?></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">All Credentials (<?php echo count($poolCredentials); ?>)</h2>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Server</th>
                                <th>Type</th>
                                <th>Credential</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($poolCredentials) > 0): ?>
                                <?php foreach ($poolCredentials as $cred): ?>
                                    <tr>
                                        <td><?php echo $cred['id']; ?></td>
                                        <td><?php echo htmlspecialchars($cred['server_name']); ?></td>
                                        <td>
                                            <span class="server-type-icon server-type-<?php echo $cred['server_type']; ?>">
                                                <?php echo strtoupper($cred['server_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($cred['server_type'] === 'sstp'): ?>
                                                <small><?php echo htmlspecialchars($cred['credential_username']); ?></small>
                                            <?php else: ?>
                                                <small><?php echo substr($cred['credential_uuid'] ?? 'config', 0, 20); ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($cred['is_assigned']): ?>
                                                <span class="badge badge-warning">Assigned</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Available</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($cred['is_assigned']): ?>
                                                <small><?php echo htmlspecialchars($cred['assigned_company'] ?? 'N/A'); ?></small>
                                            <?php else: ?>
                                                <span style="color: #94a3b8;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo formatDate($cred['created_at']); ?></td>
                                        <td class="action-buttons">
                                            <?php if (!$cred['is_assigned']): ?>
                                                <a href="?delete=<?php echo $cred['id']; ?>" class="btn btn-small btn-danger" onclick="return confirm('Delete this credential?')">Delete</a>
                                            <?php else: ?>
                                                <span style="color: #94a3b8;">In Use</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="empty-state">
                                        <div class="empty-state-icon">🔑</div>
                                        <p>No credentials in pool yet. Click "Add Credentials Bulk" to get started.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bulk Add Modal -->
    <div id="bulkAddModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2>Add Credentials in Bulk</h2>
                <span class="modal-close" onclick="closeModal('bulkAddModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="bulk_add">
                
                <div class="form-group">
                    <label for="server_id">Select Server *</label>
                    <select name="server_id" id="server_id" required onchange="updateInstructions()">
                        <option value="">-- Choose Server --</option>
                        <?php foreach ($servers as $server): ?>
                            <option value="<?php echo $server['id']; ?>" data-type="<?php echo $server['server_type']; ?>">
                                <?php echo htmlspecialchars($server['server_name']); ?> (<?php echo strtoupper($server['server_type']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <input type="hidden" name="server_type" id="server_type">
                
                <div class="form-group">
                    <label for="credentials">Credentials *</label>
                    <textarea name="credentials" id="credentials" rows="15" required placeholder="Enter credentials (one per line)"></textarea>
                    <div id="format-instructions" style="margin-top: 10px; padding: 15px; background: #f1f5f9; border-radius: 8px;">
                        <strong>Format Instructions:</strong>
                        <div id="sstp-format" style="display: none;">
                            <p style="margin: 10px 0; color: #64748b;">For SSTP, enter one credential per line in format:</p>
                            <code style="background: #fff; padding: 5px; display: block; margin: 5px 0;">username:password</code>
                            <p style="margin: 10px 0; color: #64748b;">Example:</p>
                            <code style="background: #fff; padding: 5px; display: block;">vpn_user1:abc123<br/>vpn_user2:xyz789<br/>vpn_user3:pwd456</code>
                        </div>
                        <div id="v2ray-format" style="display: none;">
                            <p style="margin: 10px 0; color: #64748b;">For V2Ray, enter one UUID per line:</p>
                            <code style="background: #fff; padding: 5px; display: block; margin: 5px 0;">UUID (36 characters)</code>
                            <p style="margin: 10px 0; color: #64748b;">Example:</p>
                            <code style="background: #fff; padding: 5px; display: block;">8b7c1a2d-3e4f-5a6b-7c8d-9e0f1a2b3c4d<br/>1a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d</code>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes (Optional)</label>
                    <textarea name="notes" rows="2" placeholder="e.g., Batch 1 - Created on SoftEther"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Add to Pool</button>
            </form>
        </div>
    </div>
    
    <script src="/assets/js/main.js"></script>
    <script>
    function updateInstructions() {
        const select = document.getElementById('server_id');
        const selectedOption = select.options[select.selectedIndex];
        const serverType = selectedOption.getAttribute('data-type');
        
        document.getElementById('server_type').value = serverType;
        
        document.getElementById('sstp-format').style.display = serverType === 'sstp' ? 'block' : 'none';
        document.getElementById('v2ray-format').style.display = serverType === 'v2ray' ? 'block' : 'none';
        
        // Update placeholder
        const textarea = document.getElementById('credentials');
        if (serverType === 'sstp') {
            textarea.placeholder = 'username1:password1\nusername2:password2\nusername3:password3';
        } else if (serverType === 'v2ray') {
            textarea.placeholder = 'UUID-1\nUUID-2\nUUID-3';
        }
    }
    </script>
</body>
</html>

