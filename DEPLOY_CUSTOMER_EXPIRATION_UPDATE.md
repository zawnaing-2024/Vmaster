# Deploy Customer Account Expiration Update
## Safe Update - Won't Affect Existing Data

---

## ⚠️ IMPORTANT: This Update is SAFE

- ✅ **No existing data will be changed**
- ✅ **All existing customers will remain active (unlimited)**
- ✅ **Only adds new columns (plan_duration, expires_at)**
- ✅ **New columns are NULL by default = unlimited**
- ✅ **Your current customers won't be affected**

---

## 🚀 Quick Deployment (3 Simple Steps)

### Step 1: SSH to Your Production Server

```bash
ssh root@your-server-ip
# Or: ssh ubuntu@your-server-ip
```

### Step 2: Navigate to Project Directory

```bash
cd /var/www/vmaster
```

### Step 3: Run This ONE Command

```bash
git pull origin main && \
docker exec -i vmaster_db mysql -uroot -prootpassword vpn_cms_portal < database/add_customer_expiration.sql && \
docker restart vmaster_web && \
echo "✅ Deployment Complete!"
```

**That's it!** Your site is now updated! 🎉

---

## 📋 What Happens During Update

### 1. Pull Latest Code (git pull origin main)
- Updates customer management UI
- Adds expiration fields to forms
- Adds JavaScript for date picker

### 2. Apply Database Migration (add_customer_expiration.sql)
- Adds `plan_duration` column (INT, NULL) - stores months
- Adds `expires_at` column (TIMESTAMP, NULL) - stores expiry date
- **NULL = unlimited (existing customers remain unlimited)** ✅

### 3. Restart Web Container
- Loads new code
- No downtime (restart takes ~2 seconds)

---

## ✅ Safety Guarantees

### Your Existing Data:

**Before Update:**
```
customers table:
- id: 1
- username: customer1
- status: active
- [other fields...]
```

**After Update:**
```
customers table:
- id: 1
- username: customer1
- status: active
- plan_duration: NULL  ← New field (NULL = unlimited)
- expires_at: NULL     ← New field (NULL = never expires)
- [other fields...]
```

**Result:** 
- ✅ Customer1 can still login
- ✅ Customer1 account never expires (NULL = unlimited)
- ✅ All their VPN accounts still work
- ✅ Nothing changes for existing customers!

---

## 🔍 Verify After Deployment

### 1. Check Database Migration

```bash
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "DESCRIBE customers;"
```

**You should see:**
- `plan_duration` (int, YES, NULL)
- `expires_at` (timestamp, YES, NULL)

### 2. Check Existing Customers

```bash
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "SELECT id, username, plan_duration, expires_at FROM customers LIMIT 5;"
```

**Expected output:**
```
+----+-----------+---------------+------------+
| id | username  | plan_duration | expires_at |
+----+-----------+---------------+------------+
|  1 | customer1 |          NULL |       NULL |
|  2 | customer2 |          NULL |       NULL |
+----+-----------+---------------+------------+
```

All existing customers have NULL = unlimited! ✅

### 3. Test Admin Panel

```bash
# Open in browser
http://your-server-ip/admin
```

- Login as admin
- Go to "Customers"
- Click "Add Customer"
- See new section: "Customer Account Expiration"
- Edit existing customer - they show "Never Expires" ✅

---

## 🎯 New Feature Usage (After Update)

### For New Customers Only:

When admin creates a new customer, they can now set:

**Option 1: Lifetime Account (Default)**
- Select: "Never Expires (Lifetime)"
- Account never expires ✅

**Option 2: Preset Plan (Trial/Subscription)**
- Select: "Preset Plan Duration"
- Choose: 1, 2, 3, 6, 12, 24, or 36 months
- Account expires after selected period ✅

**Option 3: Custom Date (Contract)**
- Select: "Custom Expiry Date"
- Pick any date
- Account expires on that date ✅

### For Existing Customers:

- **They remain unlimited (never expire)** ✅
- Admin can edit them to add expiration if needed
- Or leave them as unlimited forever

---

## 🔄 Step-by-Step Manual Deployment (Alternative)

