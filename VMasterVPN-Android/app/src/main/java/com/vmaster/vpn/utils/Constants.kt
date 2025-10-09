package com.vmaster.vpn.utils

object Constants {
    // API Configuration
    const val API_BASE_URL = "http://10.0.2.2:8000/" // For Android Emulator (localhost)
    // For production: const val API_BASE_URL = "https://your-domain.com/"
    // For physical device on same network: const val API_BASE_URL = "http://192.168.x.x:8000/"
    
    // Preferences Keys
    const val PREF_TOKEN = "auth_token"
    const val PREF_CLIENT_ID = "client_id"
    const val PREF_CLIENT_NAME = "client_name"
    const val PREF_CUSTOMER_COMPANY = "customer_company"
    const val PREF_IS_LOGGED_IN = "is_logged_in"
    
    // VPN Types
    const val VPN_TYPE_OUTLINE = "outline"
    const val VPN_TYPE_SSTP = "sstp"
    const val VPN_TYPE_V2RAY = "v2ray"
    
    // Connection Status
    const val STATUS_CONNECTED = "connected"
    const val STATUS_DISCONNECTED = "disconnected"
    const val STATUS_CONNECTING = "connecting"
    
    // Notification
    const val NOTIFICATION_CHANNEL_ID = "vpn_connection"
    const val NOTIFICATION_ID = 1001
    
    // Timeouts
    const val CONNECTION_TIMEOUT = 30000L // 30 seconds
    const val READ_TIMEOUT = 30000L
    const val WRITE_TIMEOUT = 30000L
}

