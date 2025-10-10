# VMaster CMS - Production Deployment Guide
## For Ubuntu Server with Docker

---

## 🚀 Quick Deployment (Automated)

### Option 1: One Command Deployment

```bash
cd /var/www/vmaster && bash scripts/deploy-to-production.sh
```

That's it! The script will:
- ✅ Backup database and code
- ✅ Pull latest from GitHub
- ✅ Apply database migrations
- ✅ Restart Docker containers
- ✅ Verify deployment

---

## 📋 Manual Deployment (Step by Step)

If you prefer to do it manually, follow these steps:

### Step 1: SSH to Your Ubuntu Server

```bash
ssh root@your-server-ip
# Or
ssh ubuntu@your-server-ip
```

### Step 2: Navigate to Project Directory

```bash
cd /var/www/vmaster
```

### Step 3: Backup Current State

```bash
# Backup database
mkdir -p /var/backups/vmaster
docker exec vmaster_db mysqldump -uroot -prootpassword vpn_cms_portal > /var/backups/vmaster/backup_$(date +%Y%m%d_%H%M%S).sql

# Backup code
tar -czf /var/backups/vmaster/code_backup_$(date +%Y%m%d_%H%M%S).tar.gz .
```

### Step 4: Pull Latest Code

```bash
git pull origin main
```

### Step 5: Apply Database Migrations

```bash
# Apply migrations in order
docker exec -i vmaster_db mysql -uroot -prootpassword vpn_cms_portal < database/add_plan_duration.sql
docker exec -i vmaster_db mysql -uroot -prootpassword vpn_cms_portal < database/add_customer_plan_duration_limit.sql
docker exec -i vmaster_db mysql -uroot -prootpassword vpn_cms_portal < database/add_customer_expiration.sql
```

**Note:** If you see errors like "Duplicate column" or "Column already exists", that's OK! It means the migration was already applied.

### Step 6: Restart Docker Containers

```bash
docker restart vmaster_web
docker restart vmaster_db  # Only if needed
```

### Step 7: Verify Deployment

```bash
# Check containers are running
docker ps

# Check web container logs
docker logs vmaster_web --tail 50

# Check database connection
docker exec vmaster_db mysql -uroot -prootpassword -e "SELECT 1;"
```

---

## 🔧 First Time Setup on Ubuntu Server

If this is your first deployment, follow these steps:

### 1. Install Docker & Docker Compose

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Install Docker Compose
sudo apt install docker-compose -y

# Add user to docker group (optional, to run without sudo)
sudo usermod -aG docker $USER
```

### 2. Clone Repository

```bash
# Create directory
sudo mkdir -p /var/www/vmaster
cd /var/www/vmaster

# Clone from GitHub
git clone https://github.com/zawnaing-2024/Vmaster.git .

# Or if already cloned, just pull
git pull origin main
```

### 3. Setup Configuration

```bash
# Copy environment file
cp config/config.example.php config/config.php

# Edit configuration
nano config/config.php
```

Update the following:
- Database credentials
- Site URL
- RADIUS settings (if using)

### 4. Start Docker Containers

```bash
# Start containers
docker-compose up -d

# Check status
docker ps
```

### 5. Initialize Database

```bash
# Create database
docker exec -i vmaster_db mysql -uroot -prootpassword -e "CREATE DATABASE IF NOT EXISTS vpn_cms_portal;"

# Import schema
docker exec -i vmaster_db mysql -uroot -prootpassword vpn_cms_portal < database/schema.sql

# Apply migrations
docker exec -i vmaster_db mysql -uroot -prootpassword vpn_cms_portal < database/add_plan_duration.sql
docker exec -i vmaster_db mysql -uroot -prootpassword vpn_cms_portal < database/add_customer_plan_duration_limit.sql
docker exec -i vmaster_db mysql -uroot -prootpassword vpn_cms_portal < database/add_customer_expiration.sql
```

### 6. Set Permissions

```bash
# Set ownership
sudo chown -R www-data:www-data /var/www/vmaster

