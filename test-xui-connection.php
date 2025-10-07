<?php
/**
 * Test X-UI Connection and API Integration
 */

require_once __DIR__ . '/config/xui.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>X-UI Connection Test</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #00ff00; }
        .success { color: #00ff00; }
        .error { color: #ff0000; }
        .info { color: #00aaff; }
        pre { background: #000; padding: 15px; border-radius: 5px; overflow-x: auto; }
        h1 { color: #00ff00; }
        h2 { color: #00aaff; border-bottom: 1px solid #00aaff; padding-bottom: 5px; }
    </style>
</head>
<body>

<h1>🔧 X-UI Connection Test</h1>

<h2>Configuration</h2>
<pre>
X-UI URL:       <?php echo XUI_URL; ?>

X-UI Enabled:   <?php echo XUI_ENABLED ? '<span class="success">✅ Yes</span>' : '<span class="error">❌ No</span>'; ?>

Username:       <?php echo XUI_USERNAME; ?>

Password:       <?php echo str_repeat('*', strlen(XUI_PASSWORD)); ?>

Inbound ID:     <?php echo XUI_DEFAULT_INBOUND_ID; ?>

</pre>

<?php if (XUI_ENABLED): ?>

<h2>Connection Test</h2>
<pre>
<?php
$xuiHandler = getXUIHandler();

echo "Testing connection to X-UI panel...\n";
if ($xuiHandler->testConnection()) {
    echo '<span class="success">✅ Connected to X-UI successfully!</span>' . "\n\n";
    
    echo "Fetching inbounds list...\n";
    $inbounds = $xuiHandler->getInbounds();
    
    if ($inbounds !== false && count($inbounds) > 0) {
        echo '<span class="success">✅ Found ' . count($inbounds) . ' inbound(s)</span>' . "\n\n";
        
        foreach ($inbounds as $inbound) {
            echo "Inbound ID: " . $inbound['id'] . "\n";
            echo "  Protocol: " . $inbound['protocol'] . "\n";
            echo "  Port:     " . $inbound['port'] . "\n";
            echo "  Remark:   " . ($inbound['remark'] ?? 'N/A') . "\n";
            echo "  Enable:   " . ($inbound['enable'] ? 'Yes' : 'No') . "\n";
            
            // Parse settings to count clients
            if (isset($inbound['settings'])) {
                $settings = json_decode($inbound['settings'], true);
                if (isset($settings['clients'])) {
                    echo "  Clients:  " . count($settings['clients']) . "\n";
                }
            }
            echo "\n";
        }
    } else {
        echo '<span class="error">❌ No inbounds found or error fetching inbounds</span>' . "\n";
    }
    
} else {
    echo '<span class="error">❌ Failed to connect to X-UI</span>' . "\n\n";
    echo "Please check:\n";
    echo "- X-UI panel is running at " . XUI_URL . "\n";
    echo "- Username and password are correct\n";
    echo "- X-UI panel is accessible from this server\n";
}
?>
</pre>

<h2>Test Adding Client</h2>
<pre>
<?php
if ($xuiHandler->testConnection()) {
    echo "Generating test UUID...\n";
    $testUUID = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    echo "Test UUID: $testUUID\n\n";
    
    echo "Adding test client to inbound " . XUI_DEFAULT_INBOUND_ID . "...\n";
    $testEmail = 'test_vmaster_' . time() . '@local';
    
    $result = $xuiHandler->addClient(XUI_DEFAULT_INBOUND_ID, $testUUID, $testEmail);
    
    if ($result) {
        echo '<span class="success">✅ Test client added successfully!</span>' . "\n";
        echo "Email: $testEmail\n";
        echo "UUID:  $testUUID\n\n";
        
        echo "Deleting test client...\n";
        $deleteResult = $xuiHandler->deleteClient(XUI_DEFAULT_INBOUND_ID, $testUUID);
        
        if ($deleteResult) {
            echo '<span class="success">✅ Test client deleted successfully!</span>' . "\n\n";
            echo '<span class="success">🎉 X-UI API is working perfectly!</span>' . "\n";
            echo '<span class="success">You can now integrate with VMaster!</span>' . "\n";
        } else {
            echo '<span class="error">⚠️ Added client but failed to delete</span>' . "\n";
            echo 'Please manually delete client: ' . $testEmail . "\n";
        }
    } else {
        echo '<span class="error">❌ Failed to add test client</span>' . "\n";
        echo "Check X-UI panel logs for details\n";
    }
} else {
    echo '<span class="error">Skipping test - connection failed</span>' . "\n";
}
?>
</pre>

<?php else: ?>

<pre>
<span class="error">❌ X-UI is disabled in configuration</span>

To enable X-UI integration:
1. Edit /var/www/html/config/xui.php
2. Set XUI_ENABLED to true
3. Configure your X-UI credentials
4. Refresh this page
</pre>

<?php endif; ?>

<h2>Next Steps</h2>
<pre>
<?php if (XUI_ENABLED && $xuiHandler->testConnection()): ?>
<span class="success">✅ X-UI is ready for integration!</span>

To integrate with VMaster:
1. Update vpn_handler.php to use X-UI for V2Ray accounts
2. Update customer/vpn-accounts.php to delete from X-UI
3. Update customer/clients.php to delete from X-UI
4. Test creating V2Ray account via VMaster portal

Files to update are in your Git repository.
<?php else: ?>
<span class="error">Fix the connection issues above first!</span>
<?php endif; ?>
</pre>

</body>
</html>

