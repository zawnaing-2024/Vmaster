# SSL Setup - Fix for Certificate Error

## 🎯 The Problem

You're getting this error:
```
nginx: [emerg] no "ssl_certificate" is defined
```

**Why?** Nginx config has SSL settings but certificates don't exist yet!

**Solution:** Set up HTTP first, then get SSL certificate, then switch to HTTPS.

---

## ✅ Correct SSL Setup Process

### Step 1: Remove Current Nginx Config

```bash
# On your Ubuntu server
rm /etc/nginx/sites-enabled/vmaster
nginx -t  # Should pass now
systemctl reload nginx
```

---

### Step 2: Use HTTP-Only Config First

```bash
# Copy the HTTP-only config
nano /etc/nginx/sites-available/vmaster
```

**Paste this content:**
```nginx
server {
    listen 80;
    server_name vmaster.vip www.vmaster.vip;
    
    access_log /var/log/nginx/vmaster_access.log;
    error_log /var/log/nginx/vmaster_error.log;
    
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
    
    location /phpmyadmin {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
    
    client_max_body_size 50M;
}
```

```bash
# Enable the site
ln -s /etc/nginx/sites-available/vmaster /etc/nginx/sites-enabled/

# Test configuration (should pass now!)
nginx -t

# Reload Nginx
systemctl reload nginx
```

---

### Step 3: Test HTTP Access

```bash
# Test from server
curl -I http://vmaster.vip

# Or visit in browser (HTTP not HTTPS):
# http://vmaster.vip
```

Should show VMaster admin login page!

---

### Step 4: Get SSL Certificate

**Now that HTTP works, get the SSL certificate:**

```bash
# Install certbot
apt install -y certbot python3-certbot-nginx

# Get certificate (certbot will modify Nginx config automatically!)
certbot --nginx -d vmaster.vip -d www.vmaster.vip

# Follow prompts:
# - Enter email: your-email@example.com
# - Agree to terms: Y
# - Redirect HTTP to HTTPS: 2 (recommended)
```

**Certbot will:**
- Get SSL certificate from Let's Encrypt
- Automatically update your Nginx config
- Add SSL settings
- Set up auto-renewal

---

### Step 5: Verify SSL

```bash
# Test Nginx config
nginx -t

# Reload Nginx
systemctl reload nginx

# Test SSL
curl -I https://vmaster.vip

# Check certificate
certbot certificates
```

---

### Step 6: Test Auto-Renewal

```bash
# Dry run renewal
certbot renew --dry-run

# If successful, auto-renewal is set up!
```

---

## 🎯 Quick Fix Commands

```bash
# 1. Remove broken config
rm /etc/nginx/sites-enabled/vmaster

# 2. Create simple HTTP config
cat > /etc/nginx/sites-available/vmaster << 'EOF'
server {
    listen 80;
    server_name vmaster.vip www.vmaster.vip;
    
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
EOF

# 3. Enable site
ln -s /etc/nginx/sites-available/vmaster /etc/nginx/sites-enabled/

# 4. Test and reload
nginx -t && systemctl reload nginx

# 5. Get SSL certificate
certbot --nginx -d vmaster.vip -d www.vmaster.vip

# Done! SSL configured automatically!
```

---

## 🔍 Verify Everything Works

### Check 1: HTTP to HTTPS Redirect
```bash
curl -I http://vmaster.vip
# Should show: 301 redirect to https
```

### Check 2: HTTPS Works
```bash
curl -I https://vmaster.vip
# Should show: 200 OK
```

### Check 3: VMaster Loads
```bash
curl https://vmaster.vip/admin/login.php
# Should show HTML page
```

### Check 4: Certificate Valid
```bash
openssl s_client -connect vmaster.vip:443 -servername vmaster.vip < /dev/null 2>/dev/null | grep "Verify return code"
# Should show: Verify return code: 0 (ok)
```

---

## 🎉 After SSL is Working

Your VMaster will be accessible at:
```
https://vmaster.vip/admin/login.php
https://vmaster.vip/customer/login.php
```

**Default credentials:**
```
Username: admin
Password: admin123
```

**⚠️ Change password immediately after first login!**

---

## 🔄 Certificate Renewal

Certbot sets up auto-renewal automatically!

**Manual renewal:**
```bash
certbot renew
```

**Check renewal schedule:**
```bash
systemctl list-timers | grep certbot
```

**Certificates renew automatically 30 days before expiration!**

---

## 📞 Troubleshooting

### Issue: "Connection refused"
```bash
# Check Docker is running
docker ps | grep vmaster_web

# Check Nginx is running
systemctl status nginx
```

### Issue: "502 Bad Gateway"
```bash
# Check Docker container
docker logs vmaster_web

# Restart containers
docker-compose -f docker-compose.prod.yml restart
```

### Issue: "Certificate expired"
```bash
# Force renewal
certbot renew --force-renewal
systemctl reload nginx
```

---

**SSL setup complete! VMaster is now secure and production-ready! 🔒**

