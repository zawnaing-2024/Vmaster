# VMaster Commands Cheatsheet

Quick reference for all common operations.

---

## 🚀 GitHub Operations

### Push to GitHub (First Time)
```bash
git push -u origin main
git push origin --tags
```

### Update GitHub (After Changes)
```bash
git add .
git commit -m "Your update message"
git push origin main
```

### Create New Version
```bash
echo "1.0.1" > VERSION
git add VERSION CHANGELOG.md
git commit -m "Version 1.0.1"
git tag v1.0.1
git push origin main --tags
```

---

## 🐳 Docker Operations

### Start VMaster
```bash
# Development
docker-compose up -d

# Production
docker-compose -f docker-compose.prod.yml up -d
```

### Stop VMaster
```bash
docker-compose down
```

### Restart Service
```bash
docker-compose restart web
```

### View Logs
```bash
docker logs -f vmaster_web
docker logs vmaster_db
```

### Rebuild Containers
```bash
docker-compose down
docker-compose up -d --build
```

---

## 💾 Database Operations

### Check VMaster Database
```bash
docker exec vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal \
  -e "SELECT * FROM customers"
```

### Check RADIUS Database
```bash
docker exec radius_db mysql -uradius -pradiuspass radius \
  -e "SELECT * FROM radcheck"
```

### Backup Databases
```bash
./scripts/backup.sh
```

### Restore Database
```bash
docker exec -i vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal < backup.sql
```

### Import Schema
```bash
docker exec -i vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal < database/schema.sql
```

---

## 🔄 Update Operations

### Quick Update (Production)
```bash
cd /var/www/vmaster
./scripts/quick-update.sh
```

### Manual Update
```bash
./scripts/backup.sh
git pull origin main
docker-compose up -d --build
docker exec vmaster_web php /var/www/html/admin/run-migration.php
```

### Check for Updates
```bash
git fetch origin
git log HEAD..origin/main --oneline
```

---

## 🔍 Monitoring

### Check Container Status
```bash
docker-compose ps
docker ps
```

### Check RADIUS Users
```bash
# Quick count
docker exec radius_db mysql -uradius -pradiuspass radius \
  -e "SELECT COUNT(*) FROM radcheck"

# List all users
docker exec radius_db mysql -uradius -pradiuspass radius \
  -e "SELECT id, username, value FROM radcheck"
```

### Check VPN Accounts
```bash
docker exec vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal \
  -e "SELECT id, account_username, server_id, created_at FROM vpn_accounts ORDER BY id DESC LIMIT 10"
```

### Check Activity Logs
```bash
docker exec vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal \
  -e "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 20"
```

---

## 🛠️ Troubleshooting

### View All Logs
```bash
docker logs vmaster_web --tail 100
tail -f /var/log/nginx/error.log
```

### Restart Everything
```bash
docker-compose down
docker-compose up -d
```

### Fix Permissions
```bash
chmod -R 755 uploads/
chown -R www-data:www-data uploads/
```

### Reset Admin Password
```bash
docker exec vmaster_web php /var/www/html/admin/reset-admin.php
```

### Check RADIUS Connection
```bash
docker exec vmaster_web php -r "
require_once '/var/www/html/config/radius.php';
echo getRadiusConnection() ? 'Connected' : 'Failed';
"
```

---

## 🔐 Security Operations

### Change Database Password
```bash
docker exec vmaster_db mysql -uroot -pOLDPASS -e "
ALTER USER 'root'@'%' IDENTIFIED BY 'NEWPASS';
FLUSH PRIVILEGES;"
```

### Update SSL Certificate
```bash
certbot renew
systemctl reload nginx
```

### Check Firewall
```bash
ufw status
ufw allow 80/tcp
ufw allow 443/tcp
```

---

## 📊 Useful Queries

### Customer Statistics
```bash
docker exec vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal -e "
SELECT 
    c.company_name,
    COUNT(DISTINCT ca.id) as clients,
    COUNT(DISTINCT va.id) as vpn_accounts
FROM customers c
LEFT JOIN client_accounts ca ON c.id = ca.customer_id
LEFT JOIN vpn_accounts va ON c.id = va.customer_id
GROUP BY c.id
"
```

### Server Usage
```bash
docker exec vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal -e "
SELECT 
    server_name,
    server_type,
    current_accounts,
    max_accounts,
    status
FROM vpn_servers
"
```

### RADIUS Active Connections
```bash
docker exec radius_db mysql -uradius -pradiuspass radius -e "
SELECT username, nasipaddress, acctstarttime 
FROM radacct 
WHERE acctstoptime IS NULL
"
```

---

## 🎯 Daily Operations

### Morning Checklist
```bash
# Check all services
docker-compose ps

# Check disk space
df -h

# Check logs for errors
docker logs vmaster_web --since 24h | grep -i error

# Check RADIUS users
docker exec radius_db mysql -uradius -pradiuspass radius -e "SELECT COUNT(*) FROM radcheck"
```

### Weekly Tasks
```bash
# Check for updates
git fetch origin && git log HEAD..origin/main

# Review backups
ls -lh /var/backups/vmaster/ | tail -10

# Clean old logs
docker system prune -f
```

---

## 🔄 Common Workflows

### Add New Customer
1. Login to admin: `https://your-domain.com/admin/login.php`
2. Go to: Customers → Add Customer
3. Fill form and set limits
4. Customer can login and manage VPN accounts

### Enable RADIUS
1. Edit: `nano config/radius.php`
2. Set: `RADIUS_ENABLED = true`
3. Restart: `docker-compose restart web`
4. Configure SoftEther to use RADIUS

### Create VPN Server
1. Login to admin
2. Go to: VPN Servers → Add Server
3. Enter server details
4. Test connection

---

## 📞 Emergency Commands

### Quick Rollback
```bash
git reset --hard HEAD~1
docker-compose down
docker-compose up -d
```

### Restore from Backup
```bash
docker exec -i vmaster_db mysql -uroot -prootpassword vpn_cms_portal < backup.sql
docker-compose restart
```

### Force Container Rebuild
```bash
docker-compose down
docker-compose up -d --build --force-recreate
```

---

**Bookmark this page for quick reference! 🔖**

