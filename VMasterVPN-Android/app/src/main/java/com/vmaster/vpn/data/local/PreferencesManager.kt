package com.vmaster.vpn.data.local

import android.content.Context
import android.content.SharedPreferences
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import com.vmaster.vpn.utils.Constants

class PreferencesManager(context: Context) {
    
    private val masterKey = MasterKey.Builder(context)
        .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
        .build()
    
    private val sharedPreferences: SharedPreferences = EncryptedSharedPreferences.create(
        context,
        "vmaster_secure_prefs",
        masterKey,
        EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
        EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
    )
    
    fun saveToken(token: String) {
        sharedPreferences.edit().putString(Constants.PREF_TOKEN, token).apply()
    }
    
    fun getToken(): String? {
        return sharedPreferences.getString(Constants.PREF_TOKEN, null)
    }
    
    fun saveClientInfo(clientId: Int, clientName: String, customerCompany: String) {
        sharedPreferences.edit().apply {
            putInt(Constants.PREF_CLIENT_ID, clientId)
            putString(Constants.PREF_CLIENT_NAME, clientName)
            putString(Constants.PREF_CUSTOMER_COMPANY, customerCompany)
            putBoolean(Constants.PREF_IS_LOGGED_IN, true)
            apply()
        }
    }
    
    fun getClientId(): Int {
        return sharedPreferences.getInt(Constants.PREF_CLIENT_ID, 0)
    }
    
    fun getClientName(): String? {
        return sharedPreferences.getString(Constants.PREF_CLIENT_NAME, null)
    }
    
    fun getCustomerCompany(): String? {
        return sharedPreferences.getString(Constants.PREF_CUSTOMER_COMPANY, null)
    }
    
    fun isLoggedIn(): Boolean {
        return sharedPreferences.getBoolean(Constants.PREF_IS_LOGGED_IN, false)
    }
    
    fun logout() {
        sharedPreferences.edit().clear().apply()
    }
}

