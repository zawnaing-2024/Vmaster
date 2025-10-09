package com.vmaster.vpn.ui.dashboard

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.vmaster.vpn.data.local.PreferencesManager
import com.vmaster.vpn.data.models.VpnAccount
import com.vmaster.vpn.data.repository.AuthRepository
import com.vmaster.vpn.data.repository.VpnRepository
import com.vmaster.vpn.utils.Constants
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

class DashboardViewModel(application: Application) : AndroidViewModel(application) {
    
    private val preferencesManager = PreferencesManager(application)
    private val authRepository = AuthRepository(preferencesManager)
    private val vpnRepository = VpnRepository(preferencesManager)
    
    private val _vpnAccounts = MutableStateFlow<List<VpnAccount>>(emptyList())
    val vpnAccounts: StateFlow<List<VpnAccount>> = _vpnAccounts
    
    private val _isLoading = MutableStateFlow(false)
    val isLoading: StateFlow<Boolean> = _isLoading
    
    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error
    
    private val _connectionStatus = MutableStateFlow<String?>(null)
    val connectionStatus: StateFlow<String?> = _connectionStatus
    
    private var currentConnectedAccount: VpnAccount? = null
    
    fun loadVpnAccounts() {
        viewModelScope.launch {
            _isLoading.value = true
            _error.value = null
            
            val result = vpnRepository.getVpnAccounts()
            
            _isLoading.value = false
            
            if (result.isSuccess) {
                _vpnAccounts.value = result.getOrNull() ?: emptyList()
            } else {
                _error.value = result.exceptionOrNull()?.message ?: "Failed to load accounts"
            }
        }
    }
    
    fun toggleVpnConnection(account: VpnAccount) {
        if (currentConnectedAccount?.id == account.id) {
            // Disconnect
            disconnectVpn()
        } else {
            // Connect
            connectVpn(account)
        }
    }
    
    private fun connectVpn(account: VpnAccount) {
        viewModelScope.launch {
            _connectionStatus.value = Constants.STATUS_CONNECTING
            
            try {
                // TODO: Implement actual VPN connection based on type
                when (account.serverType) {
                    Constants.VPN_TYPE_OUTLINE -> connectOutline(account)
                    Constants.VPN_TYPE_SSTP -> connectSstp(account)
                    Constants.VPN_TYPE_V2RAY -> connectV2ray(account)
                }
                
                currentConnectedAccount = account
                _connectionStatus.value = Constants.STATUS_CONNECTED
                
                // Report status to server
                vpnRepository.reportConnectionStatus(
                    accountId = account.id,
                    status = Constants.STATUS_CONNECTED
                )
                
            } catch (e: Exception) {
                _connectionStatus.value = Constants.STATUS_DISCONNECTED
                _error.value = "Connection failed: ${e.message}"
            }
        }
    }
    
    private fun disconnectVpn() {
        viewModelScope.launch {
            try {
                // TODO: Implement actual VPN disconnection
                
                currentConnectedAccount?.let { account ->
                    // Report disconnection to server
                    vpnRepository.reportConnectionStatus(
                        accountId = account.id,
                        status = Constants.STATUS_DISCONNECTED
                    )
                }
                
                currentConnectedAccount = null
                _connectionStatus.value = Constants.STATUS_DISCONNECTED
                
            } catch (e: Exception) {
                _error.value = "Disconnection failed: ${e.message}"
            }
        }
    }
    
    private fun connectOutline(account: VpnAccount) {
        // TODO: Implement Outline VPN connection
        // Use Shadowsocks library
    }
    
    private fun connectSstp(account: VpnAccount) {
        // TODO: Implement SSTP VPN connection
    }
    
    private fun connectV2ray(account: VpnAccount) {
        // TODO: Implement V2Ray VPN connection
    }
    
    fun logout() {
        authRepository.logout()
    }
    
    fun getClientName(): String? {
        return authRepository.getClientName()
    }
    
    fun getCustomerCompany(): String? {
        return authRepository.getCustomerCompany()
    }
}

