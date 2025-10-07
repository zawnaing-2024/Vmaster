<?php
require_once __DIR__ . '/../config/config.php';

if (isLoggedIn('admin')) {
    $db = new Database();
    $conn = $db->getConnection();
    logActivity($conn, 'admin', $_SESSION['admin_id'], 'logout', 'Admin logged out');
}

session_unset();
session_destroy();

redirect('/admin/login.php');
?>

