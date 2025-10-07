<?php
require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_FULL_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .vmaster-logo {
            font-size: 4rem;
            font-weight: 800;
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
            letter-spacing: -2px;
        }
        .tagline {
            font-size: 1.3rem;
            color: var(--text-muted);
            margin-bottom: 50px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="welcome-container">
        <div class="welcome-card">
            <div class="vmaster-logo">VMaster</div>
            <p class="tagline">Professional VPN Management System</p>
            
            <div class="features">
                <div class="feature-item">
                    <div class="feature-icon">🚀</div>
                    <h3>Multi-Server Support</h3>
                    <p>Outline, V2Ray, and SSTP in one platform</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">👥</div>
                    <h3>Customer Portal</h3>
                    <p>Self-service VPN management</p>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">⚡</div>
                    <h3>Client VPN Access</h3>
                    <p>Instant VPN provisioning for clients</p>
                </div>
            </div>
            
            <div class="login-buttons">
                <a href="/admin/login.php" class="btn btn-primary">🔐 Admin Login</a>
                <a href="/customer/login.php" class="btn btn-secondary">👤 Customer Login</a>
            </div>
            
            <div style="margin-top: 40px; padding-top: 30px; border-top: 1px solid #e2e8f0;">
                <p style="color: #94a3b8; font-size: 0.9rem;">
                    Powered by VMaster © 2025
                </p>
            </div>
        </div>
    </div>
</body>
</html>

