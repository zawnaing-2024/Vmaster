# 🎨 RADIUS Clients GUI - User Guide

Easy-to-use web interface for managing SSTP servers that can authenticate via RADIUS.

---

## 🚀 **Quick Start**

### **Access the Interface**

1. Login to VMaster as **Admin**
2. Click **🔐 RADIUS Clients** in the sidebar
3. Start adding your SSTP servers!

**URL:** `https://your-vmaster-domain.com/admin/radius-clients.php`

---

## ✨ **Features**

### **✅ What You Can Do:**

- ➕ **Add SSTP servers** with a simple form
- ✏️ **Edit server details** (IP, secret, description)
- 🗑️ **Delete servers** you no longer use
- 🔍 **Test connectivity** to verify servers are reachable
- 👁️ **Show/Hide secrets** for security
- 📊 **View all servers** in one table

### **✅ What It Does Automatically:**

- Validates IP addresses and required fields
- Checks for duplicate servers
- Stores configuration in RADIUS database
- Shows clear success/error messages
- Prevents accidental deletions with confirmations

---

## 📋 **How to Add an SSTP Server**

### **Step 1: Click "Add SSTP Server"**

Click the green **➕ Add SSTP Server** button at the top right.

### **Step 2: Fill in the Form**

| Field | Required | Description | Example |
|-------|----------|-------------|---------|
| **Server IP Address** | ✅ Yes | Public IP of your SSTP server | `103.117.149.112` |
| **Server Name** | ❌ No | Friendly name for identification | `sstp-server-1` |
| **Server Type** | ❌ No | Type of server (default: Other) | `Other (SSTP/Generic)` |
| **Shared Secret** | ✅ Yes | Must match SSTP server config | `testing123` |
| **Description** | ❌ No | Notes about this server | `Main SSTP server in Singapore` |

### **Step 3: Save**

Click **💾 Save RADIUS Client**

### **Step 4: Restart FreeRADIUS**

SSH to VMaster server and run:

```bash
sudo systemctl restart freeradius
```

✅ **Done!** Your SSTP server can now authenticate users via RADIUS!

---

## ✏️ **How to Edit a Server**

1. Find the server in the table
2. Click **✏️ Edit** button
3. Modify the details
4. Click **💾 Save RADIUS Client**
5. Restart FreeRADIUS:
   ```bash
   sudo systemctl restart freeradius
   ```

---

## 🗑️ **How to Delete a Server**

1. Find the server in the table
2. Click **🗑️ Delete** button
3. Confirm the deletion
4. Restart FreeRADIUS:
   ```bash
   sudo systemctl restart freeradius
   ```

⚠️ **Warning:** After deletion, this server can no longer authenticate users via RADIUS!

---

## 🔍 **How to Test a Server**

1. Find the server in the table
2. Click **🔍 Test** button
3. The system will ping the server
4. You'll see:
   - ✅ **Server is reachable** = Good!
   - ❌ **Server is not reachable** = Check IP or firewall

**Note:** This only tests network connectivity, not RADIUS authentication.

---

## 👁️ **Show/Hide Shared Secrets**

For security, shared secrets are hidden by default (shown as `••••••••••`).

**To view:**
1. Click **Show** next to the secret
2. The actual secret will be displayed
3. Click **Hide** to hide it again

---

## 🔐 **Security Best Practices**

### **1. Use Strong Shared Secrets**

❌ **Weak:** `testing123`, `password`, `12345`  
✅ **Strong:** `Kx9#mP2$vL8@qR5!nT3^`

**Generate a strong secret:**
```bash
openssl rand -base64 32
```

### **2. Change Default Secrets**

Never use `testing123` in production!

### **3. Use Descriptive Names**

Instead of: `server1`, `server2`  
Use: `sstp-singapore-main`, `sstp-usa-backup`

### **4. Document Your Servers**

Use the **Description** field to note:
- Location
- Purpose
- Contact person
- Installation date

Example: `Main SSTP server in Singapore DC, managed by John, installed 2025-01-15`

---

## 📊 **Understanding the Table**

### **Columns Explained:**

| Column | Description |
|--------|-------------|
| **Server IP** | The public IP address of the SSTP server |
| **Name** | Friendly name (shortname) |
| **Type** | Server type (usually "OTHER" for SSTP) |
| **Shared Secret** | Authentication secret (hidden by default) |
| **Description** | Notes about this server |
| **Actions** | Test, Edit, Delete buttons |

