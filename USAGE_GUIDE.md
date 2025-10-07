# 📖 VPN CMS Portal - Complete Usage Guide

## 🎯 Table of Contents

1. [Getting Started](#getting-started)
2. [Admin Workflow](#admin-workflow)
3. [Customer Workflow](#customer-workflow)
4. [VPN Setup for End Users](#vpn-setup-for-end-users)
5. [Common Scenarios](#common-scenarios)
6. [Troubleshooting](#troubleshooting)

---

## 🚀 Getting Started

### Initial Setup

1. **Start the system**:
```bash
./start.sh
```

2. **Access admin panel**:
- URL: http://localhost:8080/admin/login.php
- Username: `admin`
- Password: `admin123`

3. **Change default password** (Important!):
- After login, go to your profile settings
- Update to a strong password

---

## 👨‍💼 Admin Workflow

### Step 1: Add VPN Servers

#### Scenario: Adding an Outline Server in Singapore

1. Navigate to **VPN Servers** from sidebar
2. Click **"+ Add Server"**
3. Fill in details:
   ```
   Server Name: Singapore Outline Server
   Server Type: Outline
   Server Host: 203.0.113.10
   Server Port: 443
   Location: Singapore
   Max Accounts: 100
   API URL: https://203.0.113.10:8080/api (optional)
   API Key: your_api_key (optional)
   Description: Fast server in Singapore datacenter
   ```
4. Click **"Add Server"**

#### Scenario: Adding a V2Ray Server in USA

```
Server Name: USA V2Ray Server
Server Type: V2Ray
Server Host: vpn-usa.example.com
Server Port: 443
Location: United States
Max Accounts: 150
```

#### Scenario: Adding an SSTP Server

```
Server Name: Europe SSTP Server
Server Type: SSTP
Server Host: vpn-eu.example.com
Server Port: 443
Admin Username: admin
Admin Password: secure_password
Location: Netherlands
Max Accounts: 80
```

### Step 2: Create Customer Accounts

#### Scenario: Onboarding a New Company

1. Navigate to **Customers** from sidebar
2. Click **"+ Add Customer"**
3. Fill in company details:
   ```
   Company Name: Tech Innovations Ltd
   Contact Person: John Smith
   Username: techinnovations
   Password: SecurePass123!
   Email: john@techinnovations.com
   Phone: +95-9-123456789
   Max Staff Accounts: 20
   ```
4. Click **"Add Customer"**

### Step 3: Monitor System

#### View Activity Logs
1. Navigate to **Activity Logs**
2. See all user actions:
   - Logins
   - VPN account creations
   - Server additions
   - Customer modifications

#### Check Statistics
1. Go to **Dashboard**
2. View:
   - Total customers
   - Active VPN servers
   - Staff accounts
   - VPN accounts

---

## 👤 Customer Workflow

### Step 1: Login

1. Go to http://localhost:8080/customer/login.php
2. Enter credentials provided by admin
3. View your dashboard

### Step 2: Add Staff Members

#### Scenario: Adding IT Department Staff

1. Navigate to **My Staff**
2. Click **"+ Add Staff"**
3. Fill in details:
   ```
   Staff Name: Alice Johnson
   Email: alice@techinnovations.com
   Phone: +95-9-987654321
   Department: IT Department
   Notes: Senior Network Engineer
   ```
4. Click **"Add Staff"**

#### Scenario: Adding Sales Team Member

```
Staff Name: Bob Wilson
Email: bob@techinnovations.com
Department: Sales
Notes: Regional Sales Manager
```

### Step 3: Create VPN Accounts

#### Scenario: Creating Outline VPN for Alice

1. Navigate to **VPN Accounts**
2. Click **"🔑 Create VPN Account"**
3. Select:
   ```
   Staff Member: Alice Johnson
   VPN Server: Singapore Outline Server
   ```
4. Click **"Create VPN Account"**
5. System generates access key automatically

#### Scenario: Creating V2Ray for Bob

```
Staff Member: Bob Wilson
VPN Server: USA V2Ray Server
```

### Step 4: Share VPN Credentials

#### Method 1: View and Copy

1. In VPN Accounts list, find the account
2. Click **"📋 View"**
3. See credentials and setup instructions
4. Click **"Copy"** buttons to copy credentials
5. Send via email or messaging app to staff

#### Method 2: Share Directly

1. Click **"📤 Share"** on the account
2. See complete setup guide
3. Share the information with staff member

---

## 📱 VPN Setup for End Users

### Outline VPN Setup

#### For Android:
1. Install "Outline" from Google Play Store
2. Open the app
3. Tap "+"
4. Paste access key: `ss://xxxxx@host:port`
5. Tap "Connect"

#### For iOS:
1. Install "Outline" from App Store
2. Open the app
3. Tap "Add Server"
4. Paste access key
5. Tap "Connect"

#### For Windows:
1. Download Outline Client
2. Install and open
3. Click "Add Server"
4. Paste access key
5. Click "Connect"

### V2Ray Setup

#### For Android (V2RayNG):
1. Install "V2RayNG" from Google Play
2. Open app
3. Tap "+" → Import config from clipboard
4. Paste VMess link: `vmess://xxxxx`
5. Tap imported config to connect

#### For Windows (v2rayN):
1. Download and extract v2rayN
2. Run v2rayN.exe
3. Click "Servers" → "Add VMess server"
4. Click "Import from clipboard"
5. Paste VMess link
6. Right-click system tray icon → Connect

### SSTP VPN Setup

#### For Windows:
1. Open Settings → Network & Internet
2. Click "VPN" → "Add VPN"
3. Fill in:
   ```
   VPN Provider: Windows (built-in)
   Connection Name: Company VPN
   Server: vpn-server.example.com
   VPN Type: SSTP
   Username: vpn_xxxxxxxx
   Password: [provided password]
   ```
4. Click "Save"
5. Click "Connect"

#### For Android (SSTP Client):
1. Install "SSTP Client" from Play Store
2. Open app
3. Tap "+"
4. Enter:
   - Server: host
   - Port: port
   - Username: [provided]
   - Password: [provided]
5. Tap "Connect"

---

## 🎬 Common Scenarios

### Scenario 1: New Employee Onboarding

**Customer needs to provide VPN access to a new employee**

1. Login to customer panel
2. Go to **My Staff**
3. Add new staff member with employee details
4. Go to **VPN Accounts**
5. Create VPN account (select server based on location)
6. Click **"Share"** and send credentials to employee
7. Provide setup instructions based on device type

### Scenario 2: Server Maintenance

**Admin needs to move users to a different server**

1. Login to admin panel
2. Go to **VPN Servers**
3. Find the server going into maintenance
4. Click **"Edit"**
5. Change status to "Maintenance"
6. Notify customers via email
7. Customers can create new accounts on other servers

### Scenario 3: Customer Reaches Staff Limit

**Customer needs more staff accounts**

1. Customer contacts administrator
2. Admin logs in
3. Goes to **Customers**
4. Finds the customer and clicks **"Edit"**
5. Updates "Max Staff Accounts" to higher number
6. Customer can now add more staff

### Scenario 4: Staff Member Leaving

**Employee leaves, need to revoke VPN access**

1. Customer logs in
2. Goes to **VPN Accounts**
3. Finds all accounts for that staff member
4. Clicks **"Delete"** on each account
5. Goes to **My Staff**
6. Either deletes or suspends the staff member

### Scenario 5: Monitoring System Usage

**Admin wants to check system health**

1. Login to admin panel
2. View **Dashboard** for overview
3. Check **VPN Servers** for capacity:
   - Current accounts / Max accounts
   - Server status
4. Review **Activity Logs** for:
   - Unusual activities
   - Login patterns
   - VPN creation rate

---

## 🔧 Troubleshooting

### Problem: Can't Login

**Solution:**
1. Verify you're on the correct login page:
   - Admin: `/admin/login.php`
   - Customer: `/customer/login.php`
2. Check username and password
3. Ensure account is active (not suspended)
4. Clear browser cache and cookies

### Problem: VPN Account Creation Fails

**Solution:**
1. Check if staff member exists and is active
2. Verify selected server is active
3. Check if server has reached max capacity
4. Review activity logs for error details

### Problem: Can't View Credentials

**Solution:**
1. Ensure you're logged in as the account owner
2. Check if VPN account is active
3. Try refreshing the page
4. Check browser console for JavaScript errors

### Problem: Docker Container Not Starting

**Solution:**
```bash
# Check container status
docker ps -a

# View logs
docker-compose logs web
docker-compose logs db

# Restart containers
docker-compose restart

# If needed, rebuild
docker-compose down
docker-compose up -d --build
```

### Problem: Database Connection Error

**Solution:**
1. Wait 30 seconds after starting (MySQL initialization)
2. Check MySQL container:
   ```bash
   docker-compose logs db
   ```
3. Verify credentials in `config/database.php`
4. Restart MySQL container:
   ```bash
   docker-compose restart db
   ```

### Problem: Port Already in Use

**Solution:**
1. Check what's using the port:
   ```bash
   lsof -i :8080
   ```
2. Stop the conflicting service or change port in `docker-compose.yml`:
   ```yaml
   ports:
     - "8090:80"  # Change 8080 to 8090
   ```

---

## 📊 Best Practices

### For Administrators

1. **Regular Backups**:
   ```bash
   # Daily backup
   docker exec vpn_cms_db mysqldump -u root -p vpn_cms_portal > backup-$(date +%Y%m%d).sql
   ```

2. **Monitor Server Capacity**:
   - Keep servers below 80% capacity
   - Add new servers proactively

3. **Review Activity Logs**:
   - Check logs daily
   - Look for unusual patterns

4. **Customer Management**:
   - Set appropriate staff limits
   - Suspend inactive accounts

### For Customers

1. **Staff Management**:
   - Keep staff information up-to-date
   - Remove departed employees promptly
   - Use departments for organization

2. **VPN Account Management**:
   - Create accounts only when needed
   - Delete unused accounts
   - Choose servers based on staff location

3. **Security**:
   - Use strong passwords
   - Don't share credentials publicly
   - Log out after use

---

## 📈 Tips for Efficiency

1. **Bulk Operations**:
   - Add all staff members at once
   - Then create VPN accounts for all

2. **Naming Conventions**:
   - Use clear server names (location + type)
   - Use full staff names
   - Include department in staff records

3. **Documentation**:
   - Keep a record of which staff has which VPN
   - Document server purposes
   - Maintain customer contact info

4. **Communication**:
   - Send setup instructions with credentials
   - Provide support contacts
   - Create VPN usage guidelines

---

## 🎓 Training Checklist

### For New Administrators
- [ ] Understand dashboard layout
- [ ] Practice adding VPN servers
- [ ] Create test customer account
- [ ] Review activity logs
- [ ] Know how to backup database
- [ ] Understand capacity planning

### For New Customers
- [ ] Navigate customer dashboard
- [ ] Add staff members
- [ ] Create VPN accounts
- [ ] View and copy credentials
- [ ] Understand server selection
- [ ] Know support contact

### For End Users (Staff)
- [ ] Install correct VPN client
- [ ] Import/enter credentials
- [ ] Connect to VPN
- [ ] Test internet connection
- [ ] Know when to use VPN
- [ ] Report issues properly

---

**🎉 You're now ready to use the VPN CMS Portal effectively!**

For more information:
- **Technical Details**: See ARCHITECTURE.md
- **Quick Start**: See QUICK_START.md
- **Full Documentation**: See README.md

