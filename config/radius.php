<?php
// RADIUS Server Configuration

// RADIUS Database Connection
// For Docker: use 'radius-db' as host
// For local: use 'localhost' or your RADIUS server IP
define('RADIUS_DB_HOST', getenv('RADIUS_DB_HOST') ?: 'radius-db'); 
define('RADIUS_DB_PORT', 3306);
define('RADIUS_DB_NAME', 'radius');
define('RADIUS_DB_USER', 'radius');
define('RADIUS_DB_PASS', 'radiuspass');

// RADIUS Server Settings
define('RADIUS_SERVER_IP', '127.0.0.1');
define('RADIUS_SERVER_PORT', 1812);
define('RADIUS_SHARED_SECRET', 'testing123'); // Change this!

// Enable/Disable RADIUS
define('RADIUS_ENABLED', true); // Set to true after setup

// Get RADIUS database connection
function getRadiusConnection() {
    static $radiusConn = null;
    
    if ($radiusConn === null) {
        try {
            $radiusConn = new PDO(
                "mysql:host=" . RADIUS_DB_HOST . ";port=" . RADIUS_DB_PORT . ";dbname=" . RADIUS_DB_NAME,
                RADIUS_DB_USER,
                RADIUS_DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch(PDOException $e) {
            error_log("RADIUS DB Connection failed: " . $e->getMessage());
            return null;
        }
    }
    
    return $radiusConn;
}

