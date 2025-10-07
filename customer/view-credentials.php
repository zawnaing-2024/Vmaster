<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/vpn_handler.php';
requireLogin('customer');

$accountId = intval($_GET['id'] ?? 0);

$db = new Database();
$conn = $db->getConnection();
$vpnHandler = new VPNHandler($conn);

$result = $vpnHandler->getShareableCredentials($accountId, $_SESSION['customer_id']);

if (!$result['success']) {
    echo '<div class="alert alert-error">' . $result['message'] . '</div>';
    exit;
}

$data = $result['data'];
?>

<div style="padding: 20px 0;">
    <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
        <p><strong>Server:</strong> <?php echo htmlspecialchars($data['server_name']); ?></p>
        <p><strong>Type:</strong> <span class="server-type-icon server-type-<?php echo strtolower($data['server_type']); ?>"><?php echo $data['server_type']; ?></span></p>
        <p><strong>Client:</strong> <?php echo htmlspecialchars($data['client_name']); ?></p>
        <p><strong>Created:</strong> <?php echo formatDate($data['created_at']); ?></p>
    </div>
    
    <h3 style="margin-bottom: 10px;">Setup Instructions</h3>
    <div class="alert alert-info" style="white-space: pre-line;">
        <?php echo htmlspecialchars($data['instructions']); ?>
    </div>
    
    <?php if ($data['server_type'] === 'OUTLINE'): ?>
        <h3 style="margin-bottom: 10px;">Outline Access Key</h3>
        <div class="code-block">
            <button class="copy-btn" onclick="copyCredential('<?php echo htmlspecialchars($data['access_key']); ?>')">Copy</button>
            <code><?php echo htmlspecialchars($data['access_key']); ?></code>
        </div>
        
        <div style="margin-top: 20px;">
            <h4>Download Outline Client:</h4>
            <ul style="margin-top: 10px; padding-left: 20px;">
                <li><a href="https://itunes.apple.com/app/outline-app/id1356177741" target="_blank">iOS</a></li>
                <li><a href="https://play.google.com/store/apps/details?id=org.outline.android.client" target="_blank">Android</a></li>
                <li><a href="https://itunes.apple.com/app/outline-app/id1356178125" target="_blank">macOS</a></li>
                <li><a href="https://raw.githubusercontent.com/Jigsaw-Code/outline-releases/master/client/stable/Outline-Client.exe" target="_blank">Windows</a></li>
            </ul>
        </div>
        
    <?php elseif ($data['server_type'] === 'V2RAY'): ?>
        <h3 style="margin-bottom: 10px;">V2Ray VMess Link</h3>
        <div class="code-block">
            <button class="copy-btn" onclick="copyCredential('<?php echo htmlspecialchars($data['access_key']); ?>')">Copy</button>
            <code style="word-break: break-all;"><?php echo htmlspecialchars($data['access_key']); ?></code>
        </div>
        
        <div style="margin-top: 20px;">
            <h4>Download V2Ray Client:</h4>
            <ul style="margin-top: 10px; padding-left: 20px;">
                <li><a href="https://apps.apple.com/app/shadowrocket/id932747118" target="_blank">iOS (Shadowrocket)</a></li>
                <li><a href="https://play.google.com/store/apps/details?id=com.v2ray.ang" target="_blank">Android (V2RayNG)</a></li>
                <li><a href="https://github.com/yanue/V2rayU/releases" target="_blank">macOS (V2RayU)</a></li>
                <li><a href="https://github.com/2dust/v2rayN/releases" target="_blank">Windows (v2rayN)</a></li>
            </ul>
        </div>
        
    <?php elseif ($data['server_type'] === 'SSTP'): ?>
        <h3 style="margin-bottom: 10px;">SSTP Credentials</h3>
        
        <div style="margin-bottom: 15px;">
            <label><strong>Server:</strong></label>
            <div class="code-block">
                <button class="copy-btn" onclick="copyCredential('<?php echo htmlspecialchars($data['server']); ?>')">Copy</button>
                <code><?php echo htmlspecialchars($data['server']); ?></code>
            </div>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label><strong>Port:</strong></label>
            <div class="code-block">
                <button class="copy-btn" onclick="copyCredential('<?php echo htmlspecialchars($data['port']); ?>')">Copy</button>
                <code><?php echo htmlspecialchars($data['port']); ?></code>
            </div>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label><strong>Username:</strong></label>
            <div class="code-block">
                <button class="copy-btn" onclick="copyCredential('<?php echo htmlspecialchars($data['username']); ?>')">Copy</button>
                <code><?php echo htmlspecialchars($data['username']); ?></code>
            </div>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label><strong>Password:</strong></label>
            <div class="code-block">
                <button class="copy-btn" onclick="copyCredential('<?php echo htmlspecialchars($data['password']); ?>')">Copy</button>
                <code><?php echo htmlspecialchars($data['password']); ?></code>
            </div>
        </div>
        
        <div style="margin-top: 20px;">
            <h4>Setup SSTP VPN:</h4>
            <ul style="margin-top: 10px; padding-left: 20px;">
                <li><strong>Windows:</strong> Settings → Network & Internet → VPN → Add VPN → Select SSTP</li>
                <li><strong>Android:</strong> Download "SSTP Client" from Play Store</li>
                <li><strong>iOS:</strong> Use "SSTP Connect" app from App Store</li>
            </ul>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 30px; padding: 15px; background: #fef3c7; border-radius: 8px;">
        <strong>⚠️ Security Notice:</strong>
        <p style="margin-top: 10px;">Keep these credentials private. Do not share them publicly or with unauthorized users.</p>
    </div>
</div>

