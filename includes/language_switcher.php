<?php
// Language Switcher Component
// Include this in header or navigation

require_once __DIR__ . '/language.php';

$currentLang = getCurrentLanguage();
$availableLanguages = getAvailableLanguages();
$currentPage = $_SERVER['PHP_SELF'];
?>

<style>
.language-switcher {
    position: relative;
    display: inline-block;
}

.language-switcher .dropdown-toggle {
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #fff;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.language-switcher .dropdown-toggle:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.5);
}

.language-switcher .dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    min-width: 150px;
    margin-top: 5px;
    display: none;
    z-index: 1000;
}

.language-switcher .dropdown-menu.show {
    display: block;
}

.language-switcher .dropdown-item {
    padding: 10px 15px;
    cursor: pointer;
    color: #333;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background 0.2s ease;
}

.language-switcher .dropdown-item:hover {
    background: #f8f9fa;
}

.language-switcher .dropdown-item.active {
    background: #007bff;
    color: #fff;
}

.language-switcher .lang-flag {
    font-size: 20px;
}

.language-switcher .lang-icon {
    font-size: 16px;
}
</style>

<div class="language-switcher">
    <button class="dropdown-toggle" id="languageDropdown" type="button">
        <span class="lang-icon">🌐</span>
        <span><?php echo getLanguageName($currentLang); ?></span>
        <span style="font-size: 10px;">▼</span>
    </button>
    
    <div class="dropdown-menu" id="languageMenu">
        <?php foreach ($availableLanguages as $code => $name): ?>
            <a href="?lang=<?php echo $code; ?>" 
               class="dropdown-item <?php echo $currentLang === $code ? 'active' : ''; ?>">
                <span class="lang-flag">
                    <?php echo $code === 'en' ? '🇬🇧' : '🇨🇳'; ?>
                </span>
                <span><?php echo $name; ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropdownToggle = document.getElementById('languageDropdown');
    const dropdownMenu = document.getElementById('languageMenu');
    
    if (dropdownToggle && dropdownMenu) {
        dropdownToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.language-switcher')) {
                dropdownMenu.classList.remove('show');
            }
        });
    }
});
</script>

