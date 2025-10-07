# 🎱 VPN Credentials Pool System - Complete Guide

Pre-create SSTP and V2Ray credentials and assign them automatically!

---

## 🎯 What is the VPN Pool?

Instead of manually creating credentials for SSTP/V2Ray each time, you can:

1. **Pre-create many accounts** on your SoftEther/V2Ray server
2. **Add them to the pool** in VMaster
3. **Automatically assign** when customers create VPN accounts

**No more manual work!** 🎉

---

## ✅ How It Works

```
┌─────────────────────────────────────────┐
│ 1. ADMIN: Bulk Add to Pool              │
│    - Create 100 accounts in SoftEther   │
│    - Add all credentials to VMaster     │
└─────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────┐
│ 2. POOL: Credentials Wait               │
│    - 100 available credentials          │
│    - Ready to be assigned               │
└─────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────┐
│ 3. CUSTOMER: Creates VPN Account        │
│    - Selects client                     │
│    - Selects SSTP server                │
│    - Clicks Create                      │
└─────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────┐
│ 4. SYSTEM: Auto-Assigns                 │
│    - Takes next available from pool     │
│    - Marks as assigned                  │
│    - Shows credentials to customer      │
└─────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────┐
│ 5. CUSTOMER: Gets Working Credentials   │
│    - Username: vpn_user001              │
│    - Password: abc123                   │
│    - Ready to use immediately!          │
└─────────────────────────────────────────┘
```

---

## 📚 Step-by-Step Guide

### For SSTP (SoftEther):

#### Step 1: Create Accounts in SoftEther

```bash
# Access SoftEther command line
/usr/local/vpnserver/vpncmd

# Select your hub
Hub vpn_hub

# Create 100 users (example script)
for i in {1..100}
do
  UserCreate vpn_user$(printf "%03d" $i)
  UserPasswordSet vpn_user$(printf "%03d" $i)
  # Enter password: pass$(printf "%03d" $i)
done
```

**Or create a text file with all credentials:**
```
vpn_user001:pass001
vpn_user002:pass002
vpn_user003:pass003
... (up to 100)
```

#### Step 2: Add to VMaster Pool

1. **Login to Admin Panel**
   ```
   http://your-vmaster.com/admin/login.php
   ```

2. **Navigate to VPN Credentials Pool**
   ```
   Sidebar → 🎱 VPN Credentials Pool
   ```

3. **Click "Add Credentials Bulk"**

4. **Fill in the form:**
   ```
   Select Server: [Your SSTP Server]
   
   Credentials (paste your list):
   vpn_user001:pass001
   vpn_user002:pass002
   vpn_user003:pass003
   ... (all 100)
   
   Notes: Batch 1 - Created Jan 2025
   
   Click: Add to Pool
   ```

5. **Done!** 
   - You'll see: "Successfully added 100 credentials to the pool!"
   - Pool now shows: 100 Available

---

### For V2Ray:

#### Step 1: Generate UUIDs

```bash
# Generate 100 UUIDs
for i in {1..100}
do
  uuidgen
done > v2ray_uuids.txt
```

**Result (v2ray_uuids.txt):**
```
8b7c1a2d-3e4f-5a6b-7c8d-9e0f1a2b3c4d
1a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d
...
```

#### Step 2: Add UUIDs to V2Ray Config

```bash
# Edit V2Ray config
nano /usr/local/etc/v2ray/config.json
```

Add all UUIDs to clients array:
```json
{
  "inbounds": [{
    "protocol": "vmess",
    "settings": {
      "clients": [
        {"id": "8b7c1a2d-3e4f-5a6b-7c8d-9e0f1a2b3c4d", "alterId": 64},
        {"id": "1a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d", "alterId": 64},
        ...
      ]
    }
  }]
}
```

```bash
# Restart V2Ray
systemctl restart v2ray
```

#### Step 3: Add to VMaster Pool

1. **Admin Panel → VPN Credentials Pool**

2. **Click "Add Credentials Bulk"**

3. **Fill in:**
   ```
   Select Server: [Your V2Ray Server]
   
   Credentials (paste UUIDs):
   8b7c1a2d-3e4f-5a6b-7c8d-9e0f1a2b3c4d
   1a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d
   ...
   
   Click: Add to Pool
   ```

4. **Done!** Pool now has 100 V2Ray credentials ready

---

## 👥 Customer Experience

### Creating VPN Account (Customer Side):

1. **Login as customer**

2. **VPN Accounts → Create VPN Account**

3. **Select:**
   ```
   Client: John Doe
   Server: SSTP Production Server
   ```

4. **Click Create**

5. **Get Credentials Instantly!**
   ```
   ✅ VPN Account Created Successfully!
   
   Username: vpn_user001
   Password: pass001
   Server: vpn.yourdomain.com
   Port: 443
   Protocol: SSTP
   
   [Copy Credentials] [Share]
   ```

**No waiting! No manual creation!** The system automatically:
- Found next available credential
- Marked it as assigned
- Displayed it to customer

---

## 📊 Pool Statistics

