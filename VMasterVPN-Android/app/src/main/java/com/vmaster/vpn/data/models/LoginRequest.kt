package com.vmaster.vpn.data.models

import com.google.gson.annotations.SerializedName

data class LoginRequest(
    @SerializedName("username")
    val username: String,
    
    @SerializedName("password")
    val password: String
)

data class LoginResponse(
    @SerializedName("success")
    val success: Boolean,
    
    @SerializedName("token")
    val token: String?,
    
    @SerializedName("client_id")
    val clientId: Int?,
    
    @SerializedName("client_name")
    val clientName: String?,
    
    @SerializedName("customer_company")
    val customerCompany: String?,
    
    @SerializedName("message")
    val message: String
)

data class VpnAccountsResponse(
    @SerializedName("success")
    val success: Boolean,
    
    @SerializedName("count")
    val count: Int,
    
    @SerializedName("accounts")
    val accounts: List<VpnAccount>,
    
    @SerializedName("message")
    val message: String?
)

data class ConnectionStatusRequest(
    @SerializedName("account_id")
    val accountId: Int,
    
    @SerializedName("status")
    val status: String, // "connected", "disconnected", "connecting"
    
    @SerializedName("connected_at")
    val connectedAt: String,
    
    @SerializedName("ip_address")
    val ipAddress: String?
)

data class BaseResponse(
    @SerializedName("success")
    val success: Boolean,
    
    @SerializedName("message")
    val message: String
)

