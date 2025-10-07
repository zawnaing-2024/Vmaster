-- Add custom VPN limit per client
-- This allows setting different VPN limits for each individual client

-- Add max_vpn_accounts column to client_accounts table
ALTER TABLE client_accounts 
ADD COLUMN max_vpn_accounts INT DEFAULT NULL COMMENT 'Custom VPN limit for this client. NULL means use customer default';

-- Update customers table to allow NULL defaults (no limit enforcement if not set)
ALTER TABLE customers 
MODIFY COLUMN max_clients INT DEFAULT NULL COMMENT 'Maximum number of clients. NULL = unlimited',
MODIFY COLUMN max_vpn_per_client INT DEFAULT NULL COMMENT 'Default max VPN per client. NULL = unlimited';

-- Example: Set custom limit for specific client
-- UPDATE client_accounts SET max_vpn_accounts = 5 WHERE id = 1;

-- Example: Customer with no limits (unlimited)
-- UPDATE customers SET max_clients = NULL, max_vpn_per_client = NULL WHERE id = 1;

-- Example: Customer with specific limits
-- UPDATE customers SET max_clients = 10, max_vpn_per_client = 3 WHERE id = 2;

SELECT 'Custom limits migration completed!' as status;

