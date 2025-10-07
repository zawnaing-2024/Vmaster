# 🚀 X-UI Panel Integration with VMaster for V2Ray Automation

## Overview

You have **3x-ui** (X-UI) panel running at `http://103.117.149.112:54321/xui/`

X-UI has a **REST API** that VMaster can use to:
- ✅ Create V2Ray users automatically
- ✅ Delete V2Ray users automatically
- ✅ No manual work needed!
- ✅ No V2Ray restart required!

---

## 🎯 How X-UI + VMaster Automation Works

```
┌────────────────────────────┐
│  VMaster Portal            │
│  (Customer creates account)│
└────────────────────────────┘
            ↓
    Calls X-UI API
            ↓
┌────────────────────────────┐
│  X-UI Panel API            │
│  (Creates user in V2Ray)   │
└────────────────────────────┘
            ↓
┌────────────────────────────┐
│  V2Ray Server              │
│  (User can connect!)       │
└────────────────────────────┘
```

---

## 📋 Step-by-Step Integration

### Step 1: Get X-UI API Credentials

1. Login to X-UI panel: http://103.117.149.112:54321/
2. Go to **Panel Settings**
3. Note down:
   - **Username**
   - **Password**
   - **API Port** (usually same as web port: 54321)

### Step 2: Test X-UI API

**From your VMaster server or local machine:**

```bash
# Login to X-UI and get session cookie
curl -X POST "http://103.117.149.112:54321/login" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "username=YOUR_USERNAME&password=YOUR_PASSWORD" \
  -c /tmp/xui-cookie.txt

# List inbounds (V2Ray configurations)
curl -X GET "http://103.117.149.112:54321/xui/inbound/list" \
  -b /tmp/xui-cookie.txt

# Add a client (user)
curl -X POST "http://103.117.149.112:54321/xui/inbound/addClient" \
  -H "Content-Type: application/json" \
  -b /tmp/xui-cookie.txt \
  -d '{
    "id": 1,
    "settings": "{\"clients\":[{\"id\":\"YOUR-UUID-HERE\",\"email\":\"user@example.com\",\"alterId\":0}]}"
  }'
```

### Step 3: Create X-UI Handler for VMaster

Create a new file on **VMaster server**:

**File:** `/var/www/html/includes/xui_handler.php`

```php
<?php
/**
 * X-UI Panel API Handler for V2Ray Automation
 */

class XUIHandler {
    private $xuiUrl;
    private $username;
    private $password;
    private $cookieFile;
    
    public function __construct($xuiUrl, $username, $password) {
        $this->xuiUrl = rtrim($xuiUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->cookieFile = sys_get_temp_dir() . '/xui_session_' . md5($xuiUrl) . '.txt';
    }
    
    /**
     * Login to X-UI panel and get session
     */
    private function login() {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->xuiUrl . '/login',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'username' => $this->username,
                'password' => $this->password
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            error_log("X-UI login successful");
            return true;
        } else {
            error_log("X-UI login failed: HTTP $httpCode - $response");
            return false;
        }
    }
    
    /**
     * Make authenticated API call to X-UI
     */
    private function apiCall($endpoint, $method = 'GET', $data = null) {
        // Ensure we're logged in
        if (!file_exists($this->cookieFile)) {
            if (!$this->login()) {
                return false;
            }
        }
        
        $ch = curl_init();
        $url = $this->xuiUrl . $endpoint;
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ];
        
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            if ($data) {
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
                $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
            }
        }
        
        curl_setopt_array($ch, $options);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // If unauthorized, try logging in again
        if ($httpCode === 401 || $httpCode === 403) {
            unlink($this->cookieFile);
            $this->login();
            return $this->apiCall($endpoint, $method, $data);
        }
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        } else {
            error_log("X-UI API call failed: $url - HTTP $httpCode - $response");
            return false;
        }
    }
    
    /**
     * Get list of inbounds
     */
    public function getInbounds() {
        return $this->apiCall('/xui/inbound/list');
    }
    
    /**
     * Get inbound by ID
     */
    public function getInbound($inboundId) {
        return $this->apiCall('/xui/inbound/get/' . $inboundId);
    }
    
    /**
     * Add client to existing inbound
     */
    public function addClient($inboundId, $uuid, $email) {
        $data = [
            'id' => (int)$inboundId,
            'settings' => json_encode([
                'clients' => [
                    [
                        'id' => $uuid,
                        'email' => $email,
                        'alterId' => 0
                    ]
                ]
            ])
        ];
        
        $result = $this->apiCall('/xui/inbound/addClient', 'POST', $data);
        
        if ($result && isset($result['success']) && $result['success']) {
            error_log("X-UI: Added client $email (UUID: $uuid) to inbound $inboundId");
            return true;
        } else {
            error_log("X-UI: Failed to add client $email");
            return false;
        }
    }
    
    /**
     * Delete client from inbound
     */
    public function deleteClient($inboundId, $uuid) {
        $data = [
            'id' => (int)$inboundId,
            'clientId' => $uuid
        ];
        
        $result = $this->apiCall('/xui/inbound/delClient', 'POST', $data);
        
        if ($result && isset($result['success']) && $result['success']) {
            error_log("X-UI: Deleted client with UUID: $uuid from inbound $inboundId");
            return true;
        } else {
            error_log("X-UI: Failed to delete client UUID: $uuid");
            return false;
        }
    }
    
    /**
     * Test connection to X-UI
     */
    public function testConnection() {
        if ($this->login()) {
            $inbounds = $this->getInbounds();
            return $inbounds !== false;
        }
        return false;
    }
}
?>
```