**Admin Dashboard Shows:**
```
┌─────────────────────────────────────────┐
│ Total Credentials: 200                   │
│ Available: 150  ← Ready to assign       │
│ Assigned: 50    ← Currently in use      │
│ SSTP: 100                                │
│ V2Ray: 100                               │
└─────────────────────────────────────────┘
```

---

## 🔍 Managing the Pool

### View All Credentials:

**VPN Credentials Pool page shows table:**

| ID  | Server | Type   | Credential   | Status    | Assigned To | Actions |
|-----|--------|--------|--------------|-----------|-------------|---------|
| 1   | SSTP1  | SSTP   | vpn_user001  | Assigned  | ABC Corp    | In Use  |
| 2   | SSTP1  | SSTP   | vpn_user002  | Available | -           | Delete  |
| 3   | V2Ray1 | V2RAY  | 8b7c1a2d...  | Available | -           | Delete  |

### Delete Unused Credentials:

- Can only delete **Available** (not assigned) credentials
- Click "Delete" button
- Credential removed from pool

### Adding More:

- **Any time** you can add more credentials
- No limit to pool size
- Mix different servers

---

## 🔧 Advanced Features

### 1. Total VPN Limit per Customer

**Admin sets when creating customer:**
```
Max Total VPN Accounts: 50

This customer can create 50 VPN accounts total
(across all clients, all servers)
```

**What happens:**
- Customer creates VPN accounts
- System counts total: 45/50
- When reaching 50, customer sees:
  ```
  ❌ Your company has reached the maximum total 
     VPN accounts allowed (50 total VPN accounts).
  ```

### 2. Smart Assignment

**Pool assignment priority:**
1. Oldest credential first (FIFO)
2. Same server type
3. Available (not assigned)

### 3. Automatic Fallback

If pool is empty:
- SSTP: Generates username/password (manual creation still needed)
- V2Ray: Generates UUID (manual addition still needed)
- **Recommendation:** Keep pool filled!

---

## 💡 Best Practices

### 1. **Pre-fill Pool Before Launch**
```
Create 100-200 credentials before customers start
Always stay ahead of demand
```

### 2. **Monitor Pool Levels**
```
Check pool weekly
When Available < 20, add more
Set up notification (future feature)
```

### 3. **Batch Creation**
```
Create in batches of 50-100
Easier to manage
Can track by batch notes
```

### 4. **Use Notes Field**
```
Notes: "Batch 1 - Jan 2025"
Notes: "Server relocated batch"
Notes: "Premium tier users"
```

### 5. **Regular Cleanup**
```
Delete unused credentials if server full
Re-balance between servers
```

---

## 📝 Examples

### Example 1: Small Setup (50 users)

```
1. Create 50 SSTP accounts in SoftEther
2. Add to pool
3. Set customer limit: 50 total VPN
4. Customers create accounts
5. All assigned from pool automatically
```

### Example 2: Large Setup (500 users)

```
1. Create 250 SSTP accounts
2. Create 250 V2Ray UUIDs
3. Add all to pool
4. Set different limits per customer:
   - Company A: 100 total VPN
   - Company B: 50 total VPN
   - Company C: Unlimited
5. All assignments automatic
```

### Example 3: Mixed Servers

```
1. Pool: 100 SSTP Server 1
2. Pool: 100 SSTP Server 2
3. Pool: 100 V2Ray Server 1
4. Customers choose server
5. System assigns from correct pool
```

---

## ❓ FAQ

**Q: What if pool runs out?**
A: System falls back to generated credentials, but you'll need to manually create them on server. Keep pool filled!

**Q: Can I delete assigned credentials?**
A: No! Only available (unassigned) credentials can be deleted.

**Q: What happens when VPN account is deleted?**
A: Credential returns to pool as "Available" (future feature, currently stays assigned)

**Q: Can I use same credential for multiple servers?**
A: No, each pool entry is for one specific server.

**Q: How do I know when to refill?**
A: Check "Available" count in statistics. When low (< 20), add more.

**Q: Can customers see the pool?**
A: No, only admins can manage pool. Customers just get working credentials.

---

## 🎯 Summary

### Admin Workflow:
```
1. Create accounts in SoftEther/V2Ray (once)
2. Bulk add to VMaster pool (once)
3. Monitor pool levels (weekly)
4. Add more when needed (as needed)
```

### Customer Workflow:
```
1. Create VPN account (click, click)
2. Get working credentials (instant)
3. Use VPN (immediately)
```

### Benefits:
```
✅ No manual work per account
✅ Instant credential delivery
✅ Scalable to thousands
✅ Easy to manage
✅ Automatic assignment
✅ Track usage
```

---

## 🚀 Getting Started

### Quick Start:

```bash
# 1. Create 50 SSTP users in SoftEther
for i in {1..50}; do
  # Create user vpn_user001 - vpn_user050
done

# 2. Copy credentials list

# 3. Admin Panel → VPN Credentials Pool

# 4. Add Credentials Bulk

# 5. Paste and save

# Done! 🎉
```

---

**🎊 Now you can manage thousands of VPN accounts with ease!**

No more manual creation for each account. Pre-create, bulk add, automatic assign!

Happy VPN Management! 🚀

