# 🔐 FreeRADIUS Installation FAQ

Common questions about installing FreeRADIUS with existing RADIUS database.

---

## ❓ **What happens to my existing RADIUS database?**

### ✅ **Answer: Nothing! It stays exactly as it is.**

**FreeRADIUS installation will:**
- ✅ **Connect** to your existing database
- ✅ **Read** existing users from `radcheck` table
- ✅ **Keep** all your existing data
- ❌ **NOT** create a new database
- ❌ **NOT** delete any data
- ❌ **NOT** modify existing users

**Think of it this way:**
```
Before:
  VMaster → Creates users → RADIUS Database
                            (data stored, but no one is reading it)

After:
  VMaster → Creates users → RADIUS Database ← FreeRADIUS reads
                                             ↓
                                        SSTP Server asks
                                             ↓
                                        "Is user valid?"
```

---

## ❓ **Will my existing VPN accounts work?**

### ✅ **Yes! Immediately!**

All users already in your `radcheck` table will work as soon as FreeRADIUS starts.

**Example:**

If you have this in database:
```sql
SELECT * FROM radcheck;
+----+------------------+---------------------+----+----------------+
| id | username         | attribute           | op | value          |
+----+------------------+---------------------+----+----------------+
|  1 | sstp_abc123      | Cleartext-Password  | := | MyPass123!     |
|  2 | sstp_xyz789      | Cleartext-Password  | := | SecurePass456! |
+----+------------------+---------------------+----+----------------+
```

These users can connect **immediately** after FreeRADIUS installation! ✅

---

## ❓ **What exactly does the installation script do?**

### **Step-by-step breakdown:**

1. **Install FreeRADIUS packages** (software only, no data changes)
2. **Configure FreeRADIUS** to connect to your existing database:
   - Host: `127.0.0.1`
   - Port: `3307`
   - Database: `radius` (your existing one!)
   - User: `radius`
   - Password: `radiuspass`
3. **Add SSTP server** to `nas` table (authorized clients)
4. **Open firewall** ports 1812/1813 (UDP)
5. **Start FreeRADIUS** service
6. **Test** with a temporary user (deleted after test)

**No existing data is touched!**

---

## ❓ **What if I already have users in the database?**

### ✅ **Perfect! They'll work immediately!**

The script will:
1. Count existing users
2. Show you: "Found X existing users"
3. These users will authenticate as soon as FreeRADIUS starts

**No migration needed!**

---

## ❓ **What database tables does FreeRADIUS use?**

FreeRADIUS reads/writes these tables:

| Table | Purpose | Created By |
|-------|---------|------------|
| `radcheck` | User credentials | VMaster (already exists) |
| `radreply` | User attributes | VMaster (optional) |
| `radgroupcheck` | Group settings | VMaster (optional) |
| `radgroupreply` | Group attributes | VMaster (optional) |
| `radusergroup` | User-group mapping | VMaster (optional) |
| `radacct` | Accounting/sessions | FreeRADIUS (auto-created) |
| `radpostauth` | Auth logs | FreeRADIUS (auto-created) |
| `nas` | Authorized clients | Install script (auto-created) |

**Your existing tables:** `radcheck`, `radreply`, etc. → **Preserved!**  
**New tables:** `nas`, `radacct`, `radpostauth` → **Created if missing**

---

## ❓ **Can I test without affecting production?**

### ✅ **Yes! The test is safe.**

The installation script does this test:

```bash
# Create temporary test user
INSERT INTO radcheck VALUES ('testuser', 'Cleartext-Password', ':=', 'testpass123');

# Test authentication
radtest testuser testpass123 localhost 1812 testing123

# Delete test user
DELETE FROM radcheck WHERE username='testuser';
```

**Your real users are never touched!**

---

## ❓ **What if the installation fails?**

### **The script has safety checks:**

1. **Backup config** before changes
2. **Validate config** before starting FreeRADIUS
3. **Auto-rollback** if validation fails
4. **Your database** is never modified (only read)

**Worst case scenario:**
- FreeRADIUS doesn't start
- Your database is still intact
- Your VMaster still works
- Just fix the error and try again

---

## ❓ **How do I verify my existing users are working?**

### **After installation, test with real user:**

```bash
# Get a real username from database
docker exec vmaster_radius_db mysql -uroot -prootpassword radius \
  -e "SELECT username, value FROM radcheck WHERE attribute='Cleartext-Password' LIMIT 1;"

# Example output:
# username: sstp_abc123
# value: MyPass123!

# Test authentication
radtest sstp_abc123 MyPass123! localhost 1812 testing123
```

**Expected:** `Received Access-Accept` ✅

---

## ❓ **What's the difference between RADIUS Database and FreeRADIUS?**

### **They're two separate things:**

**RADIUS Database (MySQL):**
- Stores user credentials
- Stores session logs
- Just a database (no authentication logic)
- You already have this! ✅

**FreeRADIUS (Server):**
- Listens on port 1812/1813
- Receives authentication requests from SSTP
- Queries RADIUS database
- Returns Accept/Reject
- You need to install this! ❌

**Analogy:**
```
RADIUS Database = Phone book (stores phone numbers)
FreeRADIUS      = Directory assistance operator (looks up numbers)
```

