# 🔑 Reset Admin Password

If you've forgotten your admin password or need to reset it, follow these steps:

## Quick Reset to Default (admin123)

Run this single command to reset the admin password to `admin123`:

```bash
docker exec vpn_cms_db mysql -u root -proot_secure_password vpn_cms_portal -e "UPDATE admins SET password = '\$2y\$10\$3pVBrF6UBl3Tnit7wu.zDe.r17zXpBMctj1RMf2xOrBFVIWUCqDXS' WHERE username = 'admin';"
```

Then login with:
- **Username:** `admin`
- **Password:** `admin123`

---

## Custom Password Reset

If you want to set a custom password:

### Step 1: Generate Password Hash

Replace `YOUR_NEW_PASSWORD` with your desired password:

```bash
docker exec vpn_cms_web php -r "echo password_hash('YOUR_NEW_PASSWORD', PASSWORD_DEFAULT) . PHP_EOL;"
```

This will output something like:
```
$2y$10$AbCdEf1234567890...
```

### Step 2: Update Database

Copy the hash from step 1 and replace `PASTE_HASH_HERE`:

```bash
docker exec vpn_cms_db mysql -u root -proot_secure_password vpn_cms_portal -e "UPDATE admins SET password = 'PASTE_HASH_HERE' WHERE username = 'admin';"
```

### Step 3: Login

Login with:
- **Username:** `admin`
- **Password:** `YOUR_NEW_PASSWORD`

---

## Verify Admin Account

Check if admin account exists:

```bash
docker exec vpn_cms_db mysql -u root -proot_secure_password vpn_cms_portal -e "SELECT id, username, full_name, email FROM admins WHERE username = 'admin';"
```

Expected output:
```
id      username    full_name                 email
1       admin       System Administrator      admin@vpncms.local
```

---

## Create New Admin Account

If you need to create an additional admin account:

```bash
# First, generate password hash for new admin
docker exec vpn_cms_web php -r "echo password_hash('new_password', PASSWORD_DEFAULT) . PHP_EOL;"

# Then insert new admin (replace values as needed)
docker exec vpn_cms_db mysql -u root -proot_secure_password vpn_cms_portal -e "INSERT INTO admins (username, password, full_name, email) VALUES ('newadmin', 'HASH_HERE', 'New Admin', 'newadmin@example.com');"
```

---

## Customer Password Reset

To reset a customer password:

### Step 1: Find Customer ID

```bash
docker exec vpn_cms_db mysql -u root -proot_secure_password vpn_cms_portal -e "SELECT id, username, company_name FROM customers;"
```

### Step 2: Generate New Password Hash

```bash
docker exec vpn_cms_web php -r "echo password_hash('new_customer_password', PASSWORD_DEFAULT) . PHP_EOL;"
```

### Step 3: Update Customer Password

Replace `CUSTOMER_ID` and `HASH_HERE`:

```bash
docker exec vpn_cms_db mysql -u root -proot_secure_password vpn_cms_portal -e "UPDATE customers SET password = 'HASH_HERE' WHERE id = CUSTOMER_ID;"
```

---

## Security Notes

⚠️ **Important:**
- Always use strong passwords in production
- Change default passwords immediately after installation
- Don't share database credentials
- Store password hashes, never plain text passwords

---

## Troubleshooting

### Error: "the input device is not a TTY"

Remove the `-it` flag if you see this error. Use commands as shown above without `-it`.

### Error: "Access denied"

Check your MySQL root password in `docker-compose.yml`. Default is `root_secure_password`.

### Changes Not Taking Effect

Clear your browser cache and cookies, or try in incognito mode.

---

**Need more help?** Check `TROUBLESHOOTING.md` for other common issues.

