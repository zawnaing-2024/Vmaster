<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../config/config.php';
requireLogin('admin');

$db = new Database();
$conn = $db->getConnection();

// Get all client accounts with customer info
$stmt = $conn->query("SELECT s.*, c.company_name, c.full_name as customer_name,
    (SELECT COUNT(*) FROM vpn_accounts WHERE staff_id = s.id) as vpn_count
    FROM client_accounts s 
    JOIN customers c ON s.customer_id = c.id 
    ORDER BY s.created_at DESC");
$client = $stmt->fetchAll();

$pageTitle = 'Client Accounts - ' . SITE_NAME;
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
                    <h1 class="page-title">Client Accounts</h1>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">All Client Accounts (<?php echo count($client); ?>)</h2>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Client Name</th>
                                <th>Company</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Department</th>
                                <th>VPN Accounts</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($client) > 0): ?>
                                <?php foreach ($client as $member): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($member['staff_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($member['company_name']); ?></td>
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
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <div class="empty-state-icon">👤</div>
                                        <p>No client accounts yet.</p>
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

