package com.vmaster.vpn.data.models

import com.google.gson.annotations.SerializedName

data class VpnAccount(
    @SerializedName("id")
    val id: Int,
    
    @SerializedName("server_name")
    val serverName: String,
    
    @SerializedName("server_type")
    val serverType: String, // "outline", "sstp", "v2ray"
    
    @SerializedName("server_host")
    val serverHost: String,
    
    @SerializedName("server_port")
    val serverPort: Int,
    
    @SerializedName("location")
    val location: String?,
    
    @SerializedName("status")
    val status: String, // "active", "suspended", "inactive"
    
    @SerializedName("expiration_status")
    val expirationStatus: String, // "active", "expired", "unlimited"
    
    @SerializedName("plan_duration")
    val planDuration: Int?,
    
    @SerializedName("expires_at")
    val expiresAt: String?,
    
    @SerializedName("days_remaining")
    val daysRemaining: Int?,
    
    @SerializedName("created_at")
    val createdAt: String,
    
    // Protocol-specific fields
    @SerializedName("access_key")
    val accessKey: String?,
    
    @SerializedName("username")
    val username: String?,
    
    @SerializedName("password")
    val password: String?,
    
    @SerializedName("protocol")
    val protocol: String?,
    
    @SerializedName("v2ray_config")
    val v2rayConfig: V2RayConfig?
) {
    fun getDisplayType(): String = when (serverType) {
        "outline" -> "Outline"
        "sstp" -> "SSTP"
        "v2ray" -> "V2Ray"
        else -> serverType.uppercase()
    }
    
    fun isExpired(): Boolean = expirationStatus == "expired"
    
    fun isActive(): Boolean = status == "active" && !isExpired()
    
    fun getExpiryText(): String = when {
        expirationStatus == "unlimited" -> "Never expires"
        expirationStatus == "expired" -> "Expired"
        daysRemaining != null -> "$daysRemaining days remaining"
        else -> "Unknown"
    }
}

data class V2RayConfig(
    @SerializedName("v")
    val version: String,
    
    @SerializedName("ps")
    val name: String,
    
    @SerializedName("add")
    val address: String,
    
    @SerializedName("port")
    val port: Int,
    
    @SerializedName("id")
    val uuid: String,
    
    @SerializedName("aid")
    val alterId: Int,
    
    @SerializedName("net")
    val network: String,
    
    @SerializedName("type")
    val type: String,
    
    @SerializedName("tls")
    val tls: String?
)

