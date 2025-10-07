#!/bin/bash

################################################################################
# Bulk Add UUIDs to X-UI Panel via Direct V2Ray Config Edit
# This script connects to your V2Ray server and adds all UUIDs at once
################################################################################

set -e

echo "════════════════════════════════════════════════════════════════"
echo "🚀 Bulk Add V2Ray UUIDs to X-UI Panel"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Configuration
V2RAY_SERVER="103.117.149.112"
V2RAY_USER="ubuntu"
V2RAY_CONFIG="/etc/v2ray/config.json"
UUIDS_FILE="./v2ray-pool-export/for_xui_panel.txt"

# Check if UUIDs file exists
if [ ! -f "$UUIDS_FILE" ]; then
    echo "❌ Error: $UUIDS_FILE not found!"
    echo "Please run: ./scripts/generate-v2ray-uuids.sh first"
    exit 1
fi

echo "Configuration:"
echo "  Server:      $V2RAY_SERVER"
echo "  User:        $V2RAY_USER"
echo "  V2Ray Config: $V2RAY_CONFIG"
echo "  UUIDs File:   $UUIDS_FILE"
echo ""

# Count UUIDs
UUID_COUNT=$(wc -l < "$UUIDS_FILE")
echo "Found $UUID_COUNT UUIDs to add"
echo ""

read -p "Continue? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "Cancelled."
    exit 0
fi

echo ""
echo "Step 1: Creating PHP script to add UUIDs..."

# Create PHP script that will run on the server
cat > /tmp/add_uuids_to_v2ray.php << 'PHPSCRIPT'
<?php
/**
 * Add UUIDs to V2Ray config
 */

if ($argc < 2) {
    echo "Usage: php add_uuids_to_v2ray.php /path/to/uuids.txt\n";
    exit(1);
}

$uuidsFile = $argv[1];
$configFile = '/etc/v2ray/config.json';
$backupFile = '/etc/v2ray/config.json.backup.' . date('Y-m-d_H-i-s');

echo "Reading V2Ray config from: $configFile\n";

// Backup original config
if (!copy($configFile, $backupFile)) {
    echo "ERROR: Failed to create backup\n";
    exit(1);
}
echo "Backup created: $backupFile\n";

// Read config
$configJson = file_get_contents($configFile);
$config = json_decode($configJson, true);

if (!$config) {
    echo "ERROR: Failed to parse V2Ray config\n";
    exit(1);
}

