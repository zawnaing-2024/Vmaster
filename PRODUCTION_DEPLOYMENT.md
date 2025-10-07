# 🚀 VMaster - Production Deployment Guide

Complete guide for deploying VMaster VPN Management System to production.

---

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Server Setup](#server-setup)
3. [Initial Deployment](#initial-deployment)
4. [Domain & SSL Configuration](#domain--ssl-configuration)
5. [Security Hardening](#security-hardening)
6. [Easy Updates](#easy-updates)
7. [Backup & Restore](#backup--restore)
8. [Monitoring](#monitoring)

---

## 🔧 Prerequisites

### Server Requirements:
- **OS**: Ubuntu 20.04+ / Debian 10+ / CentOS 8+
- **RAM**: Minimum 2GB (4GB recommended)
- **CPU**: 2+ cores
- **Storage**: 20GB+ SSD
- **Docker**: Version 20.10+
- **Docker Compose**: Version 2.0+

### Domain & DNS:
- Domain name (e.g., `vmaster.yourdomain.com`)
- DNS A record pointing to your server IP

---

## 🖥️ Server Setup

### 1. Install Docker & Docker Compose

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Start Docker
sudo systemctl start docker
sudo systemctl enable docker

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Verify installation
docker --version
docker-compose --version
```

### 2. Create Application User

```bash
# Create dedicated user
sudo useradd -m -s /bin/bash vmaster
sudo usermod -aG docker vmaster

# Switch to user
sudo su - vmaster
```

---

## 📦 Initial Deployment

### 1. Transfer Files to Server

**From your local machine:**

```bash
# Create archive (exclude unnecessary files)
tar -czf vmaster.tar.gz \
  --exclude='.git' \
  --exclude='*.md' \
  --exclude='uploads/*' \
  --exclude='.DS_Store' \
  .

# Transfer to server
scp vmaster.tar.gz vmaster@your-server-ip:/home/vmaster/

# Or use rsync for better performance
rsync -avz --exclude='.git' --exclude='*.md' \
  . vmaster@your-server-ip:/home/vmaster/vmaster-app/
```

**On the server:**

```bash
# Extract files
cd /home/vmaster
tar -xzf vmaster.tar.gz -C vmaster-app/
cd vmaster-app

# Set permissions
chmod +x start.sh stop.sh
chmod 755 -R uploads/
```

### 2. Configure Production Environment

```bash
# Create production docker-compose override
cat > docker-compose.prod.yml << 'EOF'
version: '3.8'

services:
  web:
    restart: always
    environment:
      - DB_HOST=db
      - DB_NAME=vpn_cms_portal
      - DB_USER=vpn_user
      - DB_PASS=${DB_PASSWORD}
    
  db:
    restart: always
    environment:
      - MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
      - MYSQL_DATABASE=vpn_cms_portal
      - MYSQL_USER=vpn_user
      - MYSQL_PASSWORD=${DB_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./backups:/backups
    
  phpmyadmin:
    restart: always
    ports:
      - "127.0.0.1:8081:80"  # Only accessible locally
EOF

# Create .env file with secure passwords
cat > .env << 'EOF'
DB_PASSWORD=CHANGE_THIS_TO_SECURE_PASSWORD
MYSQL_ROOT_PASSWORD=CHANGE_THIS_TO_SECURE_ROOT_PASSWORD
EOF

# Secure the .env file
chmod 600 .env

# IMPORTANT: Edit .env and set strong passwords!
nano .env
```

### 3. Start the Application

```bash
# Start with production config
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# Check status
docker-compose ps

# View logs
docker-compose logs -f web
```

---

## 🌐 Domain & SSL Configuration

### Option 1: Using Nginx Reverse Proxy with Let's Encrypt

#### 1. Install Nginx

```bash
sudo apt install nginx certbot python3-certbot-nginx -y
```

#### 2. Configure Nginx

```bash
sudo nano /etc/nginx/sites-available/vmaster
```

Add this configuration:

```nginx
server {
    listen 80;
    server_name vmaster.yourdomain.com;
    
    client_max_body_size 10M;
    
    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/vmaster /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

#### 3. Get SSL Certificate

```bash
# Get SSL certificate
sudo certbot --nginx -d vmaster.yourdomain.com

# Test auto-renewal
sudo certbot renew --dry-run
```

#### 4. Update Application URL

```bash
# Edit config
nano config/config.php

# Change:
define('SITE_URL', 'https://vmaster.yourdomain.com');
```

### Option 2: Using Traefik (Advanced)

See `TRAEFIK_SETUP.md` for Traefik configuration with automatic SSL.

---

## 🔒 Security Hardening

### 1. Change Default Admin Password

```bash
# Access the application
https://vmaster.yourdomain.com/admin/login.php

# Login with: admin / admin123
# Go to: Change Password
# Set a strong password
```

### 2. Firewall Configuration

```bash
# Install UFW
sudo apt install ufw -y

# Configure firewall
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Enable firewall
sudo ufw enable
sudo ufw status
```

### 3. Secure Database

```bash
# Access MySQL container
docker exec -it vpn_cms_db mysql -u root -p

# Run these commands:
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
FLUSH PRIVILEGES;
EXIT;
```

### 4. Disable phpMyAdmin in Production

```bash
# Edit docker-compose.prod.yml
# Comment out phpMyAdmin service or bind to localhost only
```

### 5. Set Proper File Permissions

```bash
chmod 600 .env
chmod 600 config/database.php
chmod 755 -R public/ admin/ customer/
chmod 777 -R uploads/
```

---

## 🔄 Easy Updates

### Method 1: Manual Update

```bash
# On your local machine - create update package
git pull  # or get latest changes
tar -czf vmaster-update.tar.gz \
  --exclude='uploads/*' \
  --exclude='database/*' \
  --exclude='.env' \
  --exclude='docker-compose.prod.yml' \
  .

# Transfer to server
scp vmaster-update.tar.gz vmaster@your-server-ip:/home/vmaster/

# On the server
cd /home/vmaster/vmaster-app

# Backup current version
tar -czf backup-$(date +%Y%m%d-%H%M%S).tar.gz .

# Extract update (keeping uploads and config)
tar -xzf ../vmaster-update.tar.gz --exclude='uploads/*'

# Restart services
docker-compose -f docker-compose.yml -f docker-compose.prod.yml restart web

# Check logs
docker-compose logs -f web
```

### Method 2: Git-Based Updates (Recommended)

```bash
# Initial setup on server
cd /home/vmaster
git clone https://github.com/yourusername/vmaster.git vmaster-app
cd vmaster-app

# Copy production configs
cp docker-compose.prod.yml.example docker-compose.prod.yml
cp .env.example .env
# Edit these files with production values

# To update in future:
cd /home/vmaster/vmaster-app
git pull origin main

# Restart
docker-compose -f docker-compose.yml -f docker-compose.prod.yml restart web
```

### Update Script

Create `update.sh`:

```bash
#!/bin/bash

echo "🔄 VMaster Update Script"
echo "======================="

# Backup
echo "📦 Creating backup..."
tar -czf backups/backup-$(date +%Y%m%d-%H%M%S).tar.gz \
  --exclude='backups/*' \
  --exclude='uploads/*' \
  .

# Pull changes
echo "📥 Pulling updates..."
git pull origin main

# Restart services
echo "🔄 Restarting services..."
docker-compose -f docker-compose.yml -f docker-compose.prod.yml restart web

echo "✅ Update complete!"
echo "📋 View logs: docker-compose logs -f web"
```

```bash
chmod +x update.sh
```

---

## 💾 Backup & Restore

### Automated Daily Backups

Create `/home/vmaster/backup.sh`:

```bash
#!/bin/bash

BACKUP_DIR="/home/vmaster/vmaster-app/backups"
DATE=$(date +%Y%m%d-%H%M%S)
KEEP_DAYS=7

mkdir -p $BACKUP_DIR

# Backup database
docker exec vpn_cms_db mysqldump -u root -p$(grep MYSQL_ROOT_PASSWORD .env | cut -d'=' -f2) vpn_cms_portal > $BACKUP_DIR/db-$DATE.sql

# Compress database backup
gzip $BACKUP_DIR/db-$DATE.sql

# Backup uploads
tar -czf $BACKUP_DIR/uploads-$DATE.tar.gz uploads/

# Remove old backups
find $BACKUP_DIR -name "db-*.sql.gz" -mtime +$KEEP_DAYS -delete
find $BACKUP_DIR -name "uploads-*.tar.gz" -mtime +$KEEP_DAYS -delete

echo "✅ Backup completed: $DATE"
```

```bash
chmod +x /home/vmaster/backup.sh

# Add to crontab
crontab -e

# Add this line (backup daily at 2 AM):
0 2 * * * /home/vmaster/backup.sh >> /home/vmaster/backup.log 2>&1
```

### Restore from Backup

```bash
# List backups
ls -lh backups/

# Restore database
gunzip backups/db-20250107-020000.sql.gz
docker exec -i vpn_cms_db mysql -u root -p vpn_cms_portal < backups/db-20250107-020000.sql

# Restore uploads
tar -xzf backups/uploads-20250107-020000.tar.gz
```

---

## 📊 Monitoring

### 1. Check Application Health

```bash
# View running containers
docker-compose ps

# Check logs
docker-compose logs -f web
docker-compose logs -f db

# Check disk space
df -h

# Check memory
free -h
```

### 2. Monitor Resource Usage

```bash
# Install htop
sudo apt install htop -y

# Monitor
htop

# Docker stats
docker stats
```

### 3. Set Up Alerts (Optional)

**Using UptimeRobot (Free):**
1. Sign up at https://uptimerobot.com
2. Add monitor for https://vmaster.yourdomain.com
3. Get email/SMS alerts if site goes down

---

## 🔐 Production Checklist

Before going live:

- [ ] Strong admin password set
- [ ] Database passwords changed from defaults
- [ ] SSL certificate installed
- [ ] Firewall configured
- [ ] Automated backups set up
- [ ] Domain DNS configured
- [ ] phpMyAdmin disabled or secured
- [ ] File permissions set correctly
- [ ] Error reporting disabled in production
- [ ] All Outline servers configured with real API URLs
- [ ] Tested VPN account creation
- [ ] Tested password change functionality
- [ ] Monitoring/alerts configured

---

## 🆘 Troubleshooting

### Application Not Accessible

```bash
# Check containers
docker-compose ps

# Check nginx
sudo systemctl status nginx
sudo nginx -t

# Check firewall
sudo ufw status

# Check logs
docker-compose logs web
tail -f /var/log/nginx/error.log
```

### Database Connection Issues

```bash
# Check database container
docker exec vpn_cms_db mysql -u root -p

# Check environment variables
docker exec vpn_cms_web env | grep DB_

# Restart database
docker-compose restart db
```

### SSL Certificate Issues

```bash
# Renew certificate
sudo certbot renew --force-renewal

# Check certificate
sudo certbot certificates
```

---

## 📞 Support

- **Documentation**: Check README.md and other guides
- **Logs**: Always check `docker-compose logs -f` first
- **Backups**: Ensure backups are working before making changes

---

## 🎯 Quick Reference Commands

```bash
# Start application
docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# Stop application  
docker-compose down

# Restart
docker-compose restart web

# View logs
docker-compose logs -f web

# Backup database
./backup.sh

# Update application
./update.sh

# Check status
docker-compose ps
```

---

**🎉 Your VMaster VPN Management System is now production-ready!**