---

## ❓ **Will this affect my VMaster application?**

### ✅ **No! VMaster continues working normally.**

**Before FreeRADIUS:**
```
VMaster → Creates user in RADIUS database
       → Creates VPN account record
       → Shows credentials to customer
```

**After FreeRADIUS:**
```
VMaster → Creates user in RADIUS database (same as before!)
       → Creates VPN account record (same as before!)
       → Shows credentials to customer (same as before!)
       
       (FreeRADIUS just reads the database in the background)
```

**Nothing changes in VMaster!**

---

## ❓ **Can I uninstall FreeRADIUS if I don't like it?**

### ✅ **Yes! Your database stays intact.**

```bash
# Stop FreeRADIUS
sudo systemctl stop freeradius
sudo systemctl disable freeradius

# Uninstall (optional)
sudo apt-get remove --purge freeradius freeradius-mysql

# Your database is untouched!
docker exec vmaster_radius_db mysql -uroot -prootpassword radius \
  -e "SELECT COUNT(*) FROM radcheck;"
# Still shows all your users ✅
```

---

## ❓ **What if I have custom tables or data?**

### ✅ **No problem! FreeRADIUS only uses specific tables.**

FreeRADIUS will:
- ✅ Use standard tables: `radcheck`, `radreply`, `radacct`, etc.
- ✅ Ignore custom tables
- ✅ Ignore custom columns in standard tables
- ✅ Not modify any data you've added

**Your custom data is safe!**

---

## ❓ **Do I need to migrate or import anything?**

### ✅ **No! Zero migration needed!**

**If your database has:**
```sql
-- Standard RADIUS schema
CREATE TABLE radcheck (
    id int(11) PRIMARY KEY AUTO_INCREMENT,
    username varchar(64) NOT NULL,
    attribute varchar(64) NOT NULL,
    op char(2) NOT NULL,
    value varchar(253) NOT NULL
);
```

**Then you're ready!** Just install FreeRADIUS and it works.

---

## ❓ **What if my database schema is different?**

### **Check your schema:**

```bash
docker exec vmaster_radius_db mysql -uroot -prootpassword radius \
  -e "DESCRIBE radcheck;"
```

**Expected output:**
```
+-----------+--------------+------+-----+---------+----------------+
| Field     | Type         | Null | Key | Default | Extra          |
+-----------+--------------+------+-----+---------+----------------+
| id        | int(11)      | NO   | PRI | NULL    | auto_increment |
| username  | varchar(64)  | NO   | MUL |         |                |
| attribute | varchar(64)  | NO   |     |         |                |
| op        | char(2)      | NO   |     | ==      |                |
| value     | varchar(253) | NO   |     |         |                |
+-----------+--------------+------+-----+---------+----------------+
```

**If your schema matches** → ✅ You're good!  
**If your schema is different** → ⚠️ You may need to adjust FreeRADIUS queries

---

## ❓ **Summary: Is it safe to install?**

### ✅ **YES! 100% Safe!**

**What happens:**
- ✅ FreeRADIUS connects to existing database (read-only for user data)
- ✅ Existing users work immediately
- ✅ VMaster continues working normally
- ✅ No data loss
- ✅ Can be uninstalled anytime

**What does NOT happen:**
- ❌ No new database created
- ❌ No data deleted
- ❌ No data modified
- ❌ No downtime for VMaster
- ❌ No migration required

---

## 🚀 **Ready to Install?**

Just run:

```bash
cd /var/www/vmaster
git pull origin main
sudo bash scripts/install-freeradius.sh
```

**Your existing database and users are completely safe!** ✅

---

## 📊 **Visual Overview**

### **Current Setup (Without FreeRADIUS):**
```
┌─────────────┐
│   VMaster   │
│             │
│  Creates    │
│  users in   │
│  database   │
└──────┬──────┘
       │
       ↓
┌─────────────────┐
│ RADIUS Database │  ← Data stored here
│                 │     but no one reads it!
│ • radcheck      │
│ • radreply      │
└─────────────────┘

SSTP Server: Uses local files (not connected to RADIUS)
```

### **After FreeRADIUS Installation:**
```
┌─────────────┐
│   VMaster   │
│             │
│  Creates    │
│  users in   │
│  database   │
└──────┬──────┘
       │
       ↓
┌─────────────────┐      ┌──────────────┐
│ RADIUS Database │ ←────│ FreeRADIUS   │
│                 │ Read │              │
│ • radcheck      │      │ Listens on   │
│ • radreply      │      │ port 1812    │
│ • radacct       │ ────→│              │
└─────────────────┘ Write└──────┬───────┘
                                 ↑
                                 │ Auth request
                                 │
                          ┌──────┴───────┐
                          │ SSTP Server  │
                          │              │
                          │ User trying  │
                          │ to connect   │
                          └──────────────┘
```

**Same database, just now it's being used!** ✅

---

## 💡 **Key Takeaway**

Installing FreeRADIUS is like **hiring a librarian** for your library:

- **Before:** You have books (database with users), but no one to help find them
- **After:** You have books (same database) + librarian (FreeRADIUS) who knows where everything is

**The books don't change, you just added someone to read them!** 📚✅
