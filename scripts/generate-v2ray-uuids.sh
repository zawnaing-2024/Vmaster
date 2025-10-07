#!/bin/bash

################################################################################
# V2Ray UUID Generator for VMaster Pool
# Generates UUIDs and SQL import script
################################################################################

echo "════════════════════════════════════════════════════════════════"
echo "🔧 V2Ray UUID Generator"
echo "════════════════════════════════════════════════════════════════"
echo ""

# Configuration
NUM_UUIDS=${1:-100}  # Default 100 UUIDs, can override with first argument
OUTPUT_DIR="./v2ray-pool-export"
UUIDS_FILE="$OUTPUT_DIR/uuids.txt"
SQL_FILE="$OUTPUT_DIR/import_to_vmaster.sql"
XUI_IMPORT_FILE="$OUTPUT_DIR/for_xui_panel.txt"

# Create output directory
mkdir -p "$OUTPUT_DIR"

echo "Generating $NUM_UUIDS UUIDs..."
echo ""

# Clear files
> "$UUIDS_FILE"
> "$SQL_FILE"
> "$XUI_IMPORT_FILE"

# SQL header
cat > "$SQL_FILE" << 'EOF'
-- V2Ray UUID Pool Import Script
-- Generated: $(date)
-- Import to VMaster database

USE vpn_cms_portal;

-- Add V2Ray UUIDs to credentials pool
INSERT INTO vpn_credentials_pool (vpn_type, username, password, is_assigned, created_at) VALUES
EOF

# Generate UUIDs
for i in $(seq 1 $NUM_UUIDS); do
    # Generate UUID
    if command -v uuidgen &> /dev/null; then
        UUID=$(uuidgen)
    else
        # Fallback UUID generation (for Linux without uuidgen)
        UUID=$(cat /proc/sys/kernel/random/uuid 2>/dev/null || \
               python3 -c 'import uuid; print(uuid.uuid4())' 2>/dev/null || \
               php -r 'echo sprintf("%04x%04x-%04x-%04x-%04x-%04x%04x%04x", mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));')
    fi
    
    USERNAME="v2ray_$(printf "%03d" $i)"
    EMAIL="v2ray_$(printf "%03d" $i)@vmaster.local"
    
    # Save UUID to file
    echo "$UUID" >> "$UUIDS_FILE"
    
    # Add to SQL file
    if [ $i -eq $NUM_UUIDS ]; then
        # Last entry - no comma
        echo "('v2ray', '$USERNAME', '$UUID', 0, NOW());" >> "$SQL_FILE"
    else
        echo "('v2ray', '$USERNAME', '$UUID', 0, NOW())," >> "$SQL_FILE"
    fi
    
    # Add to X-UI import file
    echo "$UUID|$EMAIL" >> "$XUI_IMPORT_FILE"
    
    # Progress
    if [ $((i % 10)) -eq 0 ]; then
        echo "Generated: $i/$NUM_UUIDS"
    fi
done

echo ""
echo "✅ Generated $NUM_UUIDS UUIDs!"
echo ""
echo "════════════════════════════════════════════════════════════════"
echo "📁 Files Created:"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "1. $UUIDS_FILE"
echo "   - Plain list of UUIDs"
echo "   - One UUID per line"
echo ""
echo "2. $SQL_FILE"
echo "   - SQL script to import to VMaster"
echo "   - Run on VMaster server"
echo ""
echo "3. $XUI_IMPORT_FILE"
echo "   - Format: UUID|Email"
echo "   - For adding to X-UI panel"
echo ""
echo "════════════════════════════════════════════════════════════════"
echo "📋 Next Steps:"
echo "════════════════════════════════════════════════════════════════"
echo ""
echo "STEP 1: Add UUIDs to X-UI Panel"
echo "────────────────────────────────"
echo "1. Login to X-UI: http://103.117.149.112:54321/"
echo "2. Go to Inbounds"
echo "3. Edit your VMess inbound"
echo "4. Add clients using UUIDs from:"
echo "   $XUI_IMPORT_FILE"
echo ""
echo "STEP 2: Import to VMaster Database"
echo "───────────────────────────────────"
echo "On VMaster server, run:"
echo ""
echo "  docker exec -i vmaster_db mysql -uroot -prootpassword \\"
echo "    < $SQL_FILE"
echo ""
echo "OR copy the SQL file to server first:"
echo ""
echo "  scp $SQL_FILE ubuntu@YOUR_SERVER:/tmp/"
echo "  ssh ubuntu@YOUR_SERVER"
echo "  docker exec -i vmaster_db mysql -uroot -prootpassword < /tmp/import_to_vmaster.sql"
echo ""
echo "STEP 3: Test in VMaster Portal"
echo "───────────────────────────────"
echo "1. Login as customer"
echo "2. Create V2Ray account"
echo "3. Should get UUID from pool"
echo "4. Copy VMess link and test connection"
echo ""
echo "════════════════════════════════════════════════════════════════"
echo ""

