<?php
/**
 * VMaster CMS - Common Functions
 * Shared utility functions used across the application
 */

// Note: sanitize(), logActivity(), and requireLogin() are defined in config.php
// We don't redeclare them here to avoid conflicts

/**
 * Generate random string for usernames/passwords
 */
function generateRandomString($length = 10) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Generate random password with special characters
 */
function generateRandomPassword($length = 12) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

/**
 * Format date for display (extended version)
 */
function formatDateTime($date, $format = 'Y-m-d H:i:s') {
    if (!$date) return 'N/A';
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format bytes to human readable size
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status) {
    $badges = [
        'active' => '<span class="badge badge-success">Active</span>',
        'inactive' => '<span class="badge badge-secondary">Inactive</span>',
        'suspended' => '<span class="badge badge-warning">Suspended</span>',
        'disabled' => '<span class="badge badge-danger">Disabled</span>',
        'maintenance' => '<span class="badge badge-info">Maintenance</span>',
        'pending' => '<span class="badge badge-warning">Pending</span>',
    ];
    
    return $badges[strtolower($status)] ?? '<span class="badge badge-secondary">' . htmlspecialchars($status) . '</span>';
}

/**
 * Validate email address
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (basic)
 */
function isValidPhone($phone) {
    return preg_match('/^[0-9\-\+\(\)\s]+$/', $phone);
}

/**
 * Validate IP address
 */
function isValidIP($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

/**
 * Get client IP address
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
}

/**
 * Create success message
 */
function setSuccessMessage($message) {
    $_SESSION['success_message'] = $message;
}

/**
 * Create error message
 */
function setErrorMessage($message) {
    $_SESSION['error_message'] = $message;
}

/**
 * Get and clear success message
 */
function getSuccessMessage() {
    if (isset($_SESSION['success_message'])) {
        $message = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
        return $message;
    }
    return null;
}

/**
 * Get and clear error message
 */
function getErrorMessage() {
    if (isset($_SESSION['error_message'])) {
        $message = $_SESSION['error_message'];
        unset($_SESSION['error_message']);
        return $message;
    }
    return null;
}

/**
 * Redirect with message
 */
function redirectWithMessage($url, $message, $type = 'success') {
    if ($type === 'success') {
        setSuccessMessage($message);
    } else {
        setErrorMessage($message);
    }
    header("Location: $url");
    exit;
}

/**
 * Check if RADIUS is enabled
 */
function isRadiusEnabled() {
    return defined('RADIUS_ENABLED') && RADIUS_ENABLED === true;
}

/**
 * Get VPN type display name
 */
function getVPNTypeName($type) {
    $types = [
        'outline' => 'Outline VPN',
        'sstp' => 'SSTP (SoftEther)',
        'v2ray' => 'V2Ray',
        'openvpn' => 'OpenVPN',
        'wireguard' => 'WireGuard',
    ];
    
    return $types[strtolower($type)] ?? strtoupper($type);
}

/**
 * Truncate text to specified length
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if string is valid JSON
 */
function isJSON($string) {
    if (!is_string($string)) return false;
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

/**
 * Safe JSON decode
 */
function safeJSONDecode($string, $assoc = true) {
    if (!isJSON($string)) return null;
    return json_decode($string, $assoc);
}

/**
 * Convert array to comma-separated string
 */
function arrayToString($array, $separator = ', ') {
    if (!is_array($array)) return $array;
    return implode($separator, array_filter($array));
}

/**
 * Check if value is unlimited (NULL or 0)
 */
function isUnlimited($value) {
    return $value === null || $value === 0 || $value === '0';
}

/**
 * Display limit text (e.g., "10" or "Unlimited")
 */
function displayLimit($value, $prefix = '', $suffix = '') {
    if (isUnlimited($value)) {
        return 'Unlimited';
    }
    return $prefix . $value . $suffix;
}

/**
 * Calculate percentage
 */
function calculatePercentage($current, $total) {
    if ($total == 0 || isUnlimited($total)) {
        return 0;
    }
    return round(($current / $total) * 100, 2);
}

/**
 * Get time ago string (e.g., "5 minutes ago")
 */
function timeAgo($datetime) {
    $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
    $difference = time() - $timestamp;
    
    if ($difference < 60) {
        return 'just now';
    } elseif ($difference < 3600) {
        $minutes = floor($difference / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($difference < 86400) {
        $hours = floor($difference / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($difference < 604800) {
        $days = floor($difference / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('Y-m-d H:i', $timestamp);
    }
}

