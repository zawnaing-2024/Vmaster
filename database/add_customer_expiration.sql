-- Add expiration date and plan duration for customer accounts
-- This limits how long a customer account itself is active

ALTER TABLE customers
ADD COLUMN plan_duration INT NULL COMMENT 'Customer account plan duration in months',
ADD COLUMN expires_at TIMESTAMP NULL COMMENT 'When the customer account expires';

