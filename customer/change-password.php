<?php
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../config/config.php';
requireLogin('customer');

$db = new Database();
$conn = $db->getConnection();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = 'All fields are required.';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'New passwords do not match.';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 6) {
        $message = 'Password must be at least 6 characters long.';
        $messageType = 'error';
    } else {
        try {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM customers WHERE id = ?");
            $stmt->execute([$_SESSION['customer_id']]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($currentPassword, $admin['password'])) {
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE customers SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $_SESSION['customer_id']]);
                
                logActivity($conn, 'customer', $_SESSION['customer_id'], 'change_password', 'Admin changed password');
                $message = t('password_changed', 'customer');
                $messageType = 'success';
            } else {
                $message = t('current_password_incorrect', 'customer');
                $messageType = 'error';
            }
        } catch(Exception $e) {
            $message = t('password_change_failed', 'customer');
            $messageType = 'error';
            error_log($e->getMessage());
        }
    }
}

$pageTitle = t('change_password', 'customer') . ' - ' . SITE_NAME;
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
                    <h1 class="page-title"><?php echo t('change_password', 'customer'); ?></h1>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="card" style="max-width: 600px;">
                <div class="card-header">
                    <h2 class="card-title">🔐 <?php echo t('update_your_password', 'customer'); ?></h2>
                </div>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="current_password"><?php echo t('current_password', 'customer'); ?> *</label>
                        <input type="password" name="current_password" id="current_password" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password"><?php echo t('new_password', 'customer'); ?> * (<?php echo t('min_6_characters', 'customer'); ?>)</label>
                        <input type="password" name="new_password" id="new_password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password"><?php echo t('confirm_new_password', 'customer'); ?> *</label>
                        <input type="password" name="confirm_password" id="confirm_password" required minlength="6">
                    </div>
                    
                    <div class="alert alert-info">
                        <strong><?php echo t('password_requirements', 'customer'); ?>:</strong>
                        <ul style="margin: 10px 0 0 20px;">
                            <li><?php echo t('minimum_6_characters', 'customer'); ?></li>
                            <li><?php echo t('use_strong_password', 'customer'); ?></li>
                            <li><?php echo t('avoid_common_words', 'customer'); ?></li>
                        </ul>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block"><?php echo t('change_password', 'customer'); ?></button>
                </form>
            </div>
        </div>
    </div>
    
    <script src="/assets/js/main.js"></script>
</body>
</html>
