<?php
/**
 * X-UI Panel API Handler for V2Ray Automation
 * Integrates X-UI panel with VMaster for automatic V2Ray user management
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
            $result = json_decode($response, true);
            if (isset($result['success']) && $result['success']) {
                error_log("X-UI: Login successful");
                return true;
            }
        }
        
        error_log("X-UI: Login failed - HTTP $httpCode - $response");
        return false;
    }
    
    /**
     * Make authenticated API call to X-UI
     */
    private function apiCall($endpoint, $method = 'GET', $data = null) {
        // Ensure we're logged in
        if (!file_exists($this->cookieFile) || (time() - filemtime($this->cookieFile)) > 3600) {
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
        $error = curl_error($ch);
        curl_close($ch);
        
        // If unauthorized, try logging in again
        if ($httpCode === 401 || $httpCode === 403) {
            if (file_exists($this->cookieFile)) {
                unlink($this->cookieFile);
            }
            if ($this->login()) {
                return $this->apiCall($endpoint, $method, $data);
            }
            return false;
        }
        
        if ($error) {
            error_log("X-UI: cURL error - $error");
            return false;
        }
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        error_log("X-UI: API call failed - $url - HTTP $httpCode - $response");
        return false;
    }
    
    /**
     * Get list of inbounds
     */
    public function getInbounds() {
        $result = $this->apiCall('/xui/inbound/list');
        if ($result && isset($result['success']) && $result['success']) {
            return $result['obj'] ?? [];
        }
        return false;
    }
    
    /**
     * Get inbound by ID
     */
    public function getInbound($inboundId) {
        $result = $this->apiCall('/xui/inbound/get/' . $inboundId);
        if ($result && isset($result['success']) && $result['success']) {
            return $result['obj'] ?? null;
        }
        return false;
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
        }
        
        error_log("X-UI: Failed to add client $email - " . json_encode($result));
        return false;
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
            error_log("X-UI: Deleted client UUID: $uuid from inbound $inboundId");
            return true;
        }
        
        error_log("X-UI: Failed to delete client UUID: $uuid - " . json_encode($result));
        return false;
    }
    
    /**
     * Update client (modify email or other settings)
     */
    public function updateClient($inboundId, $uuid, $newEmail) {
        // X-UI doesn't have direct update, so we delete and re-add
        // Or use updateClient endpoint if your X-UI version supports it
        $data = [
            'id' => (int)$inboundId,
            'settings' => json_encode([
                'clients' => [
                    [
                        'id' => $uuid,
                        'email' => $newEmail,
                        'alterId' => 0
                    ]
                ]
            ])
        ];
        
        $result = $this->apiCall('/xui/inbound/updateClient/' . $uuid, 'POST', $data);
        
        if ($result && isset($result['success']) && $result['success']) {
            error_log("X-UI: Updated client UUID: $uuid");
            return true;
        }
        
        return false;
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
    
    /**
     * Get client traffic stats
     */
    public function getClientStats($inboundId, $email = null) {
        $result = $this->apiCall('/xui/inbound/clientStat/' . $inboundId);
        
        if ($result && isset($result['success']) && $result['success']) {
            if ($email) {
                $stats = $result['obj'] ?? [];
                foreach ($stats as $stat) {
                    if ($stat['email'] === $email) {
                        return $stat;
                    }
                }
                return null;
            }
            return $result['obj'] ?? [];
        }
        
        return false;
    }
}
?>

