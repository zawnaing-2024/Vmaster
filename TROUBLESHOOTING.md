# 🔧 Troubleshooting Guide

## Common Issues and Solutions

### ✅ FIXED: PHP Code Showing Instead of Running

**Problem:** When accessing admin/customer login pages, raw PHP code is displayed in the browser instead of the rendered page.

**Cause:** Apache was not configured to process .php files through the PHP handler.

**Solution Applied:**
1. Added PHP handler configuration to `/etc/apache2/conf-available/docker-php.conf`
2. Updated Dockerfile to include this configuration permanently
3. Restarted Apache to apply changes

**Files Modified:**
- `.htaccess` - Fixed incorrect rewrite rules
- `Dockerfile` - Added PHP handler configuration

**How to Test:**
```bash
# Access admin login
open http://localhost:8080/admin/login.php

# You should see a login form, not PHP code
```

---

## Other Common Issues

### Issue: Container Won't Start

**Solution:**
```bash
# Check Docker logs
docker-compose logs web

# Rebuild containers
docker-compose down
docker-compose up -d --build
```

### Issue: Database Connection Error

**Solution:**
```bash
# Wait 30 seconds after starting (MySQL initialization time)
sleep 30

# Check MySQL logs
docker-compose logs db

# Restart MySQL
docker-compose restart db
```

### Issue: Port Already in Use

**Solution:**
Edit `docker-compose.yml` and change the port:
```yaml
ports:
  - "8090:80"  # Change from 8080 to 8090
```

### Issue: Permission Denied on Uploads

**Solution:**
```bash
# Fix permissions
docker exec vpn_cms_web chown -R www-data:www-data /var/www/html/uploads
docker exec vpn_cms_web chmod -R 755 /var/www/html/uploads
```

### Issue: Changes Not Reflecting

**Solution:**
```bash
# Restart web container
docker-compose restart web

# Clear browser cache
# In browser: Ctrl+Shift+R (hard refresh)
```

### Issue: Can't Login / Wrong Username or Password

**Solutions:**
1. Verify credentials:
   - Admin: username=`admin`, password=`admin123`
2. Check if you're on the correct login page:
   - Admin: `/admin/login.php`
   - Customer: `/customer/login.php`
3. Clear browser cookies
4. **Reset admin password** (if forgot):
   ```bash
   # Generate new password hash
   docker exec vpn_cms_web php -r "echo password_hash('admin123', PASSWORD_DEFAULT) . PHP_EOL;"
   
   # Copy the hash output, then update database (replace HASH_HERE with your hash)
   docker exec vpn_cms_db mysql -u root -proot_secure_password vpn_cms_portal -e "UPDATE admins SET password = 'HASH_HERE' WHERE username = 'admin';"
   ```
5. Check activity logs for errors

### Issue: .htaccess Not Working

**Solution:**
```bash
# Ensure mod_rewrite is enabled
docker exec vpn_cms_web a2enmod rewrite
docker exec vpn_cms_web apache2ctl restart
```

---

## Diagnostic Commands

### Check Container Status
```bash
docker ps
```

### View Live Logs
```bash
docker-compose logs -f web
docker-compose logs -f db
```

### Access Container Shell
```bash
docker exec -it vpn_cms_web bash
docker exec -it vpn_cms_db bash
```

### Test PHP Processing
```bash
curl http://localhost:8080/admin/login.php | head -20
```

### Check Apache Configuration
```bash
docker exec vpn_cms_web apache2ctl -S
docker exec vpn_cms_web apache2ctl -M
```

### Database Access
```bash
docker exec -it vpn_cms_db mysql -u root -p
# Password: root_secure_password
```

---

## Fresh Start

If all else fails, completely reset:

```bash
# Stop and remove everything
docker-compose down -v

# Remove images
docker rmi vpncmsportal-web

# Rebuild and restart
docker-compose up -d --build

# Wait for initialization
sleep 30

# Test
open http://localhost:8080/admin/login.php
```

---

## Verification Checklist

After fixing any issue, verify:

- [ ] Containers are running: `docker ps`
- [ ] Web accessible: http://localhost:8080
- [ ] Admin login works: http://localhost:8080/admin/login.php
- [ ] Customer login works: http://localhost:8080/customer/login.php
- [ ] No PHP errors in logs: `docker-compose logs web`
- [ ] Database accessible via phpMyAdmin: http://localhost:8081

---

## Getting Help

1. Check logs first: `docker-compose logs -f`
2. Review this troubleshooting guide
3. Check README.md for detailed documentation
4. Ensure Docker and Docker Compose are up to date

---

**Last Updated:** After fixing PHP processing issue

