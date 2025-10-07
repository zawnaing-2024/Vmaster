# 🔐 Outline Server Setup Guide

## How to Add Your Outline Server to VPN CMS Portal

Your Outline server configuration:
```json
{
  "apiUrl": "https://183.89.209.103:29868/C_un1IHKT9zx8EvWk_f-Tw",
  "certSha256": "3190518F42B94A8B7E880B04C7D6E0836229A6B7FF6DA3680C2ED78FC8EE56DA"
}
```

---

## Step-by-Step Instructions

### 1. Login to Admin Panel

```
URL: http://localhost:8080/admin/login.php
Username: admin
Password: admin123
```

### 2. Navigate to VPN Servers

Click on **"VPN Servers"** in the left sidebar.

### 3. Click "Add Server"

Click the **"+ Add Server"** button.

### 4. Fill in Server Details

**Required Fields:**

- **Server Name**: `My Outline Server` (or any name you prefer)
- **Server Type**: Select **`Outline`** from dropdown
- **Server Host**: `183.89.209.103`
- **Server Port**: `29868`
- **Location**: `Singapore` (or your actual server location)
- **Max Accounts**: `100` (or your preferred limit)

**API Configuration (Important!):**

- **API URL**: `https://183.89.209.103:29868/C_un1IHKT9zx8EvWk_f-Tw`
  - This is the full management API URL from your Outline server
  - Make sure to include `https://` and the full path

**Optional:**

- **Description**: `Production Outline Server` (or any description)

### 5. Save the Server

Click **"Add Server"** button to save.

---

## ✅ How It Works Now

### Before (Old System):
- ❌ Generated fake/mock Outline keys
- ❌ Keys didn't work with real servers
- ❌ No connection to actual Outline API

### After (Updated System):
- ✅ **Connects to real Outline Management API**
- ✅ **Creates actual access keys** via API
- ✅ **Returns working access keys** that clients can use
- ✅ **Automatically deletes keys** when accounts are removed

---

## Testing Your Setup

### 1. Create a Test Customer

1. Go to **Customers** in admin panel
2. Click **"+ Add Customer"**
3. Fill in details:
   - Company: `Test Company`
   - Username: `testuser`
   - Password: `test123`
   - Email: `test@example.com`

### 2. Login as Customer

1. Go to: http://localhost:8080/customer/login.php
2. Login with: `testuser` / `test123`

### 3. Add a Staff Member

1. Go to **"My Staff"**
2. Click **"+ Add Staff"**
3. Enter staff name and details

### 4. Create VPN Account

1. Go to **"VPN Accounts"**
2. Click **"🔑 Create VPN Account"**
3. Select your staff member
4. Select your Outline server
5. Click **"Create VPN Account"**

### 5. View the Access Key

1. Click **"📋 View"** on the created account
2. You should see a **real Outline access key** like:
   ```
   ss://Y2hhY2hhMjAtaWV0Zi1wb2x5MTMwNTpwYXNzd29yZA==@183.89.209.103:29868
   ```
3. This key is created by your actual Outline server API

### 6. Test with Outline Client

1. Copy the access key
2. Open Outline app on your device
3. Tap "Add Server"
4. Paste the key
5. Connect - **it should work!** ✅

---

## API Communication

### What Happens When You Create a VPN Account:

```
1. Customer clicks "Create VPN Account"
   ↓
2. Portal sends POST request to Outline API:
   POST https://183.89.209.103:29868/C_un1IHKT9zx8EvWk_f-Tw/access-keys
   ↓
3. Outline server creates new access key
   ↓
4. Outline returns real access key:
   {
     "id": "12",
     "name": "",
     "password": "...",
     "port": 29868,
     "method": "chacha20-ietf-poly1305",
     "accessUrl": "ss://..."
   }
   ↓
5. Portal stores the access key
   ↓
6. Customer can view and share the key
```

### What Happens When You Delete a VPN Account:

```
1. Customer clicks "Delete" on VPN account
   ↓
2. Portal extracts the key ID from stored config
   ↓
3. Portal sends DELETE request to Outline API:
   DELETE https://183.89.209.103:29868/C_un1IHKT9zx8EvWk_f-Tw/access-keys/12
   ↓
4. Outline server deletes the key
   ↓
5. Portal removes the account from database
```

---

## Troubleshooting

### Issue: "Outline API connection failed"

**Possible Causes:**
1. Outline server is not accessible from your Docker container
2. API URL is incorrect
3. Firewall blocking the connection

**Solution:**
```bash
# Test API connection from Docker container
docker exec vpn_cms_web curl -k https://183.89.209.103:29868/C_un1IHKT9zx8EvWk_f-Tw/access-keys

# Should return a JSON list of access keys
```

### Issue: "Outline API returned HTTP 404"

**Cause:** API URL path is incorrect

**Solution:** 
- Double-check the API URL includes the full path
- Format: `https://HOST:PORT/API_SECRET_KEY`
- Example: `https://183.89.209.103:29868/C_un1IHKT9zx8EvWk_f-Tw`

### Issue: "SSL certificate verification failed"

**Already Fixed:** The system disables SSL verification for self-signed certificates (which Outline uses).

### Issue: Keys still don't work

**Check:**
1. Verify the access key format starts with `ss://`
2. Test the key directly in Outline client
3. Check Outline server logs
4. Verify server is running: `docker ps` on Outline server

---

## Getting Your Outline Server API URL

If you need to get your Outline server's API URL again:

### From Outline Manager:
1. Open Outline Manager app
2. Click on your server
3. Click "Settings" or gear icon
4. Look for "Management API URL" or "API URL"
5. Copy the entire URL

### From Command Line (if you have server access):
```bash
# SSH into your Outline server
ssh user@183.89.209.103

# Check Docker container for API URL
docker logs shadowbox

# Or check the access file
cat /opt/outline/access.txt
```

---

## Certificate SHA-256 (Optional)

The certificate fingerprint you provided:
```
3190518F42B94A8B7E880B04C7D6E0836229A6B7FF6DA3680C2ED78FC8EE56DA
```

Currently, the portal doesn't verify this (uses `CURLOPT_SSL_VERIFYPEER => false`), but you can add it to the server notes for reference.

---

## Security Notes

⚠️ **Important:**
- Your API URL contains a secret key (`C_un1IHKT9zx8EvWk_f-Tw`)
- Keep this URL private - anyone with this URL can manage your Outline server
- Don't share API URL publicly
- The portal stores it securely in the database

---

## Verify Integration is Working

### Check Portal Logs:
```bash
docker-compose logs -f web
```

Look for:
- ✅ `Outline API: Creating access key...`
- ✅ `Outline API: Key created successfully`
- ❌ `Outline API Error: ...` (if there are issues)

### Check Outline Server:
```bash
# On your Outline server, check for new keys
curl -k https://183.89.209.103:29868/C_un1IHKT9zx8EvWk_f-Tw/access-keys
```

You should see all access keys created through the portal.

---

## 🎉 You're All Set!

Once configured correctly:
- ✅ Portal creates **real Outline access keys**
- ✅ Keys work with **Outline clients**
- ✅ Automatic key management
- ✅ Track usage per customer/staff

**Happy VPN Management!** 🚀

