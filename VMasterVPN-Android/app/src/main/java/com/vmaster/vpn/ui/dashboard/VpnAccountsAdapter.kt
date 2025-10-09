package com.vmaster.vpn.ui.dashboard

import android.view.LayoutInflater
import android.view.ViewGroup
import android.widget.Toast
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.vmaster.vpn.data.models.VpnAccount
import com.vmaster.vpn.databinding.ItemVpnAccountBinding

class VpnAccountsAdapter(
    private val onAccountClick: (VpnAccount) -> Unit,
    private val onAccountLongClick: (VpnAccount) -> Unit
) : ListAdapter<VpnAccount, VpnAccountsAdapter.VpnAccountViewHolder>(VpnAccountDiffCallback()) {
    
    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VpnAccountViewHolder {
        val binding = ItemVpnAccountBinding.inflate(
            LayoutInflater.from(parent.context),
            parent,
            false
        )
        return VpnAccountViewHolder(binding)
    }
    
    override fun onBindViewHolder(holder: VpnAccountViewHolder, position: Int) {
        holder.bind(getItem(position))
    }
    
    inner class VpnAccountViewHolder(
        private val binding: ItemVpnAccountBinding
    ) : RecyclerView.ViewHolder(binding.root) {
        
        fun bind(account: VpnAccount) {
            binding.apply {
                // Server name
                serverName.text = account.serverName
                
                // Server type
                serverType.text = account.getDisplayType()
                
                // Location
                location.text = account.location ?: "Unknown"
                
                // Expiry status
                expiryText.text = account.getExpiryText()
                
                // Status badge
                statusBadge.text = if (account.isExpired()) {
                    "Expired"
                } else {
                    account.status.capitalize()
                }
                
                // Status color
                val statusColor = when {
                    account.isExpired() -> android.graphics.Color.RED
                    account.isActive() -> android.graphics.Color.GREEN
                    else -> android.graphics.Color.GRAY
                }
                statusBadge.setTextColor(statusColor)
                
                // Type icon/color
                val typeColor = when (account.serverType) {
                    "outline" -> android.graphics.Color.parseColor("#0EA5E9")
                    "sstp" -> android.graphics.Color.parseColor("#10B981")
                    "v2ray" -> android.graphics.Color.parseColor("#A855F7")
                    else -> android.graphics.Color.GRAY
                }
                serverType.setTextColor(typeColor)
                
                // Click listeners
                root.setOnClickListener {
                    if (account.isActive()) {
                        onAccountClick(account)
                    } else {
                        Toast.makeText(
                            root.context,
                            "This account is ${account.status}",
                            Toast.LENGTH_SHORT
                        ).show()
                    }
                }
                
                root.setOnLongClickListener {
                    onAccountLongClick(account)
                    true
                }
            }
        }
    }
}

class VpnAccountDiffCallback : DiffUtil.ItemCallback<VpnAccount>() {
    override fun areItemsTheSame(oldItem: VpnAccount, newItem: VpnAccount): Boolean {
        return oldItem.id == newItem.id
    }
    
    override fun areContentsTheSame(oldItem: VpnAccount, newItem: VpnAccount): Boolean {
        return oldItem == newItem
    }
}