### **Badge Colors:**

- 🔵 **Blue (OTHER)** - Generic/SSTP servers
- ⚫ **Gray** - Other types (Cisco, MikroTik, etc.)

---

## ⚠️ **Important Notes**

### **1. Always Restart FreeRADIUS**

After any change (add/edit/delete), you MUST restart FreeRADIUS:

```bash
sudo systemctl restart freeradius
```

Otherwise, changes won't take effect!

### **2. Match Secrets on Both Sides**

The **Shared Secret** in VMaster MUST match the secret in your SSTP server's `/etc/accel-ppp.conf`:

**VMaster GUI:**
```
Shared Secret: MySecretKey123
```

**SSTP Server (`/etc/accel-ppp.conf`):**
```ini
[radius]
server=VMASTER_IP,MySecretKey123,auth-port=1812,acct-port=1813
```

If they don't match → Authentication will fail!

### **3. Firewall Rules**

Ensure VMaster firewall allows RADIUS ports from your SSTP servers:

```bash
sudo ufw allow from SSTP_SERVER_IP to any port 1812 proto udp
sudo ufw allow from SSTP_SERVER_IP to any port 1813 proto udp
```

### **4. Test After Adding**

Always test authentication after adding a new server:

```bash
# On SSTP server
radtest testuser testpass VMASTER_IP 1812 YourSharedSecret
```

---

## 🐛 **Troubleshooting**

### **Issue: "Cannot connect to RADIUS database"**

**Cause:** RADIUS database is not running or not accessible.

**Fix:**
```bash
docker ps | grep radius
docker exec vmaster_radius_db mysql -uroot -prootpassword -e "SELECT 1;"
```

### **Issue: "This server IP already exists"**

**Cause:** You're trying to add a server that's already in the database.

**Fix:** Edit the existing server instead of adding a new one.

### **Issue: Changes not taking effect**

**Cause:** FreeRADIUS wasn't restarted.

**Fix:**
```bash
sudo systemctl restart freeradius
sudo systemctl status freeradius
```

### **Issue: "Server is not reachable" when testing**

**Causes:**
1. Wrong IP address
2. Server is down
3. Firewall blocking ping (ICMP)

**Fix:**
```bash
# Test manually
ping -c 3 SSTP_SERVER_IP

# Check if server is running
ssh ubuntu@SSTP_SERVER_IP "systemctl status accel-ppp"
```

### **Issue: RADIUS authentication fails**

**Causes:**
1. Shared secret mismatch
2. FreeRADIUS not restarted
3. Firewall blocking ports 1812/1813
4. User doesn't exist in RADIUS database

**Fix:**
```bash
# Check FreeRADIUS logs
sudo journalctl -u freeradius -f

# Test authentication
radtest USERNAME PASSWORD VMASTER_IP 1812 SECRET

# Check user exists
docker exec vmaster_radius_db mysql -uroot -prootpassword radius \
  -e "SELECT * FROM radcheck WHERE username='USERNAME';"
```

---

## 🎯 **Common Scenarios**

### **Scenario 1: Adding Your First SSTP Server**

1. Click **➕ Add SSTP Server**
2. Enter:
   - Server IP: `103.117.149.112`
   - Server Name: `sstp-main`
   - Shared Secret: `testing123`
   - Description: `Main SSTP server`
3. Save
4. SSH to VMaster: `sudo systemctl restart freeradius`
5. Configure SSTP server's `/etc/accel-ppp.conf`
6. Test: `radtest testuser testpass VMASTER_IP 1812 testing123`

### **Scenario 2: Adding Multiple SSTP Servers**

**Example: 3 SSTP servers in different locations**

| Server IP | Name | Secret | Description |
|-----------|------|--------|-------------|
| `103.117.149.112` | `sstp-singapore` | `SG_Secret_2025` | Singapore datacenter |
| `45.76.123.45` | `sstp-usa` | `US_Secret_2025` | USA datacenter |
| `139.180.200.100` | `sstp-europe` | `EU_Secret_2025` | Europe datacenter |

Add each one through the GUI, then restart FreeRADIUS once.

### **Scenario 3: Changing a Shared Secret**

