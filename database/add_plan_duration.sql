-- Add plan_duration column to vpn_accounts table
-- This enables tracking of subscription plans (1 month, 2 months, 3 months, 6 months, 1 year)

ALTER TABLE vpn_accounts 
ADD COLUMN plan_duration INT DEFAULT NULL COMMENT 'Duration in months (1, 2, 3, 6, 12)' 
AFTER status;

-- Update existing records to have NULL plan_duration (unlimited or legacy accounts)
UPDATE vpn_accounts SET plan_duration = NULL WHERE plan_duration IS NULL;

