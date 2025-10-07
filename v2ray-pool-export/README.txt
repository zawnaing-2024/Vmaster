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
