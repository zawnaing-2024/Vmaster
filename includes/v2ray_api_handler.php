<?php
/**
 * V2Ray gRPC API Handler for Direct V2Ray Automation
 * This handler uses V2Ray's built-in gRPC API to manage users
 */

class V2RayAPIHandler {
    private $apiHost;
    private $apiPort;
    private $apiTag;
    
    public function __construct($apiHost = '103.117.149.112', $apiPort = 62789, $apiTag = 'api') {
        $this->apiHost = $apiHost;
        $this->apiPort = $apiPort;
        $this->apiTag = $apiTag;
    }
    
    /**
     * Execute v2ctl command via SSH or local execution
     * This is a workaround since gRPC requires protocol buffers
     */
    private function executeV2Ctl($command, $sshHost = null, $sshUser = null) {
        if ($sshHost && $sshUser) {
            // Remote execution via SSH
            $sshCommand = sprintf(
                'ssh %s@%s "%s"',
                escapeshellarg($sshUser),
                escapeshellarg($sshHost),
                escapeshellcmd($command)
            );
            exec($sshCommand, $output, $returnCode);
        } else {
            // Local execution
            exec($command, $output, $returnCode);
        }
        
        return [
            'output' => implode("\n", $output),
            'code' => $returnCode
        ];
    }
    
    /**
     * Add user via v2ctl API command
     * Note: This requires SSH access to V2Ray server or v2ctl installed locally
     */
    public function addUser($inboundTag, $uuid, $email, $alterId = 0) {
        // Using v2ctl to add user via API
        $command = sprintf(
            'v2ctl api --server=127.0.0.1:%d HandlerService.AlterInbound %s',
            $this->apiPort,
            escapeshellarg(json_encode([
                'tag' => $inboundTag,
                'operation' => 'add',
                'user' => [
                    'email' => $email,
                    'level' => 0,
                    'account' => [
                        'id' => $uuid,
                        'alterId' => $alterId
                    ]
                ]
            ]))
        );
        
        $result = $this->executeV2Ctl($command, $this->apiHost, 'ubuntu');
        
        if ($result['code'] === 0) {
            error_log("V2Ray API: Added user $email (UUID: $uuid)");
            return true;
        }
        
        error_log("V2Ray API: Failed to add user - " . $result['output']);
        return false;
    }
    
    /**
     * Remove user via v2ctl API command
     */
    public function removeUser($inboundTag, $email) {
        $command = sprintf(
            'v2ctl api --server=127.0.0.1:%d HandlerService.AlterInbound %s',
            $this->apiPort,
            escapeshellarg(json_encode([
                'tag' => $inboundTag,
                'operation' => 'remove',
                'user' => [
                    'email' => $email
                ]
            ]))
        );
        
        $result = $this->executeV2Ctl($command, $this->apiHost, 'ubuntu');
        
        if ($result['code'] === 0) {
            error_log("V2Ray API: Removed user $email");
            return true;
        }
        
        error_log("V2Ray API: Failed to remove user - " . $result['output']);
        return false;
    }
    
    /**
     * Get user statistics
     */
    public function getUserStats($email) {
        $command = sprintf(
            'v2ctl api --server=127.0.0.1:%d StatsService.QueryStats %s',
            $this->apiPort,
            escapeshellarg(json_encode([
                'pattern' => "user>>>$email>>>",
                'reset' => false
            ]))
        );
        
        $result = $this->executeV2Ctl($command, $this->apiHost, 'ubuntu');
        
        if ($result['code'] === 0) {
            return json_decode($result['output'], true);
        }
        
        return false;
    }
    
    /**
     * Test API connection
     */
    public function testConnection() {
        $command = sprintf(
            'v2ctl api --server=127.0.0.1:%d StatsService.GetStats',
            $this->apiPort
        );
        
        $result = $this->executeV2Ctl($command, $this->apiHost, 'ubuntu');
        
        return $result['code'] === 0;
    }
}

/**
 * Simple V2Ray Manager using direct config file manipulation
 * This is more reliable than gRPC API for adding users
 */
class V2RayConfigManager {
    private $configPath;
    private $sshHost;
    private $sshUser;
    private $inboundTag;
    
