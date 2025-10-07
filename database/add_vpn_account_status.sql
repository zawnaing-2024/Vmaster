-- Add status column to vpn_accounts table
ALTER TABLE vpn_accounts 
ADD COLUMN IF NOT EXISTS status ENUM('active', 'suspended', 'disabled') DEFAULT 'active' AFTER config_data;

-- Update existing VPN accounts to match their client's status
UPDATE vpn_accounts va
JOIN client_accounts ca ON va.client_id = ca.id
SET va.status = ca.status
WHERE ca.status IN ('suspended', 'disabled');

