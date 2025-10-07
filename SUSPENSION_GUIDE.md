# 🔒 Account Suspension & Deletion Guide

How VPN access is controlled when accounts are deleted or suspended.

---

## 🎯 How It Works

### When You Delete/Suspend:

```
┌─────────────────────────────────────────┐
│ Action in VMaster                       │
│ ├─ Delete Client                        │
│ ├─ Delete VPN Account                   │
│ └─ Suspend Client (status change)      │
└─────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────┐
│ VMaster Actions                         │
│ ├─ Removes from database                │
│ ├─ Returns credential to pool           │
│ ├─ Updates statistics                   │
│ └─ Deletes from Outline (if Outline)    │
└─────────────────────────────────────────┘
         ↓
┌─────────────────────────────────────────┐
│ VPN Server Behavior                     │
│ ├─ Outline: Deleted via API ✅          │
│ ├─ SSTP: Credential still works ⚠️      │
│ └─ V2Ray: UUID still works ⚠️           │
└─────────────────────────────────────────┘
```

---

## ✅ What Happens Automatically

### 1. **Delete VPN Account**

**VMaster Actions:**
```
✅ Removes account from database
✅ Returns credential to pool (if from pool)
✅ Updates server statistics
✅ Deletes from Outline server (if Outline)
✅ Logs the activity
```

**VPN Server Status:**
- **Outline**: ✅ **Automatically deleted** via API
- **SSTP**: ⚠️ Credential still exists in SoftEther
- **V2Ray**: ⚠️ UUID still exists in V2Ray config

**Client Impact:**
- **Outline**: ✅ **Disconnected immediately**, cannot reconnect
- **SSTP**: ⚠️ Can still connect until manually removed
- **V2Ray**: ⚠️ Can still connect until manually removed

---

### 2. **Delete Client**

**VMaster Actions:**
```
✅ Deletes all VPN accounts for this client
✅ Returns all pool credentials
✅ Deletes from Outline servers (if any)
✅ Updates all server statistics
✅ Removes client record
```

**Example:**
```
Client "John Doe" has:
  - 3 VPN accounts (1 Outline, 2 SSTP)

Delete Client:
  → 1 Outline account deleted from server ✅
  → 2 SSTP credentials returned to pool ✅
  → All database records removed ✅
  → John cannot access VMaster anymore ✅
  → SSTP credentials still work on server ⚠️
```

---

### 3. **Suspend Client**

**VMaster Actions:**
```
✅ Changes client status to "suspended"
✅ Client cannot login
✅ VPN accounts not visible
✅ Logs the suspension
```

**VPN Server Status:**
- All existing credentials still work on servers ⚠️
- Client just cannot see/share them in VMaster

**To Unsuspend:**
```
Edit Client → Status: Active → Save
✅ Client can login again
✅ VPN accounts visible again
```

---

## ⚠️ Manual Cleanup Required (SSTP & V2Ray)

### For SSTP (SoftEther):

When you delete a VPN account from VMaster, the credential returns to the pool but **the user still exists in SoftEther**.

**Option 1: Keep Using (Recommended)**
```
✅ Credential returns to pool
✅ Gets reassigned to another client
✅ Original client still knows the password
⚠️ Not secure if client is untrusted
```

**Option 2: Manually Delete from SoftEther**
```bash
# Access SoftEther
/usr/local/vpnserver/vpncmd

# Select hub
Hub vpn_hub

# Delete user
UserDelete vpn_user001

# Exit
exit
```

**Then delete from pool:**
```
Admin → VPN Credentials Pool
→ Find credential
→ Click Delete (only works if not assigned)
```

**Option 3: Reset Password in SoftEther**
```bash
# Change the password
UserPasswordSet vpn_user001
# Enter new password

# Update in pool (manual edit in database)
# Or delete and re-add to pool
```

---

### For V2Ray:

When you delete a VPN account, the UUID returns to pool but **still exists in V2Ray config**.

**Option 1: Keep Using (Recommended)**
```
✅ UUID returns to pool
✅ Gets reassigned to another client
✅ Original client still knows the UUID
⚠️ Not secure if client is untrusted
```

**Option 2: Manually Remove from V2Ray**
```bash
# Edit config
nano /usr/local/etc/v2ray/config.json

# Remove the UUID from clients array
{
  "clients": [
    // Remove this line:
    // {"id": "uuid-to-remove", "alterId": 64},
  ]
}

# Restart V2Ray
systemctl restart v2ray
```

**Then delete from pool:**
```
Admin → VPN Credentials Pool
→ Find UUID
→ Click Delete
```

---

## 🔐 Security Recommendations

### High Security Scenario:

**When deleting untrusted clients:**

