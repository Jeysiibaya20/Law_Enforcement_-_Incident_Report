# NCDB Installation & Setup Guide

## 📋 Pre-requisites

- PHP 7.4+ with openssl extension
- MySQL 5.7+ or MariaDB 10.2+
- Law Enforcement Incident Report system installed
- Administrator access to database and server

## 📦 Installation Steps

### Step 1: Verify File Structure

Ensure all NCDB files are in place:

```
ncdb/
├── config/
│   ├── ncdb_config.php          ✓
│   └── ncdb_schema.sql          ✓
├── controllers/
│   └── query.php                ✓
├── services/
│   ├── NCDatabaseService.php    ✓
│   ├── DuplicateDetectionService.php ✓
│   └── AccessAuditLogger.php    ✓
├── views/
│   ├── index.php                ✓
│   ├── admin_dashboard.php      ✓
│   └── test.php                 ✓
├── css/
│   └── ncdb_styles.css          ✓
├── logs/
│   └── (empty - for logs)       ✓
├── README.md                    ✓
└── QUICKSTART.md                ✓
```

### Step 2: Create Directory for Logs

```bash
# Linux/Mac
mkdir -p ncdb/logs
chmod 755 ncdb/logs

# Windows (if not already created)
# Just ensure ncdb/logs folder exists
```

### Step 3: Import Database Schema

**Option A: Using Command Line**

```bash
cd /path/to/Law_Enforcement_-_Incident_Report
mysql -u your_username -p your_database < ncdb/config/ncdb_schema.sql
```

**Option B: Using phpMyAdmin**

1. Open phpMyAdmin
2. Select your database
3. Click "Import" tab
4. Choose file: `ncdb/config/ncdb_schema.sql`
5. Click "Go"
6. Wait for "Success" message

**Option C: Copy-Paste SQL**

1. Open phpMyAdmin
2. Click "SQL" tab
3. Copy entire contents of `ncdb/config/ncdb_schema.sql`
4. Paste into SQL editor
5. Click "Go"

### Step 4: Verify Database Tables

Run this query to verify all tables were created:

```sql
SELECT TABLE_NAME 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'your_database' 
AND TABLE_NAME LIKE 'ncdb_%' 
ORDER BY TABLE_NAME;
```

You should see 7 tables:
- ncdb_access_logs
- ncdb_cache
- ncdb_connections
- ncdb_duplicate_detection
- ncdb_rate_limits
- ncdb_sync_history
- ncdb_verification_results

### Step 5: Configure PHP Encryption

Edit `ncdb/config/ncdb_config.php`:

**For Development:**
```php
define('NCDB_ENCRYPTION_KEY', ''); // Use default
define('NCDB_ENABLED', true);
```

**For Production:**
```php
// Generate a secure key:
// php -r "echo bin2hex(openssl_random_pseudo_bytes(32));"

define('NCDB_ENCRYPTION_KEY', 'your_generated_hex_key_here');
define('NCDB_ENABLED', true);
```

### Step 6: Create Security Log File

```bash
# Linux/Mac
touch ncdb/logs/security_alerts.log
chmod 644 ncdb/logs/security_alerts.log

# Windows
# Create empty file: ncdb\logs\security_alerts.log
```

### Step 7: Test System Installation

Navigate to: `http://your-domain/ncdb/views/test.php`

Run "Run All Tests" and verify:
- ✓ Configuration Tests: PASS
- ✓ Database Tables Test: PASS
- ✓ Connection Tests: PASS (after adding connection)
- ✓ Security Tests: PASS
- ✓ Duplicate Detection Tests: PASS
- ✓ Performance Tests: PASS

### Step 8: Add Navigation Links

Edit `includes/navbar.php` and add:

```php
<?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'Officer' || $_SESSION['role'] === 'Admin')): ?>
    <li class="nav-item">
        <a class="nav-link" href="<?php echo $base_url; ?>ncdb/views/index.php">
            <i class="bi bi-database-check"></i> NCDB Verification
        </a>
    </li>
    <?php if ($_SESSION['role'] === 'Admin'): ?>
        <li class="nav-item">
            <a class="nav-link" href="<?php echo $base_url; ?>ncdb/views/admin_dashboard.php">
                <i class="bi bi-gear"></i> NCDB Admin
            </a>
        </li>
    <?php endif; ?>
<?php endif; ?>
```

### Step 9: Add CSS Reference

Edit your main header file and add before closing `</head>`:

```html
<!-- NCDB Styles -->
<link rel="stylesheet" href="<?php echo $base_url; ?>ncdb/css/ncdb_styles.css">
```

### Step 10: Configure First Connection

1. Login as Administrator
2. Navigate to: `http://your-domain/ncdb/views/admin_dashboard.php`
3. Click "Add New Connection"
4. Fill in your national crime database details:
   - Connection Name: e.g., "PNP Criminal Records"
   - API Endpoint: Your NCDB API URL
   - Connection Type: REST/SOAP/Database/File
   - API Key: Your API key
   - API Secret: Your API secret
   - Timeout: 30 seconds (default)
   - Retry Attempts: 3 (default)
