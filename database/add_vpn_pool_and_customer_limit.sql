-- Add total VPN limit per customer and credential pool system

-- 1. Add max total VPN accounts per customer
ALTER TABLE customers 
ADD COLUMN max_total_vpn_accounts INT DEFAULT NULL COMMENT 'Total VPN accounts this customer can create. NULL = unlimited';

-- 2. Create VPN credentials pool table
CREATE TABLE IF NOT EXISTS vpn_credentials_pool (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    server_type ENUM('outline', 'sstp', 'v2ray') NOT NULL,
    credential_username VARCHAR(255) DEFAULT NULL COMMENT 'For SSTP',
    credential_password VARCHAR(255) DEFAULT NULL COMMENT 'For SSTP',
    credential_uuid VARCHAR(255) DEFAULT NULL COMMENT 'For V2Ray',
    credential_config TEXT DEFAULT NULL COMMENT 'Full config JSON for V2Ray',
    access_key TEXT DEFAULT NULL COMMENT 'For any VPN type',
    is_assigned BOOLEAN DEFAULT FALSE COMMENT 'Has this been assigned to a client?',
    assigned_to_account_id INT DEFAULT NULL COMMENT 'VPN account ID if assigned',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (server_id) REFERENCES vpn_servers(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to_account_id) REFERENCES vpn_accounts(id) ON DELETE SET NULL,
    INDEX idx_server_assigned (server_id, is_assigned),
    INDEX idx_server_type (server_type, is_assigned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Pool of pre-created VPN credentials';

-- 3. Add pool_credential_id to vpn_accounts table
ALTER TABLE vpn_accounts 
ADD COLUMN pool_credential_id INT DEFAULT NULL COMMENT 'ID from vpn_credentials_pool if using pool',
ADD FOREIGN KEY (pool_credential_id) REFERENCES vpn_credentials_pool(id) ON DELETE SET NULL;

SELECT 'VPN pool and customer limit migration completed!' as status;