### Step 4: Configure X-UI in VMaster

Create configuration file on **VMaster server**:

**File:** `/var/www/html/config/xui.php`

```php
<?php
/**
 * X-UI Panel Configuration
 */

// X-UI Panel Settings
define('XUI_ENABLED', true); // Set to true to enable X-UI integration
define('XUI_URL', 'http://103.117.149.112:54321'); // Your X-UI panel URL
define('XUI_USERNAME', 'admin'); // X-UI panel username
define('XUI_PASSWORD', 'admin'); // X-UI panel password
define('XUI_DEFAULT_INBOUND_ID', 1); // Default inbound ID to add clients to

// Get X-UI handler instance
function getXUIHandler() {
    static $xuiHandler = null;
    
    if ($xuiHandler === null && XUI_ENABLED) {
        require_once __DIR__ . '/../includes/xui_handler.php';
        $xuiHandler = new XUIHandler(XUI_URL, XUI_USERNAME, XUI_PASSWORD);
    }
    
    return $xuiHandler;
}
?>
```

### Step 5: Update VPN Handler to Use X-UI

Modify `/var/www/html/includes/vpn_handler.php`:

**Find the V2Ray case in createVPNAccount() method and replace with:**

```php
case 'v2ray':
    // Check if X-UI is enabled
    if (defined('XUI_ENABLED') && XUI_ENABLED === true) {
        require_once __DIR__ . '/../config/xui.php';
        $xuiHandler = getXUIHandler();
        
        if ($xuiHandler) {
            // Generate UUID for V2Ray
            $uuid = $this->generateUUID();
            $email = 'vmaster_' . $customerId . '_' . $clientId . '_' . time();
            
            // Add client to X-UI
            $xuiResult = $xuiHandler->addClient(XUI_DEFAULT_INBOUND_ID, $uuid, $email);
            
            if ($xuiResult) {
                // Generate VMess link
                $accountData = $this->generateV2RayConfig($server);
                $accountData['access_key'] = str_replace(
                    $accountData['uuid'], 
                    $uuid, 
                    $accountData['access_key']
                );
                
                $username = $email;
                $password = $uuid;
                $accessKey = $accountData['access_key'];
                $configData = json_encode([
                    'uuid' => $uuid,
                    'email' => $email,
                    'server' => $server['server_host'],
                    'port' => $server['server_port'],
                    'xui_inbound_id' => XUI_DEFAULT_INBOUND_ID
                ]);
                
                error_log("V2Ray account created via X-UI: $email");
            } else {
                throw new Exception('Failed to create V2Ray account in X-UI panel');
            }
        }
    } else {
        // Fallback to RADIUS or pool method
        // ... existing code ...
    }
    break;
```

**Add deletion support in customer/vpn-accounts.php:**

```php
// In the delete VPN account section, add:
if ($account['server_type'] === 'v2ray' && defined('XUI_ENABLED') && XUI_ENABLED === true) {
    require_once __DIR__ . '/../config/xui.php';
    $xuiHandler = getXUIHandler();
    
    if ($xuiHandler) {
        $configData = json_decode($account['config_data'], true);
        if (isset($configData['uuid']) && isset($configData['xui_inbound_id'])) {
            $xuiHandler->deleteClient(
                $configData['xui_inbound_id'], 
                $configData['uuid']
            );
            error_log("Deleted V2Ray user from X-UI: " . $configData['uuid']);
        }
    }
}
```

---

## 🧪 Testing the Integration

### Step 1: Test X-UI Connection

Create test script: `/var/www/html/test-xui.php`

```php
<?php
require_once __DIR__ . '/config/xui.php';

echo "<h1>X-UI Connection Test</h1>";
echo "<pre>";

echo "X-UI URL: " . XUI_URL . "\n";
echo "X-UI Enabled: " . (XUI_ENABLED ? 'Yes' : 'No') . "\n\n";

if (XUI_ENABLED) {
    $xuiHandler = getXUIHandler();
    
    echo "Testing connection...\n";
    if ($xuiHandler->testConnection()) {
        echo "✅ Connected to X-UI successfully!\n\n";
        
        echo "Fetching inbounds...\n";
        $inbounds = $xuiHandler->getInbounds();
        
        if ($inbounds) {
            echo "✅ Inbounds retrieved!\n";
            echo json_encode($inbounds, JSON_PRETTY_PRINT);
        }
    } else {
        echo "❌ Failed to connect to X-UI\n";
        echo "Check:\n";
        echo "- X-UI panel is running\n";
        echo "- URL is correct: " . XUI_URL . "\n";
        echo "- Username and password are correct\n";
    }
} else {
    echo "X-UI is disabled in config\n";
}

echo "</pre>";
?>
```