1. **Delete from VMaster** ✓
2. **Manually remove from VPN servers:**
   - SSTP: Delete users from SoftEther
   - V2Ray: Remove UUIDs from config
3. **Delete from pool** (if not reassigned)
4. **Monitor connection logs**

### Medium Security (Default):

**Use pool recycling:**

1. **Delete from VMaster** ✓
2. **Credential returns to pool** ✓
3. **Gets reassigned to new client** ✓
4. **Old client can't get new credentials from VMaster** ✓

**Risk:** Old client could still use their old credentials if they remember them.

### Low Security (Convenient):

**Just delete from VMaster:**

1. **Delete from VMaster** ✓
2. **Don't worry about server cleanup**
3. **Pool handles recycling**

**Risk:** Deleted clients can technically still connect if they saved credentials.

---

## 📊 Real-World Examples

### Example 1: Employee Leaves Company

**Scenario:**
```
Employee John Doe leaves company
Has 3 VPN accounts (all SSTP)
```

**Recommended Action:**
```
1. Customer → My Clients → Delete John Doe
   ✅ VMaster deletes all 3 accounts
   ✅ 3 SSTP credentials return to pool

2. Admin → VPN Pool
   ✅ Pool shows 3 more available
   ✅ Will be reassigned to new employees

3. Optional (High Security):
   Admin manually deletes users from SoftEther
```

**Result:**
- John cannot access VMaster ✅
- John's credentials returned to pool ✅
- New employee gets recycled credentials ✅
- If John saved password, he could technically still connect (until credential reassigned) ⚠️

---

### Example 2: Temporary Suspension

**Scenario:**
```
Client Alice violated policy
Suspend for 1 week
```

**Action:**
```
1. Customer → My Clients → Edit Alice
2. Status: Suspended → Save
```

**Result:**
- Alice's VPN accounts hidden in VMaster ✅
- Alice cannot see credentials ✅
- Credentials still work if she saved them ⚠️
- After 1 week: Status: Active → Restored ✅

---

### Example 3: Security Breach

**Scenario:**
```
Credentials leaked
Must revoke immediately
```

**Action (SSTP):**
```
1. Delete from VMaster
2. Immediately delete from SoftEther:
   
   /usr/local/vpnserver/vpncmd
   Hub vpn_hub
   UserDelete compromised_user
   
3. Delete from pool (if not reassigned)
4. Create new credential
```

**Action (Outline):**
```
1. Delete from VMaster
   ✅ Automatically deleted from Outline server
   ✅ Client disconnected immediately
   ✅ Cannot reconnect
```

---

## 🎯 Best Practices

### 1. **Use Outline for High Security**
```
✅ Automatic API deletion
✅ Immediate disconnection
✅ Cannot reconnect
✅ No manual cleanup needed
```

### 2. **Pool Rotation for SSTP/V2Ray**
```
✅ Keep large pool (100+ credentials)
✅ Credentials get reassigned
✅ Old clients lose access when reassigned
✅ Minimal manual work
```

### 3. **Monitor Pool Levels**
```
✅ Check "Available" count weekly
✅ Add more when low
✅ Delete unused if needed
```

### 4. **Regular Audits**
```
✅ Review active VPN accounts monthly
✅ Delete unused accounts
✅ Check for suspicious activity
✅ Sync with employee roster
```

### 5. **Document Deletions**
```
✅ Activity logs track all deletions
✅ Review logs for compliance
✅ Export logs for audit trail
```

---

## 💡 Summary

### Automatic Actions:
```
✅ Delete from VMaster database
✅ Return credential to pool
✅ Update statistics
✅ Delete from Outline (if Outline)
✅ Log activity
```

### Manual Actions (If High Security Needed):
```
⚠️ Delete from SoftEther (SSTP)
⚠️ Remove from V2Ray config
⚠️ Delete from pool (if credential compromised)
⚠️ Monitor server logs
```

### Client Impact:
```
✅ Cannot access VMaster
✅ Cannot get new credentials
✅ Outline: Disconnected immediately
⚠️ SSTP/V2Ray: Can use if saved credentials
```

---

## 🔍 Checking Status

### View Pool Status:
```
Admin → VPN Credentials Pool

Shows:
- Available: 150 (ready for assignment)
- Assigned: 50 (currently in use)
- Can delete: Only Available credentials
```

### View VPN Accounts:
```
Customer → VPN Accounts

Shows:
- Active accounts
- Server type
- Created date
- Can delete: Any account
```

### Check Server:
```
For SSTP (SoftEther):
  /usr/local/vpnserver/vpncmd
  Hub vpn_hub
  UserList

For V2Ray:
  Check config file for active UUIDs
```

---

**🔒 Remember: For maximum security, manually remove credentials from VPN servers when deleting untrusted clients!**

For normal employee turnover, pool recycling is sufficient. ✅