# Set permissions
find /var/www/vmaster -type d -exec chmod 755 {} \;
find /var/www/vmaster -type f -exec chmod 644 {} \;
```

---

## 🔄 Regular Update Process

### When You Make Changes on Local:

1. **Commit and push to GitHub:**
   ```bash
   # On your local machine
   git add .
   git commit -m "Your changes description"
   git push origin main
   ```

2. **Deploy to production:**
   ```bash
   # On production server
   ssh root@your-server-ip
   cd /var/www/vmaster
   bash scripts/deploy-to-production.sh
   ```

That's it! ✅

---

## 📝 Important Files & Directories

```
/var/www/vmaster/              # Main application directory
├── admin/                      # Admin panel
├── customer/                   # Customer portal
├── database/                   # Database migrations
├── scripts/                    # Deployment scripts
├── config/                     # Configuration files
└── docker-compose.yml         # Docker configuration

/var/backups/vmaster/          # Backup directory
├── backup_YYYYMMDD_HHMMSS.sql     # Database backups
└── code_backup_YYYYMMDD_HHMMSS.tar.gz  # Code backups
```

---

## 🔍 Troubleshooting

### Problem: Git pull fails with conflicts

**Solution:**
```bash
# Stash local changes
git stash

# Pull updates
git pull origin main

# Reapply local changes if needed
git stash pop
```

### Problem: Containers won't start

**Solution:**
```bash
# Check container logs
docker logs vmaster_web
docker logs vmaster_db

# Restart Docker service
sudo systemctl restart docker

# Rebuild containers
docker-compose down
docker-compose up -d
```

### Problem: Database migration fails

**Solution:**
```bash
# Check if migration already applied
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "DESCRIBE customers;"

# If column exists, skip that migration
# Otherwise, try applying manually:
docker exec -it vmaster_db mysql -uroot -prootpassword vpn_cms_portal
```

### Problem: Permission denied errors

**Solution:**
```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/vmaster

# Fix permissions
sudo chmod -R 755 /var/www/vmaster
```

### Problem: Can't connect to site

**Solution:**
```bash
# Check if containers are running
docker ps

# Check firewall
sudo ufw status

# Allow HTTP/HTTPS if needed
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Check nginx/apache config
docker logs vmaster_web
```

---

## 🔐 Security Checklist

- ✅ Change default database passwords
- ✅ Use HTTPS (SSL certificate)
- ✅ Keep Docker updated
- ✅ Regular backups (automated)
- ✅ Firewall configured (UFW)
- ✅ SSH key authentication
- ✅ Disable root login via SSH
- ✅ Regular security updates

---

## 📊 Monitoring

### Check System Resources

```bash
# CPU and Memory usage
docker stats

# Disk usage
df -h

# Check logs
docker logs vmaster_web --tail 100 -f
```

### Automated Monitoring

Consider setting up:
- Uptime monitoring (UptimeRobot, Pingdom)
- Log aggregation (ELK Stack, Graylog)
- Server monitoring (Netdata, Grafana)

---

## 🔄 Backup Strategy

The deployment script automatically:
- Creates database backups before each deployment
- Keeps backups for 7 days
- Stores in `/var/backups/vmaster/`

### Manual Backup

```bash
# Full backup
docker exec vmaster_db mysqldump -uroot -prootpassword vpn_cms_portal > backup.sql

# Backup specific table
docker exec vmaster_db mysqldump -uroot -prootpassword vpn_cms_portal customers > customers_backup.sql
```

### Restore from Backup

```bash
# Restore database
docker exec -i vmaster_db mysql -uroot -prootpassword vpn_cms_portal < backup.sql
```

---

## 📞 Support

If you encounter issues:
1. Check logs: `docker logs vmaster_web`
2. Check database: `docker exec -it vmaster_db mysql -uroot -prootpassword`
3. Review this guide
4. Check GitHub issues

---

## 🎯 Quick Reference

### Most Common Commands

```bash
# Deploy updates
cd /var/www/vmaster && bash scripts/deploy-to-production.sh

# Check containers
docker ps

# View logs
docker logs vmaster_web --tail 50 -f

# Restart containers
docker restart vmaster_web

# Backup database
docker exec vmaster_db mysqldump -uroot -prootpassword vpn_cms_portal > backup.sql

# Apply migration
docker exec -i vmaster_db mysql -uroot -prootpassword vpn_cms_portal < database/migration.sql
```

---

## ✅ Deployment Checklist

Before deployment:
- [ ] Changes committed to GitHub
- [ ] Database migrations tested locally
- [ ] Backup current production state

During deployment:
- [ ] Pull latest code
- [ ] Apply database migrations
- [ ] Restart containers
- [ ] Verify site is accessible

After deployment:
- [ ] Test critical features
- [ ] Check logs for errors
- [ ] Monitor for issues

---

**Last Updated:** October 2025
**Version:** 2.0

