# VMaster Update Guide - Zero Downtime Updates

This guide shows you how to update VMaster in production without affecting your customers.

---

## 🎯 Update Strategy Overview

VMaster uses a **rolling update strategy** with these principles:

1. **Always backup first** - Database and files
2. **Test updates locally** - Never update production directly
3. **Quick rollback** - If something goes wrong
4. **Zero downtime** - Keep services running during update
5. **Database migrations** - Run automatically

---

## 📋 Update Process (Step by Step)

### Step 1: Backup Everything (2 minutes)

```bash
# Go to your production directory
cd /var/www/vmaster

# Run backup script
./scripts/backup.sh

# Or manual backup:
docker exec vpn_cms_db mysqldump -uroot -prootpassword vpn_cms_portal > backup_$(date +%Y%m%d_%H%M%S).sql
docker exec radius_db mysqldump -uradius -pradiuspass radius > backup_radius_$(date +%Y%m%d_%H%M%S).sql

# Backup uploaded files
tar -czf backup_uploads_$(date +%Y%m%d_%H%M%S).tar.gz uploads/
```

---

### Step 2: Pull Latest Code (30 seconds)

```bash
# Fetch updates from GitHub
git fetch origin

# Check what's new
git log HEAD..origin/main --oneline

# See changed files
git diff HEAD..origin/main --name-only

# Pull the updates
git pull origin main
```

---

### Step 3: Update Docker Containers (1 minute)

```bash
# Method 1: Rolling update (zero downtime)
docker-compose up -d --build --no-deps web

# Method 2: Full restart (recommended for database changes)
docker-compose down
docker-compose up -d --build

# Wait for containers to be ready
sleep 5
docker ps
```

---

### Step 4: Run Database Migrations (30 seconds)

```bash
# Option 1: Browser-based migration
# Visit: http://your-domain.com/admin/run-migration.php

# Option 2: Command line
docker exec vpn_cms_web php /var/www/html/admin/run-migration.php

# Verify tables
docker exec vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal -e "SHOW TABLES"
```

---

### Step 5: Verify Update (1 minute)

```bash
# Check version
cat VERSION

# Test admin login
curl -I http://localhost/admin/login.php

# Check database connection
docker exec vpn_cms_web php -r "
require_once '/var/www/html/config/database.php';
\$db = new Database();
echo \$db->getConnection() ? 'DB OK' : 'DB FAILED';
"

# Check all containers are running
docker-compose ps
```

---

### Step 6: Test Key Features (2 minutes)

1. ✅ Login to admin panel
2. ✅ Login to customer portal
3. ✅ Create a test VPN account
4. ✅ Check RADIUS (if enabled)
5. ✅ Verify existing accounts still work

---

## 🔄 Quick Update (5 Minutes Total)

```bash
#!/bin/bash
# save as: quick-update.sh

echo "🔄 Starting VMaster update..."

# 1. Backup
echo "📦 Backing up database..."
docker exec vpn_cms_db mysqldump -uroot -prootpassword vpn_cms_portal > backup_$(date +%Y%m%d_%H%M%S).sql

# 2. Pull code
echo "📥 Pulling latest code..."
git pull origin main

# 3. Update containers
echo "🐳 Updating Docker containers..."
docker-compose up -d --build

# 4. Wait for startup
echo "⏳ Waiting for containers..."
sleep 10

# 5. Run migrations
echo "🔧 Running migrations..."
docker exec vpn_cms_web php /var/www/html/admin/run-migration.php

# 6. Verify
echo "✅ Checking services..."
docker-compose ps

echo "🎉 Update complete!"
echo "📝 Check: http://your-domain.com"
```

Make it executable:
```bash
chmod +x quick-update.sh
```

---

## 🚨 Rollback Procedure (If Update Fails)

### Quick Rollback (2 minutes)

```bash
# 1. Go back to previous version
git log --oneline  # Find previous commit
git reset --hard COMMIT_HASH  # Replace with actual commit

# 2. Restore containers
docker-compose down
docker-compose up -d

# 3. Restore database (if needed)
docker exec -i vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal < backup_YYYYMMDD_HHMMSS.sql

# 4. Verify
docker-compose ps
```

---

## 📊 Version Management

### Check Current Version

```bash
cat VERSION
# Output: 1.0.0
```

### Tag Versions in Git

```bash
# After successful update
git tag -a v1.0.0 -m "Version 1.0.0 - Initial release"
git push origin v1.0.0

# List all versions
git tag -l
```

### View Version History

```bash
# See all tagged versions
git tag -l -n5

# Compare versions
git diff v1.0.0 v1.1.0
```

---

## 🔍 Update Checklist

Before updating:
- [ ] Read CHANGELOG.md
- [ ] Backup database
- [ ] Backup uploaded files
- [ ] Test update locally first
- [ ] Notify customers (if downtime expected)

During update:
- [ ] Pull latest code
- [ ] Update Docker containers
- [ ] Run migrations
- [ ] Check logs for errors
- [ ] Verify all services running

After update:
- [ ] Test admin login
- [ ] Test customer login
- [ ] Test VPN account creation
- [ ] Check RADIUS (if enabled)
- [ ] Verify existing features
- [ ] Update VERSION file
- [ ] Tag release in Git

---

## 🛡️ Update Best Practices

### 1. Always Test Locally First

```bash
# On your local machine
git pull origin main
docker-compose down
docker-compose up -d --build
# Test thoroughly
```

### 2. Scheduled Maintenance Windows

- Update during low-traffic hours
- Notify customers in advance
- Keep downtime under 5 minutes
- Have rollback plan ready

### 3. Database Migration Safety

```bash
# Always backup before migration
mysqldump > backup.sql

# Test migration on backup first
mysql test_db < backup.sql
# Run migration on test_db
# If OK, run on production
```

