# VMaster Production Deployment on Ubuntu 22.04

Complete guide for deploying VMaster CMS to Ubuntu 22.04 server with Docker.

---

## 🎯 Overview

This guide will help you:
- Set up Ubuntu 22.04 server
- Install Docker and Docker Compose
- Deploy VMaster with SSL
- Configure for production
- Set up automatic updates
- Monitor the system

**Estimated time:** 30-45 minutes

---

## 📋 Prerequisites

- Ubuntu 22.04 server (VPS or dedicated)
- Root or sudo access
- Domain name pointing to your server
- Minimum 2GB RAM, 20GB storage
- SSH access to server

---

## Step 1: Server Preparation (5 minutes)

### Update Ubuntu

```bash
# SSH into your server
ssh root@your-server-ip

# Update system
apt update && apt upgrade -y

# Install basic tools
apt install -y curl git wget nano ufw
```

### Configure Firewall

```bash
# Allow SSH, HTTP, HTTPS
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 8080/tcp  # phpMyAdmin (optional, can remove later)

# Enable firewall
ufw --force enable

# Check status
ufw status
```

---

## Step 2: Install Docker (5 minutes)

```bash
# Install Docker
curl -fsSL https://get.docker.com | sh

# Start Docker
systemctl start docker
systemctl enable docker

# Install Docker Compose
apt install -y docker-compose

# Verify installation
docker --version
docker-compose --version

# Add your user to docker group (if not root)
usermod -aG docker $USER
newgrp docker
```

---

## Step 3: Clone VMaster (2 minutes)

```bash
# Create directory
mkdir -p /var/www
cd /var/www

# Clone from GitHub
git clone https://github.com/zawnaing-2024/Vmaster.git vmaster
cd vmaster

# Check version
cat VERSION
# Should show: 1.0.0
```

---

## Step 4: Production Configuration (5 minutes)

### Create Production Environment File

```bash
# Create .env file
nano .env.production
```

Add this content:
```env
# Database Configuration
DB_HOST=db
DB_NAME=vpn_cms_portal
DB_USER=vmaster_user
DB_PASS=YOUR_STRONG_PASSWORD_HERE

# RADIUS Database (if using)
RADIUS_DB_HOST=radius-db
RADIUS_DB_NAME=radius
RADIUS_DB_USER=radius_user
RADIUS_DB_PASS=YOUR_STRONG_RADIUS_PASSWORD

# MySQL Root Password
MYSQL_ROOT_PASSWORD=YOUR_STRONG_ROOT_PASSWORD

# Application
APP_ENV=production
APP_DEBUG=false
SITE_URL=https://your-domain.com

# Security
SESSION_LIFETIME=7200
```

### Update Configuration Files

```bash
# Edit database config for production
nano config/database.php
```

Change error reporting to 0 for production:
```php
error_reporting(0);
ini_set('display_errors', 0);
```

```bash
# Edit main config
nano config/config.php
```

Update site URL:
```php
define('SITE_URL', 'https://your-domain.com');
```

---

## Step 5: Production Docker Compose (3 minutes)

### Create Production Docker Compose

```bash
nano docker-compose.prod.yml
```

