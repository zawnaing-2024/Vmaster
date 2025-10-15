<?php require_once __DIR__ . '/../includes/language.php'; ?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div style="text-align: center; margin-bottom: 10px;">
            <img src="/assets/images/logo.jpg" alt="VMaster Logo" style="max-width: 120px; height: auto; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        </div>
        <h2 style="font-size: 1.5rem; font-weight: 800; background: linear-gradient(135deg, #a855f7 0%, #6366f1 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">VMaster</h2>
        <p><?php echo t('app_name', 'common'); ?></p>
        
        <!-- Language Switcher -->
        <div style="margin-top: 15px; padding: 0 10px;">
            <?php include __DIR__ . '/../includes/language_switcher.php'; ?>
        </div>
    </div>
    
    <nav class="sidebar-menu">
        <a href="/customer/index.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            📊 <?php echo t('dashboard', 'customer'); ?>
        </a>
        <a href="/customer/clients.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'clients.php' ? 'active' : ''; ?>">
            👤 <?php echo t('my_clients', 'customer'); ?>
        </a>
        <a href="/customer/vpn-accounts.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'vpn-accounts.php' ? 'active' : ''; ?>">
            🔑 <?php echo t('my_vpn_accounts', 'customer'); ?>
        </a>
        <a href="/customer/change-password.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'change-password.php' ? 'active' : ''; ?>">
            🔐 <?php echo t('change_password', 'customer'); ?>
        </a>
        <a href="/customer/logout.php" class="menu-item">
            🚪 <?php echo t('logout', 'common'); ?>
        </a>
    </nav>
</aside>

