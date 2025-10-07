# How to Add V2Ray UUIDs to X-UI Panel

## Method 1: Manual Addition (Recommended)

### Step-by-Step:

1. **Login to X-UI Panel**
   - URL: http://103.117.149.112:54321/
   - Username: `gdadmin`
   - Password: `GD@dm!n`

2. **Navigate to Inbounds**
   - Click "Inbounds" in the sidebar
   - Find your VMess inbound (usually the one on port 10086)
   - Click "Edit" or "Settings" icon

3. **Add Clients**
   - Look for "Clients" section
   - Click "Add Client" or "+" button
   - For each UUID from `for_xui_panel.txt`:
     ```
     UUID:     [paste UUID here]
     Email:    [paste email here, e.g., v2ray_001@vmaster.local]
     AlterID:  0
     Level:    0
     ```
   - Click "Save" or "Add"

4. **Repeat for All UUIDs**
   - Add all 100 UUIDs one by one
   - Or do batches (e.g., 10 at a time)

5. **Save Configuration**
   - Click "Save" at the bottom
   - X-UI will automatically reload V2Ray
   - No manual restart needed!

---

## Method 2: Bulk Import (If Supported)

Some X-UI versions support bulk import. Check if you see this option:

### Steps:

1. Go to Inbounds → Edit VMess inbound
2. Look for "Import Clients" or "Bulk Add" button
3. If available, prepare JSON format:

```json
{
  "clients": [
    {
      "id": "UUID-1-HERE",
      "email": "v2ray_001@vmaster.local",
      "alterId": 0,
      "level": 0
    },
    {
      "id": "UUID-2-HERE",
      "email": "v2ray_002@vmaster.local",
      "alterId": 0,
      "level": 0
    }
  ]
}
```

4. Paste and import

---

## Method 3: Direct Config Edit (Advanced)

If you have SSH access to X-UI server:

### Steps:

1. **Backup current config:**
   ```bash
   sudo cp /etc/v2ray/config.json /etc/v2ray/config.json.backup
   ```

2. **Download helper script:**
   ```bash
   cat > /tmp/add_v2ray_clients.php << 'EOF'
   <?php
   // Read V2Ray config
   $config = json_decode(file_get_contents('/etc/v2ray/config.json'), true);
   
   // Read UUIDs from file
   $uuids = file('/tmp/v2ray_uuids.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
   
   // Find VMess inbound
   foreach ($config['inbounds'] as &$inbound) {
       if (isset($inbound['protocol']) && $inbound['protocol'] === 'vmess') {
           if (!isset($inbound['settings']['clients'])) {
               $inbound['settings']['clients'] = [];
           }
           
           // Add each UUID
           foreach ($uuids as $i => $uuid) {
               $userNum = str_pad($i + 1, 3, '0', STR_PAD_LEFT);
               $inbound['settings']['clients'][] = [
                   'id' => trim($uuid),
                   'email' => "v2ray_$userNum@vmaster.local",
                   'alterId' => 0,
                   'level' => 0
               ];
           }
           break;
       }
   }
   
   // Save config
   file_put_contents('/etc/v2ray/config.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
   echo "Added " . count($uuids) . " clients to V2Ray config\n";
   ?>
   EOF
   ```

3. **Upload your UUIDs file:**
   ```bash
   # From your local machine
   scp v2ray-pool-export/uuids.txt ubuntu@103.117.149.112:/tmp/v2ray_uuids.txt
   ```

4. **Run the script:**
   ```bash
   ssh ubuntu@103.117.149.112
   sudo php /tmp/add_v2ray_clients.php
   sudo systemctl reload v2ray
   ```

5. **Verify:**
   ```bash
   sudo cat /etc/v2ray/config.json | grep -c '"email"'
   # Should show 100
   ```

---

## Verification

After adding UUIDs, verify they're loaded:

### Check via X-UI Panel:
1. Go to Inbounds
2. Look at your VMess inbound
3. Should show "100 clients" or similar

### Check via V2Ray Logs:
```bash
ssh ubuntu@103.117.149.112
sudo journalctl -u v2ray -n 20
# Should show "loaded 100 users" or no errors
```

### Test a UUID:
1. Pick any UUID from the list
2. Create VMess link manually:
   ```
   vmess://BASE64_ENCODED_JSON
   ```
   Where JSON is:
   ```json
   {
     "v": "2",
     "ps": "Test V2Ray",
     "add": "103.117.149.112",
     "port": "10086",
     "id": "YOUR-UUID-HERE",
     "aid": "0",
     "net": "tcp",
     "type": "none",
     "host": "",
     "path": "",
     "tls": ""
   }
   ```
3. Import to V2Ray client and test connection

---

## Troubleshooting

### Issue: "Cannot add client"
- Check if you're editing the correct inbound
- Ensure inbound protocol is "vmess"
- Try refreshing the X-UI panel page

### Issue: "UUID already exists"
- Each UUID must be unique
- Check if you accidentally added the same UUID twice

### Issue: "Config save failed"
- Check X-UI has write permissions
- Check V2Ray config syntax is valid
- Look at X-UI logs for errors

### Issue: "V2Ray won't reload"
- Check V2Ray service status: `systemctl status v2ray`
- Check V2Ray logs: `journalctl -u v2ray -n 50`
- Validate config: `v2ray test -config /etc/v2ray/config.json`

---

## Tips

- **Add in batches**: Add 10-20 UUIDs at a time to avoid timeouts
- **Use consistent emails**: Pattern like `v2ray_001@vmaster.local` helps tracking
- **Keep backup**: Always backup X-UI database before bulk changes
- **Test first**: Add 1-2 UUIDs first and test before adding all 100

---

## Next Steps

After adding all UUIDs to X-UI:
1. ✅ Import UUIDs to VMaster pool (via SQL)
2. ✅ Test creating account in VMaster portal
3. ✅ Verify UUID assignment works
4. ✅ Test V2Ray client connection

