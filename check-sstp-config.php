<?php
/**
 * SSTP & RADIUS Configuration Checker
 * Run this to diagnose why SSTP accounts are not being created
 */

echo "<h1>🔍 SSTP & RADIUS Configuration Check</h1>";
echo "<pre>";

// 1. Check if RADIUS config is loaded
echo "1️⃣ RADIUS Configuration Status:\n";
echo str_repeat("=", 50) . "\n";

if (file_exists(__DIR__ . '/config/radius.php')) {
    echo "✅ config/radius.php exists\n";
    require_once __DIR__ . '/config/radius.php';
    
    echo "RADIUS_ENABLED: " . (defined('RADIUS_ENABLED') ? (RADIUS_ENABLED ? 'TRUE' : 'FALSE') : 'NOT DEFINED') . "\n";
    echo "RADIUS_DB_HOST: " . (defined('RADIUS_DB_HOST') ? RADIUS_DB_HOST : 'NOT DEFINED') . "\n";
    echo "RADIUS_DB_NAME: " . (defined('RADIUS_DB_NAME') ? RADIUS_DB_NAME : 'NOT DEFINED') . "\n";
} else {
    echo "❌ config/radius.php NOT FOUND!\n";
}

echo "\n";

// 2. Check RADIUS database connection
echo "2️⃣ RADIUS Database Connection:\n";
echo str_repeat("=", 50) . "\n";

if (defined('RADIUS_ENABLED') && RADIUS_ENABLED === true) {
    if (function_exists('getRadiusConnection')) {
        $radiusConn = getRadiusConnection();
        if ($radiusConn) {
            echo "✅ RADIUS database connection successful\n";
            
            // Check if radcheck table exists
            try {
                $stmt = $radiusConn->query("SHOW TABLES LIKE 'radcheck'");
                if ($stmt->rowCount() > 0) {
                    echo "✅ radcheck table exists\n";
                    
                    // Count RADIUS users
                    $stmt = $radiusConn->query("SELECT COUNT(*) as total FROM radcheck");
                    $count = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo "📊 Total RADIUS users: " . $count['total'] . "\n";
                } else {
                    echo "❌ radcheck table NOT FOUND!\n";
                }
            } catch (Exception $e) {
                echo "❌ Error checking tables: " . $e->getMessage() . "\n";
            }
        } else {
            echo "❌ RADIUS database connection FAILED!\n";
            echo "Check docker-compose logs: docker logs vmaster_radius_db\n";
        }
    } else {
        echo "⚠️ getRadiusConnection() function not found\n";
    }
} else {
    echo "⚠️ RADIUS is DISABLED in config\n";
}

echo "\n";

// 3. Check VPN credentials pool
echo "3️⃣ VPN Credentials Pool Status:\n";
echo str_repeat("=", 50) . "\n";

require_once __DIR__ . '/config/config.php';
$db = new Database();
$conn = $db->getConnection();

try {
    $stmt = $conn->query("SHOW TABLES LIKE 'vpn_credentials_pool'");
    if ($stmt->rowCount() > 0) {
        echo "✅ vpn_credentials_pool table exists\n";
        
        // Check SSTP credentials
        $stmt = $conn->query("SELECT COUNT(*) as total FROM vpn_credentials_pool WHERE vpn_type = 'sstp'");
        $sstpCount = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "📊 SSTP pool credentials: " . $sstpCount['total'] . "\n";
        
        // Check available SSTP credentials
        $stmt = $conn->query("SELECT COUNT(*) as total FROM vpn_credentials_pool WHERE vpn_type = 'sstp' AND is_assigned = 0");
        $availableCount = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "📊 Available SSTP credentials: " . $availableCount['total'] . "\n";
    } else {
        echo "❌ vpn_credentials_pool table NOT FOUND!\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. Check SSTP servers
echo "4️⃣ SSTP Servers Status:\n";
echo str_repeat("=", 50) . "\n";

try {
    $stmt = $conn->query("SELECT id, server_name, server_type, status FROM vpn_servers WHERE server_type = 'sstp'");
    $sstpServers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($sstpServers) > 0) {
        echo "✅ Found " . count($sstpServers) . " SSTP server(s):\n";
        foreach ($sstpServers as $server) {
            echo "  - ID: {$server['id']}, Name: {$server['server_name']}, Status: {$server['status']}\n";
        }
    } else {
        echo "⚠️ No SSTP servers found!\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// 5. Summary and Recommendations
echo "5️⃣ Diagnosis & Recommendations:\n";
echo str_repeat("=", 50) . "\n";

$issues = [];

if (!defined('RADIUS_ENABLED') || RADIUS_ENABLED !== true) {
    $issues[] = "RADIUS is disabled. SSTP will try to use credentials pool instead.";
}

if (defined('RADIUS_ENABLED') && RADIUS_ENABLED === true) {
    if (!function_exists('getRadiusConnection') || !getRadiusConnection()) {
        $issues[] = "RADIUS is enabled but database connection failed.";
        echo "❌ CRITICAL: RADIUS database not accessible!\n";
        echo "   Solution: Run 'docker-compose -f docker-compose.prod.yml up -d'\n";
        echo "   to ensure radius-db container is running.\n";
    }
}

if (isset($sstpCount) && $sstpCount['total'] == 0 && (!defined('RADIUS_ENABLED') || RADIUS_ENABLED !== true)) {
    $issues[] = "No SSTP credentials in pool and RADIUS is disabled.";
    echo "❌ CRITICAL: No way to create SSTP accounts!\n";
    echo "   Solution 1: Enable RADIUS (recommended)\n";
    echo "   Solution 2: Add credentials to pool via admin panel\n";
}

if (count($issues) === 0) {
    echo "✅ All checks passed! SSTP should work correctly.\n";
} else {
    echo "⚠️ Issues found:\n";
    foreach ($issues as $i => $issue) {
        echo "   " . ($i+1) . ". $issue\n";
    }
}

echo "\n";
echo str_repeat("=", 50) . "\n";
echo "Check complete!\n";
echo "</pre>";
?>

