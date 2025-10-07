# 🚀 Easy V2Ray Pool Setup (One-Command Method)

Since manually adding 100 UUIDs to X-UI panel is too time-consuming, use this automated method instead!

---

## ⚡ **Quick Setup (3 Commands)**

### **Step 1: Generate UUIDs** (Already Done! ✅)

```bash
cd /Users/zawnainghtun/My\ Coding\ Project/VPN\ CMS\ Portal
./scripts/generate-v2ray-uuids.sh 100
```

**Output:** `v2ray-pool-export/` folder with all files

---

### **Step 2: Bulk Add to V2Ray Server** (Automated!)

This script will:
- ✅ Connect to your V2Ray server via SSH
- ✅ Add all 100 UUIDs to V2Ray config automatically
- ✅ Validate config
- ✅ Reload V2Ray (no downtime!)

```bash
chmod +x scripts/bulk-add-to-xui.sh
./scripts/bulk-add-to-xui.sh
```

**Time:** ~1 minute  
**Manual work:** Just press "yes" to confirm

---

### **Step 3: Import to VMaster Database**

```bash
# Copy SQL file to VMaster server
scp v2ray-pool-export/import_to_vmaster.sql ubuntu@VMASTER_SERVER_IP:/tmp/

# SSH to VMaster server
ssh ubuntu@VMASTER_SERVER_IP

# Run auto-setup script
cd /var/www/vmaster
git pull origin main
chmod +x scripts/setup-v2ray-pool.sh
sudo bash scripts/setup-v2ray-pool.sh
```

**Time:** ~1 minute

---

## ✅ **Done!**

Now test:
1. Login to VMaster as customer
2. Create V2Ray account
3. Get VMess link with UUID from pool
4. Connect with V2Ray client ✅

---

## 🔍 **What the Bulk Script Does:**

```
Your Mac
   ↓
SSH to V2Ray Server (103.117.149.112)
   ↓
Backup current V2Ray config
   ↓
Add all 100 UUIDs to VMess inbound
   ↓
Validate config (if fails, auto-restore backup)
   ↓
Reload V2Ray (seamless, no disconnect)
   ↓
✅ All 100 UUIDs ready!
```

---

## 🛡️ **Safety Features:**

✅ **Auto-backup** - Creates timestamped backup before changes  
✅ **Validation** - Tests config before applying  
✅ **Auto-rollback** - Restores backup if anything fails  
✅ **No downtime** - Uses reload instead of restart  

---

## 📊 **Comparison:**

| Method | Time | Manual Work | Risk |
|--------|------|-------------|------|
| **Manual X-UI** | 30-60 min | Click 100 times | High (human error) |
| **Bulk Script** | 1 min | Just confirm | Low (auto-rollback) |

---

## 🔧 **Troubleshooting:**

### **Issue: SSH connection refused**
```bash
# Test SSH first
ssh ubuntu@103.117.149.112 "echo OK"

# If fails, check:
# - Server IP is correct
# - SSH key is set up
# - Firewall allows SSH (port 22)
```

### **Issue: Permission denied**
```bash
# Add your SSH key to server
ssh-copy-id ubuntu@103.117.149.112
```

### **Issue: Config validation failed**
The script will automatically restore the backup.  
Check V2Ray logs:
```bash
ssh ubuntu@103.117.149.112
sudo journalctl -u v2ray -n 50
```

### **Issue: V2Ray won't reload**
The script will automatically restart V2Ray.  
If still fails, restore manually:
```bash
ssh ubuntu@103.117.149.112
sudo systemctl status v2ray
# Find latest backup
ls -lt /etc/v2ray/config.json.backup.*
# Restore it
sudo cp /etc/v2ray/config.json.backup.TIMESTAMP /etc/v2ray/config.json
sudo systemctl restart v2ray
```

---

## 🎯 **Manual Method (If Bulk Script Fails)**

If you still want to add manually via X-UI panel:

### **Tips for Faster Manual Entry:**

1. **Use keyboard shortcuts:**
   - Open X-UI panel
   - Use `Tab` to navigate fields
   - Use `Ctrl+V` to paste
   - Use `Enter` to submit

2. **Prepare in advance:**
   - Open `v2ray-pool-export/for_xui_panel.txt` in a text editor
   - Split screen: X-UI panel on left, UUIDs on right
   - Copy-paste-submit-repeat

3. **Do in batches:**
   - Add 10 UUIDs
   - Save
   - Take a break
   - Continue with next 10

4. **Use browser autofill (if available):**
   - Some X-UI versions support JSON import
   - Check if your X-UI has "Import Clients" button

---

## 💡 **Alternative: Start Small**

Don't need 100 UUIDs right away?

**Generate fewer UUIDs:**
```bash
./scripts/generate-v2ray-uuids.sh 20  # Only 20 UUIDs
```

Then manually add these 20 to X-UI (takes ~5 minutes).

**Later, when you need more:**
```bash
./scripts/generate-v2ray-uuids.sh 30  # Generate 30 more
# Add these to V2Ray and VMaster
```

---

## 🚀 **Recommended: Use the Bulk Script!**

It's:
- ✅ Faster (1 min vs 30-60 min)
- ✅ Safer (auto-backup and rollback)
- ✅ Easier (no clicking fatigue)
- ✅ Reliable (no human error)

Just run:
```bash
./scripts/bulk-add-to-xui.sh
```

And you're done! 🎉