**Access:** `https://vmaster.vip/test-xui.php`

### Step 2: Create Test V2Ray Account

1. Login to VMaster as customer
2. Go to VPN Accounts
3. Create V2Ray account
4. Check X-UI panel - user should appear!

---

## 🔧 X-UI Panel Configuration

### Find Your Inbound ID

1. Login to X-UI: http://103.117.149.112:54321/
2. Go to **Inbounds**
3. Look for your V2Ray VMess inbound
4. Note the **ID** (usually 1, 2, 3, etc.)
5. Update `XUI_DEFAULT_INBOUND_ID` in `/var/www/html/config/xui.php`

### Security Settings

**Change default X-UI credentials:**

1. Login to X-UI panel
2. Go to **Panel Settings**
3. Change:
   - Username (from default 'admin')
   - Password (from default 'admin')
4. Update credentials in VMaster's `/var/www/html/config/xui.php`

---

## 📊 How It Works

### Create V2Ray Account Flow:

```
Customer clicks "Create V2Ray Account"
   ↓
VMaster generates UUID
   ↓
VMaster calls X-UI API: addClient(UUID, email)
   ↓
X-UI adds user to V2Ray (NO restart!)
   ↓
VMaster generates VMess link with UUID
   ↓
Customer gets link and can connect immediately ✅
```

### Delete V2Ray Account Flow:

```
Customer clicks "Delete V2Ray Account"
   ↓
VMaster calls X-UI API: deleteClient(UUID)
   ↓
X-UI removes user from V2Ray
   ↓
User disconnected immediately ✅
```

---

## ✅ Advantages of X-UI Integration

| Feature | X-UI + VMaster | Pool Method | Manual V2Ray |
|---------|----------------|-------------|--------------|
| Automation | ✅ Full | ⚠️ Semi | ❌ None |
| Max Users | ✅ Unlimited | Pool size | Unlimited |
| Restart Needed | ✅ Never | Once | Every time |
| Real-time Delete | ✅ Yes | Partial | No |
| API Integration | ✅ Yes | No | No |
| User Management | ✅ Automated | Manual refill | Manual |

---

## 🔍 Troubleshooting

### Issue 1: "Failed to connect to X-UI"

**Check:**
```bash
# Can VMaster reach X-UI?
curl -I http://103.117.149.112:54321/

# Test X-UI login
curl -X POST "http://103.117.149.112:54321/login" \
  -d "username=admin&password=admin"
```

**Fix:**
- Ensure X-UI panel is running
- Check firewall allows port 54321
- Verify credentials in config

### Issue 2: "Failed to add client"

**Check X-UI logs:**
```bash
# On X-UI server
docker logs x-ui

# Or if installed directly
journalctl -u x-ui -n 50
```

### Issue 3: User created but can't connect

**Check:**
- Correct inbound ID used
- VMess link has correct UUID
- V2Ray server is running
- Firewall allows V2Ray port

---

## 🎯 Recommended Setup

### For Production:

1. **Use HTTPS for X-UI panel:**
   - Set up SSL certificate
   - Use `https://` instead of `http://`
   - More secure API calls

2. **Restrict X-UI panel access:**
   - Firewall: Only allow VMaster server IP
   - Change default username/password
   - Use strong credentials

3. **Monitor API calls:**
   - Enable logging in X-UI
   - Monitor VMaster logs: `docker logs vmaster_web | grep X-UI`

---

## 📋 Complete Setup Checklist

- [ ] X-UI panel installed and running
- [ ] X-UI accessible at http://103.117.149.112:54321/
- [ ] Default credentials changed
- [ ] Inbound ID identified
- [ ] `xui_handler.php` created on VMaster
- [ ] `config/xui.php` configured with correct credentials
- [ ] VPN handler updated to use X-UI
- [ ] Test script shows successful connection
- [ ] Test V2Ray account created via portal
- [ ] User appears in X-UI panel
- [ ] VMess link works for connection
- [ ] Delete function removes user from X-UI

---

## 🚀 Deployment Commands

**On VMaster server:**

```bash
# 1. Create X-UI handler
sudo nano /var/www/html/includes/xui_handler.php
# Paste the XUIHandler class code

# 2. Create X-UI config
sudo nano /var/www/html/config/xui.php
# Paste the configuration with your credentials

# 3. Update VPN handler
sudo nano /var/www/html/includes/vpn_handler.php
# Add X-UI integration code

# 4. Restart web container
docker restart vmaster_web

# 5. Test connection
curl https://vmaster.vip/test-xui.php
```

---

## 🎉 Result

**You now have FULL V2Ray automation!**

✅ Customer creates account → User added to X-UI instantly  
✅ Customer deletes account → User removed from X-UI instantly  
✅ No manual work needed  
✅ No V2Ray restart required  
✅ Scales to unlimited users  

**This is the BEST solution for V2Ray automation with VMaster!** 🚀

