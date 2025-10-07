<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    die('Please login as admin first');
}

$messages = [];

try {
    // Add status column to client_accounts
    $conn->exec("ALTER TABLE client_accounts ADD COLUMN IF NOT EXISTS status ENUM('active', 'suspended', 'disabled') DEFAULT 'active'");
    $messages[] = "✅ Added status column to client_accounts";
} catch(Exception $e) {
    $messages[] = "⚠️ Status column (client_accounts): " . $e->getMessage();
}

try {
    // Add status column to vpn_accounts
    $conn->exec("ALTER TABLE vpn_accounts ADD COLUMN IF NOT EXISTS status ENUM('active', 'suspended', 'disabled') DEFAULT 'active'");
    $messages[] = "✅ Added status column to vpn_accounts";
} catch(Exception $e) {
    $messages[] = "⚠️ Status column (vpn_accounts): " . $e->getMessage();
}

try {
    // Create admin_notifications table
    $sql = "CREATE TABLE IF NOT EXISTS admin_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        notification_type ENUM('client_suspended', 'client_disabled', 'vpn_manual_action', 'system_alert') NOT NULL,
        severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        related_customer_id INT DEFAULT NULL,
        related_client_id INT DEFAULT NULL,
        action_required TEXT DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_is_read(is_read),
        INDEX idx_created_at(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->exec($sql);
    $messages[] = "✅ Created admin_notifications table";
} catch(Exception $e) {
    $messages[] = "⚠️ Notifications table: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Migration</title>
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
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Database Migration Complete!</h1>
        
        <?php foreach ($messages as $msg): ?>
            <div class="message"><?php echo htmlspecialchars($msg); ?></div>
        <?php endforeach; ?>
        
        <h2>✅ New Features Added:</h2>
        <ul>
            <li>Client status (active/suspended/disabled)</li>
            <li>Admin notification system</li>
            <li>Automatic Outline VPN deletion on suspend</li>
            <li>Manual action notifications for SSTP/V2Ray</li>
        </ul>
        
        <h2>🚀 Test It:</h2>
        <ol>
            <li>Go to Customer Panel → My Clients</li>
            <li>Edit a client and change status to "Suspended"</li>
            <li>Check Admin Panel → Notifications</li>
        </ol>
        
        <a href="index.php" class="btn">Go to Admin Dashboard</a>
        <a href="notifications.php" class="btn">View Notifications</a>
    </div>
</body>
</html>

