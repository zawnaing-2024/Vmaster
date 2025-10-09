-- Add app_password column to client_accounts table
-- This allows clients to login to the mobile app

ALTER TABLE client_accounts 
ADD COLUMN app_password VARCHAR(255) DEFAULT NULL 
COMMENT 'Password for mobile app login (hashed)'
AFTER staff_phone;

-- Create index for faster lookups
CREATE INDEX idx_staff_name ON client_accounts(staff_name);

-- Note: Passwords should be set by customers in the web portal
-- Default: NULL (no mobile app access until password is set)

