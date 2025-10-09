<?php
// VPN Account Generation Handler

class VPNHandler {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Generate Outline VPN Access Key using Real Outline API
     */
    public function generateOutlineKey($server) {
        // If API URL is configured, use real Outline API
        if (!empty($server['api_url'])) {
            try {
                return $this->createOutlineAccessKey($server);
            } catch (Exception $e) {
                error_log("Outline API Error: " . $e->getMessage());
                // Fall back to manual key generation if API fails
            }
        }
        
        // Fallback: Generate manual Outline key (for servers without API)
        $method = 'aes-256-gcm';
        $password = base64_encode(random_bytes(16));
        $credentials = "$method:$password";
        $encoded = base64_encode($credentials);
        $accessKey = "ss://{$encoded}@{$server['server_host']}:{$server['server_port']}";
        
        return [
            'access_key' => $accessKey,
            'config_data' => json_encode([
                'method' => $method,
                'password' => $password,
                'server' => $server['server_host'],
                'port' => $server['server_port'],
                'server_name' => $server['server_name']
            ])
        ];
    }
    
    /**
     * Create access key via Outline Management API
     */
    private function createOutlineAccessKey($server) {
        $apiUrl = rtrim($server['api_url'], '/');
        
        // Initialize cURL
        $ch = curl_init();
        
        // Configure cURL for Outline API
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl . '/access-keys',
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false, // Outline uses self-signed certs
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ]
        ]);
        
        // Execute API call
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Outline API connection failed: $error");
        }
        
        if ($httpCode !== 201) {
            throw new Exception("Outline API returned HTTP $httpCode: $response");
        }
        
        $result = json_decode($response, true);
        
        if (!isset($result['accessUrl'])) {
            throw new Exception("Invalid Outline API response - no accessUrl");
        }
        
        return [
            'access_key' => $result['accessUrl'],
            'config_data' => json_encode([
                'id' => $result['id'] ?? null,
                'name' => $result['name'] ?? '',
                'method' => $result['method'] ?? 'chacha20-ietf-poly1305',
                'server' => $server['server_host'],
                'port' => $result['port'] ?? $server['server_port'],
                'server_name' => $server['server_name'],
                'access_url' => $result['accessUrl']
            ])
        ];
    }
    
    /**
     * Generate V2Ray Configuration
     */
    public function generateV2RayConfig($server) {
        // V2Ray uses VMess protocol
        $uuid = $this->generateUUID();
        $alterId = 0; // Modern V2Ray uses 0
        
        $config = [
            'v' => '2',
            'ps' => $server['server_name'],
            'add' => $server['server_host'],
            'port' => $server['server_port'],
            'id' => $uuid,
            'aid' => $alterId,
            'net' => 'tcp',
            'type' => 'none',
            'host' => '',
            'path' => '',
            'tls' => 'tls'
        ];
        
        $vmessLink = 'vmess://' . base64_encode(json_encode($config));
        
        return [
            'access_key' => $vmessLink,
            'config_data' => json_encode($config)
        ];
    }
    
    /**
     * Generate SSTP Account
     */
    public function generateSSTPAccount($server) {
        // SSTP uses username/password authentication
        $username = 'vpn_' . substr(md5(random_bytes(16)), 0, 8);
        $password = $this->generateSecurePassword();
        
        $config = [
            'username' => $username,
            'password' => $password,
            'server' => $server['server_host'],
            'port' => $server['server_port'],
            'protocol' => 'SSTP',
            'server_name' => $server['server_name']
        ];
        
        return [
            'username' => $username,
            'password' => $password,
            'config_data' => json_encode($config)
        ];
    }
    
    /**
     * Create VPN Account
     */
    public function createVPNAccount($customerId, $clientId, $serverId, $planDuration = null, $customExpiryDate = null) {
        try {
            // Get server details
            $stmt = $this->conn->prepare("SELECT * FROM vpn_servers WHERE id = ? AND status = 'active'");
            $stmt->execute([$serverId]);
            $server = $stmt->fetch();
            
            if (!$server) {
                throw new Exception('Server not found or inactive');
            }
            
            // Check if server has reached max accounts
            if ($server['current_accounts'] >= $server['max_accounts']) {
                throw new Exception('Server has reached maximum capacity');
            }
            
            // Generate credentials based on server type
            $accountData = null;
            $username = null;
            $password = null;
            $accessKey = null;
            $configData = null;
            $apiKeyId = null;
            
            switch ($server['server_type']) {
                case 'outline':
                    $accountData = $this->generateOutlineKey($server);
                    $accessKey = $accountData['access_key'];
                    $configData = $accountData['config_data'];
                    // Store Outline key ID for future deletion
                    $configArray = json_decode($configData, true);
                    $apiKeyId = $configArray['id'] ?? null;
                    break;
                    
                case 'v2ray':
                    // Check if RADIUS is enabled
                    if (defined('RADIUS_ENABLED') && RADIUS_ENABLED === true) {
                        // Generate credentials for RADIUS
                        $username = 'v2ray_' . substr(md5(uniqid(rand(), true)), 0, 10);
                        $password = $this->generateRandomPassword(16);
                        
                        // Create user in RADIUS
                        require_once __DIR__ . '/radius_handler.php';
                        $radiusHandler = new RadiusHandler();
                        $radiusResult = $radiusHandler->createUser($username, $password);
                        
                        if (!$radiusResult) {
                            error_log("Failed to create RADIUS user: $username");
                        }
                        
                        // Still generate V2Ray UUID
                        $accountData = $this->generateV2RayConfig($server);
                        $accessKey = $accountData['access_key'];
                        $configData = $accountData['config_data'];
                    } else {
                        // Try to use from pool first
                        $poolCred = $this->getAvailablePoolCredential($serverId, 'v2ray');
                        if ($poolCred) {
                            $accessKey = $poolCred['credential_uuid'];
                            $poolCredentialId = $poolCred['id'];
                            $configData = $poolCred['credential_config'] ?? json_encode([
                                'uuid' => $poolCred['credential_uuid'],
                                'server' => $server['server_host'],
                                'port' => $server['server_port'],
                                'from_pool' => true
                            ]);
                        } else {
                            // Fallback to generated
                            $accountData = $this->generateV2RayConfig($server);
                            $accessKey = $accountData['access_key'];
                            $configData = $accountData['config_data'];
                        }
                    }
                    break;
                    
                case 'sstp':
                    // Check if RADIUS is enabled
                    if (defined('RADIUS_ENABLED') && RADIUS_ENABLED === true) {
                        // Generate credentials for RADIUS
                        $username = 'sstp_' . substr(md5(uniqid(rand(), true)), 0, 10);
                        $password = $this->generateRandomPassword(16);
                        
                        // Create user in RADIUS
                        require_once __DIR__ . '/radius_handler.php';
                        $radiusHandler = new RadiusHandler();
                        $radiusResult = $radiusHandler->createUser($username, $password);
                        
                        if (!$radiusResult) {
                            error_log("Failed to create RADIUS user: $username");
                        }
                        
                        $configData = json_encode([
                            'server' => $server['server_host'],
                            'port' => $server['server_port'],
                            'radius_enabled' => true
                        ]);
                    } else {
                        // Try to use from pool first
                        $poolCred = $this->getAvailablePoolCredential($serverId, 'sstp');
                        if ($poolCred) {
                            $username = $poolCred['credential_username'];
                            $password = $poolCred['credential_password'];
                            $poolCredentialId = $poolCred['id'];
                            $configData = json_encode([
                                'server' => $server['server_host'],
                                'port' => $server['server_port'],
                                'from_pool' => true
                            ]);
                        } else {
                            // Fallback to generated (manual creation needed)
                            $accountData = $this->generateSSTPAccount($server);
                            $username = $accountData['username'];
                            $password = $accountData['password'];
                            $configData = $accountData['config_data'];
                        }
                    }
                    break;
            }
            
            // Calculate expiration date
            $expiresAt = null;
            if ($customExpiryDate) {
                // Use custom expiry date if provided
                $expiresAt = date('Y-m-d H:i:s', strtotime($customExpiryDate . ' 23:59:59'));
            } elseif ($planDuration && $planDuration > 0) {
                // Use plan duration if specified
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$planDuration} months"));
            }
            
            // Insert VPN account
            $stmt = $this->conn->prepare("INSERT INTO vpn_accounts (customer_id, staff_id, server_id, account_username, account_password, access_key, config_data, pool_credential_id, plan_duration, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$customerId, $clientId, $serverId, $username, $password, $accessKey, $configData, $poolCredentialId ?? null, $planDuration, $expiresAt]);
            
            $accountId = $this->conn->lastInsertId();
            
            // Mark pool credential as assigned if used from pool
            if (isset($poolCredentialId) && $poolCredentialId) {
                $stmt = $this->conn->prepare("UPDATE vpn_credentials_pool SET is_assigned = 1, assigned_to = ?, assigned_at = NOW() WHERE id = ?");
                $stmt->execute([$accountId, $poolCredentialId]);
            }
            
            // Update server account count
            $stmt = $this->conn->prepare("UPDATE vpn_servers SET current_accounts = current_accounts + 1 WHERE id = ?");
            $stmt->execute([$serverId]);
            
            return [
                'success' => true,
                'account_id' => $accountId,
                'message' => 'VPN account created successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate secure password
     */
    private function generateSecurePassword($length = 16) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }
    
    /**
     * Generate UUID for V2Ray
     */
    private function generateUUID() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Delete Outline access key via API
     */
    public function deleteOutlineAccessKey($server, $keyId) {
        if (empty($server['api_url']) || empty($keyId)) {
            return false;
        }
        
        try {
            $apiUrl = rtrim($server['api_url'], '/');
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl . '/access-keys/' . $keyId,
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 10
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return ($httpCode === 204 || $httpCode === 200);
        } catch (Exception $e) {
            error_log("Outline API Delete Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get shareable credentials for an account
     */
    public function getShareableCredentials($accountId, $customerId) {
        try {
            $stmt = $this->conn->prepare("SELECT va.*, vs.server_type, vs.server_name, vs.server_host, vs.server_port, s.staff_name 
                FROM vpn_accounts va 
                JOIN vpn_servers vs ON va.server_id = vs.id 
                JOIN client_accounts s ON va.staff_id = s.id 
                WHERE va.id = ? AND va.customer_id = ?");
            $stmt->execute([$accountId, $customerId]);
            $account = $stmt->fetch();
            
            if (!$account) {
                throw new Exception('Account not found');
            }
            
            $shareData = [
                'server_name' => $account['server_name'],
                'server_type' => strtoupper($account['server_type']),
                'client_name' => $account['staff_name'],
                'created_at' => $account['created_at']
            ];
            
            switch ($account['server_type']) {
                case 'outline':
                    $shareData['access_key'] = $account['access_key'];
                    $shareData['instructions'] = "1. Download Outline Client\n2. Click 'Add Server'\n3. Paste the access key below";
                    break;
                    
                case 'v2ray':
                    $shareData['access_key'] = $account['access_key'];
                    $shareData['instructions'] = "1. Download V2Ray Client (V2RayNG for Android, V2RayX for iOS)\n2. Click '+' or 'Add Config'\n3. Paste the VMess link below";
                    break;
                    
                case 'sstp':
                    $shareData['username'] = $account['account_username'];
                    $shareData['password'] = $account['account_password'];
                    $shareData['server'] = $account['server_host'];
                    $shareData['port'] = $account['server_port'];
                    $shareData['instructions'] = "1. Open VPN Settings on your device\n2. Add new SSTP VPN connection\n3. Enter the credentials below";
                    break;
            }
            
            return [
                'success' => true,
                'data' => $shareData
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate random password with special characters
     */
    private function generateRandomPassword($length = 16) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=';
        $charactersLength = strlen($characters);
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $password;
    }
    
    /**
     * Get available credential from pool (for SSTP/V2Ray auto-assignment)
     */
    private function getAvailablePoolCredential($serverId, $serverType) {
        try {
            // Note: vpn_credentials_pool uses 'vpn_type' not 'server_type', and no 'server_id'
            $stmt = $this->conn->prepare("SELECT * FROM vpn_credentials_pool 
                WHERE vpn_type = ? AND is_assigned = 0 
                ORDER BY created_at ASC LIMIT 1");
            $stmt->execute([$serverType]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Pool credential fetch error: " . $e->getMessage());
            return null;
        }
    }
}
