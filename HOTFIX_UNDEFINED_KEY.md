# Hotfix: Undefined Array Key Warning

## Issue
When viewing VPN accounts before running the database migration, you may see:
```
Warning: Undefined array key "plan_duration" in /var/www/html/customer/vpn-accounts.php on line 220
```

## Cause
The code tries to access `$account['plan_duration']` before the database column exists.

## Fix Applied
Added `isset()` check to handle cases where the column doesn't exist yet:

```php
// Before (causes warning):
if ($account['plan_duration']) {

// After (safe):
if (isset($account['plan_duration']) && $account['plan_duration']) {
```

## Files Fixed
1. `customer/vpn-accounts.php` - Line 220
2. `admin/vpn-accounts.php` - Added plan duration display

## Resolution
✅ **Fixed in the code** - No action needed if you're deploying fresh files

If you already deployed and see this warning:
1. Re-upload the fixed files
2. Or run the database migration: `docker exec -i vmaster_db mysql -uroot -proot_secure_password vpn_cms_portal < database/add_plan_duration.sql`
3. Refresh the page

## Prevention
The deployment script (`scripts/deploy-plan-expiration.sh`) handles this automatically by:
1. Running the database migration first
2. Then restarting the web container

Always run the deployment script to avoid this issue.

