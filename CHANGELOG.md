# VMaster CMS Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2025-10-07

### 🎉 Initial Release

#### Features
- **Multi-VPN Server Support**
  - Outline VPN with real API integration
  - SSTP (SoftEther) support
  - V2Ray support
  - Manage all VPN types from single portal

- **Admin Panel**
  - VPN server management (add/edit/delete servers)
  - Customer management with custom limits
  - Client account management
  - VPN account overview
  - VPN credentials pool system
  - Admin notifications center
  - Activity logs and audit trail
  - RADIUS user management GUI
  - Change password functionality

- **Customer Portal**
  - Client management (create/edit/suspend/disable)
  - VPN account creation with server selection
  - Real-time VPN account status
  - View and share VPN credentials
  - Custom limits per client
  - Change password functionality

- **3-Level Limit System**
  - Total VPN accounts per customer
  - Max clients per customer
  - Max VPN accounts per client
  - Unlimited option support

- **VPN Credentials Pool**
  - Pre-create SSTP/V2Ray credentials
  - Bulk import functionality
  - Automatic assignment system
  - Pool statistics and tracking

- **Status Management System**
  - Client status: active, suspended, disabled
  - VPN account status synchronization
  - Automatic Outline key deletion on disable
  - Admin notifications for SSTP/V2Ray manual actions

- **RADIUS Integration** (Optional)
  - FreeRADIUS support for SSTP/V2Ray
  - Automatic user creation/deletion
  - Suspend/reactivate functionality
  - Password management
  - Built-in RADIUS management GUI
  - Docker-based RADIUS setup

- **Security Features**
  - Bcrypt password hashing
  - Session-based authentication
  - SQL injection prevention (PDO prepared statements)
  - XSS protection (sanitization)
  - Activity logging
  - Role-based access control

- **User Experience**
  - Modern, responsive UI
  - VMaster branding
  - Real-time status badges
  - Intuitive navigation
  - Mobile-friendly design
  - Currency support (Ks)

#### Technical Stack
- PHP 8.2
- MySQL 8.0
- Apache 2.4
- Docker & Docker Compose
- PDO for database operations
- cURL for Outline API integration
- FreeRADIUS (optional)

#### Documentation
- Complete setup guides
- RADIUS integration guide
- Production deployment guide
- SSTP/V2Ray setup guide
- Troubleshooting documentation
- API integration guides
- Quick start guides

---

## Development Notes

### Database Schema
- 8 core tables
- Foreign key relationships
- Optimized indexes
- ENUM types for statuses
- Automatic timestamps

### Docker Setup
- Multi-container architecture
- Separate RADIUS database
- phpMyAdmin for management
- Volume persistence
- Network isolation

### Future Roadmap
- [ ] OpenVPN support
- [ ] WireGuard support
- [ ] Usage statistics and billing
- [ ] Email notifications
- [ ] Two-factor authentication
- [ ] API for external integrations
- [ ] Mobile app
- [ ] Backup/restore functionality
- [ ] Multi-language support

---

**Version 1.0.0 represents a fully functional VPN management system with RADIUS integration and automated account management.**

