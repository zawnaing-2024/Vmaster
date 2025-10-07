# 🚀 VMaster - Quick Start Guide

Get up and running in minutes!

---

## 🎯 **Local Testing (Right Now)**

### Start the Application:
```bash
# If not already running:
docker-compose up -d

# Check status:
docker-compose ps
```

### Access Points:
```
🏠 Landing Page:  http://localhost:8080
👤 Admin Panel:   http://localhost:8080/admin/login.php  
👥 Customer Portal: http://localhost:8080/customer/login.php
📊 phpMyAdmin:    http://localhost:8081
```

### Default Login:
```
Username: admin
Password: admin123

⚠️ IMPORTANT: Change password after first login!
Go to: Change Password (in sidebar)
```

---

## 📝 **Quick Workflow Test**

### 1️⃣ Login as Admin
```
URL: http://localhost:8080/admin/login.php
Username: admin
Password: admin123
```

### 2️⃣ Change Admin Password
```
Navigation: Change Password (sidebar)
Set a secure password
```

### 3️⃣ Add VPN Server
```
Navigation: VPN Servers → Add Server

Example (Outline):
- Server Name: MaeSaing Production
- Type: outline
- Host: 183.89.209.103
- Port: 17315
- API URL: https://183.89.209.103:29868/C_un1IHKT9zx8EvWk_f-Tw
- Max Accounts: 100
- Status: Active
```

### 4️⃣ Create Customer
```
Navigation: Customers → Add Customer

Example:
- Company: ABC Corporation
- Full Name: John Doe
- Username: johndoe
- Password: secure123
- Email: john@abc.com
- Phone: +95911111111
- Max Clients: 10
- Max VPN per Client: 3
```

### 5️⃣ Login as Customer
```
URL: http://localhost:8080/customer/login.php
Username: johndoe
Password: secure123
```

### 6️⃣ Create Client Account
```
Navigation: My Clients → Add Client

Example:
- Client Name: Alice Smith
- Email: alice@abc.com
- Phone: +95922222222
- Status: Active
```

### 7️⃣ Create VPN Account
```
Navigation: VPN Accounts → Create VPN Account

Select:
- Client: Alice Smith
- Server: MaeSaing Production
- Click Create
```

### 8️⃣ View & Share Credentials
```
Click "View" next to the VPN account
Copy the access key
Share with your client
```

---

## 🐳 **Docker Commands**

### Basic Operations:
```bash
# Start all services
docker-compose up -d

# Stop all services
docker-compose down

# Restart web container
docker-compose restart web

# View logs
docker-compose logs -f web

# Check status
docker-compose ps

# Access database
docker-compose exec db mysql -u root -prootpass vpn_cms_portal
```

---

## 🚀 **Production Deployment**

### Quick Production Setup:

#### 1. Prepare Server
```bash
# Install Docker
curl -fsSL https://get.docker.com | sh

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose
```

#### 2. Transfer Files
```bash
# From local machine
tar -czf vmaster.tar.gz --exclude='.git' .
scp vmaster.tar.gz user@server:/home/vmaster/

# On server
tar -xzf vmaster.tar.gz -C vmaster-app/
cd vmaster-app
```

#### 3. Configure Production
```bash
# Create .env with secure passwords
cat > .env << EOF
DB_PASSWORD=CHANGE_THIS_PASSWORD
MYSQL_ROOT_PASSWORD=CHANGE_THIS_ROOT_PASSWORD
EOF

# Start services
docker-compose up -d
```

#### 4. Configure Domain & SSL
```bash
# Install Nginx
sudo apt install nginx certbot python3-certbot-nginx -y

# Configure (see PRODUCTION_DEPLOYMENT.md)
# Get SSL certificate
sudo certbot --nginx -d vmaster.yourdomain.com
```

#### 5. Secure the System
```bash
# Change admin password via web interface
# Configure firewall
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

# Set up backups (see PRODUCTION_DEPLOYMENT.md)
```

---

## 🔄 **Easy Updates**

### Update Script:
```bash
#!/bin/bash
# Save as update.sh

# Backup
tar -czf backup-$(date +%Y%m%d).tar.gz .

# Pull updates (if using git)
git pull origin main

# Or extract new version
# tar -xzf vmaster-update.tar.gz

# Restart
docker-compose restart web

echo "✅ Update complete!"
```

```bash
chmod +x update.sh
./update.sh
```

---

## 🔧 **Troubleshooting**

### Issue: Can't access application
```bash
# Check if containers are running
docker-compose ps

# Check logs
docker-compose logs web

# Restart services
docker-compose restart
```

### Issue: Database errors
```bash
# Access database
docker-compose exec db mysql -u root -prootpass vpn_cms_portal

# Run migration
docker-compose exec db mysql -u root -prootpass vpn_cms_portal < database/migration_to_clients.sql
```

### Issue: Outline keys not working
```bash
# Check server API URL in Admin Panel
# Should be: https://IP:PORT/API_KEY
# Example: https://183.89.209.103:29868/C_un1IHKT9zx8EvWk_f-Tw

# Test API manually:
curl -k https://183.89.209.103:29868/C_un1IHKT9zx8EvWk_f-Tw/access-keys
```

---

## 📋 **Feature Checklist**

### Current Features:
- ✅ Multi-VPN server management (Outline, V2Ray, SSTP)
- ✅ Admin panel with full control
- ✅ Customer portal for self-service
- ✅ Client management
- ✅ VPN account creation with real API integration
- ✅ Password change for admin & customers
- ✅ Activity logging
- ✅ Beautiful modern UI
- ✅ Docker deployment
- ✅ Currency: Ks (Myanmar Kyat)
- ✅ Professional VMaster branding

---

## 🎯 **System Limits**

### Configurable per Customer:
- **Max Clients**: Set by admin when creating customer
- **Max VPN per Client**: Set by admin when creating customer
- **Example**: Customer can have 10 clients, each client can have 3 VPN accounts

---

## 📞 **Getting Help**

### Documentation:
1. **README.md** - Overview and architecture
2. **PRODUCTION_DEPLOYMENT.md** - Complete production guide
3. **OUTLINE_SERVER_SETUP.md** - Outline integration
4. **TROUBLESHOOTING.md** - Common issues
5. **VMASTER_UPDATES.md** - Recent changes
6. **FINAL_UPDATE_SUMMARY.md** - Complete change log
7. **QUICK_START.md** - This file!

### Common Tasks:
| Task | Where to Look |
|------|---------------|
| Deploy to production | PRODUCTION_DEPLOYMENT.md |
| Fix Outline issues | OUTLINE_SERVER_SETUP.md |
| Update system | PRODUCTION_DEPLOYMENT.md → Easy Updates |
| Change passwords | Login → Change Password menu |
| Add customers | Admin Panel → Customers |
| Create VPN accounts | Customer Portal → VPN Accounts |

---

## 🎊 **You're Ready!**

Your VMaster system is fully set up and ready to use!

### What's Working:
✅ All database tables (client_accounts, not staff_accounts)  
✅ No SQL errors  
✅ Password change functionality  
✅ No default credentials on login  
✅ Real Outline API integration  
✅ Beautiful UI with VMaster branding  
✅ Production deployment ready  
✅ Easy update process  

### Start Using:
1. Test locally at http://localhost:8080
2. Deploy to production when ready
3. Configure your Outline servers
4. Start creating customers and VPN accounts!

**Happy VPN Management! 🚀**
