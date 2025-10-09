package com.vmaster.vpn.data.repository

import com.vmaster.vpn.data.api.ApiClient
import com.vmaster.vpn.data.local.PreferencesManager
import com.vmaster.vpn.data.models.ConnectionStatusRequest
import com.vmaster.vpn.data.models.VpnAccount
import java.text.SimpleDateFormat
import java.util.*

class VpnRepository(private val preferencesManager: PreferencesManager) {
    
    private val apiService = ApiClient.apiService
    
    suspend fun getVpnAccounts(): Result<List<VpnAccount>> {
        return try {
            val token = preferencesManager.getToken()
                ?: return Result.failure(Exception("Not logged in"))
            
            val response = apiService.getVpnAccounts("Bearer $token")
            
            if (response.isSuccessful && response.body() != null) {
                val accountsResponse = response.body()!!
                
                if (accountsResponse.success) {
                    Result.success(accountsResponse.accounts)
                } else {
                    Result.failure(Exception(accountsResponse.message ?: "Failed to fetch accounts"))
                }
            } else {
                Result.failure(Exception("Failed to fetch accounts: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun reportConnectionStatus(
        accountId: Int,
        status: String,
        ipAddress: String? = null
    ): Result<Boolean> {
        return try {
            val token = preferencesManager.getToken()
                ?: return Result.failure(Exception("Not logged in"))
            
            val dateFormat = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
            val currentTime = dateFormat.format(Date())
            
            val request = ConnectionStatusRequest(
                accountId = accountId,
                status = status,
                connectedAt = currentTime,
                ipAddress = ipAddress
            )
            
            val response = apiService.reportConnectionStatus("Bearer $token", request)
            
            if (response.isSuccessful && response.body() != null) {
                val statusResponse = response.body()!!
                
                if (statusResponse.success) {
                    Result.success(true)
                } else {
                    Result.failure(Exception(statusResponse.message))
                }
            } else {
                Result.failure(Exception("Failed to report status: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}

