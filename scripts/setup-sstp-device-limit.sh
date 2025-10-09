#!/bin/bash

# Setup SSTP Server to Limit 1 Device Per Account
# Run this on your SSTP server (accel-ppp)

echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                                                              ║"
echo "║     🔧 SSTP Device Limit Setup (1 Device Per Account)       ║"
echo "║                                                              ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""

# Check if accel-ppp is installed
if ! systemctl list-units --full -all | grep -q accel-ppp; then
    echo "❌ accel-ppp service not found!"
    echo "Please install accel-ppp first."
    exit 1
fi

echo "✅ accel-ppp service found"
echo ""

# Backup config
echo "📦 Creating backup..."
cp /etc/accel-ppp.conf /etc/accel-ppp.conf.backup_$(date +%Y%m%d_%H%M%S)
echo "✅ Backup created"
echo ""

# Check if settings already exist
if grep -q "single-session=replace" /etc/accel-ppp.conf; then
    echo "ℹ️  single-session setting already exists"
else
    echo "➕ Adding single-session=replace to [common] section..."
    sed -i '/\[common\]/a single-session=replace' /etc/accel-ppp.conf
    echo "✅ Added single-session setting"
fi

if grep -q "max-sessions=1" /etc/accel-ppp.conf; then
    echo "ℹ️  max-sessions setting already exists"
else
    echo "➕ Adding max-sessions=1 to [ppp] section..."
    sed -i '/\[ppp\]/a max-sessions=1' /etc/accel-ppp.conf
    echo "✅ Added max-sessions setting"
fi

echo ""
echo "🔄 Restarting accel-ppp service..."
systemctl restart accel-ppp

if systemctl is-active --quiet accel-ppp; then
    echo "✅ accel-ppp restarted successfully"
else
    echo "❌ Failed to restart accel-ppp"
    echo "Check logs: journalctl -u accel-ppp -n 50"
    exit 1
fi

echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                                                              ║"
echo "║              ✅ SETUP COMPLETE! ✅                           ║"
echo "║                                                              ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
echo "📋 Configuration Applied:"
echo "  • single-session=replace → Disconnect old session when new login"
echo "  • max-sessions=1 → Only 1 active session per user"
echo ""
echo "🎯 Result:"
echo "  • Each SSTP account can now only be used on 1 device at a time"
echo "  • If user connects from device 2, device 1 will be disconnected"
echo ""
echo "🧪 Test it:"
echo "  1. Connect device 1 with SSTP credentials → Should work"
echo "  2. Connect device 2 with same credentials → Device 1 disconnects"
echo ""
echo "📝 Backup saved at:"
echo "  /etc/accel-ppp.conf.backup_$(date +%Y%m%d)_*"
echo ""

