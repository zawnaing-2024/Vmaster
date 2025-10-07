# VMaster - VPN Management System

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.2-purple.svg)
![MySQL](https://img.shields.io/badge/MySQL-8.0-orange.svg)
![Docker](https://img.shields.io/badge/Docker-Ready-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)

**VMaster** is a comprehensive VPN management system that allows you to manage multiple VPN servers (Outline, SSTP, V2Ray) from a single centralized portal.

---

## 🌟 Features

### Multi-VPN Server Management
- ✅ **Outline VPN** - Real API integration with key management
- ✅ **SSTP** (SoftEther) - With optional RADIUS automation
- ✅ **V2Ray** - With optional RADIUS automation
- ✅ Manage all VPN types from one interface

### Admin Panel
- 👥 Customer management with custom limits
- 🌐 VPN server management
- 📊 Client account overview
- 🔑 VPN credentials pool system
- 🔔 Admin notification center
- 📋 Activity logs and audit trail
- 🛡️ RADIUS user management
- 🔐 Secure authentication

### Customer Portal
- 👨‍💼 Client management (their staff/users)
- 🌐 VPN account creation
- 📱 View and share credentials
- 📊 Real-time status tracking
- ⚙️ Custom limits per client
- 🔒 Secure password management

### Advanced Features
- **3-Level Limit System** - Control at customer, client, and total VPN levels
- **VPN Credentials Pool** - Pre-create and auto-assign SSTP/V2Ray credentials
- **Status Management** - Suspend/disable with automatic server synchronization
- **RADIUS Integration** - Fully automated SSTP/V2Ray user management
- **Activity Logging** - Complete audit trail
- **Admin Notifications** - Real-time alerts for manual actions

---

## 🚀 Quick Start (Development)

### Prerequisites
- Docker
- Docker Compose
- Git

### Installation

```bash
# Clone repository
git clone https://github.com/zawnaing-2024/Vmaster.git
cd Vmaster

# Start with Docker Compose
docker-compose up -d

# Access the portal
# Admin: http://localhost/admin/login.php
# Customer: http://localhost/customer/login.php

# Default credentials
# Username: admin
# Password: admin123
```

### With RADIUS Integration

```bash
# Use RADIUS-enabled Docker Compose
docker-compose -f docker-compose-radius.yml up -d

# Import databases
docker exec -i vpn_cms_db mysql -uroot -prootpassword vpn_cms_portal < database/schema.sql
docker exec -i radius_db mysql -uradius -pradiuspass radius < radius/schema.sql

# Enable RADIUS in config
nano config/radius.php
# Set: RADIUS_ENABLED = true
```

---

## 📚 Documentation

- **[Production Deployment Guide](PRODUCTION_DEPLOYMENT.md)** - Deploy to Ubuntu 22.04
- **[RADIUS Setup Guide](RADIUS_SSTP_SETUP_GUIDE.md)** - Complete RADIUS integration
- **[Quick RADIUS Setup](QUICK_RADIUS_SETUP.md)** - 3-step RADIUS setup
- **[SSTP/V2Ray Setup](SSTP_V2RAY_SETUP.md)** - Configure SSTP and V2Ray servers
- **[How RADIUS Works](HOW_RADIUS_WORKS.md)** - Understanding RADIUS authentication
- **[Update Guide](UPDATE_GUIDE.md)** - Zero-downtime updates
- **[Troubleshooting](TROUBLESHOOTING.md)** - Common issues and solutions

---

## 🏗️ Architecture

```
┌─────────────────┐
│  Admin Panel    │ ← Manage servers, customers, notifications
└────────┬────────┘
         │
    ┌────▼─────┐
    │  VMaster │ ← Core system
    │   CMS    │
    └────┬─────┘
         │
         ├─────────► Outline API (Create/Delete keys)
         ├─────────► RADIUS DB (SSTP/V2Ray auth)
         └─────────► MySQL (Data storage)
         
┌─────────────────┐
│ Customer Portal │ ← Create clients & VPN accounts
└─────────────────┘
```

---

## 💻 Technology Stack

- **Backend:** PHP 8.2 (Pure PHP, no frameworks)
- **Database:** MySQL 8.0
- **Web Server:** Apache 2.4
- **Containerization:** Docker & Docker Compose
- **Authentication:** FreeRADIUS (optional)
- **VPN APIs:** Outline Management API

---

## 📊 Database Schema

### Core Tables (8 total):
- `admins` - Admin users
- `customers` - Your customers
- `client_accounts` - Customer's staff/users
- `vpn_servers` - VPN server configurations
- `vpn_accounts` - VPN accounts created
- `vpn_credentials_pool` - Pre-created SSTP/V2Ray credentials
- `admin_notifications` - Notification system
- `activity_logs` - Audit trail

### RADIUS Tables (5 total):
- `radcheck` - User credentials
- `radacct` - Accounting/usage data
- `radreply` - User-specific attributes
- `radgroupcheck` - Group settings
- `radusergroup` - User-group assignments

---

## 🔐 Security Features

- ✅ Bcrypt password hashing
- ✅ SQL injection prevention (PDO prepared statements)
- ✅ XSS protection (input sanitization)
- ✅ Session-based authentication
- ✅ Role-based access control
- ✅ Activity logging
- ✅ Secure API communication (cURL with SSL)

---

## 🎯 Use Cases

### Service Providers
- Sell VPN services to customers
- Customers manage their own clients
- Automated billing integration ready
- Multi-server load balancing

### Enterprises
- Manage VPN access for departments
- Control access at user level
- Audit trail for compliance
- Centralized credential management

### Resellers
- White-label ready
- Multi-tenant architecture
- Per-customer limits and quotas
- Automated provisioning

---

## 📦 Production Deployment

See **[PRODUCTION_DEPLOYMENT.md](PRODUCTION_DEPLOYMENT.md)** for complete guide including:

- Ubuntu 22.04 setup
- Docker installation
- SSL/TLS configuration
- Nginx reverse proxy
- Automatic backups
- Monitoring setup
- Security hardening
- Update procedures

---

## 🔄 Update Strategy

### Zero-Downtime Updates

```bash
# Pull latest code
git pull origin main

# Backup database
./scripts/backup.sh

# Update containers
docker-compose down
docker-compose up -d --build

# Run migrations
docker exec vpn_cms_web php admin/run-migration.php
```

See **[UPDATE_GUIDE.md](UPDATE_GUIDE.md)** for detailed update procedures.

---

## 🤝 Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

---

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 🆘 Support

- 📖 Documentation: See `/docs` folder
- 🐛 Issues: [GitHub Issues](https://github.com/zawnaing-2024/Vmaster/issues)
- 💬 Discussions: [GitHub Discussions](https://github.com/zawnaing-2024/Vmaster/discussions)

---

## 🙏 Acknowledgments

- **Outline VPN** - For the excellent VPN solution and API
- **SoftEther VPN** - For robust SSTP support
- **FreeRADIUS** - For authentication server
- **Docker** - For containerization

---

## 📸 Screenshots

### Admin Dashboard
![Admin Dashboard](docs/screenshots/admin-dashboard.png)

### Customer Portal
![Customer Portal](docs/screenshots/customer-portal.png)

### RADIUS Management
![RADIUS Management](docs/screenshots/radius-management.png)

---

## 🔗 Links

- **Repository:** https://github.com/zawnaing-2024/Vmaster
- **Documentation:** [Full Docs](docs/)
- **Issues:** [Report Bug](https://github.com/zawnaing-2024/Vmaster/issues/new)

---

**VMaster - Simplifying VPN Management** 🚀

Made with ❤️ for service providers and enterprises
