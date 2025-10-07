# RADIUS Database Commands Quick Reference

## 🎯 Important: Two Database Containers!

Your setup has **2 separate database containers:**

1. **vpn_cms_db** - VMaster main database
   - Database: `vpn_cms_portal`
   - Tables: customers, vpn_accounts, client_accounts, etc.
   - Port: 3306

2. **radius_db** - RADIUS authentication database
   - Database: `radius`
   - Tables: radcheck, radacct, etc.
   - Port: 3307

---

## ✅ Correct Commands for RADIUS

### View All RADIUS Users

**CORRECT** ✅
```bash
docker exec radius_db mysql -uradius -pradiuspass radius \
  -e "SELECT id, username, value as password FROM radcheck"
```

**WRONG** ❌ (This checks wrong database!)
```bash
docker exec vpn_cms_db mysql -uroot -prootpassword radius -e "SELECT * FROM radcheck"
```

---

### Count RADIUS Users

```bash
docker exec radius_db mysql -uradius -pradiuspass radius \
  -e "SELECT COUNT(*) as total_users FROM radcheck"
```

---

### View Active Users (Currently Connected)

```bash
docker exec radius_db mysql -uradius -pradiuspass radius \
  -e "SELECT * FROM radacct WHERE acctstoptime IS NULL"
```

---

### Create RADIUS User Manually

```bash
docker exec radius_db mysql -uradius -pradiuspass radius -e \
  "INSERT INTO radcheck (username, attribute, op, value) \
   VALUES ('testuser', 'Cleartext-Password', ':=', 'testpass123')"
```

---

### Delete RADIUS User

```bash
docker exec radius_db mysql -uradius -pradiuspass radius -e \
  "DELETE FROM radcheck WHERE username='testuser'"
```

---

### Search for Specific User

```bash
docker exec radius_db mysql -uradius -pradiuspass radius -e \
  "SELECT * FROM radcheck WHERE username LIKE 'sstp_%'"
```

---

### Clear All RADIUS Users

```bash
docker exec radius_db mysql -uradius -pradiuspass radius -e \
  "TRUNCATE TABLE radcheck"
```

---

## 📊 Verify SSTP Account Creation

### After creating SSTP account in VMaster:

**Step 1: Check VMaster Database**
```bash
docker exec vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal \
  -e "SELECT id, account_username, account_password, server_id, created_at 
      FROM vpn_accounts ORDER BY id DESC LIMIT 3"
```

**Step 2: Check RADIUS Database**
```bash
docker exec radius_db mysql -uradius -pradiuspass radius \
  -e "SELECT id, username, value as password FROM radcheck ORDER BY id DESC LIMIT 3"
```

**Both should show the same username!**

---

## 🔍 Troubleshooting Commands

### Test RADIUS Connection

```bash
docker exec vpn_cms_web php -r "
require_once '/var/www/html/config/radius.php';
\$conn = getRadiusConnection();
echo \$conn ? 'Connected!' : 'Failed!';
"
```

### Test RadiusHandler

```bash
docker exec vpn_cms_web php -r "
require_once '/var/www/html/config/radius.php';
require_once '/var/www/html/includes/radius_handler.php';
\$handler = new RadiusHandler();
echo 'RadiusHandler: ';
echo \$handler->testConnection() ? 'OK' : 'Failed';
"
```

### Check Database Containers

```bash
docker ps --format "table {{.Names}}\t{{.Image}}\t{{.Ports}}" | grep db
```

---

## 📋 Database Credentials

### VMaster Database (vpn_cms_db):
```
Host: db (inside Docker) or localhost:3306 (from host)
Database: vpn_cms_portal
User: root
Password: rootpassword
```

### RADIUS Database (radius_db):
```
Host: radius-db (inside Docker) or localhost:3307 (from host)
Database: radius
User: radius
Password: radiuspass
```

---

## 🎯 Quick Reference

| What | Command |
|------|---------|
| View RADIUS users | `docker exec radius_db mysql -uradius -pradiuspass radius -e "SELECT * FROM radcheck"` |
| Count users | `docker exec radius_db mysql -uradius -pradiuspass radius -e "SELECT COUNT(*) FROM radcheck"` |
| View VMaster accounts | `docker exec vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal -e "SELECT * FROM vpn_accounts"` |
| Check connections | `docker exec radius_db mysql -uradius -pradiuspass radius -e "SELECT * FROM radacct WHERE acctstoptime IS NULL"` |

---

## 💡 Pro Tip

**Save this as an alias in your shell:**

```bash
# Add to ~/.zshrc or ~/.bashrc
alias radius-users='docker exec radius_db mysql -uradius -pradiuspass radius -e "SELECT id, username, value FROM radcheck"'
alias radius-count='docker exec radius_db mysql -uradius -pradiuspass radius -e "SELECT COUNT(*) as total FROM radcheck"'
alias vmaster-vpn='docker exec vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal -e "SELECT id, account_username, server_id, created_at FROM vpn_accounts ORDER BY id DESC LIMIT 5"'
```

Then just run:
```bash
radius-users
radius-count
vmaster-vpn
```

---

**Use the CORRECT container: `radius_db` for RADIUS queries!** ✅

