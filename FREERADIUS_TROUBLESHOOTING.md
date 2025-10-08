# 🔧 FreeRADIUS Docker Troubleshooting

Quick fixes for common FreeRADIUS container issues.

---

## ❌ **Issue: Container Keeps Restarting**

### **Error Message:**
```
Container is restarting, wait until the container is running
```

### **Cause:**
FreeRADIUS configuration error or missing dependencies.

### **Quick Fix:**

```bash
cd /var/www/vmaster
sudo bash scripts/fix-freeradius-docker.sh
```

This script will:
1. ✅ Stop problematic container
2. ✅ Create simplified working config
3. ✅ Fix permissions
4. ✅ Restart with proper settings

---

## 🔍 **Manual Debugging**

### **Step 1: Check Container Status**

```bash
docker ps -a | grep freeradius
```

**Look for:**
- Status: `Restarting` = Problem
- Status: `Up` = Good

### **Step 2: Check Logs**

```bash
docker logs vmaster_freeradius --tail 50
```

**Common errors:**

#### **Error: "Failed to open /etc/raddb/radiusd.conf"**
**Fix:**
```bash
cd /var/www/vmaster
sudo chmod -R 755 radius/config
docker-compose -f docker-compose.prod.yml restart freeradius
```

#### **Error: "Failed to connect to MySQL"**
**Fix:**
```bash
# Check if radius-db is running
docker ps | grep radius-db

# If not, start it
docker-compose -f /var/www/vmaster/docker-compose.prod.yml up -d radius-db

# Wait 10 seconds, then restart FreeRADIUS
sleep 10
docker-compose -f /var/www/vmaster/docker-compose.prod.yml restart freeradius
```

#### **Error: "Permission denied"**
**Fix:**
```bash
cd /var/www/vmaster
sudo chown -R 101:101 radius/
docker-compose -f docker-compose.prod.yml restart freeradius
```

#### **Error: "Address already in use"**
**Fix:**
```bash
# Check what's using port 1812
sudo netstat -ulnp | grep 1812

# If another FreeRADIUS is running
sudo systemctl stop freeradius
sudo systemctl disable freeradius

# Restart container
docker-compose -f /var/www/vmaster/docker-compose.prod.yml restart freeradius
```

### **Step 3: Test Configuration**

```bash
# Run FreeRADIUS in debug mode
docker run --rm -it \
  --network vmaster-network \
  -v /var/www/vmaster/radius/config:/etc/raddb \
  freeradius/freeradius-server:latest \
  radiusd -X
```

**Look for:**
- `Ready to process requests` = Good! ✅
- Error messages = Fix the config

Press `Ctrl+C` to exit.

---

## 🛠️ **Common Fixes**

### **Fix 1: Reset Configuration**

```bash
cd /var/www/vmaster
sudo rm -rf radius/config/*
sudo bash scripts/fix-freeradius-docker.sh
```

### **Fix 2: Rebuild Container**

```bash
cd /var/www/vmaster
docker-compose -f docker-compose.prod.yml down freeradius
docker-compose -f docker-compose.prod.yml up -d --build freeradius
```

### **Fix 3: Use Host Network Mode**

Edit `docker-compose.prod.yml`:

```yaml
freeradius:
  image: freeradius/freeradius-server:latest
  container_name: vmaster_freeradius
  restart: always
  network_mode: host  # Add this line
  volumes:
    - ./radius/config:/etc/raddb
    - ./radius/logs:/var/log/radius
```

Then:
```bash
docker-compose -f /var/www/vmaster/docker-compose.prod.yml up -d freeradius
```

### **Fix 4: Disable FreeRADIUS, Use System Installation**

If Docker keeps failing, use system installation instead:

```bash
# Stop and remove Docker container
docker-compose -f /var/www/vmaster/docker-compose.prod.yml down freeradius

# Install on system
sudo apt-get update
sudo apt-get install -y freeradius freeradius-mysql

# Configure
sudo bash scripts/install-freeradius.sh
```

---

## ✅ **Verification Steps**

### **1. Check Container is Running**

```bash
docker ps | grep freeradius
```

Expected:
```
vmaster_freeradius   Up 2 minutes   0.0.0.0:1812-1813->1812-1813/udp
```

