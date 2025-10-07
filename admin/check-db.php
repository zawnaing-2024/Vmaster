<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    die('Please login as admin first');
}

$checks = [];

// Check if status column exists
try {
    $stmt = $conn->query("SHOW COLUMNS FROM client_accounts LIKE 'status'");
    $result = $stmt->fetch();
    if ($result) {
        $checks[] = ['✅ Status column exists in client_accounts', 'success'];
    } else {
        $checks[] = ['❌ Status column MISSING in client_accounts - Run migration!', 'error'];
    }
} catch(Exception $e) {
    $checks[] = ['❌ Error checking status column: ' . $e->getMessage(), 'error'];
}

// Check if admin_notifications table exists
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'admin_notifications'");
    $result = $stmt->fetch();
    if ($result) {
        $checks[] = ['✅ admin_notifications table exists', 'success'];
        
        // Count notifications
        $stmt = $conn->query("SELECT COUNT(*) as count FROM admin_notifications");
        $count = $stmt->fetch()['count'];
        $checks[] = ["📊 Total notifications: $count", 'info'];
    } else {
        $checks[] = ['❌ admin_notifications table MISSING - Run migration!', 'error'];
    }
} catch(Exception $e) {
    $checks[] = ['❌ Error checking notifications table: ' . $e->getMessage(), 'error'];
}

// Check if vpn_handler.php exists
if (file_exists('../includes/vpn_handler.php')) {
    $checks[] = ['✅ vpn_handler.php exists', 'success'];
} else {
    $checks[] = ['❌ vpn_handler.php MISSING', 'error'];
}

// Check for clients with status
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM client_accounts WHERE status = 'suspended'");
    $suspended = $stmt->fetch()['count'];
    $stmt = $conn->query("SELECT COUNT(*) as count FROM client_accounts WHERE status = 'disabled'");
    $disabled = $stmt->fetch()['count'];
    $checks[] = ["📊 Suspended clients: $suspended, Disabled clients: $disabled", 'info'];
} catch(Exception $e) {
    $checks[] = ['⚠️ Cannot read status field: ' . $e->getMessage(), 'warning'];
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Check</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
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
        }
        .check {
            padding: 12px;
            margin: 10px 0;
            border-radius: 4px;
            border-left: 4px solid #3498db;
        }
        .check.success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        .check.error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        .check.warning {
            background: #fff3cd;
            color: #856404;
            border-left-color: #ffc107;
        }
        .check.info {
            background: #d1ecf1;
            color: #0c5460;
            border-left-color: #17a2b8;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
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
        .btn-danger {
            background: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Database Status Check</h1>
        
        <?php foreach ($checks as $check): ?>
            <div class="check <?php echo $check[1]; ?>">
                <?php echo htmlspecialchars($check[0]); ?>
            </div>
        <?php endforeach; ?>
        
        <h2>🔧 Actions:</h2>
        
        <?php
        $hasErrors = false;
        foreach ($checks as $check) {
            if ($check[1] === 'error') {
                $hasErrors = true;
                break;
            }
        }
        ?>
        
        <?php if ($hasErrors): ?>
            <p style="color: #dc3545; font-weight: bold;">
                ⚠️ Database migration required! Click below to run it:
            </p>
            <a href="run-migration.php" class="btn btn-danger">🚀 Run Migration Now</a>
        <?php else: ?>
            <p style="color: #28a745; font-weight: bold;">
                ✅ Database is ready! Suspension system should work.
            </p>
            <a href="notifications.php" class="btn btn-success">View Notifications</a>
        <?php endif; ?>
        
        <a href="index.php" class="btn">Back to Dashboard</a>
        
        <h2>📝 Test Instructions:</h2>
        <ol>
            <li>Go to Customer Panel → My Clients</li>
            <li>Edit a client that has VPN accounts</li>
            <li>Change Status to "Suspended"</li>
            <li>Click Save</li>
            <li>Check the success message</li>
            <li>Check Admin → Notifications for SSTP/V2Ray alerts</li>
            <li>Check VPN Accounts page - Outline should be deleted</li>
        </ol>
    </div>
</body>
</html>

