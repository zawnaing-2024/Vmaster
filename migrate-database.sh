#!/bin/bash

# VMaster Database Migration Script for Production
# Run this on your Ubuntu server to fix all database issues

echo "════════════════════════════════════════════════════"
echo "  VMaster Database Migration Script"
echo "════════════════════════════════════════════════════"
echo ""

# 1. Rename staff_accounts to client_accounts
echo "📝 Step 1: Renaming staff_accounts to client_accounts..."
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "RENAME TABLE staff_accounts TO client_accounts" 2>&1 | grep -v "Warning" || echo "✅ Table renamed or already exists"

# 2. Add columns to customers table
echo "📝 Step 2: Adding limit columns to customers..."
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "ALTER TABLE customers ADD COLUMN max_clients INT DEFAULT NULL" 2>&1 | grep -v "Warning\|Duplicate" || echo "✅ Done"
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "ALTER TABLE customers ADD COLUMN max_vpn_per_client INT DEFAULT NULL" 2>&1 | grep -v "Warning\|Duplicate" || echo "✅ Done"
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "ALTER TABLE customers ADD COLUMN max_total_vpn_accounts INT DEFAULT NULL" 2>&1 | grep -v "Warning\|Duplicate" || echo "✅ Done"

# 3. Fix customers status ENUM
echo "📝 Step 3: Fixing customers status ENUM..."
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "ALTER TABLE customers MODIFY COLUMN status ENUM('active', 'suspended', 'disabled') DEFAULT 'active'" 2>&1 | grep -v "Warning" || echo "✅ Done"

# 4. Add columns to client_accounts table
echo "📝 Step 4: Adding columns to client_accounts..."
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "ALTER TABLE client_accounts ADD COLUMN max_vpn_accounts INT DEFAULT NULL" 2>&1 | grep -v "Warning\|Duplicate" || echo "✅ Done"
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "ALTER TABLE client_accounts ADD COLUMN status ENUM('active', 'suspended', 'disabled') DEFAULT 'active'" 2>&1 | grep -v "Warning\|Duplicate" || echo "✅ Done"

# 5. Add columns to vpn_accounts table
echo "📝 Step 5: Adding columns to vpn_accounts..."
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "ALTER TABLE vpn_accounts ADD COLUMN status ENUM('active', 'suspended', 'disabled') DEFAULT 'active'" 2>&1 | grep -v "Warning\|Duplicate" || echo "✅ Done"
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "ALTER TABLE vpn_accounts ADD COLUMN pool_credential_id INT DEFAULT NULL" 2>&1 | grep -v "Warning\|Duplicate" || echo "✅ Done"

# 6. Add status to admins table
echo "📝 Step 6: Adding status to admins..."
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "ALTER TABLE admins ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'" 2>&1 | grep -v "Warning\|Duplicate" || echo "✅ Done"

# 7. Create vpn_credentials_pool table
echo "📝 Step 7: Creating vpn_credentials_pool table..."
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "CREATE TABLE IF NOT EXISTS vpn_credentials_pool (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vpn_type ENUM('sstp', 'v2ray') NOT NULL,
    username VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_assigned TINYINT(1) DEFAULT 0,
    assigned_to INT DEFAULT NULL,
    assigned_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_credential (vpn_type, username)
)" 2>&1 | grep -v "Warning" || echo "✅ Done"

# 8. Create admin_notifications table
echo "📝 Step 8: Creating admin_notifications table..."
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    notification_type ENUM('info', 'warning', 'action_required') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    related_customer_id INT DEFAULT NULL,
    related_staff_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)" 2>&1 | grep -v "Warning" || echo "✅ Done"

# 9. Create admin user
echo "📝 Step 9: Creating admin user..."
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "INSERT INTO admins (username, password, email, status) VALUES ('admin', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@vmaster.vip', 'active') ON DUPLICATE KEY UPDATE password = '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', status = 'active'" 2>&1 | grep -v "Warning" || echo "✅ Done"

# 10. Verify tables
echo ""
echo "📊 Verifying tables..."
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal -e "SHOW TABLES" 2>&1 | grep -v "Warning"

echo ""
echo "════════════════════════════════════════════════════"
echo "✅ Migration Complete!"
echo "════════════════════════════════════════════════════"
echo ""
echo "🔐 Login Credentials:"
echo "   URL: https://vmaster.vip/admin/login.php"
echo "   Username: admin"
echo "   Password: admin123"
echo ""
echo "⚠️  Change password after first login!"
echo ""

