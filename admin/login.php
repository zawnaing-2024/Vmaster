<?php
require_once __DIR__ . '/../config/config.php';

// If already logged in, redirect to dashboard
if (isLoggedIn('admin')) {
    redirect('/admin/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
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
                $error = 'Invalid username or password.';
            }
        } catch(Exception $e) {
            $error = 'Login failed. Please try again.';
            error_log($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div style="text-align: center; margin-bottom: 20px;">
                <img src="/assets/images/logo.jpg" alt="VMaster Logo" style="max-width: 150px; height: auto; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            </div>
            <h1>🔐 Admin Login</h1>
            <p class="subtitle"><?php echo SITE_NAME; ?></p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>
            
            <div class="login-footer">
                <a href="/">← Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>

