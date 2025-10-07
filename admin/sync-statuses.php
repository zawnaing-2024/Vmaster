<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    die('Please login as admin first');
}

$messages = [];

try {
    // First, ensure the columns exist
    $conn->exec("ALTER TABLE client_accounts ADD COLUMN IF NOT EXISTS status ENUM('active', 'suspended', 'disabled') DEFAULT 'active'");
    $messages[] = "✅ Ensured status column exists in client_accounts";
} catch(Exception $e) {
    $messages[] = "⚠️ Client status column: " . $e->getMessage();
}

try {
    $conn->exec("ALTER TABLE vpn_accounts ADD COLUMN IF NOT EXISTS status ENUM('active', 'suspended', 'disabled') DEFAULT 'active'");
    $messages[] = "✅ Ensured status column exists in vpn_accounts";
} catch(Exception $e) {
    $messages[] = "⚠️ VPN status column: " . $e->getMessage();
}

try {
    // Sync all VPN account statuses with their client statuses
    $stmt = $conn->exec("UPDATE vpn_accounts va 
        JOIN client_accounts ca ON va.staff_id = ca.id 
        SET va.status = ca.status");
    $messages[] = "✅ Synced VPN account statuses with client statuses";
    
    // Count how many were updated
    $stmt = $conn->query("SELECT COUNT(*) as count FROM vpn_accounts WHERE status != 'active'");
    $count = $stmt->fetch()['count'];
    $messages[] = "📊 Found $count VPN accounts with suspended/disabled status";
} catch(Exception $e) {
    $messages[] = "❌ Failed to sync statuses: " . $e->getMessage();
}

// Get statistics
try {
    $stmt = $conn->query("SELECT 
        (SELECT COUNT(*) FROM client_accounts WHERE status = 'suspended') as suspended_clients,
        (SELECT COUNT(*) FROM client_accounts WHERE status = 'disabled') as disabled_clients,
        (SELECT COUNT(*) FROM vpn_accounts WHERE status = 'suspended') as suspended_vpns,
        (SELECT COUNT(*) FROM vpn_accounts WHERE status = 'disabled') as disabled_vpns");
    $stats = $stmt->fetch();
} catch(Exception $e) {
    $stats = null;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Sync Status</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #7f8c8d;
            margin-bottom: 30px;
        }
        .message {
            padding: 12px;
            margin: 10px 0;
            border-radius: 4px;
            border-left: 4px solid #3498db;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        .message.warning {
            background: #fff3cd;
            color: #856404;
            border-left-color: #ffc107;
        }
        .stats {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .stats h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }
        .stat-item:last-child {
            border-bottom: none;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
            margin-right: 10px;
        }
        .btn-success {
            background: #28a745;
        }
        .instructions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .instructions ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        .instructions li {
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Status Synchronization</h1>
        <p class="subtitle">Sync VPN account statuses with their client statuses</p>
        
        <h2>Results:</h2>
        <?php foreach ($messages as $msg): ?>
            <?php
            $class = 'success';
            if (strpos($msg, '❌') !== false) $class = 'error';
            elseif (strpos($msg, '⚠️') !== false) $class = 'warning';
            ?>
            <div class="message <?php echo $class; ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endforeach; ?>
        
        <?php if ($stats): ?>
        <div class="stats">
            <h3>📊 Current Statistics</h3>
            <div class="stat-item">
                <span>Suspended Clients:</span>
                <strong><?php echo $stats['suspended_clients']; ?></strong>
            </div>
            <div class="stat-item">
                <span>Disabled Clients:</span>
                <strong><?php echo $stats['disabled_clients']; ?></strong>
            </div>
            <div class="stat-item">
                <span>Suspended VPN Accounts:</span>
                <strong><?php echo $stats['suspended_vpns']; ?></strong>
            </div>
            <div class="stat-item">
                <span>Disabled VPN Accounts:</span>
                <strong><?php echo $stats['disabled_vpns']; ?></strong>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="instructions">
            <h3>✅ Next Steps:</h3>
            <ol>
                <li>Go to <strong>Customer Panel → VPN Accounts</strong></li>
                <li>Check the status column for each VPN account</li>
                <li>They should now match their client's status</li>
                <li>If client is suspended → VPN should show "⏸️ Suspended"</li>
                <li>If client is disabled → VPN should show "🚫 Disabled"</li>
            </ol>
            
            <h3>🔄 Future Updates:</h3>
            <p>From now on, when you change a client's status, all their VPN accounts will automatically update to match!</p>
        </div>
        
        <a href="../customer/vpn-accounts.php" class="btn btn-success">View VPN Accounts</a>
        <a href="../customer/clients.php" class="btn">View Clients</a>
        <a href="index.php" class="btn">Admin Dashboard</a>
    </div>
</body>
</html>

