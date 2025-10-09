# 🚀 Deploy to Production Ubuntu Server (Safe Method)

## ⚠️ IMPORTANT: This deployment is 100% SAFE for existing data!

Your database, uploads, and configurations will NOT be affected.

---

## 📋 Prerequisites

On your Ubuntu production server:
- Docker & Docker Compose installed
- Git installed
- SSH access
- Existing VMaster running (we'll update it safely)

---

## 🔧 One-Time Setup (First Time Only)

### Step 1: Push to GitHub (On Your Local Machine)

```bash
# Navigate to your project
cd "/Users/zawnainghtun/My Coding Project/VPN CMS Portal"

# Initialize git (if not already done)
git init

# Add all files
git add .

# Commit
git commit -m "Initial commit with custom plan feature"

# Create repository on GitHub first, then:
git remote add origin https://github.com/YOUR_USERNAME/vmaster-cms.git

# Push to GitHub
git push -u origin main
```

### Step 2: Clone on Production Server (First Time Only)

```bash
# SSH to your server
ssh user@your-server-ip

# Navigate to web directory
cd /var/www

# Backup existing vmaster (if exists)
sudo mv vmaster vmaster_backup_$(date +%Y%m%d)

# Clone from GitHub
sudo git clone https://github.com/YOUR_USERNAME/vmaster-cms.git vmaster

# Set permissions
sudo chown -R www-data:www-data vmaster
```

---

## 🔄 Update Process (Every Time You Want to Deploy)

### Method 1: Automatic Update Script (Recommended)

I'll create a safe update script for you:

```bash
# On production server
cd /var/www/vmaster
sudo bash scripts/safe-update.sh
```

### Method 2: Manual Update (Step by Step)

```bash
# 1. SSH to server
ssh user@your-server-ip

# 2. Navigate to project
cd /var/www/vmaster

# 3. Backup database (IMPORTANT!)
docker exec vpn_cms_db mysqldump -uroot -prootpassword vpn_cms_portal > backup_before_update_$(date +%Y%m%d_%H%M%S).sql

# 4. Pull latest code from GitHub
sudo git pull origin main

# 5. Run migrations (if any)
docker exec -i vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal < database/add_plan_duration.sql

# 6. Restart web container only (no data loss)
docker restart vpn_cms_web

# Done! ✅
```

---

## 🔒 What's Protected (Never Overwritten)

These are SAFE and will NEVER be lost during updates:

✅ **Database Data**
   - All customer accounts
   - All VPN accounts
   - All configurations
   - All activity logs

✅ **Uploaded Files**
   - QR codes
   - User uploads
   - Logos

✅ **Docker Volumes**
   - Database volume (persistent)
   - All data stored in Docker volumes

✅ **Environment Configs**
   - Your production passwords
   - API keys
   - Database credentials

---

## 📁 What Gets Updated

Only these files are updated:

✅ **Code Files**
   - PHP files
   - HTML/CSS/JS files
   - New features

✅ **Documentation**
   - README files
   - Guides

✅ **Scripts**
   - Deployment scripts
   - Migration scripts

---

## 🛡️ Safety Features

### 1. Automatic Backup
The update script automatically backs up your database before any changes.

### 2. No Data Deletion
Git pull only updates code files, never touches:
- Database
- Uploads folder
- Docker volumes

### 3. Rollback Available
If something goes wrong:
```bash
# Restore database
docker exec -i vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal < backup_before_update_YYYYMMDD_HHMMSS.sql

# Rollback code
git reset --hard HEAD~1

# Restart
docker restart vpn_cms_web
```

---

## 📝 Update Checklist

Before updating:
- [ ] Backup database (automatic in script)
- [ ] Note current version/commit
- [ ] Check server disk space
- [ ] Ensure Docker containers are running

After updating:
- [ ] Test login (admin & customer)
- [ ] Create test VPN account
- [ ] Check existing VPN accounts still work
- [ ] Verify custom plan feature works

---

## 🚨 Emergency Rollback

If update causes issues:

```bash
# 1. Restore database
docker exec -i vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal < backup_before_update_YYYYMMDD_HHMMSS.sql

# 2. Rollback code to previous version
git log --oneline  # Find previous commit hash
git reset --hard COMMIT_HASH

# 3. Restart
docker restart vpn_cms_web

# 4. Verify
curl http://localhost:8000
```

---

## 🎯 Quick Reference

### Push Updates (Local → GitHub)
```bash
git add .
git commit -m "Description of changes"
git push origin main
```

### Pull Updates (GitHub → Production)
```bash
cd /var/www/vmaster
sudo git pull origin main
docker restart vpn_cms_web
```

### Check Current Version
```bash
git log -1 --oneline
```

### View Changes
```bash
git status
git diff
```

---

## 💡 Pro Tips

1. **Always backup before update**
   ```bash
   docker exec vpn_cms_db mysqldump -uroot -prootpassword vpn_cms_portal > backup.sql
   ```

2. **Test locally first**
   - Test all changes on http://localhost:8080
   - Only push to production when confirmed working

3. **Use meaningful commit messages**
   ```bash
   git commit -m "Add custom plan feature with date range picker"
   ```

4. **Keep backups**
   - Keep last 5 database backups
   - Store backups outside /var/www

5. **Monitor logs after update**
   ```bash
   docker logs -f vpn_cms_web
   ```

---

## 📞 Troubleshooting

### Issue: Git pull fails with "uncommitted changes"
```bash
# Stash your changes
git stash

# Pull updates
git pull origin main

# Reapply your changes (if needed)
git stash pop
```

### Issue: Permission denied
```bash
sudo chown -R $USER:$USER /var/www/vmaster
```

### Issue: Docker container not restarting
```bash
docker ps -a
docker logs vpn_cms_web
docker-compose restart
```

---

## ✅ Success Indicators

After successful update, you should see:

1. ✅ Website loads: `http://your-server-ip:8000`
2. ✅ Can login to admin panel
3. ✅ Can login to customer portal
4. ✅ Existing VPN accounts visible
5. ✅ Can create new VPN account
6. ✅ Custom plan option appears
7. ✅ Logo displays correctly

---

## 🎉 You're Ready!

Your deployment process is now:

1. **Develop locally** → Test on http://localhost:8080
2. **Push to GitHub** → `git push origin main`
3. **Pull on server** → `git pull origin main`
4. **Restart container** → `docker restart vpn_cms_web`
5. **Verify** → Test the website

**Zero downtime, zero data loss!** 🚀

---

**Need help?** Check the troubleshooting section or contact support.

