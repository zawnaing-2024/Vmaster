# 🐳 FreeRADIUS Docker Setup Guide

Run FreeRADIUS as a Docker container - cleaner, easier, and more manageable!

---

## 🎯 **Why Docker?**

### **Benefits:**

✅ **No system installation** - Runs in isolated container  
✅ **Easy updates** - Just pull new image  
✅ **Clean uninstall** - Remove container, done!  
✅ **Portable** - Same setup on any server  
✅ **Logs management** - Centralized in one folder  
✅ **Easy restart** - One command  

### **vs Traditional Installation:**

| Feature | Docker | Traditional |
|---------|--------|-------------|
| Installation | `docker-compose up` | `apt-get install` + config |
| Updates | Pull new image | `apt-get upgrade` |
| Uninstall | Remove container | `apt-get purge` + cleanup |
| Isolation | ✅ Isolated | ❌ System-wide |
| Portability | ✅ Portable | ❌ Server-specific |

---

## 🚀 **Quick Setup (One Command!)**

### **Step 1: Run Setup Script**

SSH to your VMaster server:

```bash
cd /var/www/vmaster
git pull origin main
chmod +x scripts/setup-freeradius-docker.sh
sudo bash scripts/setup-freeradius-docker.sh
```

**The script will:**
1. ✅ Pull latest code
2. ✅ Create RADIUS config files
3. ✅ Ask for your SSTP server IP
4. ✅ Add SSTP server to database
5. ✅ Start FreeRADIUS container
6. ✅ Configure firewall
7. ✅ Test authentication

**Time:** ~3 minutes  
**Manual work:** Just enter SSTP server IP and secret

---

### **Step 2: Configure SSTP Server**

On your SSTP server, edit `/etc/accel-ppp.conf`:

```ini
[modules]
radius

[radius]
server=YOUR_VMASTER_IP,testing123,auth-port=1812,acct-port=1813
nas-identifier=sstp-server
nas-ip-address=YOUR_SSTP_SERVER_IP
```

Restart SSTP:

```bash
sudo systemctl restart accel-ppp
```

---

### **Step 3: Test!**

```bash
# On VMaster server
docker exec vmaster_freeradius radtest testuser testpass localhost 1812 testing123
```

Expected: `Received Access-Accept` ✅

---

## 📁 **File Structure**

After setup, you'll have:

```
/var/www/vmaster/
├── docker-compose.prod.yml      ← FreeRADIUS service added
├── radius/
│   ├── config/                  ← FreeRADIUS configuration
│   │   ├── radiusd.conf
│   │   ├── mods-enabled/
│   │   │   ├── sql
│   │   │   └── pap
│   │   ├── sites-enabled/
│   │   │   └── default
│   │   ├── policy.d/
│   │   │   └── filter
│   │   └── mods-config/
│   │       └── sql/main/mysql/
│   │           └── queries.conf
│   └── logs/                    ← FreeRADIUS logs
│       └── radius.log
```

---

## 🔧 **Docker Commands**

### **Check Status**

```bash
docker ps | grep freeradius
```

Expected output:
```
vmaster_freeradius   Up 2 hours   0.0.0.0:1812-1813->1812-1813/udp
```

### **View Logs**

```bash
# Live logs
docker logs -f vmaster_freeradius

# Last 50 lines
docker logs vmaster_freeradius --tail 50

# Logs with timestamps
docker logs -t vmaster_freeradius
```

### **Restart Container**

```bash
cd /var/www/vmaster
docker-compose -f docker-compose.prod.yml restart freeradius
```

### **Stop Container**

```bash
docker-compose -f docker-compose.prod.yml stop freeradius
```

### **Start Container**

```bash
docker-compose -f docker-compose.prod.yml start freeradius
```

### **Rebuild Container**

```bash
docker-compose -f docker-compose.prod.yml up -d --build freeradius
```

### **Remove Container**

```bash
docker-compose -f docker-compose.prod.yml down freeradius
```

### **Execute Commands Inside Container**

```bash
# Test authentication
docker exec vmaster_freeradius radtest user pass localhost 1812 secret

# Check FreeRADIUS version
docker exec vmaster_freeradius radiusd -v

# Debug mode
docker exec -it vmaster_freeradius radiusd -X
```

---

## 🎨 **Managing RADIUS Clients (GUI)**

### **Add SSTP Servers via Web Interface**

1. Login to VMaster Admin Panel
2. Go to **🔐 RADIUS Clients**
3. Click **➕ Add SSTP Server**
4. Fill in details:
   - Server IP: `103.117.149.112`
   - Shared Secret: `testing123`
   - Description: `Main SSTP server`
5. Save
6. Restart FreeRADIUS:
   ```bash
   docker-compose -f /var/www/vmaster/docker-compose.prod.yml restart freeradius
   ```

**No SSH needed! All from web interface!** 🎉

---

## 🔍 **Monitoring**

### **Check Active Sessions**