Content:
```yaml
version: '3.8'

services:
  web:
    build: .
    container_name: vmaster_web
    restart: always
    ports:
      - "8000:80"
    volumes:
      - ./:/var/www/html
      - ./uploads:/var/www/html/uploads
    depends_on:
      - db
      - radius-db
    networks:
      - vmaster-network
    environment:
      - DB_HOST=db
      - DB_NAME=vpn_cms_portal
      - DB_USER=${DB_USER}
      - DB_PASS=${DB_PASS}
      - RADIUS_DB_HOST=radius-db
    env_file:
      - .env.production

  db:
    image: mysql:8.0
    container_name: vmaster_db
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      MYSQL_DATABASE: vpn_cms_portal
      MYSQL_USER: ${DB_USER}
      MYSQL_PASSWORD: ${DB_PASS}
    ports:
      - "127.0.0.1:3306:3306"
    volumes:
      - vmaster_db_data:/var/lib/mysql
      - ./database/schema.sql:/docker-entrypoint-initdb.d/01-schema.sql
    networks:
      - vmaster-network
    command: --default-authentication-plugin=mysql_native_password

  radius-db:
    image: mysql:8.0
    container_name: vmaster_radius_db
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      MYSQL_DATABASE: radius
      MYSQL_USER: ${RADIUS_DB_USER}
      MYSQL_PASSWORD: ${RADIUS_DB_PASS}
    ports:
      - "127.0.0.1:3307:3306"
    volumes:
      - vmaster_radius_data:/var/lib/mysql
      - ./radius/schema.sql:/docker-entrypoint-initdb.d/schema.sql
    networks:
      - vmaster-network
    command: --default-authentication-plugin=mysql_native_password

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    container_name: vmaster_phpmyadmin
    restart: always
    environment:
      PMA_ARBITRARY: 1
      PMA_HOST: db
      PMA_PORT: 3306
    ports:
      - "127.0.0.1:8080:80"
    depends_on:
      - db
      - radius-db
    networks:
      - vmaster-network

networks:
  vmaster-network:
    driver: bridge

volumes:
  vmaster_db_data:
  vmaster_radius_data:
```

---

## Step 6: Install Nginx Reverse Proxy (5 minutes)

```bash
# Install Nginx
apt install -y nginx

# Create Nginx configuration
nano /etc/nginx/sites-available/vmaster
```

Add this content:
```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com www.your-domain.com;
    
    # SSL certificates (will add after Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    
    # SSL settings
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # Logs
    access_log /var/log/nginx/vmaster_access.log;
    error_log /var/log/nginx/vmaster_error.log;
    
    # Proxy to Docker container
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
    
    # phpMyAdmin (optional, for admin only)
    location /phpmyadmin {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        
        # Restrict access (optional)
        allow your-admin-ip;
        deny all;
    }
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Max upload size
    client_max_body_size 50M;
}
```

```bash
# Enable site
ln -s /etc/nginx/sites-available/vmaster /etc/nginx/sites-enabled/

# Remove default site
rm /etc/nginx/sites-enabled/default

# Test configuration
nginx -t
```

---

## Step 7: Install SSL Certificate (3 minutes)

```bash
# Install Certbot
apt install -y certbot python3-certbot-nginx

# Get SSL certificate
certbot --nginx -d your-domain.com -d www.your-domain.com

# Test auto-renewal
certbot renew --dry-run
```

After SSL is installed, update Nginx config and reload:
```bash
systemctl reload nginx
```

---

## Step 8: Deploy VMaster (5 minutes)

```bash
cd /var/www/vmaster

# Create uploads directory
mkdir -p uploads/qrcodes
chmod -R 755 uploads
chown -R www-data:www-data uploads

# Start containers
docker-compose -f docker-compose.prod.yml up -d

# Wait for databases to initialize
sleep 30

# Import databases (first time only)
docker exec -i vmaster_db mysql -uroot -p${MYSQL_ROOT_PASSWORD} vpn_cms_portal < database/schema.sql
docker exec -i vmaster_radius_db mysql -uradius -p${RADIUS_DB_PASS} radius < radius/schema.sql

# Create admin user
docker exec vmaster_web php /var/www/html/admin/reset-admin.php

# Check all containers are running
docker-compose -f docker-compose.prod.yml ps
```

---

## Step 9: Security Hardening (5 minutes)

### Secure Database Ports

```bash
# Edit docker-compose.prod.yml
# Change database ports to bind to localhost only:
# ports:
#   - "127.0.0.1:3306:3306"  # Only accessible from localhost
```

### Secure phpMyAdmin

```bash
# Option 1: Disable phpMyAdmin in production
docker-compose -f docker-compose.prod.yml stop phpmyadmin

# Option 2: Restrict by IP in Nginx (see Step 6)

# Option 3: Use strong password
# Access only via SSH tunnel:
# ssh -L 8080:localhost:8080 user@your-server
```

### Disable Debug Mode

```bash
nano config/config.php
```

Set:
```php
error_reporting(0);
ini_set('display_errors', 0);
```

### Secure File Permissions