// Read UUIDs
$uuidsData = file($uuidsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
echo "Found " . count($uuidsData) . " UUIDs to add\n";

// Find VMess inbound
$vmessInboundFound = false;
foreach ($config['inbounds'] as $key => &$inbound) {
    if (isset($inbound['protocol']) && $inbound['protocol'] === 'vmess') {
        echo "Found VMess inbound at index $key\n";
        
        // Initialize clients array if not exists
        if (!isset($inbound['settings']['clients'])) {
            $inbound['settings']['clients'] = [];
        }
        
        $existingCount = count($inbound['settings']['clients']);
        echo "Existing clients: $existingCount\n";
        
        // Add new UUIDs
        $added = 0;
        foreach ($uuidsData as $line) {
            $parts = explode('|', $line);
            if (count($parts) === 2) {
                $uuid = trim($parts[0]);
                $email = trim($parts[1]);
                
                // Check if UUID already exists
                $exists = false;
                foreach ($inbound['settings']['clients'] as $client) {
                    if ($client['id'] === $uuid) {
                        $exists = true;
                        break;
                    }
                }
                
                if (!$exists) {
                    $inbound['settings']['clients'][] = [
                        'id' => $uuid,
                        'email' => $email,
                        'alterId' => 0,
                        'level' => 0
                    ];
                    $added++;
                }
            }
        }
        
        echo "Added $added new clients\n";
        echo "Total clients now: " . count($inbound['settings']['clients']) . "\n";
        
        $vmessInboundFound = true;
        break;
    }
}

if (!$vmessInboundFound) {
    echo "ERROR: No VMess inbound found in config\n";
    exit(1);
}

// Save config
$newConfig = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (file_put_contents($configFile, $newConfig) === false) {
    echo "ERROR: Failed to write config\n";
    exit(1);
}

echo "✅ Config updated successfully!\n";
echo "Backup saved at: $backupFile\n";
exit(0);
?>
PHPSCRIPT

echo "✅ PHP script created"
echo ""

echo "Step 2: Uploading files to V2Ray server..."

# Upload PHP script
scp /tmp/add_uuids_to_v2ray.php ${V2RAY_USER}@${V2RAY_SERVER}:/tmp/ || {
    echo "❌ Failed to upload PHP script"
    exit 1
}
echo "✅ PHP script uploaded"

# Upload UUIDs file
scp "$UUIDS_FILE" ${V2RAY_USER}@${V2RAY_SERVER}:/tmp/uuids.txt || {
    echo "❌ Failed to upload UUIDs file"
    exit 1
}
echo "✅ UUIDs file uploaded"
echo ""

echo "Step 3: Executing on V2Ray server..."
ssh ${V2RAY_USER}@${V2RAY_SERVER} << 'ENDSSH'
#!/bin/bash
set -e

echo "Running PHP script to add UUIDs..."
sudo php /tmp/add_uuids_to_v2ray.php /tmp/uuids.txt

echo ""
echo "Validating V2Ray config..."
if sudo v2ray test -config /etc/v2ray/config.json; then
    echo "✅ Config is valid"
else
    echo "❌ Config validation failed!"
    echo "Restoring backup..."
    LATEST_BACKUP=$(ls -t /etc/v2ray/config.json.backup.* 2>/dev/null | head -1)
    if [ -n "$LATEST_BACKUP" ]; then
        sudo cp "$LATEST_BACKUP" /etc/v2ray/config.json
        echo "✅ Backup restored"
    fi
    exit 1
fi

echo ""
echo "Reloading V2Ray service..."
if sudo systemctl reload v2ray; then
    echo "✅ V2Ray reloaded successfully"
else
    echo "⚠️  Reload failed, trying restart..."
    sudo systemctl restart v2ray
    sleep 2
    if sudo systemctl is-active --quiet v2ray; then
        echo "✅ V2Ray restarted successfully"
    else
        echo "❌ V2Ray failed to start!"
        echo "Restoring backup..."
        LATEST_BACKUP=$(ls -t /etc/v2ray/config.json.backup.* 2>/dev/null | head -1)
        if [ -n "$LATEST_BACKUP" ]; then
            sudo cp "$LATEST_BACKUP" /etc/v2ray/config.json
            sudo systemctl restart v2ray
            echo "✅ Backup restored and V2Ray restarted"
        fi
        exit 1
    fi
fi

echo ""
echo "Checking V2Ray status..."
sudo systemctl status v2ray --no-pager | head -10

echo ""
echo "Cleanup temporary files..."
rm -f /tmp/add_uuids_to_v2ray.php /tmp/uuids.txt

echo ""
echo "✅ All done!"
ENDSSH

if [ $? -eq 0 ]; then
    echo ""
    echo "════════════════════════════════════════════════════════════════"
    echo "✅ SUCCESS! All UUIDs Added to V2Ray!"
    echo "════════════════════════════════════════════════════════════════"
    echo ""
    echo "Next step: Import UUIDs to VMaster database"
    echo ""
    echo "Run on VMaster server:"
    echo "  scp v2ray-pool-export/import_to_vmaster.sql ubuntu@VMASTER_IP:/tmp/"
    echo "  ssh ubuntu@VMASTER_IP"
    echo "  cd /var/www/vmaster"
    echo "  sudo bash scripts/setup-v2ray-pool.sh"
    echo ""
else
    echo ""
    echo "❌ Failed to add UUIDs"
    echo "Please check the error messages above"
fi

# Cleanup local temp file
rm -f /tmp/add_uuids_to_v2ray.php

