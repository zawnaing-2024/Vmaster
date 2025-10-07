<aside class="sidebar">
    <div class="sidebar-header">
        <h2 style="font-size: 1.8rem; font-weight: 800; background: linear-gradient(135deg, #a855f7 0%, #6366f1 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">VMaster</h2>
        <p>Customer Portal</p>
    </div>
    
    <nav class="sidebar-menu">
        <a href="/customer/index.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            📊 Dashboard
        </a>
        <a href="/customer/clients.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'clients.php' ? 'active' : ''; ?>">
            👤 My Clients
        </a>
        <a href="/customer/vpn-accounts.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'vpn-accounts.php' ? 'active' : ''; ?>">
            🔑 VPN Accounts
        </a>
        <a href="/customer/change-password.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'change-password.php' ? 'active' : ''; ?>">
            🔐 Change Password
        </a>
        <a href="/customer/logout.php" class="menu-item">
            🚪 Logout
        </a>
    </nav>
</aside>

