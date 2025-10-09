# 🐙 GitHub Setup Guide - Quick Start

## 📝 Step-by-Step Instructions

### 1️⃣ Create GitHub Repository (5 minutes)

1. Go to https://github.com
2. Click **"+"** → **"New repository"**
3. Fill in:
   - **Repository name:** `vmaster-cms` (or any name you like)
   - **Description:** "VMaster VPN CMS Portal with Custom Plans"
   - **Visibility:** Private (recommended) or Public
   - **DO NOT** check "Initialize with README" (we already have files)
4. Click **"Create repository"**

### 2️⃣ Push Your Code to GitHub (On Your Mac)

```bash
# Navigate to your project
cd "/Users/zawnainghtun/My Coding Project/VPN CMS Portal"

# Initialize git (if not already done)
git init

# Add all files
git add .

# Commit
git commit -m "Initial commit: VMaster CMS with custom plan feature and logo"

# Add GitHub as remote (replace YOUR_USERNAME with your GitHub username)
git remote add origin https://github.com/YOUR_USERNAME/vmaster-cms.git

# Push to GitHub
git branch -M main
git push -u origin main
```

**Done!** Your code is now on GitHub! 🎉

---

## 🚀 Deploy to Production Server

### First Time Setup (One Time Only)

```bash
# 1. SSH to your Ubuntu server
ssh root@your-server-ip

# 2. Navigate to web directory
cd /var/www

# 3. Backup existing vmaster (if exists)
sudo mv vmaster vmaster_backup_$(date +%Y%m%d)

# 4. Clone from GitHub
sudo git clone https://github.com/YOUR_USERNAME/vmaster-cms.git vmaster

# 5. Navigate to project
cd vmaster

# 6. Run initial setup
docker exec -i vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal < database/add_plan_duration.sql

# 7. Restart web container
docker restart vpn_cms_web

# Done! ✅
```

---

## 🔄 Update Production (Every Time)

### Method 1: Automatic (Recommended)

```bash
# SSH to server
ssh root@your-server-ip

# Navigate to project
cd /var/www/vmaster

# Run safe update script
sudo bash scripts/safe-update.sh
```

**That's it!** The script will:
- ✅ Backup database automatically
- ✅ Pull latest code
- ✅ Run migrations
- ✅ Restart container
- ✅ Verify everything works

### Method 2: Manual

```bash
# SSH to server
ssh root@your-server-ip

# Navigate to project
cd /var/www/vmaster

# Backup database
docker exec vpn_cms_db mysqldump -uroot -prootpassword vpn_cms_portal > backup_$(date +%Y%m%d).sql

# Pull latest code
sudo git pull origin main

# Restart web container
docker restart vpn_cms_web
```

---

## 📋 Typical Workflow

### On Your Mac (Development):

```bash
# 1. Make changes to code
# 2. Test locally: http://localhost:8080
# 3. When satisfied, commit and push:

git add .
git commit -m "Description of what you changed"
git push origin main
```

### On Ubuntu Server (Production):

```bash
# Pull and deploy the changes
cd /var/www/vmaster
sudo bash scripts/safe-update.sh
```

---

## 🛡️ Safety Guarantees

### What's Protected (NEVER Lost):
- ✅ Database (all customer data, VPN accounts, etc.)
- ✅ Uploaded files (QR codes, logos)
- ✅ Docker volumes (persistent data)
- ✅ Configuration files (passwords, API keys)

### What Gets Updated:
- ✅ PHP code files
- ✅ HTML/CSS/JavaScript
- ✅ Documentation
- ✅ Scripts

---

## 💡 Pro Tips

### 1. Always Test Locally First
```bash
# Test on your Mac first
open http://localhost:8080

# Only push to production when confirmed working
git push origin main
```

### 2. Use Meaningful Commit Messages
```bash
# Good ✅
git commit -m "Add custom plan feature with date range picker"
git commit -m "Fix undefined array key warning in vpn-accounts.php"

# Bad ❌
git commit -m "update"
git commit -m "fix"
```

### 3. Check What Changed Before Pushing
```bash
git status          # See what files changed
git diff            # See exact changes
git log --oneline   # See recent commits
```

### 4. Keep Backups
The safe-update script automatically creates backups, but you can also:
```bash
# Manual backup on server
docker exec vpn_cms_db mysqldump -uroot -prootpassword vpn_cms_portal > backup_manual_$(date +%Y%m%d).sql

# Download backup to your Mac
scp root@your-server-ip:/var/www/vmaster/backup_*.sql ~/Desktop/
```

---

## 🔧 Common Commands

### Check Current Version
```bash
git log -1 --oneline
```

### See What Will Be Updated
```bash
git fetch origin
git log HEAD..origin/main --oneline
```

### Rollback to Previous Version
```bash
# On server
git log --oneline  # Find commit hash
git reset --hard COMMIT_HASH
docker restart vpn_cms_web
```

### View File Changes
```bash
git diff filename.php
```

---

## 🚨 Troubleshooting

### Issue: "Permission denied (publickey)"
**Solution:** Use HTTPS instead of SSH for GitHub
```bash
git remote set-url origin https://github.com/YOUR_USERNAME/vmaster-cms.git
```

### Issue: "Git pull fails with uncommitted changes"
**Solution:**
```bash
git stash           # Save your changes
git pull            # Pull updates
git stash pop       # Restore your changes
```

### Issue: "Merge conflict"
**Solution:**
```bash
# Keep server version
git checkout --theirs filename.php

# Or keep your version
git checkout --ours filename.php

# Then commit
git add .
git commit -m "Resolved merge conflict"
```

---

## ✅ Quick Reference

### First Time:
```bash
# On Mac
git init
git add .
git commit -m "Initial commit"
git remote add origin https://github.com/YOUR_USERNAME/vmaster-cms.git
git push -u origin main

# On Server
cd /var/www
git clone https://github.com/YOUR_USERNAME/vmaster-cms.git vmaster
```

### Every Update:
```bash
# On Mac
git add .
git commit -m "Description"
git push origin main

# On Server
cd /var/www/vmaster
sudo bash scripts/safe-update.sh
```

---

## 🎉 You're All Set!

Your deployment workflow is now:

1. **Develop** on your Mac → Test at http://localhost:8080
2. **Push** to GitHub → `git push origin main`
3. **Deploy** to server → `sudo bash scripts/safe-update.sh`
4. **Verify** → Test at http://your-server-ip:8000

**Zero data loss, automatic backups, easy rollback!** 🚀

---

## 📞 Need Help?

- GitHub Docs: https://docs.github.com
- Git Basics: https://git-scm.com/book/en/v2
- Or check `DEPLOY_TO_PRODUCTION.md` for detailed instructions

