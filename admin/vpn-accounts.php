<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../config/config.php';
requireLogin('admin');

$db = new Database();
$conn = $db->getConnection();

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
            </div>
            
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
    
    <script src="/assets/js/main.js"></script>
</body>
</html>