If you prefer to do it manually:

### Step 1: Backup Database (Optional but Recommended)

```bash
mkdir -p /var/backups/vmaster
docker exec vmaster_db mysqldump -uroot -prootpassword vpn_cms_portal > /var/backups/vmaster/backup_before_expiration_$(date +%Y%m%d).sql
```

### Step 2: Pull Latest Code

```bash
cd /var/www/vmaster
git pull origin main
```

**Expected output:**
```
Updating 85de23e..8df4ed0
Fast-forward
 admin/customers.php | 230 changes
 database/add_customer_expiration.sql | 5 insertions
```

### Step 3: Apply Database Migration

```bash
docker exec -i vmaster_db mysql -uroot -prootpassword vpn_cms_portal < database/add_customer_expiration.sql
```

**Expected output:**
- ✅ Success (no output) - columns added
- ⚠️ OR "Duplicate column" error - already applied (safe to ignore)

### Step 4: Restart Web Container

```bash
docker restart vmaster_web
```

**Expected output:**
```
vmaster_web
```

Wait 5 seconds, then verify:
```bash
docker ps | grep vmaster_web
```

Should show container is running ✅

### Step 5: Test

- Open admin panel: `http://your-server-ip/admin`
- Go to Customers
- Try adding new customer - see expiration options
- Edit existing customer - shows "Never Expires"

---

## 🔧 Troubleshooting

### Problem: "Duplicate column" Error

**What it means:** Migration already applied (safe!)

**Solution:** Nothing needed, just restart container:
```bash
docker restart vmaster_web
```

### Problem: Can't Pull from GitHub

**Solution:**
```bash
cd /var/www/vmaster
git status  # Check for local changes
git stash   # Stash local changes if any
git pull origin main
```

### Problem: Container Won't Restart

**Solution:**
```bash
# Check logs
docker logs vmaster_web --tail 50

# Force restart
docker stop vmaster_web
docker start vmaster_web
```

### Problem: Existing Customers Can't Login

**This shouldn't happen, but if it does:**

```bash
# Check database
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "SELECT id, username, status, expires_at FROM customers;"

# All expires_at should be NULL (unlimited)
# If any are not NULL, fix them:
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "UPDATE customers SET expires_at = NULL WHERE expires_at IS NOT NULL;"
```

---

## 🎯 Quick Command Reference

### Full Deployment (One Command)
```bash
cd /var/www/vmaster && \
git pull origin main && \
docker exec -i vmaster_db mysql -uroot -prootpassword vpn_cms_portal < database/add_customer_expiration.sql && \
docker restart vmaster_web
```

### Check Migration Applied
```bash
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "SHOW COLUMNS FROM customers LIKE '%expires%';"
```

### View Existing Customers (Check They're Still Unlimited)
```bash
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "SELECT id, username, expires_at FROM customers;"
```

### Rollback (If Needed - Removes Columns)
```bash
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "ALTER TABLE customers DROP COLUMN expires_at, DROP COLUMN plan_duration;"
docker restart vmaster_web
```

---

## ✅ Post-Deployment Checklist

After deployment, verify:

- [ ] Admin panel loads correctly
- [ ] Can view Customers page
- [ ] Can add new customer (see expiration options)
- [ ] Can edit existing customer (shows "Never Expires")
- [ ] Existing customers can still login
- [ ] No errors in logs: `docker logs vmaster_web --tail 50`

---

## 📊 What Changed

### Files Modified:
- `admin/customers.php` - Added expiration UI
- `database/add_customer_expiration.sql` - Database migration

### Database Changes:
- Added 2 columns to `customers` table
- All existing rows have NULL (unlimited)

### No Changes To:
- VPN accounts table
- Client accounts table
- Any existing customer data
- Login system
- Any other functionality

---

## 🎉 Summary

**This update is SAFE!**

- ✅ Takes 30 seconds to deploy
- ✅ No downtime
- ✅ No data loss
- ✅ No impact on existing customers
- ✅ Just adds new feature for future use

**Deploy with confidence!** 🚀

---

**Last Updated:** October 10, 2025

