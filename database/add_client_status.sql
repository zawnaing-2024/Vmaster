-- Add status column to client_accounts if not exists
ALTER TABLE client_accounts 
ADD COLUMN IF NOT EXISTS status ENUM('active', 'suspended', 'disabled') DEFAULT 'active' AFTER max_vpn_accounts;

-- Create admin notifications table
CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    notification_type ENUM('client_suspended', 'client_disabled', 'vpn_manual_action', 'system_alert') NOT NULL,
    severity ENUM('info', 'warning', 'critical') DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_customer_id INT DEFAULT NULL,
    related_client_id INT DEFAULT NULL,
    action_required TEXT DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (related_customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (related_client_id) REFERENCES client_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for faster queries
CREATE INDEX idx_is_read ON admin_notifications(is_read);
CREATE INDEX idx_created_at ON admin_notifications(created_at);

