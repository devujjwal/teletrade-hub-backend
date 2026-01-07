# TeleTrade Hub Backend - Production Deployment Checklist

**Date:** January 7, 2026  
**Version:** 1.0.0  
**Security Level:** PRODUCTION-READY

---

## Pre-Deployment Checklist

### 1. Environment Configuration

#### Required Environment Variables (.env file)
```bash
# Copy from example and configure
cp .env.example .env
nano .env
```

**Critical Variables to Set:**
```bash
# Application
APP_ENV=production
APP_DEBUG=false
TIMEZONE=Europe/Berlin

# Database (CHANGE THESE!)
DB_HOST=localhost
DB_NAME=teletrade_hub
DB_USER=teletrade_app
DB_PASSWORD=[GENERATE STRONG PASSWORD]

# Vendor API (CHANGE THESE!)
VENDOR_API_BASE_URL=https://b2b.triel.sk/api
VENDOR_API_KEY=[YOUR_ACTUAL_API_KEY]

# Security
ADMIN_TOKEN_EXPIRY=86400
DEFAULT_MARKUP_PERCENTAGE=15.0

# CORS (CRITICAL - Set to your actual frontend domain!)
CORS_ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com

# Payment Gateway (Configure based on your provider)
PAYMENT_GATEWAY_API_KEY=[YOUR_PAYMENT_GATEWAY_KEY]
PAYMENT_GATEWAY_SECRET=[YOUR_PAYMENT_GATEWAY_SECRET]
```

#### ✅ Configuration Validation
- [ ] All sensitive credentials are unique and strong
- [ ] CORS_ALLOWED_ORIGINS set to specific domain (NO WILDCARDS)
- [ ] APP_DEBUG is set to false
- [ ] APP_ENV is set to production
- [ ] Database credentials are non-default
- [ ] Vendor API key is valid and tested

---

### 2. Database Setup

#### Create Database and User
```sql
-- Create database
CREATE DATABASE teletrade_hub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create dedicated user with limited privileges
CREATE USER 'teletrade_app'@'localhost' IDENTIFIED BY 'your_strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON teletrade_hub.* TO 'teletrade_app'@'localhost';
FLUSH PRIVILEGES;
```

#### Run Migrations
```bash
mysql -u teletrade_app -p teletrade_hub < database/migrations.sql
```

#### Verify Database
```sql
-- Check tables created
USE teletrade_hub;
SHOW TABLES;

-- Verify default admin user exists
SELECT username, email FROM admin_users WHERE role = 'super_admin';
-- Default: username=admin, password=Admin@123456 (CHANGE IMMEDIATELY!)
```

#### ✅ Database Validation
- [ ] All tables created successfully
- [ ] Foreign keys are working
- [ ] Indexes are created
- [ ] Default admin user exists
- [ ] Default settings are populated
- [ ] Default global markup rule exists

---

### 3. Web Server Configuration

#### Nginx Configuration (Recommended)
```nginx
server {
    listen 443 ssl http2;
    server_name api.yourdomain.com;
    root /var/www/teletrade-hub-backend/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security Headers (additional layer)
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    server_tokens off;

    # Client limits
    client_max_body_size 10M;
    client_body_timeout 30s;

    # PHP processing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        
        # Security
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ /\.env {
        deny all;
    }

    # Logging
    access_log /var/log/nginx/teletrade_access.log;
    error_log /var/log/nginx/teletrade_error.log;
}

# Redirect HTTP to HTTPS
server {
    listen 80;
    server_name api.yourdomain.com;
    return 301 https://$server_name$request_uri;
}
```

#### Apache Configuration (Alternative)
```apache
<VirtualHost *:443>
    ServerName api.yourdomain.com
    DocumentRoot /var/www/teletrade-hub-backend/public

    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/ssl/cert.pem
    SSLCertificateKeyFile /path/to/ssl/key.pem
    SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite HIGH:!aNULL:!MD5

    <Directory /var/www/teletrade-hub-backend/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Deny access to .env
    <Files ".env">
        Require all denied
    </Files>

    # Security Headers
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/teletrade_error.log
    CustomLog ${APACHE_LOG_DIR}/teletrade_access.log combined
</VirtualHost>

<VirtualHost *:80>
    ServerName api.yourdomain.com
    Redirect permanent / https://api.yourdomain.com/
</VirtualHost>
```

#### ✅ Web Server Validation
- [ ] HTTPS is configured and working
- [ ] HTTP redirects to HTTPS
- [ ] SSL certificate is valid
- [ ] PHP-FPM/mod_php is working
- [ ] .env file is not accessible via web
- [ ] Server tokens are hidden
- [ ] Security headers are set

---

### 4. File Permissions

```bash
# Navigate to project directory
cd /var/www/teletrade-hub-backend

# Set ownership
chown -R www-data:www-data .

# Set directory permissions
find . -type d -exec chmod 755 {} \;

# Set file permissions
find . -type f -exec chmod 644 {} \;

# Secure .env file
chmod 600 .env

# Make storage writable
chmod -R 775 storage
chmod -R 775 storage/logs

# Ensure public is readable
chmod 755 public
```

