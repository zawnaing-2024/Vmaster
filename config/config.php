<?php
// Application Configuration

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Site Configuration
define('SITE_NAME', 'VMaster');
define('SITE_FULL_NAME', 'VMaster - VPN Management System');
define('SITE_URL', 'http://localhost:8080');
define('CURRENCY_SYMBOL', 'Ks');

// Path Configuration
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('QRCODE_PATH', UPLOAD_PATH . '/qrcodes');

// Timezone
date_default_timezone_set('Asia/Yangon');

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include Database Configuration
require_once BASE_PATH . '/config/database.php';

// Helper Functions
function redirect($url) {
    header("Location: " . $url);
    exit();
}

function isLoggedIn($type = 'admin') {
    return isset($_SESSION[$type . '_id']);
}

function requireLogin($type = 'admin') {
    if (!isLoggedIn($type)) {
        redirect('/' . $type . '/login.php');
    }
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatDate($date) {
    return date('M d, Y H:i', strtotime($date));
}

function logActivity($conn, $userType, $userId, $action, $description = '') {
    try {
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_type, user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userType,
            $userId,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch(Exception $e) {
        error_log("Log Activity Error: " . $e->getMessage());
    }
}

function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

function generateAccessKey($prefix = 'ss://') {
    return $prefix . base64_encode(random_bytes(32));
}
