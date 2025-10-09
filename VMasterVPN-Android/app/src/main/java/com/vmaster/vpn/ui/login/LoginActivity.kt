package com.vmaster.vpn.ui.login

import android.content.Intent
import android.os.Bundle
import android.widget.Toast
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.vmaster.vpn.R
import com.vmaster.vpn.databinding.ActivityLoginBinding
import com.vmaster.vpn.ui.dashboard.MainActivity
import kotlinx.coroutines.launch

class LoginActivity : AppCompatActivity() {
    
    private lateinit var binding: ActivityLoginBinding
    private val viewModel: LoginViewModel by viewModels()
    
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityLoginBinding.inflate(layoutInflater)
        setContentView(binding.root)
        
        setupUI()
        observeViewModel()
    }
    
    private fun setupUI() {
        binding.apply {
            loginButton.setOnClickListener {
                val username = usernameInput.text.toString().trim()
                val password = passwordInput.text.toString()
                
                if (username.isEmpty()) {
                    usernameInput.error = "Username is required"
                    return@setOnClickListener
                }
                
                if (password.isEmpty()) {
                    passwordInput.error = "Password is required"
                    return@setOnClickListener
                }
                
                lifecycleScope.launch {
                    viewModel.login(username, password)
                }
            }
        }
    }
    
    private fun observeViewModel() {
        lifecycleScope.launch {
            viewModel.loginState.collect { state ->
                when (state) {
                    is LoginState.Idle -> {
                        binding.loginButton.isEnabled = true
                        binding.progressBar.visibility = android.view.View.GONE
                    }
                    is LoginState.Loading -> {
                        binding.loginButton.isEnabled = false
                        binding.progressBar.visibility = android.view.View.VISIBLE
                    }
                    is LoginState.Success -> {
                        binding.progressBar.visibility = android.view.View.GONE
                        Toast.makeText(this@LoginActivity, "Login successful!", Toast.LENGTH_SHORT).show()
                        
                        // Navigate to dashboard
                        val intent = Intent(this@LoginActivity, MainActivity::class.java)
                        intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
                        startActivity(intent)
                        finish()
                    }
                    is LoginState.Error -> {
                        binding.loginButton.isEnabled = true
                        binding.progressBar.visibility = android.view.View.GONE
                        Toast.makeText(this@LoginActivity, state.message, Toast.LENGTH_LONG).show()
                    }
                }
            }
        }
    }
}

sealed class LoginState {
    object Idle : LoginState()
    object Loading : LoginState()
    object Success : LoginState()
    data class Error(val message: String) : LoginState()
}

