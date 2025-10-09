package com.vmaster.vpn.ui.dashboard

import android.content.Intent
import android.os.Bundle
import android.view.Menu
import android.view.MenuItem
import android.widget.Toast
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import com.vmaster.vpn.R
import com.vmaster.vpn.databinding.ActivityMainBinding
import com.vmaster.vpn.ui.login.LoginActivity
import kotlinx.coroutines.launch

class MainActivity : AppCompatActivity() {
    
    private lateinit var binding: ActivityMainBinding
    private val viewModel: DashboardViewModel by viewModels()
    private lateinit var vpnAdapter: VpnAccountsAdapter
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)
        
        setupToolbar()
        setupRecyclerView()
        setupSwipeRefresh()
        observeViewModel()
        
        // Load VPN accounts
        viewModel.loadVpnAccounts()
    }
    
    private fun setupToolbar() {
        setSupportActionBar(binding.toolbar)
        supportActionBar?.title = "VMaster VPN"
    }
    
    private fun setupRecyclerView() {
        vpnAdapter = VpnAccountsAdapter(
            onAccountClick = { account ->
                // Handle VPN account click (connect/disconnect)
                viewModel.toggleVpnConnection(account)
            },
            onAccountLongClick = { account ->
                // Show account details
                showAccountDetails(account)
            }
        )
        
        binding.recyclerView.apply {
            layoutManager = LinearLayoutManager(this@MainActivity)
            adapter = vpnAdapter
        }
    }
    
    private fun setupSwipeRefresh() {
        binding.swipeRefresh.setOnRefreshListener {
            viewModel.loadVpnAccounts()
        }
    }
    
    private fun observeViewModel() {
        lifecycleScope.launch {
            // Observe VPN accounts
            viewModel.vpnAccounts.collect { accounts ->
                binding.swipeRefresh.isRefreshing = false
                vpnAdapter.submitList(accounts)
                
                // Update empty state
                if (accounts.isEmpty()) {
                    binding.emptyState.visibility = android.view.View.VISIBLE
                    binding.recyclerView.visibility = android.view.View.GONE
                } else {
                    binding.emptyState.visibility = android.view.View.GONE
                    binding.recyclerView.visibility = android.view.View.VISIBLE
                }
            }
        }
        
        lifecycleScope.launch {
            // Observe loading state
            viewModel.isLoading.collect { isLoading ->
                binding.progressBar.visibility = if (isLoading) {
                    android.view.View.VISIBLE
                } else {
                    android.view.View.GONE
                }
            }
        }
        
        lifecycleScope.launch {
            // Observe errors
            viewModel.error.collect { error ->
                error?.let {
                    Toast.makeText(this@MainActivity, it, Toast.LENGTH_LONG).show()
                }
            }
        }
        
        lifecycleScope.launch {
            // Observe connection status
            viewModel.connectionStatus.collect { status ->
                // Update UI based on connection status
                updateConnectionStatus(status)
            }
        }
    }
    
    private fun updateConnectionStatus(status: String?) {
        binding.connectionStatus.text = when (status) {
            "connected" -> "🟢 Connected"
            "connecting" -> "🟡 Connecting..."
            "disconnected" -> "🔴 Disconnected"
            else -> "⚪ Not Connected"
        }
    }
    
    private fun showAccountDetails(account: com.vmaster.vpn.data.models.VpnAccount) {
        // Show account details dialog
        val details = buildString {
            append("Server: ${account.serverName}\n")
            append("Type: ${account.getDisplayType()}\n")
            append("Location: ${account.location}\n")
            append("Status: ${account.status}\n")
            append("Expiry: ${account.getExpiryText()}\n")
        }
        
        androidx.appcompat.app.AlertDialog.Builder(this)
            .setTitle("Account Details")
            .setMessage(details)
            .setPositiveButton("OK", null)
            .show()
    }
    
    override fun onCreateOptionsMenu(menu: Menu): Boolean {
        menuInflater.inflate(R.menu.main_menu, menu)
        return true
    }
    
    override fun onOptionsItemSelected(item: MenuItem): Boolean {
        return when (item.itemId) {
            R.id.action_refresh -> {
                viewModel.loadVpnAccounts()
                true
            }
            R.id.action_profile -> {
                // Navigate to profile
                true
            }
            R.id.action_logout -> {
                logout()
                true
            }
            else -> super.onOptionsItemSelected(item)
        }
    }
    
    private fun logout() {
        viewModel.logout()
        val intent = Intent(this, LoginActivity::class.java)
        intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
        startActivity(intent)
        finish()
    }
}