```bash
docker exec vmaster_radius_db mysql -uroot -prootpassword radius -e "
SELECT username, nasipaddress, acctstarttime, 
       TIMESTAMPDIFF(MINUTE, acctstarttime, NOW()) as minutes_connected
FROM radacct 
WHERE acctstoptime IS NULL;"
```

### **Check Authentication Logs**

```bash
docker exec vmaster_radius_db mysql -uroot -prootpassword radius -e "
SELECT username, reply, authdate 
FROM radpostauth 
ORDER BY authdate DESC 
LIMIT 10;"
```

### **Check Container Resources**

```bash
docker stats vmaster_freeradius
```

---

## 🐛 **Troubleshooting**

### **Issue: Container won't start**

**Check logs:**
```bash
docker logs vmaster_freeradius
```

**Common causes:**
1. Port 1812/1813 already in use
2. Config file syntax error
3. Database connection failed

**Fix:**
```bash
# Check if ports are free
netstat -ulnp | grep 181

# Restart with fresh config
cd /var/www/vmaster
docker-compose -f docker-compose.prod.yml down freeradius
docker-compose -f docker-compose.prod.yml up -d freeradius
```

### **Issue: Cannot connect to database**

**Test database connection:**
```bash
docker exec vmaster_freeradius ping -c 3 radius-db
docker exec vmaster_radius_db mysql -uroot -prootpassword -e "SELECT 1;"
```

**Fix:**
```bash
# Restart both containers
docker-compose -f /var/www/vmaster/docker-compose.prod.yml restart radius-db freeradius
```

### **Issue: Authentication fails**

**Debug mode:**
```bash
# Stop container
docker-compose -f /var/www/vmaster/docker-compose.prod.yml stop freeradius

# Run in debug mode
docker run --rm -it \
  --network vmaster-network \
  -v /var/www/vmaster/radius/config:/etc/raddb \
  freeradius/freeradius-server:latest \
  radiusd -X
```

**Check:**
1. User exists in database
2. Shared secret matches
3. SSTP server IP is in `nas` table

### **Issue: Firewall blocking**

```bash
# Check firewall
sudo ufw status | grep 181

# Allow RADIUS ports
sudo ufw allow 1812/udp
sudo ufw allow 1813/udp
```

---

## 🔄 **Updates**

### **Update FreeRADIUS**

```bash
cd /var/www/vmaster
docker-compose -f docker-compose.prod.yml pull freeradius
docker-compose -f docker-compose.prod.yml up -d freeradius
```

### **Update Configuration**

```bash
# Edit config files
nano /var/www/vmaster/radius/config/radiusd.conf

# Restart to apply
docker-compose -f /var/www/vmaster/docker-compose.prod.yml restart freeradius
```

---

## 🗑️ **Uninstall**

### **Remove FreeRADIUS Container**

```bash
cd /var/www/vmaster
docker-compose -f docker-compose.prod.yml down freeradius
docker volume rm vmaster_radius_data
rm -rf radius/
```

**Your RADIUS database stays intact!**

---

## 📊 **Performance**

### **Resource Usage**

Typical FreeRADIUS container:
- **CPU:** < 1%
- **RAM:** ~50-100 MB
- **Disk:** ~200 MB

Very lightweight! ✅

### **Capacity**

Can handle:
- **1000+ concurrent users**
- **10,000+ auth requests/second**

More than enough for most VPN setups!

---

## 🔐 **Security**

### **Best Practices**

1. **Change default secret:**
   ```bash
   # Generate strong secret
   openssl rand -base64 32
   ```

2. **Restrict firewall:**
   ```bash
   # Only allow specific SSTP servers
   sudo ufw delete allow 1812/udp
   sudo ufw allow from SSTP_SERVER_IP to any port 1812 proto udp
   ```

3. **Monitor logs:**
   ```bash
   # Watch for failed auth attempts
   docker logs -f vmaster_freeradius | grep -i reject
   ```

4. **Regular updates:**
   ```bash
   # Update monthly
   docker-compose -f /var/www/vmaster/docker-compose.prod.yml pull freeradius
   docker-compose -f /var/www/vmaster/docker-compose.prod.yml up -d freeradius
   ```

---

## 📚 **Related Documentation**

- **GUI Management:** `RADIUS_GUI_GUIDE.md`
- **SSTP Configuration:** `CONFIGURE_SSTP_RADIUS.md`
- **FAQ:** `FREERADIUS_FAQ.md`

---

## ✅ **Success Checklist**

- [ ] FreeRADIUS container running
- [ ] Can view logs: `docker logs vmaster_freeradius`
- [ ] Firewall ports 1812/1813 open
- [ ] SSTP server added via GUI
- [ ] Test authentication passes
- [ ] SSTP server configured
- [ ] User can connect via SSTP
- [ ] Sessions logged in `radacct` table

---

## 🎉 **You're All Set!**

FreeRADIUS is now running in Docker!

**Advantages:**
- ✅ No system pollution
- ✅ Easy management
- ✅ Clean logs
- ✅ Simple updates
- ✅ Quick restart

**Enjoy your containerized RADIUS server!** 🐳🚀