### **2. Check Logs Show No Errors**

```bash
docker logs vmaster_freeradius --tail 20
```

Expected:
```
Ready to process requests
```

### **3. Test Database Connection**

```bash
docker exec vmaster_freeradius radclient -h
```

Should show radclient help (means FreeRADIUS tools work).

### **4. Test Authentication**

```bash
# Create test user
docker exec vmaster_radius_db mysql -uroot -prootpassword radius -e "
INSERT INTO radcheck (username, attribute, op, value) 
VALUES ('testuser', 'Cleartext-Password', ':=', 'testpass123')
ON DUPLICATE KEY UPDATE value='testpass123';"

# Test
docker exec vmaster_freeradius radtest testuser testpass123 localhost 1812 testing123

# Clean up
docker exec vmaster_radius_db mysql -uroot -prootpassword radius -e "
DELETE FROM radcheck WHERE username='testuser';"
```

Expected:
```
Received Access-Accept
```

---

## 📊 **Health Check Script**

Create this script to check FreeRADIUS health:

```bash
#!/bin/bash
echo "🔍 FreeRADIUS Health Check"
echo ""

echo "1. Container Status:"
docker ps | grep freeradius || echo "❌ Not running"

echo ""
echo "2. Recent Logs:"
docker logs vmaster_freeradius --tail 5

echo ""
echo "3. Database Connection:"
docker exec vmaster_freeradius ping -c 1 radius-db >/dev/null 2>&1 && echo "✅ Can reach database" || echo "❌ Cannot reach database"

echo ""
echo "4. Listening Ports:"
docker exec vmaster_freeradius netstat -ulnp 2>/dev/null | grep 181 || echo "❌ Not listening"

echo ""
echo "5. RADIUS Clients:"
docker exec vmaster_radius_db mysql -uroot -prootpassword radius -se "SELECT COUNT(*) FROM nas;" 2>/dev/null || echo "❌ Cannot query database"
```

Save as `/var/www/vmaster/scripts/check-freeradius-health.sh` and run:

```bash
chmod +x /var/www/vmaster/scripts/check-freeradius-health.sh
bash /var/www/vmaster/scripts/check-freeradius-health.sh
```

---

## 🆘 **Still Not Working?**

### **Collect Debug Info:**

```bash
cd /var/www/vmaster

echo "=== Container Status ===" > freeradius-debug.txt
docker ps -a | grep freeradius >> freeradius-debug.txt

echo "" >> freeradius-debug.txt
echo "=== Container Logs ===" >> freeradius-debug.txt
docker logs vmaster_freeradius >> freeradius-debug.txt 2>&1

echo "" >> freeradius-debug.txt
echo "=== Docker Compose Config ===" >> freeradius-debug.txt
cat docker-compose.prod.yml >> freeradius-debug.txt

echo "" >> freeradius-debug.txt
echo "=== RADIUS Config ===" >> freeradius-debug.txt
cat radius/config/radiusd.conf >> freeradius-debug.txt 2>&1

echo "" >> freeradius-debug.txt
echo "=== Database Status ===" >> freeradius-debug.txt
docker exec vmaster_radius_db mysql -uroot -prootpassword -e "SHOW DATABASES;" >> freeradius-debug.txt 2>&1

cat freeradius-debug.txt
```

Share the output for further assistance.

---

## 💡 **Prevention Tips**

1. **Always check logs after changes:**
   ```bash
   docker logs -f vmaster_freeradius
   ```

2. **Test config before restarting:**
   ```bash
   docker exec vmaster_freeradius radiusd -XC
   ```

3. **Keep backups:**
   ```bash
   tar -czf radius-config-backup-$(date +%Y%m%d).tar.gz radius/
   ```

4. **Monitor container health:**
   ```bash
   docker stats vmaster_freeradius
   ```

---

## 🚀 **Quick Recovery Commands**

```bash
# Nuclear option: Complete reset
cd /var/www/vmaster
docker-compose -f docker-compose.prod.yml down freeradius
sudo rm -rf radius/
git pull origin main
sudo bash scripts/setup-freeradius-docker.sh
```

This will give you a fresh start! ✅