```bash
chmod -R 755 /var/www/vmaster
chown -R www-data:www-data /var/www/vmaster/uploads
chmod 600 config/*.php
```

---

## Step 10: Enable RADIUS (Optional) (2 minutes)

If using RADIUS for SSTP/V2Ray automation:

```bash
# Edit RADIUS config
nano config/radius.php
```

Change:
```php
define('RADIUS_ENABLED', true);
```

Then configure your SoftEther VPN server to use RADIUS (see RADIUS_SSTP_SETUP_GUIDE.md).

---

## Step 11: Verify Deployment (5 minutes)

### Access Tests

```bash
# Test from server
curl -I https://your-domain.com

# Test admin login
curl https://your-domain.com/admin/login.php

# Test customer login
curl https://your-domain.com/customer/login.php
```

### Functional Tests

1. **Admin Panel**
   - Visit: https://your-domain.com/admin/login.php
   - Login with: admin / admin123
   - Change password immediately!
   - Add a VPN server
   - Create a customer

2. **Customer Portal**
   - Visit: https://your-domain.com/customer/login.php
   - Login with customer credentials
   - Create a client
   - Create a VPN account

3. **RADIUS (if enabled)**
   - Visit: https://your-domain.com/admin/radius-management.php
   - Check RADIUS users
   - Verify authentication

---

## 🔄 Easy Update System

### Method 1: Using Quick Update Script

```bash
cd /var/www/vmaster
./scripts/quick-update.sh
```

This will:
- Backup database automatically
- Pull latest code from GitHub
- Update Docker containers
- Run migrations
- Verify deployment

### Method 2: Manual Update

```bash
# Backup
./scripts/backup.sh

# Pull updates
git pull origin main

# Update containers
docker-compose -f docker-compose.prod.yml up -d --build

# Run migrations
docker exec vmaster_web php /var/www/html/admin/run-migration.php

# Verify
docker-compose -f docker-compose.prod.yml ps
```

---

## 📊 Monitoring

### Setup Log Monitoring

```bash
# Watch application logs
docker logs -f vmaster_web

# Watch Nginx logs
tail -f /var/log/nginx/vmaster_access.log
tail -f /var/log/nginx/vmaster_error.log

# Watch database logs
docker logs -f vmaster_db
```

### Setup Automated Backups

```bash
# Create cron job
crontab -e
```

Add:
```cron
# Daily backup at 2 AM
0 2 * * * /var/www/vmaster/scripts/backup.sh >> /var/log/vmaster-backup.log 2>&1

# Weekly update check (Sunday 3 AM)
0 3 * * 0 cd /var/www/vmaster && git fetch origin && git log HEAD..origin/main --oneline > /tmp/vmaster-updates.txt
```

### Monitor Docker Containers

```bash
# Install monitoring tool
docker run -d \
  --name watchtower \
  --restart always \
  -v /var/run/docker.sock:/var/run/docker.sock \
  containrrr/watchtower \
  --interval 86400  # Check daily
```

---

## 🔐 Post-Deployment Security

### 1. Change Default Admin Password

```bash
# Visit admin panel
https://your-domain.com/admin/login.php

# Login with: admin / admin123
# Go to: Change Password
# Set strong password immediately!
```

### 2. Secure Database Passwords

```bash
# Change root password
docker exec vmaster_db mysql -uroot -p${MYSQL_ROOT_PASSWORD} -e "
ALTER USER 'root'@'%' IDENTIFIED BY 'new-super-strong-password';
FLUSH PRIVILEGES;
"

# Update .env.production with new password
```

### 3. Setup Fail2Ban

```bash
# Install Fail2Ban
apt install -y fail2ban

# Create Nginx jail
nano /etc/fail2ban/jail.local
```

Add:
```ini
[nginx-http-auth]
enabled = true
filter = nginx-http-auth
port = http,https
logpath = /var/log/nginx/vmaster_error.log
maxretry = 5
bantime = 3600
```

```bash
# Restart Fail2Ban
systemctl restart fail2ban

# Check status
fail2ban-client status
```

---

## 📦 Backup Strategy

### Automated Daily Backups

```bash
# Create backup directory
mkdir -p /var/backups/vmaster

# Edit backup script
nano /var/www/vmaster/scripts/backup.sh
```

