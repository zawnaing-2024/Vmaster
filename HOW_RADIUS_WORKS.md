# How RADIUS Works with SSTP in VMaster

## 🎯 Your Question: "When customer creates SSTP account, I want to create on RADIUS database and also to SSTP account. Is it right?"

**Answer: NO! You only create in RADIUS database. Here's why:**

---

## 🔄 How RADIUS Authentication Works

### WITHOUT RADIUS (Manual - Old Way):

```
1. Customer creates VPN account in VMaster
   ↓
2. Admin gets notification
   ↓
3. Admin logs into SoftEther server
   ↓
4. Admin manually creates user account on server
   ↓
5. Admin shares credentials with customer
   ↓
6. Client connects to SSTP
   ↓
7. SoftEther checks LOCAL user database
   ↓
8. Connection succeeds if user exists locally
```

**Problem:** Manual work for every account!

---

### WITH RADIUS (Automatic - New Way):

```
1. Customer creates VPN account in VMaster
   ↓
2. VMaster creates user in RADIUS database ONLY
   ↓
3. VMaster shows credentials to customer
   ↓
4. Client connects to SSTP
   ↓
5. SoftEther asks RADIUS: "Is user 'john123' with password 'pass456' valid?"
   ↓
6. RADIUS checks its database
   ↓
7. RADIUS responds: "YES, allow connection"
   ↓
8. SoftEther allows connection
   ↓
9. Connection succeeds WITHOUT creating account on SSTP server!
```

**Benefit:** ZERO manual work! Fully automatic!

---

## 🎯 Key Concept: RADIUS is an Authentication Server

### What RADIUS Does:
- **Stores usernames and passwords**
- **Validates credentials when SoftEther asks**
- **Acts as a central authentication database**

### What RADIUS Does NOT Do:
- ❌ Does NOT create accounts on SSTP server
- ❌ Does NOT need accounts on SSTP server
- ❌ Does NOT store VPN configurations

---

## 📊 Visual Explanation

### Traditional Way (Manual):
```
┌─────────────┐
│   VMaster   │ → Stores credentials info
└─────────────┘

┌─────────────┐
│   SSTP      │ → Has LOCAL user accounts
│   Server    │    (admin must create manually)
└─────────────┘
```

**Accounts must exist in BOTH places!**

---

### RADIUS Way (Automatic):
```
┌─────────────┐
│   VMaster   │ → Creates user in RADIUS
│             │    Shows credentials to customer
└─────────────┘
       ↓
┌─────────────┐
│   RADIUS    │ → Stores ALL usernames/passwords
│  Database   │    Acts as authentication server
└─────────────┘
       ↑
       │ "Is user valid?"
       │ "Yes/No"
       │
┌─────────────┐
│   SSTP      │ → NO local accounts needed!
│   Server    │    Just asks RADIUS
└─────────────┘
```

**Accounts ONLY in RADIUS! SSTP just checks RADIUS!**

---

## 💻 What VMaster Does Automatically

When RADIUS is ENABLED in `config/radius.php`:

### Creating SSTP Account:

```php
// Customer clicks "Create VPN Account"
// VMaster automatically:

1. Generate username (e.g., "sstp_user123")
2. Generate password (e.g., "SecurePass!456")
3. Call RadiusHandler->createUser(username, password)
   ↓
   This adds to RADIUS database:
   INSERT INTO radcheck VALUES (username, 'Cleartext-Password', password)
4. Store in VMaster database (vpn_accounts table)
5. Show credentials to customer
6. DONE! User can connect immediately!
```

### What Happens on SoftEther:
```
1. Client tries to connect with username/password
2. SoftEther sends to RADIUS:
   "Authenticate: username='sstp_user123', password='SecurePass!456'"
3. RADIUS checks radcheck table
4. RADIUS responds: "ACCEPT" or "REJECT"
5. SoftEther allows/denies connection based on RADIUS response
```

**NO ACCOUNT CREATED ON SSTP SERVER!**

---

## 🔧 SoftEther Configuration

You configure SoftEther ONCE to use RADIUS for ALL users:

```
Virtual Hub → User Authentication Settings
→ Use External RADIUS Server

RADIUS Server: localhost (or IP)
Port: 1812
Shared Secret: testing123

✅ Enable: "Use RADIUS for all authentication"
```

