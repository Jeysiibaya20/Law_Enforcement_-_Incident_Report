# National Crime Database (NCDB) Integration System

Complete secure integration system for verifying records against national crime databases with full audit logging, duplicate detection, and compliance features.

## 📋 Table of Contents

- [Features](#features)
- [Architecture](#architecture)
- [Security Implementation](#security-implementation)
- [Installation & Setup](#installation--setup)
- [Configuration](#configuration)
- [Usage](#usage)
- [API Reference](#api-reference)
- [Testing](#testing)
- [Maintenance](#maintenance)

## ✨ Features

### 1. **Secure Data Connection**
- Encrypted API credential storage (AES-256-CBC)
- Support for multiple connection types (REST, SOAP, Database, File)
- Configurable timeout and retry logic
- SSL/TLS verification support
- VPN requirement support for sensitive connections

### 2. **Record Verification**
- Identity verification
- Criminal history checking
- Warrant verification
- Case lookup and cross-referencing
- Real-time NCDB synchronization

### 3. **Duplicate Detection**
- Fuzzy string matching (Levenshtein distance)
- Similarity scoring algorithm
- Configurable confidence thresholds
- Multiple record type support (Blotter, Case, Suspect, Witness)
- Manual review workflow
- Duplicate merge capabilities

### 4. **Comprehensive Audit Logging**
- All data access is logged with encryption
- Suspicious activity detection and flagging
- IP geolocation tracking
- User-agent logging
- Rate limit enforcement
- Anomaly threshold detection
- Security alert generation

### 5. **Performance Optimization**
- Intelligent result caching with TTL
- Cache hit/miss tracking
- Query optimization with indexes
- Async operation support
- Bulk operation handling

### 6. **Compliance & Security**
- Role-based access control (Officer, Admin)
- Data encryption at rest
- Audit trail for compliance
- GDPR/Privacy considerations
- Suspicious activity monitoring
- Threat level assessment

## 🏗️ Architecture

```
ncdb/
├── config/
│   ├── ncdb_config.php          # Configuration and encryption
│   └── ncdb_schema.sql          # Database schema
├── controllers/
│   └── query.php                # AJAX/API endpoint
├── services/
│   ├── NCDatabaseService.php    # Main NCDB service
│   ├── DuplicateDetectionService.php # Duplicate detection
│   └── AccessAuditLogger.php    # Audit logging
├── views/
│   ├── index.php                # Main verification interface
│   ├── admin_dashboard.php      # Admin configuration
│   └── test.php                 # Testing & verification
└── logs/
    └── security_alerts.log      # Security alert log
```

### Database Schema

#### ncdb_connections
Stores encrypted NCDB API credentials and connection configurations.

#### ncdb_cache
Intelligent caching system to reduce API calls and improve response time.

#### ncdb_access_logs
Comprehensive audit trail of all NCDB operations with encryption and threat detection.

#### ncdb_duplicate_detection
Tracks potential and confirmed duplicate records with confidence scoring.

#### ncdb_verification_results
Stores verification results with timestamps and expiration.

#### ncdb_sync_history
Records all synchronization operations with detailed logging.

#### ncdb_rate_limits
Tracks API rate limiting per user and connection.

## 🔒 Security Implementation

### Encryption
```php
// AES-256-CBC encryption for sensitive data
NCDBConfig::encrypt($sensitive_data);
NCDBConfig::decrypt($encrypted_data);
```

### Authentication
- Session-based authentication required
- Role validation (Officer/Admin)
- 2FA required for sensitive operations

### Authorization
- Row-level access control
- User-specific audit trails
- Department-based filtering

### Data Protection
- Encrypted storage of API credentials
- Encrypted query parameter logging
- Encrypted sensitive data in logs
- Data minimization principles

### Threat Detection
- Unusual activity pattern detection
- Rate limit enforcement
- Anomaly threshold monitoring
- Suspicious activity flagging
- IP-based threat assessment

## 📦 Installation & Setup

### Step 1: Create Database Tables

Run the SQL schema file to create all required tables:

```bash
mysql -u your_user -p your_database < ncdb/config/ncdb_schema.sql
```

Or import through phpMyAdmin:
1. Go to phpMyAdmin
2. Select your database
3. Click "Import"
4. Select `ncdb/config/ncdb_schema.sql`
5. Click "Go"

### Step 2: Configure Environment

Edit `ncdb/config/ncdb_config.php`:

```php
// Set encryption key (required for production)
define('NCDB_ENCRYPTION_KEY', getenv('NCDB_ENCRYPTION_KEY') ?: 'your-production-key');

// Enable NCDB feature
define('NCDB_ENABLED', true);
```

### Step 3: Add Connections

1. Navigate to Admin Dashboard: `/ncdb/views/admin_dashboard.php`
2. Click "Add New Connection"
3. Enter connection details:
   - Connection Name: e.g., "PNP Criminal Records"
   - API Endpoint: e.g., `https://ncdb.pnp.gov.ph/api/v1`
   - API Key: Your API key
   - API Secret: Your API secret
4. Click "Add Connection"

### Step 4: Test Connections

1. In Admin Dashboard, find your connection
2. Click "Test" button
3. Verify status shows "Active"

### Step 5: Navigate Links

Add navigation links to your navbar:

```php
<a href="/ncdb/views/index.php" class="nav-link">NCDB Verification</a>
<a href="/ncdb/views/admin_dashboard.php" class="nav-link">NCDB Admin</a>
```

## ⚙️ Configuration

### Cache Settings

```php
NCDBConfig::get('cache.enabled');              // Enable/disable caching
NCDBConfig::get('cache.ttl_seconds');          // Cache TTL (default: 3600)
NCDBConfig::get('cache.max_cache_size_mb');    // Max cache size
```

### Rate Limiting

```php
NCDBConfig::get('rate_limit.enabled');                // Enable/disable
NCDBConfig::get('rate_limit.requests_per_hour');      // Limit per hour
NCDBConfig::get('rate_limit.requests_per_minute');    // Limit per minute
```

### Duplicate Detection

```php
NCDBConfig::get('duplicate_detection.enabled');              // Enable/disable
NCDBConfig::get('duplicate_detection.similarity_threshold'); // Threshold (0-1)
NCDBConfig::get('duplicate_detection.require_manual_review'); // Require review
```

### Security Settings

```php
NCDBConfig::get('security.require_authentication');  // Require login
NCDBConfig::get('security.require_2fa_for_export');  // 2FA for export
NCDBConfig::get('security.encrypt_cache');           // Encrypt cached data
NCDBConfig::get('security.encrypt_logs');            // Encrypt logs
NCDBConfig::get('security.log_sensitive_data');      // Log sensitive data
```

## 🎯 Usage

### Verify a Record

1. Go to `/ncdb/views/index.php`
2. Select Record Type (Blotter, Case, Suspect, Witness)
3. Select the specific record
4. Choose Verification Type (Identity, Criminal History, Warrant, Case Lookup)
5. Click "Verify Record"

The system will:
- Query NCDB for matches
- Check for duplicates
- Log the operation
- Display results with confidence levels

### Handle Duplicates

When duplicates are found:
1. Review the "Potential Duplicates Found" section
2. Review each match with confidence score
3. Click "Flag" to mark as confirmed duplicate
4. System logs action for compliance

### View Audit Logs

1. Go to Admin Dashboard
2. Click "Access Logs" tab
3. View all NCDB operations
4. Filter by user, action type, date range

### Monitor System Health

1. Go to Testing & Verification: `/ncdb/views/test.php`
2. Run appropriate test suite:
   - Configuration
   - Database Tables
   - Connections
   - Security
   - Duplicate Detection
   - Performance
   - Run All Tests

## 🔌 API Reference

### NCDatabaseService

```php
// Initialize service
$service = new NCDatabaseService($pdo, $connection_id);

// Verify a record
$result = $service->verifyRecord(
    'BLOTTER',                    // Record type
    123,                          // Record ID
    'IDENTITY_VERIFICATION'       // Verification type
);

// Query NCDB
$results = $service->query(
    'CRIMINAL_HISTORY',           // Query type
    ['name' => 'John Doe']        // Parameters
);

// Test connection
$status = $service->testConnection($connection_id);
```

### DuplicateDetectionService

```php
// Initialize service
$dup_service = new DuplicateDetectionService($pdo);

// Check for duplicates
$check = $dup_service->checkForDuplicates(
    'SUSPECT',                    // Record type
    $record_data                  // Record data
);

// Flag as duplicate
$dup_service->flagAsDuplicate(
    123,                          // Local record ID
    'SUSPECT',                    // Record type
    456,                          // NCDB record ID
    'Manual review completed'     // Notes
);

// Get statistics
$stats = $dup_service->getDuplicateStatistics();
```

### AccessAuditLogger

```php
// Initialize logger
$logger = new AccessAuditLogger($pdo, $user_id);

// Log access
$logger->logAccess(
    'QUERY',                      // Action type
    'CRIMINAL_HISTORY',           // Query type
    $parameters,                  // Parameters
    42,                           // Result count
    156,                          // Execution time (ms)
    'SUCCESS'                     // Status
);

// Get audit trail
$trail = $logger->getAuditTrail($user_id, 100, 0);

// Generate summary
$summary = $logger->generateAuditSummary(30); // Last 30 days
```

## ✅ Testing

### Run System Tests

Navigate to `/ncdb/views/test.php` and run tests:

**Configuration Tests**
- Encryption key configured
- NCDB feature enabled
- Configuration file accessible

**Database Tests**
- All required tables exist
- Tables are accessible
- Indexes are in place

**Connection Tests**
- Connections are configured
- Connections are active
- Test connections pass

**Security Tests**
- Encryption/decryption working
- Audit logging functional
- Rate limiting operational

**Duplicate Detection Tests**
- Duplicate detection working
- Pending reviews retrievable
- Merge functionality operational

**Performance Tests**
- Cache query performance < 100ms
- Log query performance < 200ms
- Index usage efficient

## 🛠️ Maintenance

### Database Cleanup

Clean up expired cache entries:

```sql
DELETE FROM ncdb_cache WHERE expires_at < NOW();
```

Delete old audit logs (older than 1 year):

```sql
DELETE FROM ncdb_access_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 365 DAY);
```

### Backup Recommendations

Backup `ncdb_access_logs` regularly for compliance:

```bash
mysqldump -u user -p database ncdb_access_logs > ncdb_logs_backup.sql
```

### Monitor Performance

Regular index analysis:

```sql
ANALYZE TABLE ncdb_access_logs;
ANALYZE TABLE ncdb_cache;
ANALYZE TABLE ncdb_duplicate_detection;
```

### Security Checks

- Review audit logs monthly
- Check for suspicious activities
- Test encryption keys periodically
- Update API endpoints when needed
- Rotate credentials quarterly

## 📊 Performance Optimization

### Indexes Created

- `idx_ncdb_access_time_range` - Fast time-based queries
- `idx_ncdb_cache_expire_cleanup` - Quick cache cleanup
- `idx_ncdb_sync_progress` - Sync monitoring
- `idx_ncdb_duplicate_confidence` - Duplicate filtering

### Caching Strategy

Default TTL: 3600 seconds (1 hour)
Cache hit tracking for analytics
Automatic expiration cleanup

### Query Optimization

Parameterized queries prevent SQL injection
Batch operations for bulk inserts
Connection pooling support

## 📞 Support & Documentation

- Admin Dashboard: `/ncdb/views/admin_dashboard.php`
- Testing Interface: `/ncdb/views/test.php`
- Verification Interface: `/ncdb/views/index.php`
- Configuration: `ncdb/config/ncdb_config.php`

## ✅ Checklist

- [x] Secure data connections (AES-256 encryption)
- [x] Record verification with NCDB
- [x] Duplicate case record prevention
- [x] Complete audit logging with encryption
- [x] Security alerts and threat detection
- [x] Database integration testing
- [x] Performance optimization
- [x] Role-based access control
- [x] Compliance audit trail
- [x] Admin configuration interface

---

**Version:** 1.0.0  
**Last Updated:** January 2026  
**Status:** Production Ready ✓