Update BACKUP_DIR to `/var/backups/vmaster`

```bash
# Make executable
chmod +x /var/www/vmaster/scripts/backup.sh

# Test backup
/var/www/vmaster/scripts/backup.sh
```

### Backup to Remote Server

```bash
# Using rsync to backup server
rsync -avz /var/backups/vmaster/ user@backup-server:/backups/vmaster/

# Add to cron for daily remote backup
0 4 * * * rsync -avz /var/backups/vmaster/ user@backup-server:/backups/vmaster/ >> /var/log/vmaster-remote-backup.log 2>&1
```

---

## 🔄 Update Workflow

### Regular Updates (Weekly/Monthly)

```bash
# 1. Check for updates
cd /var/www/vmaster
git fetch origin
git log HEAD..origin/main --oneline

# 2. If updates available
./scripts/quick-update.sh

# 3. Verify
docker-compose -f docker-compose.prod.yml ps
curl -I https://your-domain.com
```

### Emergency Hotfix

```bash
# Quick hotfix deploy
cd /var/www/vmaster
git pull origin hotfix/critical-security-fix
docker-compose -f docker-compose.prod.yml restart web
```

---

## 📊 Production Checklist

Before going live:
- [ ] SSL certificate installed and working
- [ ] Firewall configured
- [ ] Default admin password changed
- [ ] Database passwords changed
- [ ] .env.production configured
- [ ] Debug mode disabled
- [ ] Backups automated
- [ ] Monitoring setup
- [ ] Domain DNS pointed correctly
- [ ] All containers running
- [ ] Test customer workflow
- [ ] Test VPN account creation
- [ ] RADIUS working (if enabled)
- [ ] Activity logs recording
- [ ] Uploads directory writable

---

## 🆘 Troubleshooting

### Container Won't Start

```bash
# Check logs
docker logs vmaster_web
docker logs vmaster_db

# Restart specific container
docker-compose -f docker-compose.prod.yml restart web

# Rebuild if needed
docker-compose -f docker-compose.prod.yml up -d --build --force-recreate
```

### Database Connection Failed

```bash
# Check database is running
docker ps | grep vmaster_db

# Test connection
docker exec vmaster_db mysqladmin ping -uroot -p${MYSQL_ROOT_PASSWORD}

# Check credentials in .env.production
cat .env.production | grep DB_
```

### SSL Certificate Issues

```bash
# Renew certificate
certbot renew

# Check expiration
certbot certificates

# Force renewal
certbot renew --force-renewal
```

### Site Not Accessible

```bash
# Check Nginx
systemctl status nginx
nginx -t

# Check Docker container
docker ps | grep vmaster_web

# Check firewall
ufw status
```

---

## 📈 Performance Optimization

### Enable PHP OPcache

```bash
# Edit Dockerfile
nano Dockerfile
```

Add before CMD:
```dockerfile
RUN docker-php-ext-install opcache
COPY docker-opcache.ini /usr/local/etc/php/conf.d/opcache.ini
```

Create `docker-opcache.ini`:
```ini
[opcache]
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

### MySQL Optimization

```bash
# Edit MySQL config
docker exec vmaster_db nano /etc/mysql/my.cnf
```

Add:
```ini
[mysqld]
max_connections = 200
innodb_buffer_pool_size = 512M
query_cache_size = 32M
```

---

## 🎯 Quick Commands Reference

```bash
# Start VMaster
docker-compose -f docker-compose.prod.yml up -d

# Stop VMaster
docker-compose -f docker-compose.prod.yml down

# View logs
docker logs -f vmaster_web

# Backup
./scripts/backup.sh

# Update
./scripts/quick-update.sh

# Check status
docker-compose -f docker-compose.prod.yml ps

# Restart specific service
docker-compose -f docker-compose.prod.yml restart web
```

---

## 📞 Support

If you encounter issues:
1. Check logs: `docker logs vmaster_web`
2. Review documentation
3. Check GitHub issues
4. Create new issue with logs

---

**Production deployment complete! Your VMaster is now live! 🚀**

Access: https://your-domain.com

