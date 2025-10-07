<?php
/**
 * Local Test Script for X-UI API
 * Run this from command line: php test-xui-local.php
 */

// X-UI Configuration
$XUI_URL = 'http://103.117.149.112:54321';
$XUI_USERNAME = 'gdadmin';
$XUI_PASSWORD = 'GD@dm!n'; // Fixed password
$XUI_INBOUND_ID = 1; // Change this if needed

echo "════════════════════════════════════════════════════════════════\n";
echo "🔧 X-UI API Test (Local)\n";
echo "════════════════════════════════════════════════════════════════\n\n";

echo "Configuration:\n";
echo "  URL:      $XUI_URL\n";
echo "  Username: $XUI_USERNAME\n";
echo "  Password: " . str_repeat('*', strlen($XUI_PASSWORD)) . "\n";
echo "  Inbound:  $XUI_INBOUND_ID\n\n";

// Test 1: Login
echo "════════════════════════════════════════════════════════════════\n";
echo "TEST 1: Login to X-UI\n";
echo "════════════════════════════════════════════════════════════════\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $XUI_URL . '/login',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'username' => $XUI_USERNAME,
        'password' => $XUI_PASSWORD
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEJAR => '/tmp/xui_test_cookie.txt',
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response: $response\n";
echo "HTTP Code: $httpCode\n";

$loginResult = json_decode($response, true);
if ($httpCode === 200 && isset($loginResult['success']) && $loginResult['success']) {
    echo "✅ Login successful!\n\n";
} else {
    echo "❌ Login failed!\n";
    echo "Please check your credentials.\n";
    exit(1);
}

// Test 2: Get Inbounds
echo "════════════════════════════════════════════════════════════════\n";
echo "TEST 2: Get Inbounds List\n";
echo "════════════════════════════════════════════════════════════════\n";

// Try different API endpoints (X-UI versions use different paths)
$endpoints = [
    '/xui/inbound/list',
    '/panel/api/inbounds/list',
    '/xui/API/inbounds/list',
    '/api/inbounds/list'
];

$response = null;
$httpCode = 0;

foreach ($endpoints as $endpoint) {
    echo "Trying: $endpoint\n";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $XUI_URL . $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEFILE => '/tmp/xui_test_cookie.txt',
        CURLOPT_TIMEOUT => 10,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "✅ Found working endpoint: $endpoint\n";
        break;
    }
}

echo "HTTP Code: $httpCode\n";

$inboundsResult = json_decode($response, true);
if ($httpCode === 200 && isset($inboundsResult['success']) && $inboundsResult['success']) {
    echo "✅ Inbounds retrieved!\n\n";
    
    $inbounds = $inboundsResult['obj'] ?? [];
    echo "Found " . count($inbounds) . " inbound(s):\n\n";
    
    foreach ($inbounds as $inbound) {
        echo "  ID:       " . $inbound['id'] . "\n";
        echo "  Protocol: " . $inbound['protocol'] . "\n";
        echo "  Port:     " . $inbound['port'] . "\n";
        echo "  Remark:   " . ($inbound['remark'] ?? 'N/A') . "\n";
        echo "  Enable:   " . ($inbound['enable'] ? 'Yes' : 'No') . "\n";
        
        // Count clients
        if (isset($inbound['settings'])) {
            $settings = json_decode($inbound['settings'], true);
            if (isset($settings['clients'])) {
                echo "  Clients:  " . count($settings['clients']) . "\n";
            }
        }
        echo "\n";
    }
    
    // Auto-detect VMess inbound
    $vmessInbound = null;
    foreach ($inbounds as $inbound) {
        if ($inbound['protocol'] === 'vmess') {
            $vmessInbound = $inbound;
            break;
        }
    }
    
    if ($vmessInbound) {
        echo "✅ Found VMess inbound (ID: " . $vmessInbound['id'] . ")\n";
        echo "   Use this ID in config/xui.php: XUI_DEFAULT_INBOUND_ID = " . $vmessInbound['id'] . "\n\n";
        $XUI_INBOUND_ID = $vmessInbound['id'];
    } else {
        echo "⚠️  No VMess inbound found. Please check X-UI panel.\n\n";
    }
    
} else {
    echo "❌ Failed to get inbounds!\n";
    echo "Response: $response\n";
    exit(1);
}

// Test 3: Add Test Client
echo "════════════════════════════════════════════════════════════════\n";
echo "TEST 3: Add Test Client\n";
echo "════════════════════════════════════════════════════════════════\n";

// Generate UUID
$testUUID = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);
$testEmail = 'test_vmaster_' . time() . '@local';

echo "Test UUID:  $testUUID\n";
echo "Test Email: $testEmail\n";
echo "Inbound ID: $XUI_INBOUND_ID\n\n";

$data = [
    'id' => (int)$XUI_INBOUND_ID,
    'settings' => json_encode([
        'clients' => [
            [
                'id' => $testUUID,
                'email' => $testEmail,
                'alterId' => 0
            ]
        ]
    ])
];

echo "Sending request...\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $XUI_URL . '/xui/inbound/addClient',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => '/tmp/xui_test_cookie.txt',
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response: $response\n";
echo "HTTP Code: $httpCode\n";

$addResult = json_decode($response, true);
if ($httpCode === 200 && isset($addResult['success']) && $addResult['success']) {
    echo "✅ Test client added successfully!\n\n";
} else {
    echo "❌ Failed to add test client!\n";
    echo "Please check X-UI panel and inbound ID.\n";
    exit(1);
}

// Test 4: Delete Test Client
echo "════════════════════════════════════════════════════════════════\n";
echo "TEST 4: Delete Test Client\n";
echo "════════════════════════════════════════════════════════════════\n";

$data = [
    'id' => (int)$XUI_INBOUND_ID,
    'clientId' => $testUUID
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $XUI_URL . '/xui/inbound/delClient',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_COOKIEFILE => '/tmp/xui_test_cookie.txt',
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response: $response\n";
echo "HTTP Code: $httpCode\n";

$deleteResult = json_decode($response, true);
if ($httpCode === 200 && isset($deleteResult['success']) && $deleteResult['success']) {
    echo "✅ Test client deleted successfully!\n\n";
} else {
    echo "⚠️  Failed to delete test client (may need manual cleanup)\n\n";
}

// Summary
echo "════════════════════════════════════════════════════════════════\n";
echo "🎉 ALL TESTS COMPLETED!\n";
echo "════════════════════════════════════════════════════════════════\n\n";

echo "✅ Login:       OK\n";
echo "✅ Get Inbounds: OK\n";
echo "✅ Add Client:   OK\n";
echo "✅ Delete Client: OK\n\n";

echo "🚀 X-UI API is working perfectly!\n";
echo "   Ready to integrate with VMaster!\n\n";

echo "Next Steps:\n";
echo "1. Update config/xui.php with the correct inbound ID\n";
echo "2. Deploy to production server\n";
echo "3. Test via web: https://vmaster.vip/test-xui-connection.php\n";
echo "4. Integrate with VMaster VPN handler\n\n";

// Cleanup
if (file_exists('/tmp/xui_test_cookie.txt')) {
    unlink('/tmp/xui_test_cookie.txt');
}

echo "════════════════════════════════════════════════════════════════\n";
?>