    public function __construct($sshHost, $sshUser = 'ubuntu', $configPath = '/etc/v2ray/config.json', $inboundTag = 'inbound-vmess') {
        $this->sshHost = $sshHost;
        $this->sshUser = $sshUser;
        $this->configPath = $configPath;
        $this->inboundTag = $inboundTag;
    }
    
    /**
     * Execute command on V2Ray server via SSH
     */
    private function sshExec($command) {
        $sshCommand = sprintf(
            'ssh %s@%s "%s" 2>&1',
            escapeshellarg($this->sshUser),
            escapeshellarg($this->sshHost),
            str_replace('"', '\\"', $command)
        );
        
        exec($sshCommand, $output, $returnCode);
        
        return [
            'output' => implode("\n", $output),
            'code' => $returnCode
        ];
    }
    
    /**
     * Get current V2Ray config
     */
    public function getConfig() {
        $result = $this->sshExec("cat {$this->configPath}");
        
        if ($result['code'] === 0) {
            return json_decode($result['output'], true);
        }
        
        return false;
    }
    
    /**
     * Add user to V2Ray config
     */
    public function addUser($uuid, $email, $alterId = 0) {
        // Create PHP script to add user
        $phpScript = <<<'PHP'
<?php
$config = json_decode(file_get_contents('/etc/v2ray/config.json'), true);

$newUser = [
    'id' => $argv[1],
    'email' => $argv[2],
    'alterId' => (int)$argv[3]
];

// Find VMess inbound and add user
foreach ($config['inbounds'] as &$inbound) {
    if (isset($inbound['protocol']) && $inbound['protocol'] === 'vmess') {
        if (!isset($inbound['settings']['clients'])) {
            $inbound['settings']['clients'] = [];
        }
        $inbound['settings']['clients'][] = $newUser;
        break;
    }
}

file_put_contents('/etc/v2ray/config.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "User added successfully";
?>
PHP;
        
        // Upload script and execute
        $tempScript = '/tmp/v2ray_add_user_' . time() . '.php';
        $this->sshExec("cat > $tempScript << 'EOFPHP'\n$phpScript\nEOFPHP");
        
        $result = $this->sshExec(sprintf(
            'sudo php %s %s %s %d && sudo systemctl reload v2ray',
            $tempScript,
            escapeshellarg($uuid),
            escapeshellarg($email),
            $alterId
        ));
        
        $this->sshExec("rm -f $tempScript");
        
        if ($result['code'] === 0) {
            error_log("V2Ray: Added user $email (UUID: $uuid)");
            return true;
        }
        
        error_log("V2Ray: Failed to add user - " . $result['output']);
        return false;
    }
    
    /**
     * Remove user from V2Ray config
     */
    public function removeUser($email) {
        $phpScript = <<<'PHP'
<?php
$config = json_decode(file_get_contents('/etc/v2ray/config.json'), true);

// Find VMess inbound and remove user
foreach ($config['inbounds'] as &$inbound) {
    if (isset($inbound['protocol']) && $inbound['protocol'] === 'vmess') {
        if (isset($inbound['settings']['clients'])) {
            $inbound['settings']['clients'] = array_filter(
                $inbound['settings']['clients'],
                function($client) use ($argv) {
                    return $client['email'] !== $argv[1];
                }
            );
            $inbound['settings']['clients'] = array_values($inbound['settings']['clients']);
        }
        break;
    }
}

file_put_contents('/etc/v2ray/config.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "User removed successfully";
?>
PHP;
        
        $tempScript = '/tmp/v2ray_remove_user_' . time() . '.php';
        $this->sshExec("cat > $tempScript << 'EOFPHP'\n$phpScript\nEOFPHP");
        
        $result = $this->sshExec(sprintf(
            'sudo php %s %s && sudo systemctl reload v2ray',
            $tempScript,
            escapeshellarg($email)
        ));
        
        $this->sshExec("rm -f $tempScript");
        
        if ($result['code'] === 0) {
            error_log("V2Ray: Removed user $email");
            return true;
        }
        
        error_log("V2Ray: Failed to remove user - " . $result['output']);
        return false;
    }
    
    /**
     * Test SSH connection
     */
    public function testConnection() {
        $result = $this->sshExec('echo "OK"');
        return $result['code'] === 0 && trim($result['output']) === 'OK';
    }
}
?>

