# VPN Account Plan Expiration Feature Guide

## 📅 Overview

The VMaster CMS Portal now includes a **Plan Duration** feature that allows you to set expiration dates for VPN accounts based on subscription plans.

## ✨ New Features

### 1. **Plan Duration Options**
When creating a new VPN account, you can now select from the following plan durations:
- **Unlimited** (No Expiration) - Default option
- **1 Month Plan**
- **2 Months Plan**
- **3 Months Plan**
- **6 Months Plan**
- **1 Year Plan** (12 months)

### 2. **Automatic Expiration Calculation**
- Expiration date is automatically calculated from the **creation date**
- For example:
  - Created on: October 9, 2025
  - Plan: 2 Months
  - Expires on: December 9, 2025

### 3. **Expiration Status Display**
VPN accounts now show:
- **Plan Duration** (e.g., "2 months", "Unlimited")
- **Expiration Date** with color-coded badges:
  - 🟢 **Green** (Never) - Unlimited accounts
  - 🔵 **Blue** - More than 7 days remaining
  - 🟡 **Warning** - 7 days or less remaining
  - 🔴 **Expired** - Past expiration date
- **Days Remaining** countdown for active plans

### 4. **Visual Plan Preview**
When selecting a plan duration in the create form, you'll see:
- Real-time calculation of the expiration date
- Clear display showing when the account will expire

## 🎨 Logo Integration

Your **VMaster logo** has been integrated throughout the portal:
- ✅ Landing page
- ✅ Admin login page
- ✅ Customer login page
- ✅ Admin sidebar
- ✅ Customer sidebar

The logo displays with rounded corners and a subtle shadow for a professional look.

## 📋 Database Changes

### New Columns in `vpn_accounts` Table:
- `plan_duration` (INT) - Duration in months (1, 2, 3, 6, 12, or NULL for unlimited)
- `expires_at` (TIMESTAMP) - Calculated expiration date

### Migration Required
Run the migration script to add these columns:
```bash
mysql -u root -p vpn_cms_portal < database/add_plan_duration.sql
```

## 🚀 Usage Guide

### For Customers:

1. **Creating a VPN Account with Plan**:
   - Go to **VPN Accounts** page
   - Click **"Create VPN Account"**
   - Select the client
   - Select the VPN server
   - **Choose Plan Duration** from dropdown
   - See the calculated expiration date preview
   - Click **"Create VPN Account"**

2. **Viewing Expiration Status**:
   - In the VPN Accounts list, you'll see:
     - **Plan column**: Shows duration (e.g., "2 months")
     - **Expires column**: Shows expiration date and days remaining
     - **Status column**: Automatically shows "Expired" if past expiration date

### For Developers:

**Creating accounts via code:**
```php
$vpnHandler = new VPNHandler($conn);
$result = $vpnHandler->createVPNAccount(
    $customerId, 
    $clientId, 
    $serverId, 
    $planDuration  // Pass 1, 2, 3, 6, 12, or null for unlimited
);
```

**Checking expiration status:**
```sql
SELECT 
    *,
    CASE 
        WHEN expires_at IS NULL THEN 'unlimited'
        WHEN expires_at > NOW() THEN 'active'
        ELSE 'expired'
    END as expiration_status
FROM vpn_accounts;
```

## 🔄 Backward Compatibility

- **Existing VPN accounts** will have `plan_duration = NULL` and `expires_at = NULL`
- They will display as **"Unlimited"** accounts
- No action required for existing accounts
- All existing functionality remains unchanged

## 📊 Features Summary

| Feature | Status |
|---------|--------|
| Plan duration selection | ✅ Added |
| Automatic expiration calculation | ✅ Added |
| Visual expiration status | ✅ Added |
| Days remaining countdown | ✅ Added |
| Color-coded expiration badges | ✅ Added |
| Logo integration | ✅ Added |
| Backward compatibility | ✅ Maintained |
| Database migration script | ✅ Created |

## 🎯 Benefits

1. **Better Subscription Management**: Track and manage VPN account subscriptions easily
2. **Visual Expiration Alerts**: See at a glance which accounts are expiring soon
3. **Flexible Plans**: Support multiple subscription durations
4. **Professional Branding**: Your logo is now prominently displayed
5. **No Breaking Changes**: Existing accounts continue to work as unlimited

## 📝 Notes

- The expiration check is **visual only** - accounts don't automatically disconnect
- You may want to add a cron job to automatically suspend expired accounts
- Consider sending email notifications before accounts expire

## 🔧 Future Enhancements (Optional)

Potential additions:
- Auto-suspend expired accounts
- Email notifications 7 days before expiration
- Renewal/extension functionality
- Custom plan duration input
- Plan pricing integration

---

**Last Updated**: October 9, 2025
**Version**: 1.0