# Create quick reference guide
cat > "$OUTPUT_DIR/README.txt" << 'README'
════════════════════════════════════════════════════════════════
V2Ray UUID Pool Setup Guide
════════════════════════════════════════════════════════════════

FILES IN THIS FOLDER:
─────────────────────

1. uuids.txt
   - Plain list of all UUIDs
   - One per line
   - Total: 100 UUIDs

2. import_to_vmaster.sql
   - SQL script to add UUIDs to VMaster pool
   - Run this on your VMaster server

3. for_xui_panel.txt
   - Format: UUID|Email
   - Use this to add clients to X-UI panel

4. README.txt
   - This file


SETUP INSTRUCTIONS:
───────────────────

STEP 1: Add to X-UI Panel
──────────────────────────

Option A: Manual (Recommended for small batches)
1. Open X-UI panel
2. Go to Inbounds → Edit VMess inbound
3. Click "Add Client"
4. For each UUID in for_xui_panel.txt:
   - UUID: copy from file
   - Email: copy from file (after |)
   - AlterID: 0
5. Save

Option B: Bulk Import (if X-UI supports it)
1. Some X-UI versions have bulk import
2. Check if your X-UI has "Import Clients" feature
3. Format required is usually JSON


STEP 2: Import to VMaster
──────────────────────────

On your VMaster production server:

# Copy SQL file to server
scp import_to_vmaster.sql ubuntu@YOUR_SERVER_IP:/tmp/

# SSH to server
ssh ubuntu@YOUR_SERVER_IP

# Import to database
docker exec -i vmaster_db mysql -uroot -prootpassword \
  < /tmp/import_to_vmaster.sql

# Check import
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal \
  -e "SELECT COUNT(*) as total, 
      SUM(is_assigned=0) as available, 
      SUM(is_assigned=1) as assigned 
      FROM vpn_credentials_pool WHERE vpn_type='v2ray';"

Expected output:
+-------+-----------+----------+
| total | available | assigned |
+-------+-----------+----------+
|   100 |       100 |        0 |
+-------+-----------+----------+


STEP 3: Test
─────────────

1. Login to VMaster customer panel
2. Go to VPN Accounts
3. Click "Add VPN Account"
4. Select your V2Ray server
5. Create account
6. You should get a VMess link with UUID from pool
7. Test connection with V2Ray client


TROUBLESHOOTING:
────────────────

Issue: "No available credentials in pool"
Fix: Check if UUIDs were imported to database

Issue: "Invalid UUID in VMess link"
Fix: Ensure UUID in VMaster matches UUID in X-UI

Issue: "Connection failed"
Fix: 
  - Check V2Ray server is running
  - Check firewall allows port
  - Check UUID exists in X-UI panel


MONITORING:
───────────

Check pool status:
docker exec vmaster_db mysql -uroot -prootpassword vpn_cms_portal \
  -e "SELECT vpn_type, 
      COUNT(*) as total,
      SUM(is_assigned=0) as available 
      FROM vpn_credentials_pool 
      GROUP BY vpn_type;"


ADDING MORE UUIDs:
──────────────────

When pool runs low, generate more:

./scripts/generate-v2ray-uuids.sh 50

Then repeat Steps 1 and 2 with the new files.


════════════════════════════════════════════════════════════════
README

echo "📄 Created README.txt with detailed instructions"
echo ""
echo "✅ All files ready in: $OUTPUT_DIR/"
echo ""