#### ✅ File Permission Validation
- [ ] Web server user owns the files
- [ ] .env file is not readable by others (600)
- [ ] Storage directory is writable
- [ ] Public directory is readable

---

### 5. PHP Configuration

#### Required PHP Extensions
```bash
# Install required extensions
apt-get install -y php8.1-cli php8.1-fpm php8.1-mysql php8.1-curl \
    php8.1-mbstring php8.1-xml php8.1-bcmath php8.1-json php8.1-zip

# Verify extensions
php -m | grep -E "pdo_mysql|curl|mbstring|json"
```

#### PHP.ini Security Settings
```ini
; Security
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log

; Resource Limits
max_execution_time = 300
max_input_time = 300
memory_limit = 256M
post_max_size = 10M
upload_max_filesize = 10M

; Session Security
session.cookie_httponly = On
session.cookie_secure = On
session.cookie_samesite = Strict
session.use_strict_mode = On
```

#### ✅ PHP Validation
- [ ] All required extensions installed
- [ ] expose_php is Off
- [ ] display_errors is Off
- [ ] Session security settings applied
- [ ] Resource limits are appropriate

---

### 6. Security Hardening

#### Firewall Configuration
```bash
# Allow SSH
ufw allow 22/tcp

# Allow HTTP/HTTPS
ufw allow 80/tcp
ufw allow 443/tcp

# Allow MySQL only from localhost
ufw allow from 127.0.0.1 to any port 3306

# Enable firewall
ufw enable
```

#### Fail2Ban (Recommended)
```bash
# Install fail2ban
apt-get install fail2ban

# Configure for Nginx
cat > /etc/fail2ban/jail.local <<EOF
[nginx-limit-req]
enabled = true
filter = nginx-limit-req
logpath = /var/log/nginx/*error.log
maxretry = 5
bantime = 3600
EOF

# Restart fail2ban
systemctl restart fail2ban
```

#### ✅ Security Validation
- [ ] Firewall is configured and enabled
- [ ] Fail2Ban is installed and configured
- [ ] SSH key authentication enabled (password disabled)
- [ ] Unnecessary services are stopped
- [ ] System is updated

---

### 7. SSL Certificate

#### Using Let's Encrypt (Recommended)
```bash
# Install certbot
apt-get install certbot python3-certbot-nginx

# Obtain certificate
certbot --nginx -d api.yourdomain.com

# Verify auto-renewal
certbot renew --dry-run
```

#### ✅ SSL Validation
- [ ] SSL certificate is installed
- [ ] Certificate is valid and not expired
- [ ] Auto-renewal is configured
- [ ] HTTPS grade is A or A+ (check at ssllabs.com)

---

### 8. Monitoring & Logging

#### Setup Log Rotation
```bash
cat > /etc/logrotate.d/teletrade <<EOF
/var/www/teletrade-hub-backend/storage/logs/*.log {
    daily
    rotate 90
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
    sharedscripts
    postrotate
        systemctl reload php8.1-fpm > /dev/null
    endscript
}
EOF
```

#### Setup Monitoring
```bash
# Install monitoring tools
apt-get install monit

# Example monit configuration
cat > /etc/monit/conf.d/teletrade <<EOF
check process nginx with pidfile /var/run/nginx.pid
    start program = "/usr/sbin/service nginx start"
    stop program = "/usr/sbin/service nginx stop"
    if failed host api.yourdomain.com port 443 protocol https then restart

check process php-fpm with pidfile /var/run/php/php8.1-fpm.pid
    start program = "/usr/sbin/service php8.1-fpm start"
    stop program = "/usr/sbin/service php8.1-fpm stop"
    if failed unixsocket /var/run/php/php8.1-fpm.sock then restart
EOF

# Reload monit
systemctl reload monit
```

#### ✅ Monitoring Validation
- [ ] Log rotation is configured
- [ ] Logs are being written correctly
- [ ] Monitoring is active
- [ ] Alerts are configured
- [ ] Disk space monitoring is active

---

### 9. Cron Jobs Setup

#### Create Cron Jobs
```bash
crontab -e

# Add these lines:
# Product sync (daily at 3 AM)
0 3 * * * curl -X POST https://api.yourdomain.com/admin/sync/products \
    -H "Authorization: Bearer YOUR_ADMIN_TOKEN"

# Create vendor sales orders (daily at 2 AM)
0 2 * * * curl -X POST https://api.yourdomain.com/admin/vendor/create-sales-order \
    -H "Authorization: Bearer YOUR_ADMIN_TOKEN"

# Clean expired sessions (daily at 4 AM)
0 4 * * * php /var/www/teletrade-hub-backend/cli/cleanup-sessions.php

# Rotate logs (daily at 5 AM)
0 5 * * * php /var/www/teletrade-hub-backend/cli/rotate-logs.php
```

