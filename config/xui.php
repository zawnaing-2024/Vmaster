<?php
/**
 * X-UI Panel Configuration for V2Ray Automation
 */

// X-UI Panel Settings
define('XUI_ENABLED', true); // Set to true to enable X-UI integration
define('XUI_URL', 'http://103.117.149.112:54321'); // Your X-UI panel URL
define('XUI_USERNAME', 'gdadmin'); // X-UI panel username
define('XUI_PASSWORD', 'GD@adm!n'); // X-UI panel password
define('XUI_DEFAULT_INBOUND_ID', 1); // Default inbound ID to add clients to (check X-UI panel)

/**
 * Get X-UI handler instance
 */
function getXUIHandler() {
    static $xuiHandler = null;
    
    if ($xuiHandler === null && XUI_ENABLED) {
        require_once __DIR__ . '/../includes/xui_handler.php';
        $xuiHandler = new XUIHandler(XUI_URL, XUI_USERNAME, XUI_PASSWORD);
    }
    
    return $xuiHandler;
}
?>

