# 🏗️ System Architecture

## Overview

The VPN CMS Portal is a three-tier web application designed to manage multiple VPN servers and customer VPN accounts efficiently.

## System Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                         VPN CMS Portal                           │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ├── Admin Panel
                              │   ├── Server Management
                              │   ├── Customer Management  
                              │   ├── Activity Monitoring
                              │   └── System Overview
                              │
                              └── Customer Panel
                                  ├── Staff Management
                                  ├── VPN Account Creation
                                  └── Credential Sharing
```

## User Roles & Permissions

### 👨‍💼 Administrator
- Full system access
- Manage VPN servers (add/edit/delete)
- Manage customer accounts (create/suspend/delete)
- View all staff and VPN accounts
- Monitor activity logs
- System configuration

### 👤 Customer
- Limited to their own data
- Manage their staff members (within quota)
- Create VPN accounts for staff
- View and share VPN credentials
- Choose from available VPN servers

### 👥 Staff
- No direct login access
- VPN accounts created by customer
- Receive VPN credentials from customer

## Data Flow

### Creating a VPN Account

```
1. Customer Login
   ↓
2. Navigate to Staff Management
   ↓
3. Add Staff Member
   ↓
4. Navigate to VPN Accounts
   ↓
5. Select Staff & Server
   ↓
6. System Generates Credentials
   │
   ├── Outline: ss:// access key
   ├── V2Ray: vmess:// link
   └── SSTP: username/password
   ↓
7. View/Share Credentials
   ↓
8. Staff Receives Credentials
   ↓
9. Staff Connects to VPN
```

## Database Schema

### Core Tables

```
admins
├── id (PK)
├── username
├── password (hashed)
├── full_name
├── email
└── created_at

customers
├── id (PK)
├── username
├── password (hashed)
├── company_name
├── full_name
├── email
├── max_staff_accounts
├── status
├── created_by (FK → admins.id)
└── created_at

vpn_servers
├── id (PK)
├── server_name
├── server_type (outline/v2ray/sstp)
├── server_host
├── server_port
├── api_url
├── api_key
├── max_accounts
├── current_accounts
├── status
├── location
└── created_at

staff_accounts
├── id (PK)
├── customer_id (FK → customers.id)
├── staff_name
├── staff_email
├── department
├── status
└── created_at

vpn_accounts
├── id (PK)
├── customer_id (FK → customers.id)
├── staff_id (FK → staff_accounts.id)
├── server_id (FK → vpn_servers.id)
├── account_username
├── account_password
├── access_key
├── config_data (JSON)
├── status
└── created_at

activity_logs
├── id (PK)
├── user_type (admin/customer)
├── user_id
├── action
├── description
├── ip_address
└── created_at
```

## VPN Account Generation

### Outline VPN
```php
Method: Shadowsocks Protocol
Format: ss://[base64(method:password)]@host:port
Example: ss://YWVzLTI1Ni1nY206cGFzc3dvcmQ=@1.2.3.4:443
```

### V2Ray
```php
Method: VMess Protocol
Format: vmess://[base64(json_config)]
Config: {
  "v": "2",
  "ps": "server_name",
  "add": "host",
  "port": "port",
  "id": "uuid",
  "aid": "0",
  "net": "tcp",
  "type": "none",
  "tls": "tls"
}
```

### SSTP
```php
Method: Username/Password Authentication
Credentials: {
  "username": "vpn_xxxxxxxx",
  "password": "random_secure_password",
  "server": "host",
  "port": "port"
}
```

## Security Features

### Authentication
- Password hashing using `password_hash()` (bcrypt)
- Session-based authentication
- Role-based access control (RBAC)
- Automatic session timeout

### Data Protection
- SQL injection prevention (prepared statements)
- XSS protection (input sanitization)
- CSRF protection (session validation)
- Secure credential storage

### Activity Logging
- All user actions logged
- IP address tracking
- User agent logging
- Timestamp recording

## Docker Architecture

```
┌─────────────────────────────────────────────────────┐
│                   Docker Host                        │
│                                                      │
│  ┌──────────────────────────────────────────────┐  │
│  │         vpn_cms_web (PHP/Apache)             │  │
│  │  - Port 8080:80                              │  │
│  │  - PHP 8.2                                   │  │
│  │  - Apache 2.4                                │  │
│  └──────────────────┬───────────────────────────┘  │
│                     │                               │
│  ┌──────────────────▼───────────────────────────┐  │
│  │         vpn_cms_db (MySQL 8.0)               │  │
│  │  - Port 3306                                 │  │
│  │  - Persistent volume                         │  │
│  └──────────────────────────────────────────────┘  │
│                                                      │
│  ┌──────────────────────────────────────────────┐  │
│  │      vpn_cms_phpmyadmin (phpMyAdmin)         │  │
│  │  - Port 8081:80                              │  │
│  │  - Database management                       │  │
│  └──────────────────────────────────────────────┘  │
│                                                      │
└─────────────────────────────────────────────────────┘
```

## Technology Stack

### Backend
- **PHP 8.2**: Core application logic
- **MySQL 8.0**: Database management
- **PDO**: Database abstraction layer

### Frontend
- **HTML5**: Markup
- **CSS3**: Styling (custom, no frameworks)
- **Vanilla JavaScript**: Client-side logic

### Infrastructure
- **Docker**: Containerization
- **Docker Compose**: Multi-container orchestration
- **Apache 2.4**: Web server

## Performance Considerations

### Database Optimization
- Indexed foreign keys
- Efficient query design
- Connection pooling via PDO

### Application Optimization
- Session management
- Prepared statements
- Minimal external dependencies

### Scalability
- Horizontal scaling possible (add more web containers)
- Database replication supported
- Stateless application design

## Integration Points

### VPN Server Integration
The system is designed to integrate with actual VPN server APIs:

1. **Outline Server**: REST API integration ready
2. **V2Ray**: Can connect to V2Ray management API
3. **SSTP**: Can integrate with RADIUS or AD authentication

Currently generates valid credential formats that can be used with actual servers.

## Backup & Recovery

### Database Backup
```bash
docker exec vpn_cms_db mysqldump -u root -p vpn_cms_portal > backup.sql
```

### Full System Backup
```bash
tar -czf vpn-cms-backup.tar.gz \
  docker-compose.yml \
  config/ \
  database/ \
  uploads/
```

### Restoration
```bash
# Restore database
docker exec -i vpn_cms_db mysql -u root -p vpn_cms_portal < backup.sql

# Restore files
tar -xzf vpn-cms-backup.tar.gz
```

## Monitoring

### Application Logs
- Activity logs table tracks all user actions
- Docker logs: `docker-compose logs -f`

### Database Monitoring
- phpMyAdmin for visual monitoring
- MySQL command line tools

### Server Monitoring
- Track VPN account counts per server
- Monitor server capacity
- Status tracking (active/maintenance/inactive)

## Future Enhancements

Potential areas for expansion:
- Real-time VPN server API integration
- Bandwidth usage tracking
- Automated server health checks
- Customer billing integration
- Multi-language support
- 2FA authentication
- Email notifications
- API for third-party integrations
- Mobile application

---

**This architecture provides a solid foundation for managing VPN infrastructure at scale while maintaining security and ease of use.**

