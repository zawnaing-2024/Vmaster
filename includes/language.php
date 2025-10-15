<?php
// Language management system

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle language switching FIRST (before any output)
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'zh'])) {
    $_SESSION['language'] = $_GET['lang'];
    
    // Redirect to same page without lang parameter
    $redirect_url = strtok($_SERVER["REQUEST_URI"], '?');
    if (!empty($_SERVER['QUERY_STRING'])) {
        parse_str($_SERVER['QUERY_STRING'], $params);
        unset($params['lang']);
        if (!empty($params)) {
            $redirect_url .= '?' . http_build_query($params);
        }
    }
    header("Location: $redirect_url");
    exit();
}

// Set default language if not set
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en'; // Default to English
}

// Get current language
function getCurrentLanguage() {
    return $_SESSION['language'] ?? 'en';
}

// Load language file
function loadLanguage($section = 'common') {
    $lang = getCurrentLanguage();
    $langFile = __DIR__ . "/../languages/{$lang}/{$section}.php";
    
    if (file_exists($langFile)) {
        return require $langFile;
    }
    
    // Fallback to English if file not found
    $fallbackFile = __DIR__ . "/../languages/en/{$section}.php";
    if (file_exists($fallbackFile)) {
        return require $fallbackFile;
    }
    
    return [];
}

// Get translation
function t($key, $section = 'common', $default = null) {
    static $cache = [];
    
    // Load section if not cached
    if (!isset($cache[$section])) {
        $cache[$section] = loadLanguage($section);
    }
    
    // Get translation
    $keys = explode('.', $key);
    $value = $cache[$section];
    
    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $default ?? $key;
        }
    }
    
    return $value;
}

// Get language name
function getLanguageName($code) {
    $languages = [
        'en' => 'English',
        'zh' => '中文'
    ];
    return $languages[$code] ?? $code;
}

// Get available languages
function getAvailableLanguages() {
    return [
        'en' => 'English',
        'zh' => '中文'
    ];
}