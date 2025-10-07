-- VPN CMS Portal Database Schema

CREATE DATABASE IF NOT EXISTS vpn_cms_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE vpn_cms_portal;

-- Admins Table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customers Table
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(50),
    status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
    max_staff_accounts INT DEFAULT 10,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- VPN Servers Table
CREATE TABLE IF NOT EXISTS vpn_servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_name VARCHAR(255) NOT NULL,
    server_type ENUM('outline', 'v2ray', 'sstp') NOT NULL,
    server_host VARCHAR(255) NOT NULL,
    server_port INT NOT NULL,
    api_url VARCHAR(500),
    api_key VARCHAR(500),
    api_secret VARCHAR(500),
    admin_username VARCHAR(255),
    admin_password VARCHAR(255),
    max_accounts INT DEFAULT 100,
    current_accounts INT DEFAULT 0,
    status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active',
    location VARCHAR(100),
    description TEXT,
    config_json TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_server_type (server_type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff Accounts (created by customers)
CREATE TABLE IF NOT EXISTS staff_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    staff_name VARCHAR(255) NOT NULL,
    staff_email VARCHAR(255),
    staff_phone VARCHAR(50),
    department VARCHAR(100),
    notes TEXT,
    status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    INDEX idx_customer_id (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- VPN Accounts (assigned to staff)
CREATE TABLE IF NOT EXISTS vpn_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    staff_id INT NOT NULL,
    server_id INT NOT NULL,
    account_username VARCHAR(255),
    account_password VARCHAR(255),
    access_key TEXT,
    config_data TEXT,
    qr_code_path VARCHAR(500),
    status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
    expires_at TIMESTAMP NULL,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (server_id) REFERENCES vpn_servers(id) ON DELETE CASCADE,
    INDEX idx_customer_id (customer_id),
    INDEX idx_staff_id (staff_id),
    INDEX idx_server_id (server_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity Logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin', 'customer') NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_type_id (user_type, user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin (username: admin, password: admin123)
INSERT INTO admins (username, password, full_name, email) 
VALUES ('admin', '$2y$10$3pVBrF6UBl3Tnit7wu.zDe.r17zXpBMctj1RMf2xOrBFVIWUCqDXS', 'System Administrator', 'admin@vpncms.local')
ON DUPLICATE KEY UPDATE id=id;

