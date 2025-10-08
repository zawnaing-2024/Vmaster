<aside class="sidebar">
    <div class="sidebar-header">
        <h2 style="font-size: 1.8rem; font-weight: 800; background: linear-gradient(135deg, #a855f7 0%, #6366f1 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">VMaster</h2>
        <p>Admin Control Panel</p>
    </div>
    
    <nav class="sidebar-menu">
        <a href="/admin/index.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            📊 Dashboard
        </a>
        <a href="/admin/servers.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'servers.php' ? 'active' : ''; ?>">
            🖥️ VPN Servers
        </a>
        <a href="/admin/customers.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : ''; ?>">
            👥 Customers
        </a>
        <a href="/admin/clients.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'clients.php' ? 'active' : ''; ?>">
            👤 Client Accounts
        </a>
        <a href="/admin/vpn-accounts.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'vpn-accounts.php' ? 'active' : ''; ?>">
            🔑 VPN Accounts
        </a>
        <a href="/admin/vpn-pool.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'vpn-pool.php' ? 'active' : ''; ?>">
            🎱 VPN Credentials Pool
        </a>
        <a href="/admin/radius-clients.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'radius-clients.php' ? 'active' : ''; ?>">
            🔐 RADIUS Clients
        </a>
        <a href="/admin/activity-logs.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'activity-logs.php' ? 'active' : ''; ?>">
            📋 Activity Logs
        </a>
        <a href="/admin/change-password.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'change-password.php' ? 'active' : ''; ?>">
            🔐 Change Password
        </a>
        <a href="/admin/logout.php" class="menu-item">
            🚪 Logout
        </a>
    </nav>
</aside>

