-- Migration: Change "Staff" to "Client" and add VPN limits
-- Run this to update existing database

USE vpn_cms_portal;

-- Rename staff_accounts table to client_accounts
RENAME TABLE staff_accounts TO client_accounts;

-- Add max VPN accounts per client field to customers table
ALTER TABLE customers 
ADD COLUMN max_vpn_per_client INT DEFAULT 5 AFTER max_staff_accounts;

-- Update column names in client_accounts table for clarity
ALTER TABLE client_accounts 
CHANGE COLUMN staff_name client_name VARCHAR(255) NOT NULL,
CHANGE COLUMN staff_email client_email VARCHAR(255),
CHANGE COLUMN staff_phone client_phone VARCHAR(50);

-- Update foreign key reference name (if needed, recreate the constraint)
ALTER TABLE vpn_accounts DROP FOREIGN KEY vpn_accounts_ibfk_2;
ALTER TABLE vpn_accounts 
CHANGE COLUMN staff_id client_id INT NOT NULL;
ALTER TABLE vpn_accounts 
ADD CONSTRAINT vpn_accounts_ibfk_2 FOREIGN KEY (client_id) REFERENCES client_accounts(id) ON DELETE CASCADE;

-- Update index name
ALTER TABLE vpn_accounts DROP INDEX idx_staff_id;
ALTER TABLE vpn_accounts ADD INDEX idx_client_id (client_id);

-- Done! Database updated to use "Client" terminology

