<?php
require_once __DIR__ . '/../config/config.php';

if (isLoggedIn('customer')) {
    $db = new Database();
    $conn = $db->getConnection();
    logActivity($conn, 'customer', $_SESSION['customer_id'], 'logout', 'Customer logged out');
}

session_unset();
session_destroy();

redirect('/customer/login.php');
?>