### 4. Monitor After Update

```bash
# Watch logs for 10 minutes after update
docker logs -f vpn_cms_web

# Check error rates
tail -f /var/log/apache2/error.log
```

---

## 📦 Update Types

### Patch Update (1.0.0 → 1.0.1)
- Bug fixes only
- No database changes
- No downtime
- Just: git pull + docker restart

### Minor Update (1.0.0 → 1.1.0)
- New features
- May include database migrations
- 2-5 minutes downtime
- git pull + docker rebuild + migrations

### Major Update (1.0.0 → 2.0.0)
- Breaking changes
- Database restructure
- 5-10 minutes downtime
- Full backup required
- Migration testing required

---

## 🔧 Common Update Scenarios

### Scenario 1: Bug Fix (No Database Changes)

```bash
# Super fast - 30 seconds
git pull origin main
docker-compose restart web
```

### Scenario 2: New Feature (Database Changes)

```bash
# 5 minutes
./scripts/backup.sh
git pull origin main
docker-compose down
docker-compose up -d --build
docker exec vpn_cms_web php /var/www/html/admin/run-migration.php
```

### Scenario 3: Security Update (Critical)

```bash
# Immediate
git pull origin main
docker-compose up -d --force-recreate --build web
# Verify patch applied
```

---

## 📞 Post-Update Support

If customers report issues:

1. **Check logs immediately**
   ```bash
   docker logs vpn_cms_web --tail 100
   ```

2. **Verify database**
   ```bash
   docker exec vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal -e "SHOW TABLES"
   ```

3. **Test affected feature**
   - Login as customer
   - Reproduce the issue
   - Check error logs

4. **Rollback if critical**
   - Use git reset
   - Restore database backup
   - Restart containers

---

## 🎯 Update Frequency

### Recommended Schedule:

- **Security patches:** Immediately
- **Bug fixes:** Weekly
- **New features:** Monthly
- **Major versions:** Quarterly

### Notification Template:

```
Subject: VMaster Maintenance - [Date] [Time]

Dear valued customer,

We will be performing a brief system update on [DATE] at [TIME].

Expected downtime: 5 minutes
Features affected: None
New features: [List features]

What you need to do: Nothing! All your data and accounts are safe.

Thank you for your patience!
```

---

## 📈 Monitoring Updates

### Check Update Success

```bash
# All containers running?
docker-compose ps | grep Up

# Database accessible?
docker exec vpn_cms_db mysqladmin ping -uroot -prootpassword

# Web server responding?
curl -I http://localhost

# RADIUS working (if enabled)?
docker exec radius_db mysqladmin ping -uradius -pradiuspass
```

### Monitor Metrics

```bash
# Check VPN account creation rate
docker exec vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal -e "
SELECT DATE(created_at) as date, COUNT(*) as accounts 
FROM vpn_accounts 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at)
"

# Check error rate
docker logs vpn_cms_web 2>&1 | grep -i error | wc -l
```

---

## ✅ Update Success Criteria

An update is successful when:

1. ✅ All Docker containers are running
2. ✅ Admin panel loads
3. ✅ Customer portal loads
4. ✅ Can create VPN accounts
5. ✅ Existing accounts still work
6. ✅ RADIUS users created (if enabled)
7. ✅ No errors in logs
8. ✅ Database migrations applied
9. ✅ Backups are safe
10. ✅ Customers not complaining 😊

---

## 🎉 Automated Updates (Advanced)

### Create Auto-Update Script

```bash
#!/bin/bash
# auto-update.sh

# Configuration
BACKUP_DIR="/var/backups/vmaster"
LOG_FILE="/var/log/vmaster-update.log"

# Function: Log message
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a $LOG_FILE
}

# 1. Check for updates
log "Checking for updates..."
git fetch origin
if [ $(git rev-list HEAD...origin/main --count) -eq 0 ]; then
    log "No updates available"
    exit 0
fi

# 2. Backup
log "Creating backup..."
mkdir -p $BACKUP_DIR
docker exec vpn_cms_db mysqldump -uroot -prootpassword vpn_cms_portal > $BACKUP_DIR/backup_$(date +%Y%m%d_%H%M%S).sql

# 3. Pull updates
log "Pulling updates..."
git pull origin main

# 4. Update containers
log "Updating containers..."
docker-compose up -d --build

# 5. Run migrations
log "Running migrations..."
sleep 10
docker exec vpn_cms_web php /var/www/html/admin/run-migration.php

# 6. Verify
log "Verifying update..."
if docker-compose ps | grep -q "Up"; then
    log "✅ Update successful!"
    
    # Update version
    NEW_VERSION=$(cat VERSION)
    log "Updated to version: $NEW_VERSION"
    
    # Tag in git
    git tag -a "v$NEW_VERSION" -m "Auto-updated to $NEW_VERSION"
    
else
    log "❌ Update failed! Rolling back..."
    git reset --hard HEAD~1
    docker-compose down
    docker-compose up -d
    log "Rolled back to previous version"
    exit 1
fi
```

### Schedule Auto-Updates (Optional)

```bash
# Add to crontab
crontab -e

# Check for updates every Sunday at 3 AM
0 3 * * 0 /var/www/vmaster/scripts/auto-update.sh
```

---

## 📞 Emergency Hotfix Procedure

For critical security issues:

```bash
# 1. Pull hotfix immediately
git pull origin hotfix/security-fix

# 2. Quick rebuild
docker-compose up -d --force-recreate web

# 3. Verify
curl -I http://localhost

# 4. Notify customers
# Send email about security update
```

---

**Keep VMaster up-to-date for best security and features! 🚀**

For questions, check the docs or create an issue on GitHub.

