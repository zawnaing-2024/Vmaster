-- Add plan duration limit for customers
-- This limits how long VPN accounts can be created for each customer

ALTER TABLE customers
ADD COLUMN max_plan_duration INT NULL COMMENT 'Maximum VPN plan duration in months for this customer (NULL = unlimited)' AFTER max_total_vpn_accounts;

