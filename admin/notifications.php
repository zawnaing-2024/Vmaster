<?php
session_start();
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Mark notification as read
if (isset($_POST['mark_read'])) {
    $notificationId = intval($_POST['notification_id']);
    $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1, read_at = NOW() WHERE id = ?");
    $stmt->execute([$notificationId]);
    header('Location: notifications.php');
    exit;
}

// Mark all as read
if (isset($_POST['mark_all_read'])) {
    $stmt = $conn->query("UPDATE admin_notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0");
    header('Location: notifications.php');
    exit;
}

// Delete notification
if (isset($_POST['delete'])) {
    $notificationId = intval($_POST['notification_id']);
    $stmt = $conn->prepare("DELETE FROM admin_notifications WHERE id = ?");
    $stmt->execute([$notificationId]);
    header('Location: notifications.php');
    exit;
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$whereClause = '';
if ($filter === 'unread') {
    $whereClause = 'WHERE is_read = 0';
} elseif ($filter === 'action_required') {
    $whereClause = 'WHERE action_required IS NOT NULL AND is_read = 0';
}

// Get notifications with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$countStmt = $conn->query("SELECT COUNT(*) as total FROM admin_notifications $whereClause");
$totalNotifications = $countStmt->fetch()['total'];
$totalPages = ceil($totalNotifications / $perPage);

$stmt = $conn->query("SELECT n.*, c.company_name, cl.staff_name 
    FROM admin_notifications n
    LEFT JOIN customers c ON n.related_customer_id = c.id
    LEFT JOIN client_accounts cl ON n.related_staff_id = cl.id
    $whereClause
    ORDER BY n.created_at DESC 
    LIMIT $perPage OFFSET $offset");
$notifications = $stmt->fetchAll();

// Count unread
$unreadStmt = $conn->query("SELECT COUNT(*) as unread FROM admin_notifications WHERE is_read = 0");
$unreadCount = $unreadStmt->fetch()['unread'];

$pageTitle = 'Admin Notifications';
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
            <h1>🔔 Notifications <?php if ($unreadCount > 0) echo "<span class='badge-danger'>$unreadCount</span>"; ?></h1>
            <?php if ($unreadCount > 0): ?>
            <form method="POST" style="display: inline;">
                <button type="submit" name="mark_all_read" class="btn-secondary">Mark All as Read</button>
            </form>
            <?php endif; ?>
        </div>

        <div class="filter-tabs">
            <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                All (<?php echo $totalNotifications; ?>)
            </a>
            <a href="?filter=unread" class="filter-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                Unread (<?php echo $unreadCount; ?>)
            </a>
            <a href="?filter=action_required" class="filter-tab <?php echo $filter === 'action_required' ? 'active' : ''; ?>">
                ⚠️ Action Required
            </a>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="alert alert-info">
                <p>📭 No notifications found.</p>
            </div>
        <?php else: ?>
            <div class="notifications-list">
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-card <?php echo $notif['is_read'] ? 'read' : 'unread'; ?> severity-<?php echo $notif['severity']; ?>">
                        <div class="notification-header">
                            <div class="notification-title">
                                <?php if (!$notif['is_read']): ?>
                                    <span class="unread-dot">●</span>
                                <?php endif; ?>
                                <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
                                <?php if ($notif['severity'] === 'critical'): ?>
                                    <span class="badge-danger">CRITICAL</span>
                                <?php elseif ($notif['severity'] === 'warning'): ?>
                                    <span class="badge-warning">WARNING</span>
                                <?php endif; ?>
                            </div>
                            <div class="notification-time">
                                <?php echo date('Y-m-d H:i', strtotime($notif['created_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="notification-body">
                            <p><?php echo nl2br(htmlspecialchars($notif['message'])); ?></p>
                            
                            <?php if ($notif['company_name']): ?>
                                <p><strong>Company:</strong> <?php echo htmlspecialchars($notif['company_name']); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($notif['staff_name']): ?>
                                <p><strong>Client:</strong> <?php echo htmlspecialchars($notif['staff_name']); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($notif['action_required']): ?>
                                <div class="action-required">
                                    <strong>⚠️ Action Required:</strong>
                                    <pre><?php echo htmlspecialchars($notif['action_required']); ?></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="notification-actions">
                            <?php if (!$notif['is_read']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                    <button type="submit" name="mark_read" class="btn-sm btn-secondary">Mark as Read</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                <button type="submit" name="delete" class="btn-sm btn-danger" onclick="return confirm('Delete this notification?')">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>" 
                           class="page-link <?php echo $page === $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <style>
    .filter-tabs {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        border-bottom: 2px solid #e0e0e0;
    }
    .filter-tab {
        padding: 0.75rem 1.5rem;
        text-decoration: none;
        color: #666;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        transition: all 0.3s;
    }
    .filter-tab.active {
        color: var(--primary-color);
        border-bottom-color: var(--primary-color);
        font-weight: 600;
    }
    .notifications-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .notification-card {
        background: white;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-left: 4px solid #3498db;
    }
    .notification-card.unread {
        background: #f8f9fa;
        border-left-color: #3498db;
    }
    .notification-card.severity-warning {
        border-left-color: #f39c12;
    }
    .notification-card.severity-critical {
        border-left-color: #e74c3c;
    }
    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    .notification-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .unread-dot {
        color: #3498db;
        font-size: 1.5rem;
        line-height: 0;
    }
    .notification-time {
        color: #999;
        font-size: 0.9rem;
    }
    .notification-body {
        margin-bottom: 1rem;
    }
    .action-required {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 4px;
        padding: 1rem;
        margin-top: 1rem;
    }
    .action-required pre {
        margin-top: 0.5rem;
        white-space: pre-wrap;
        font-family: monospace;
        font-size: 0.9rem;
    }
    .notification-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    .btn-sm {
        padding: 0.4rem 0.8rem;
        font-size: 0.9rem;
    }
    .badge-danger, .badge-warning {
        display: inline-block;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .badge-danger {
        background: #e74c3c;
        color: white;
    }
    .badge-warning {
        background: #f39c12;
        color: white;
    }
    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 4px;
        font-size: 0.9rem;
        font-weight: 500;
    }
    .status-active {
        background: #d4edda;
        color: #155724;
    }
    .status-suspended {
        background: #fff3cd;
        color: #856404;
    }
    .status-disabled {
        background: #f8d7da;
        color: #721c24;
    }
    </style>
</body>
</html>

