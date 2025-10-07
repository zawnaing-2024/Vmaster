# Quick RADIUS Setup for VMaster

## ⚡ 3-Step Setup

### Step 1: Enable RADIUS (30 seconds)
```bash
cd /Users/zawnainghtun/My\ Coding\ Project/VPN\ CMS\ Portal
nano config/radius.php
```

Change line 19:
```php
define('RADIUS_ENABLED', true);  // Change from false
```

Save: `Ctrl+O`, `Enter`, `Ctrl+X`

---

### Step 2: Configure SoftEther (2 minutes)

**Option A: Using GUI (Easier)**
1. Download SoftEther VPN Server Manager
2. Connect to your server
3. Go to: Virtual Hub → User Authentication → RADIUS Settings
4. Enter:
   - Server: `localhost` (or your server IP)
   - Port: `1812`
   - Secret: `testing123`

**Option B: Using Command Line**
```bash
vpncmd /SERVER localhost
Hub DEFAULT
RadiusServerSet localhost:1812 testing123
```

---

### Step 3: Test (1 minute)

1. **Open RADIUS Management:**
   ```
   http://localhost/admin/radius-management.php
   ```

2. **Create test user:**
   - Username: `test`
   - Password: `test123`

3. **Verify in database:**
   ```bash
   docker exec vpn_cms_db mysql -uroot -prootpassword radius -e "SELECT * FROM radcheck"
   ```

4. **Try connecting to SSTP** with test credentials

---

## ✅ How to Create SSTP with RADIUS

### From Customer Portal:

1. **Login as customer**
2. **Go to: VPN Accounts → Create VPN Account**
3. **Fill form:**
   - Client: Select your client
   - Server: Select SSTP server
   - Click "Create Account"

### What Happens Automatically:

```
Customer clicks "Create"
    ↓
VMaster generates random username/password
    ↓
VMaster adds user to RADIUS database
    ↓
VMaster stores credentials in vpn_accounts table
    ↓
Customer gets username/password to share with client
    ↓
Client connects to SSTP VPN
    ↓
SoftEther checks RADIUS
    ↓
Authentication succeeds!
```

---

## 🔍 Verify RADIUS is Working

```bash
# Check RADIUS users
docker exec vpn_cms_db mysql -uroot -prootpassword radius -e "SELECT * FROM radcheck"

# Check VMaster VPN accounts
docker exec vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal -e "SELECT id, account_username, server_id FROM vpn_accounts WHERE server_id IN (SELECT id FROM vpn_servers WHERE server_type='sstp')"
```

---

## 🎯 Key Features

✅ **Automatic user creation** - No manual account setup  
✅ **Automatic deletion** - Users removed when account deleted  
✅ **Suspend/Resume** - Control access instantly  
✅ **Password changes** - Update credentials anytime  
✅ **Usage tracking** - Monitor connections in radacct table  

---

## 📊 RADIUS vs Manual

| Feature | Manual | RADIUS |
|---------|--------|--------|
| Create account | Admin logs in to server, creates user | Automatic |
| Delete account | Admin logs in to server, deletes user | Automatic |
| Suspend account | Admin logs in to server, disables user | Automatic |
| Change password | Admin logs in to server, updates | Automatic |
| Time required | 5-10 minutes per account | 2 seconds |

---

## 🚨 Troubleshooting

### RADIUS not connecting?
```bash
# Check RADIUS database connection
docker exec vpn_cms_db mysql -uroot -prootpassword -e "SHOW DATABASES" | grep radius

# Check if container is running
docker ps | grep radius_db
```

### SoftEther not authenticating?
- Verify RADIUS server IP and port in SoftEther
- Check shared secret matches: `testing123`
- Enable debug logging in SoftEther
- Check `/var/log/softether/` for errors

### Users created but can't connect?
- Verify SoftEther is using RADIUS (not local auth)
- Check firewall allows port 1812 (RADIUS)
- Verify password in radcheck table
- Test with `radtest` command:
  ```bash
  radtest testuser testpass123 localhost 1812 testing123
  ```

---

## 📖 Full Documentation

For complete setup guide with diagrams and advanced features:
→ **See: RADIUS_SSTP_SETUP_GUIDE.md**

---

## 🎉 That's It!

RADIUS is now handling authentication automatically.  
No more manual account creation on VPN servers!

**Questions?** Check the full guide or RADIUS Management page.