After this:
- SoftEther will NEVER check local users
- SoftEther will ALWAYS ask RADIUS
- ANY user in RADIUS can connect
- NO need to create users in SoftEther!

---

## 📋 Complete Flow Example

### Step 1: Customer Creates SSTP Account

Customer goes to VMaster → VPN Accounts → Create Account
- Selects SSTP server
- Clicks Create

### Step 2: VMaster Creates in RADIUS (Automatic)

```sql
-- VMaster executes:
INSERT INTO radius.radcheck (username, attribute, op, value)
VALUES ('sstp_user123', 'Cleartext-Password', ':=', 'SecurePass!456');

-- VMaster also stores in its own database:
INSERT INTO vpn_cms_portal.vpn_accounts 
(customer_id, staff_id, server_id, account_username, account_password)
VALUES (1, 5, 2, 'sstp_user123', 'SecurePass!456');
```

### Step 3: Customer Gets Credentials

VMaster shows:
```
✅ SSTP Account Created!

Server: vpn.example.com
Username: sstp_user123
Password: SecurePass!456
```

### Step 4: Client Connects

User opens SSTP VPN client:
- Server: vpn.example.com
- Username: sstp_user123
- Password: SecurePass!456
- Clicks "Connect"

### Step 5: SoftEther Checks RADIUS

```
SoftEther → RADIUS: "Is sstp_user123 with password SecurePass!456 valid?"
RADIUS → Checks database → Finds matching entry
RADIUS → SoftEther: "ACCESS-ACCEPT"
SoftEther → Client: "Connection established!"
```

### Step 6: Connection Success!

User is now connected to SSTP VPN!

**NO manual account creation was needed!**

---

## 🎯 Summary

### Question: Do I create account in both RADIUS and SSTP?
**Answer: NO! Only in RADIUS!**

### How it works:
1. ✅ VMaster creates user in RADIUS database
2. ✅ VMaster stores credentials in its own database (for display)
3. ❌ NO account created on SSTP server
4. ✅ SoftEther asks RADIUS for authentication
5. ✅ RADIUS approves/denies based on its database
6. ✅ Connection works without SSTP server accounts!

---

## 🔍 Verify It's Working

### Check RADIUS Database:
```bash
docker exec vpn_cms_db mysql -uroot -prootpassword radius -e "SELECT * FROM radcheck"
```

You should see:
```
+----+---------------+---------------------+----+------------------+
| id | username      | attribute           | op | value            |
+----+---------------+---------------------+----+------------------+
|  1 | sstp_user123  | Cleartext-Password  | := | SecurePass!456   |
+----+---------------+---------------------+----+------------------+
```

### Check SoftEther Logs:
```
RADIUS: Authentication request for user sstp_user123
RADIUS: Response from 127.0.0.1:1812 - ACCESS-ACCEPT
Connection: User sstp_user123 connected successfully
```

### Check SoftEther Users List:
```bash
vpncmd /SERVER localhost
Hub DEFAULT
UserList
```

**You will see NO users!** Because they're all in RADIUS!

---

## ✅ Benefits of RADIUS

| Feature | Manual | RADIUS |
|---------|--------|--------|
| Create account | 5-10 min | 2 seconds |
| Delete account | Login to server | Automatic |
| Suspend account | Login to server | Automatic |
| Change password | Login to server | Automatic |
| Scale to 1000 users | Impossible | Easy |
| Centralized control | No | Yes |
| Works across multiple VPN servers | No | Yes |

---

## 🚀 Conclusion

**You DON'T create accounts on SSTP server!**

**RADIUS IS the authentication server!**

SoftEther just asks RADIUS "Is this user valid?" and RADIUS answers yes/no.

That's the whole point of RADIUS - centralized authentication without needing local accounts on each server!

---

**Think of RADIUS as a bouncer at a club:**
- The bouncer (RADIUS) has a list of allowed guests
- The club (SSTP server) trusts the bouncer's decision
- The club doesn't keep its own guest list
- The bouncer checks the list and says "yes, let them in" or "no, reject"

**The club (SSTP) doesn't need to know who's allowed - it just trusts the bouncer (RADIUS)!**

