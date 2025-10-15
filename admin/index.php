<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../config/config.php';
requireLogin('admin');

$db = new Database();
$conn = $db->getConnection();

// Get statistics
$stmt = $conn->query("SELECT COUNT(*) as total FROM customers WHERE status = 'active'");
$totalCustomers = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM vpn_servers WHERE status = 'active'");
$totalServers = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM client_accounts WHERE status = 'active'");
$totalClients = $stmt->fetch()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM vpn_accounts WHERE status = 'active'");
$totalAccounts = $stmt->fetch()['total'];

// Get recent activities
$stmt = $conn->prepare("SELECT * FROM activity_logs WHERE user_type = 'admin' ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$recentActivities = $stmt->fetchAll();

$pageTitle = 'Admin Dashboard - ' . SITE_NAME;
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
                    <h1 class="page-title">Dashboard</h1>
                </div>
                <div class="user-info">
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['admin_name'], 0, 1)); ?></div>
                    <div>
                        <strong><?php echo $_SESSION['admin_name']; ?></strong>
                        <br><small>Administrator</small>
                    </div>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Customers</div>
                    <div class="stat-value"><?php echo $totalCustomers; ?></div>
                </div>
                <div class="stat-card success">
                    <div class="stat-label">VPN Servers</div>
                    <div class="stat-value"><?php echo $totalServers; ?></div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-label">Client Accounts</div>
                    <div class="stat-value"><?php echo $totalClients; ?></div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-label">VPN Accounts</div>
                    <div class="stat-value"><?php echo $totalAccounts; ?></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Activities</h2>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recentActivities) > 0): ?>
                                <?php foreach ($recentActivities as $activity): ?>
                                    <tr>
                                        <td><span class="badge badge-info"><?php echo htmlspecialchars($activity['action']); ?></span></td>
                                        <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['ip_address']); ?></td>
                                        <td><?php echo formatDate($activity['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty-state">
                                        <div class="empty-state-icon">📊</div>
                                        <p>No activities yet</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="/assets/js/main.js"></script>
</body>
</html>