#### ✅ Cron Validation
- [ ] Cron jobs are created
- [ ] Product sync is running
- [ ] Sales order creation is running
- [ ] Session cleanup is running
- [ ] Logs are being rotated

---

### 10. Testing

#### API Health Check
```bash
# Check health endpoint
curl https://api.yourdomain.com/health

# Expected response:
# {"success":true,"message":"API is running","data":{"status":"healthy"}}
```

#### Authentication Test
```bash
# Test admin login
curl -X POST https://api.yourdomain.com/admin/login \
    -H "Content-Type: application/json" \
    -d '{"username":"admin","password":"Admin@123456"}'

# Expected: 200 OK with token (CHANGE PASSWORD IMMEDIATELY!)
```

#### Rate Limiting Test
```bash
# Test rate limiting (should block after 3 attempts)
for i in {1..5}; do
    curl -X POST https://api.yourdomain.com/admin/login \
        -H "Content-Type: application/json" \
        -d '{"username":"test","password":"wrong"}'
    echo "Attempt $i"
done

# Expected: 429 Too Many Requests on attempts 4-5
```

#### ✅ Testing Validation
- [ ] Health endpoint returns 200
- [ ] Admin login works
- [ ] Product listing works
- [ ] Rate limiting blocks after threshold
- [ ] CORS works with frontend
- [ ] Security headers are present

---

### 11. Post-Deployment Actions

#### Immediate Actions (First Hour)
1. [ ] **CHANGE DEFAULT ADMIN PASSWORD**
   ```bash
   # Access admin panel and change password via UI
   # Or manually update in database:
   UPDATE admin_users 
   SET password_hash = '$2y$10$[NEW_BCRYPT_HASH]' 
   WHERE username = 'admin';
   ```

2. [ ] Create additional admin users (if needed)

3. [ ] Test all critical endpoints

4. [ ] Verify vendor API integration

5. [ ] Test payment flow (sandbox mode first)

#### First 24 Hours
1. [ ] Monitor error logs continuously
2. [ ] Watch for suspicious activity
3. [ ] Verify cron jobs executed successfully
4. [ ] Check database performance
5. [ ] Monitor API response times

#### First Week
1. [ ] Review security logs daily
2. [ ] Monitor vendor API usage and errors
3. [ ] Check reservation failures
4. [ ] Optimize slow queries if any
5. [ ] Gather performance metrics

---

### 12. Rollback Plan

#### If Critical Issue Occurs
```bash
# 1. Backup current state
mysqldump -u root -p teletrade_hub > backup_$(date +%Y%m%d_%H%M%S).sql

# 2. Restore previous version
cd /var/www
mv teletrade-hub-backend teletrade-hub-backend-broken
mv teletrade-hub-backend-backup teletrade-hub-backend

# 3. Restart services
systemctl restart nginx
systemctl restart php8.1-fpm

# 4. Verify rollback
curl https://api.yourdomain.com/health
```

#### ✅ Rollback Validation
- [ ] Backup plan documented
- [ ] Previous version backed up
- [ ] Database backup exists
- [ ] Rollback procedure tested

---

## Security Checklist Summary

### Critical Items (MUST be completed)
- [ ] APP_DEBUG=false, APP_ENV=production
- [ ] Strong, unique database password
- [ ] CORS_ALLOWED_ORIGINS set to specific domain(s)
- [ ] Default admin password changed
- [ ] HTTPS configured and working
- [ ] .env file secured (600 permissions)
- [ ] Firewall configured
- [ ] SSL certificate installed
- [ ] File permissions set correctly
- [ ] Customer order ownership validation tested

### High Priority Items (Complete within 1 week)
- [ ] Monitoring and alerting configured
- [ ] Log rotation configured
- [ ] Fail2Ban configured
- [ ] Cron jobs running successfully
- [ ] All API endpoints tested
- [ ] Payment webhook signature validation
- [ ] CSRF tokens for admin actions

### Medium Priority Items (Complete within 30 days)
- [ ] MFA for admin accounts
- [ ] Centralized logging (ELK/Splunk)
- [ ] Automated security scanning
- [ ] Penetration testing completed
- [ ] Security incident response plan documented
- [ ] Backup and disaster recovery tested

---

## Contact & Support

### Emergency Contacts
- **DevOps Team:** devops@yourdomain.com
- **Security Team:** security@yourdomain.com
- **On-Call:** +XX-XXX-XXX-XXXX

### Documentation
- Security Audit Report: `SECURITY_AUDIT_REPORT.md`
- API Documentation: `README.md`
- Database Schema: `database/migrations.sql`

---

## Sign-Off

**Deployment Completed By:** ____________________  
**Date:** ____________________  
**Environment:** Production  
**Version:** 1.0.0  

**Verification:**
- [ ] All critical items completed
- [ ] Security checklist passed
- [ ] Testing completed successfully
- [ ] Monitoring active
- [ ] Team notified

**DEPLOYMENT APPROVED:** ☐ YES ☐ NO

---

**END OF DEPLOYMENT CHECKLIST**