1. Click **✏️ Edit** on the server
2. Change **Shared Secret** to new value
3. Save
4. Restart FreeRADIUS: `sudo systemctl restart freeradius`
5. Update SSTP server's `/etc/accel-ppp.conf` with new secret
6. Restart SSTP: `sudo systemctl restart accel-ppp`

### **Scenario 4: Removing an Old Server**

1. Click **🗑️ Delete** on the server
2. Confirm deletion
3. Restart FreeRADIUS: `sudo systemctl restart freeradius`
4. ✅ Old server can no longer authenticate users

---

## 📱 **Mobile Friendly**

The interface is fully responsive and works on:
- 💻 Desktop
- 📱 Tablet
- 📱 Mobile phones

---

## 🔄 **Workflow Example**

### **Complete Setup Flow:**

```
1. Install FreeRADIUS on VMaster
   ↓
2. Login to VMaster Admin Panel
   ↓
3. Go to "RADIUS Clients" tab
   ↓
4. Add SSTP server (IP + Secret)
   ↓
5. Restart FreeRADIUS
   ↓
6. Configure SSTP server's accel-ppp.conf
   ↓
7. Restart SSTP service
   ↓
8. Create VPN account in VMaster
   ↓
9. User connects to SSTP
   ↓
10. SSTP asks RADIUS "Is user valid?"
    ↓
11. RADIUS checks database
    ↓
12. User connected! ✅
```

---

## 💡 **Tips & Tricks**

### **Tip 1: Use Naming Convention**

Format: `sstp-{location}-{purpose}`

Examples:
- `sstp-singapore-main`
- `sstp-usa-backup`
- `sstp-europe-test`

### **Tip 2: Document Everything**

Use the Description field to note:
```
Location: Singapore DC
Installed: 2025-01-15
Contact: john@example.com
IP Pool: 10.0.0.0/24
Max Users: 100
```

### **Tip 3: Test Before Production**

Add a test server first, verify everything works, then add production servers.

### **Tip 4: Keep Secrets Secure**

- Don't share secrets in emails
- Use password managers
- Rotate secrets regularly
- Use different secrets for each server

### **Tip 5: Monitor Regularly**

Check the RADIUS Clients page weekly to:
- Verify all servers are listed
- Test connectivity
- Update descriptions
- Remove unused servers

---

## 🎉 **Benefits of Using the GUI**

### **Before (Manual Method):**
- ❌ SSH to server
- ❌ Edit config files
- ❌ Risk of syntax errors
- ❌ Need to remember SQL commands
- ❌ Time-consuming

### **After (With GUI):**
- ✅ Click and type
- ✅ Visual interface
- ✅ Automatic validation
- ✅ No SQL knowledge needed
- ✅ Fast and easy!

---

## 📚 **Related Documentation**

- **FreeRADIUS Installation:** `scripts/install-freeradius.sh`
- **SSTP Configuration:** `CONFIGURE_SSTP_RADIUS.md`
- **FAQ:** `FREERADIUS_FAQ.md`

---

## ❓ **FAQ**

### **Q: Can I add non-SSTP servers?**
**A:** Yes! You can add any RADIUS client (Cisco, MikroTik, etc.). Just select the appropriate type.

### **Q: How many servers can I add?**
**A:** Unlimited! Add as many as you need.

### **Q: Can multiple admins use this?**
**A:** Yes! All admins can access and manage RADIUS clients.

### **Q: What if I forget a shared secret?**
**A:** Click "Show" next to the secret in the table to reveal it.

### **Q: Can I export the list?**
**A:** Currently no, but you can view all servers in the table. Export feature coming soon!

### **Q: Does this work with Docker?**
**A:** Yes! It connects to the RADIUS database in Docker automatically.

---

## ✅ **Success Checklist**

- [ ] FreeRADIUS installed on VMaster
- [ ] Can access RADIUS Clients page
- [ ] Added first SSTP server via GUI
- [ ] Restarted FreeRADIUS
- [ ] Configured SSTP server's accel-ppp.conf
- [ ] Tested with `radtest` command
- [ ] Created VPN account in VMaster
- [ ] User successfully connected via SSTP
- [ ] RADIUS authentication working! 🎉

---

## 🚀 **You're All Set!**

Managing RADIUS clients is now as easy as clicking a few buttons!

**No more SSH, no more config files, no more SQL commands!** 🎉
