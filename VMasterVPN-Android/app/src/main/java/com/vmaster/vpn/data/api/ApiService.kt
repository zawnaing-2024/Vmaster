package com.vmaster.vpn.data.api

import com.vmaster.vpn.data.models.*
import retrofit2.Response
import retrofit2.http.*

interface ApiService {
    
    @POST("api/client/login.php")
    suspend fun login(
        @Body request: LoginRequest
    ): Response<LoginResponse>
    
    @GET("api/client/vpn-accounts.php")
    suspend fun getVpnAccounts(
        @Header("Authorization") authorization: String
    ): Response<VpnAccountsResponse>
    
    @POST("api/client/connection-status.php")
    suspend fun reportConnectionStatus(
        @Header("Authorization") authorization: String,
        @Body request: ConnectionStatusRequest
    ): Response<BaseResponse>
}