5. Click "Add Connection"

### Step 11: Test Connection

1. In Admin Dashboard, find your connection
2. Click "Test" button
3. Verify status shows "Active" (green checkmark)

## ✅ Installation Verification Checklist

- [ ] All NCDB files are in place
- [ ] Database tables created successfully
- [ ] `ncdb/logs` directory exists and is writable
- [ ] PHP openssl extension is enabled
- [ ] `NCDB_ENCRYPTION_KEY` configured
- [ ] All tests pass in test.php
- [ ] Navigation links added
- [ ] CSS file linked in header
- [ ] First connection added and tested
- [ ] Users can access NCDB interface

## 🔒 Security Configuration

### Encryption Key Generation (Linux/Mac)

```bash
php -r "echo 'define(\'NCDB_ENCRYPTION_KEY\', \'' . bin2hex(openssl_random_pseudo_bytes(32)) . '\');' . PHP_EOL;"
```

Copy the output and paste into `ncdb/config/ncdb_config.php`

### Encryption Key Generation (Windows)

```php
<?php
echo 'define(\'NCDB_ENCRYPTION_KEY\', \'' . bin2hex(openssl_random_pseudo_bytes(32)) . '\');';
?>
```

### File Permissions (Linux/Mac)

```bash
# Configuration files should be readable but not executable
chmod 644 ncdb/config/ncdb_config.php
chmod 644 ncdb/config/ncdb_schema.sql

# Logs directory should be writable
chmod 755 ncdb/logs
chmod 644 ncdb/logs/*.log

# PHP files should be readable
chmod 644 ncdb/views/*.php
chmod 644 ncdb/controllers/*.php
chmod 644 ncdb/services/*.php
```

## 🌐 Web Server Configuration

### Apache (.htaccess)

Create `ncdb/.htaccess`:

```apache
# Prevent direct access to sensitive files
<FilesMatch "\.env|\.sql|config">
    Order allow,deny
    Deny from all
</FilesMatch>

# Require authentication for logs directory
<Directory "logs">
    Order allow,deny
    Deny from all
</Directory>

# Enable PHP
AddType application/x-httpd-php .php
```

### Nginx

Add to your vhost configuration:

```nginx
# Prevent access to sensitive files
location ~ /ncdb/(config|logs|\.env|\.sql) {
    deny all;
    access_log off;
    log_not_found off;
}

# Require authentication
location /ncdb/views/ {
    auth_basic "NCDB - Restricted Access";
    auth_basic_user_file /path/to/.htpasswd;
}
```

## 🐛 Troubleshooting

### Issue: "Tables not found"

**Solution:**
```sql
-- Run schema file again
source ncdb/config/ncdb_schema.sql;

-- Verify tables exist
SHOW TABLES LIKE 'ncdb_%';
```

### Issue: "Encryption errors"

**Solution:**
- Verify `NCDB_ENCRYPTION_KEY` is set
- Check PHP openssl extension: `php -m | grep openssl`
- Ensure key is valid hex string

### Issue: "Permission denied on logs"

**Solution:**
```bash
chmod 755 ncdb/logs
chmod 644 ncdb/logs/security_alerts.log
```

### Issue: "Cannot connect to database"

**Solution:**
- Verify database credentials in `config/db_connect.php`
- Test connection: `mysql -u user -p database`
- Check database server is running

### Issue: "Tests failing"

**Solution:**
1. Go to `/ncdb/views/test.php`
2. Check specific test that failed
3. Run individual tests to isolate issue
4. Review error message carefully
5. Consult [README.md](./README.md) section matching error

## 📚 Next Steps

After installation:

1. **Read Documentation**: [README.md](./README.md)
2. **Quick Start**: [QUICKSTART.md](./QUICKSTART.md)
3. **Run Tests**: http://your-domain/ncdb/views/test.php
4. **Configure Connections**: Admin Dashboard
5. **Test Verification**: Try verifying a sample record

## 🎓 Training

### For Officers

- Access: `/ncdb/views/index.php`
- Can verify records against NCDB
- Can check for duplicates
- Cannot configure connections

### For Administrators

- Access: `/ncdb/views/admin_dashboard.php`
- Can manage connections
- Can view access logs
- Can configure settings
- Can run tests

## 📞 Support

If you encounter issues:

1. Check error logs: `ncdb/logs/security_alerts.log`
2. Run diagnostic tests: `/ncdb/views/test.php`
3. Review [README.md](./README.md) for detailed information
4. Check database connectivity
5. Verify file permissions
6. Ensure PHP extensions are installed

## ✅ Installation Complete!

Your NCDB system is now ready to use. Access it via:

- **Verification Interface**: `/ncdb/views/index.php`
- **Admin Dashboard**: `/ncdb/views/admin_dashboard.php`
- **Testing Suite**: `/ncdb/views/test.php`

---

**Version:** 1.0.0  
**Last Updated:** January 2026  
**Status:** Ready for Production ✓
