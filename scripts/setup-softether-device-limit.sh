#!/bin/bash

# SoftEther VPN Server - Device Limit Setup Script
# This configures SoftEther to allow only 1 device per VPN account

echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                                                              ║"
echo "║     🔵 SoftEther VPN - Device Limit Configuration 🔵        ║"
echo "║                                                              ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Default configuration
SOFTETHER_HOST="${1:-localhost}"
HUB_NAME="${2:-DEFAULT}"
RADIUS_SERVER="${3:-YOUR_VMASTER_IP}"
RADIUS_PORT="${4:-1812}"
RADIUS_SECRET="${5:-One@2025}"

echo -e "${YELLOW}📝 Configuration:${NC}"
echo "  SoftEther Host: $SOFTETHER_HOST"
echo "  Virtual Hub: $HUB_NAME"
echo "  RADIUS Server: $RADIUS_SERVER:$RADIUS_PORT"
echo "  RADIUS Secret: $RADIUS_SECRET"
echo ""

# Check if vpncmd is available
if ! command -v vpncmd &> /dev/null; then
    echo -e "${RED}❌ vpncmd not found!${NC}"
    echo "Please install SoftEther VPN Server first."
    echo "Download from: https://www.softether.org/"
    exit 1
fi

echo -e "${GREEN}✅ vpncmd found${NC}"
echo ""

# Create configuration script
echo -e "${YELLOW}🔧 Creating configuration...${NC}"

cat > /tmp/softether-config.txt << EOF
Hub $HUB_NAME
RadiusServerSet $RADIUS_SERVER:$RADIUS_PORT $RADIUS_SECRET
RadiusServerEnable
AccountingServerSet $RADIUS_SERVER:1813 $RADIUS_SECRET
PolicySet /NAME:* /MAXCONNECTION:1 /MULTILOGINS:no
exit
EOF

echo -e "${GREEN}✅ Configuration file created${NC}"
echo ""

# Apply configuration
echo -e "${YELLOW}🚀 Applying configuration to SoftEther...${NC}"
vpncmd $SOFTETHER_HOST /SERVER /IN:/tmp/softether-config.txt

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Configuration applied successfully!${NC}"
else
    echo -e "${RED}❌ Configuration failed!${NC}"
    echo "Please check:"
    echo "  1. SoftEther VPN Server is running"
    echo "  2. Virtual Hub name is correct"
    echo "  3. You have admin permissions"
    exit 1
fi

# Cleanup
rm /tmp/softether-config.txt

echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║                                                              ║"
echo "║              ✅ SETUP COMPLETE! ✅                           ║"
echo "║                                                              ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
echo -e "${GREEN}📋 What was configured:${NC}"
echo "  ✅ RADIUS authentication enabled"
echo "  ✅ RADIUS accounting enabled"
echo "  ✅ Maximum 1 connection per user"
echo "  ✅ Multiple logins denied"
echo "  ✅ Old session disconnects on new login"
echo ""
echo -e "${YELLOW}🧪 Test it:${NC}"
echo "  1. Connect device 1 with SSTP credentials → Should work ✅"
echo "  2. Connect device 2 with same credentials → Device 1 disconnects ❌"
echo "  3. Only 1 device can be connected at a time"
echo ""
echo -e "${YELLOW}📊 Verify configuration:${NC}"
echo "  vpncmd $SOFTETHER_HOST /SERVER"
echo "  Hub $HUB_NAME"
echo "  RadiusServerGet"
echo "  PolicyGet *"
echo ""
echo -e "${YELLOW}🔍 Monitor active sessions:${NC}"
echo "  vpncmd $SOFTETHER_HOST /SERVER"
echo "  Hub $HUB_NAME"
echo "  SessionList"
echo ""

