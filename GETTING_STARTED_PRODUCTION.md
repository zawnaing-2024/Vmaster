# Getting Started - GitHub & Production Deployment

## 🎯 Quick Overview

You have VMaster v1.0.0 ready to:
1. Push to GitHub
2. Deploy to Ubuntu 22.04 production server
3. Update easily in the future

**Total time: ~45 minutes**

---

## Part 1: Push to GitHub (5 minutes)

### Step 1: Push Your Code

```bash
cd /Users/zawnainghtun/My\ Coding\ Project/VPN\ CMS\ Portal

# Push main branch and tags
git push -u origin main
git push origin --tags
```

### Step 2: Verify on GitHub

Visit: https://github.com/zawnaing-2024/Vmaster

You should see:
- ✅ All 93 files
- ✅ Beautiful README
- ✅ v1.0.0 tag
- ✅ Complete documentation

---

## Part 2: Deploy to Production (40 minutes)

### On Your Ubuntu 22.04 Server:

```bash
# 1. Update system (2 min)
apt update && apt upgrade -y

# 2. Install Docker (3 min)
curl -fsSL https://get.docker.com | sh
apt install -y docker-compose

# 3. Clone VMaster (1 min)
cd /var/www
git clone https://github.com/zawnaing-2024/Vmaster.git vmaster
cd vmaster

# 4. Create production config (2 min)
nano .env.production
```

Add:
```env
DB_USER=vmaster_user
DB_PASS=your-strong-password
MYSQL_ROOT_PASSWORD=your-root-password
RADIUS_DB_USER=radius_user
RADIUS_DB_PASS=your-radius-password
```

```bash
# 5. Deploy with Docker (5 min)
docker-compose -f docker-compose.prod.yml up -d

# Wait for startup
sleep 30

# 6. Import databases (2 min)
docker exec -i vmaster_db mysql -uroot -p'your-root-password' vpn_cms_portal < database/schema.sql
docker exec -i vmaster_radius_db mysql -uradius -p'your-radius-password' radius < radius/schema.sql

# 7. Install Nginx (3 min)
apt install -y nginx

# 8. Setup SSL (3 min)
apt install -y certbot python3-certbot-nginx
certbot --nginx -d your-domain.com

# 9. Verify (2 min)
curl -I https://your-domain.com
docker-compose ps
```

---

## Part 3: First Time Setup (5 minutes)

### Access Your VMaster

```
https://your-domain.com/admin/login.php

Username: admin
Password: admin123
```

### Immediate Actions:

1. **Change admin password!**
   - Go to: Change Password
   - Set strong password

2. **Add your first VPN server**
   - Go to: VPN Servers → Add Server
   - Enter Outline/SSTP/V2Ray details

3. **Create your first customer**
   - Go to: Customers → Add Customer
   - Set limits as needed

4. **Enable RADIUS (optional)**
   - Edit: `config/radius.php`
   - Set: `RADIUS_ENABLED = true`
   - Configure SoftEther to use RADIUS

---

## Part 4: Future Updates (2 minutes)

### When You Make Changes:

**On Your Local Machine:**
```bash
# 1. Make changes
# 2. Test locally
docker-compose up -d

# 3. Commit and push
git add .
git commit -m "Update: your changes"
git push origin main
```

**On Production Server:**
```bash
# Just run the update script!
cd /var/www/vmaster
./scripts/quick-update.sh
```

**That's it!** Update done in 2 minutes with zero downtime! 🎉

---

## 🎯 Update Workflow Diagram

```
Local Development
├── Make changes
├── Test with docker-compose up -d
├── Commit: git commit -m "..."
└── Push: git push origin main
     │
     ▼
GitHub Repository
├── Code stored
├── Version tagged
└── Ready to deploy
     │
     ▼
Production Server
├── Run: git pull origin main
├── Run: ./scripts/quick-update.sh
├── Auto backup
├── Auto update
├── Auto migrate
└── Zero downtime! ✅
```

---

## 📊 Complete File Structure

```
VMaster/
├── admin/              # Admin panel
├── customer/           # Customer portal
├── assets/            # CSS, JS
├── config/            # Configuration
├── database/          # SQL schemas
├── includes/          # PHP classes
├── public/            # Public homepage
├── radius/            # RADIUS config
├── scripts/           # Automation scripts
│   ├── backup.sh          # Auto backup
│   └── quick-update.sh    # Auto update
├── uploads/           # User uploads
├── .gitignore         # Git ignore rules
├── .htaccess          # Apache rules
├── Dockerfile         # Docker build
├── docker-compose.yml # Local development
├── docker-compose-radius.yml  # With RADIUS
├── docker-compose.prod.yml    # Production (from DEPLOY_UBUNTU.md)
├── VERSION            # Current version
├── README.md          # Project overview
├── CHANGELOG.md       # Version history
├── LICENSE            # MIT License
├── DEPLOY_UBUNTU.md   # Production deployment
├── UPDATE_GUIDE.md    # Update procedures
└── PUSH_TO_GITHUB.md  # GitHub guide
```

---

## ✅ Pre-Push Checklist

Before pushing to GitHub:
- [x] All code committed
- [x] Version tagged (v1.0.0)
- [x] README.md created
- [x] LICENSE added
- [x] .gitignore configured
- [x] Sensitive files excluded
- [x] Documentation complete
- [x] Scripts executable
- [ ] Ready to push!

---

## 🚀 Quick Start Commands

### Push to GitHub:
```bash
git push -u origin main && git push origin --tags
```

### Deploy to Production:
```bash
# On Ubuntu server
git clone https://github.com/zawnaing-2024/Vmaster.git vmaster
cd vmaster
# Follow DEPLOY_UBUNTU.md
```

### Update Production:
```bash
# On Ubuntu server
cd /var/www/vmaster
./scripts/quick-update.sh
```

---

## 📞 Need Help?

- 📖 **Deployment:** See DEPLOY_UBUNTU.md
- 📖 **Updates:** See UPDATE_GUIDE.md  
- 📖 **RADIUS:** See RADIUS_SSTP_SETUP_GUIDE.md
- 🐛 **Issues:** GitHub Issues
- 💬 **Questions:** GitHub Discussions

---

**Everything is ready! Push to GitHub and deploy to production! 🎉**

Repository: https://github.com/zawnaing-2024/Vmaster.git

