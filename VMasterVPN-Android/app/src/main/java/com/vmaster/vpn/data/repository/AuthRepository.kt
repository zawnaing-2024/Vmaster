package com.vmaster.vpn.data.repository

import com.vmaster.vpn.data.api.ApiClient
import com.vmaster.vpn.data.local.PreferencesManager
import com.vmaster.vpn.data.models.LoginRequest
import com.vmaster.vpn.data.models.LoginResponse

class AuthRepository(private val preferencesManager: PreferencesManager) {
    
    private val apiService = ApiClient.apiService
    
    suspend fun login(username: String, password: String): Result<LoginResponse> {
        return try {
            val response = apiService.login(LoginRequest(username, password))
            
            if (response.isSuccessful && response.body() != null) {
                val loginResponse = response.body()!!
                
                if (loginResponse.success && loginResponse.token != null) {
                    // Save token and user info
                    preferencesManager.saveToken(loginResponse.token)
                    preferencesManager.saveClientInfo(
                        loginResponse.clientId ?: 0,
                        loginResponse.clientName ?: "",
                        loginResponse.customerCompany ?: ""
                    )
                    Result.success(loginResponse)
                } else {
                    Result.failure(Exception(loginResponse.message))
                }
            } else {
                Result.failure(Exception("Login failed: ${response.message()}"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    fun logout() {
        preferencesManager.logout()
    }
    
    fun isLoggedIn(): Boolean {
        return preferencesManager.isLoggedIn() && preferencesManager.getToken() != null
    }
    
    fun getClientName(): String? {
        return preferencesManager.getClientName()
    }
    
    fun getCustomerCompany(): String? {
        return preferencesManager.getCustomerCompany()
    }
}

