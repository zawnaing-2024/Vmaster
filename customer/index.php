<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../config/config.php';
requireLogin('customer');

$db = new Database();
$conn = $db->getConnection();

// Get customer info
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$_SESSION['customer_id']]);
$customer = $stmt->fetch();

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM client_accounts WHERE customer_id = ? AND status = 'active'");
$stmt->execute([$_SESSION['customer_id']]);
$totalClients = $stmt->fetch()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM vpn_accounts WHERE customer_id = ? AND status = 'active'");
$stmt->execute([$_SESSION['customer_id']]);
$totalVpnAccounts = $stmt->fetch()['total'];

// Get VPN accounts by type
$stmt = $conn->prepare("SELECT vs.server_type, COUNT(*) as count 
    FROM vpn_accounts va 
    JOIN vpn_servers vs ON va.server_id = vs.id 
    WHERE va.customer_id = ? AND va.status = 'active'
    GROUP BY vs.server_type");
$stmt->execute([$_SESSION['customer_id']]);
$accountsByType = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = t('dashboard', 'customer') . ' - ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
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
                    <h1 class="page-title"><?php echo t('dashboard', 'customer'); ?></h1>
                </div>
                <div class="user-info">
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['customer_name'], 0, 1)); ?></div>
                    <div>
                        <strong><?php echo $_SESSION['customer_name']; ?></strong>
                        <br><small><?php echo $_SESSION['customer_company']; ?></small>
                    </div>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label"><?php echo t('total_clients', 'customer'); ?></div>
                    <div class="stat-value"><?php echo $totalClients; ?> / <?php echo $customer['max_clients']; ?></div>
                </div>
                <div class="stat-card success">
                    <div class="stat-label"><?php echo t('vpn_accounts', 'customer'); ?></div>
                    <div class="stat-value"><?php echo $totalVpnAccounts; ?></div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-label">Outline <?php echo t('vpn_accounts', 'customer'); ?></div>
                    <div class="stat-value"><?php echo $accountsByType['outline'] ?? 0; ?></div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-label">V2Ray + SSTP</div>
                    <div class="stat-value"><?php echo ($accountsByType['v2ray'] ?? 0) + ($accountsByType['sstp'] ?? 0); ?></div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?php echo t('quick_actions', 'customer'); ?></h2>
                </div>
                <div class="action-buttons">
                    <a href="/customer/clients.php" class="btn btn-primary">👤 <?php echo t('manage_clients', 'customer'); ?></a>
                    <a href="/customer/vpn-accounts.php" class="btn btn-success">🔑 <?php echo t('create_vpn_account', 'customer'); ?></a>
                    <a href="/customer/vpn-accounts.php" class="btn btn-info">📋 <?php echo t('view_all_accounts', 'customer'); ?></a>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?php echo t('account_info', 'customer'); ?></h2>
                </div>
                <table>
                    <tr>
                        <td><strong><?php echo t('company_name', 'customer'); ?>:</strong></td>
                        <td><?php echo htmlspecialchars($customer['company_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo t('contact_person', 'customer'); ?>:</strong></td>
                        <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo t('email', 'common'); ?>:</strong></td>
                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo t('phone', 'common'); ?>:</strong></td>
                        <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo t('max_client_accounts', 'customer'); ?>:</strong></td>
                        <td><?php echo $customer['max_clients']; ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo t('account_status', 'customer'); ?>:</strong></td>
                        <td>
                            <?php
                            $badgeClass = 'badge-success';
                            if ($customer['status'] === 'suspended') $badgeClass = 'badge-warning';
                            if ($customer['status'] === 'inactive') $badgeClass = 'badge-danger';
                            ?>
                            <span class="badge <?php echo $badgeClass; ?>">
                                <?php echo t($customer['status'], 'common'); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <script src="/assets/js/main.js"></script>
</body>
</html>

