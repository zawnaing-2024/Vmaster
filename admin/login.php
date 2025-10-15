<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/language.php';

// If already logged in, redirect to dashboard
if (isLoggedIn('admin')) {
    redirect('/admin/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = t('invalid_credentials', 'customer');
    } else {
        $db = new Database();
        $conn = $db->getConnection();
        
        try {
            $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_name'] = $admin['full_name'];
                
                logActivity($conn, 'admin', $admin['id'], 'login', 'Admin logged in');
                redirect('/admin/index.php');
            } else {
                $error = t('invalid_credentials', 'customer');
            }
        } catch(Exception $e) {
            $error = t('error_occurred', 'customer');
            error_log($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('admin_login', 'admin'); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .language-switcher-login {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .language-switcher-login .dropdown-toggle {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #ddd;
            color: #333;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .language-switcher-login .dropdown-toggle:hover {
            background: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="language-switcher-login">
        <?php include __DIR__ . '/../includes/language_switcher.php'; ?>
    </div>
    
    <div class="login-container">
        <div class="login-card">
            <div style="text-align: center; margin-bottom: 20px;">
                <img src="/assets/images/logo.jpg" alt="VMaster Logo" style="max-width: 150px; height: auto; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            </div>
            <h1>🔐 <?php echo t('admin_login', 'admin'); ?></h1>
            <p class="subtitle"><?php echo t('login_to_admin', 'admin'); ?></p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username"><?php echo t('username', 'common'); ?></label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password"><?php echo t('password', 'common'); ?></label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block"><?php echo t('login', 'common'); ?></button>
            </form>
            
            <div class="login-footer">
                <a href="/">← <?php echo t('back', 'common'); ?></a>
            </div>
        </div>
    </div>
</body>
</html>

