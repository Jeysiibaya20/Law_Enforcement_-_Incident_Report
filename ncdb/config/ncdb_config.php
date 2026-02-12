<?php
/**
 * NCDB (National Crime Database) Configuration
 * Secure connection configuration with encrypted credentials
 * 
 * Security Features:
 * - Encrypted credential storage
 * - Environment-based configuration
 * - Connection validation
 * - Rate limiting configuration
 * - Audit logging settings
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../config/db_connect.php';

// ============================================================================
// SECURITY CONFIGURATION
// ============================================================================

class NCDBConfig {
    
    private static $encryption_key;
    private static $config = [];
    
    /**
     * Initialize configuration
     */
    public static function init() {
        // Set encryption key from environment or config
        self::$encryption_key = defined('NCDB_ENCRYPTION_KEY') 
            ? NCDB_ENCRYPTION_KEY 
            : hash('sha256', 'ncdb_default_encryption_key_change_in_production', true);
        
        // Initialize default configuration
        self::$config = [
            // API Configuration
            'api' => [
                'timeout' => 30,
                'retry_attempts' => 3,
                'retry_delay' => 5,
                'max_timeout' => 60,
                'verify_ssl' => true,
                'follow_redirects' => true,
            ],
            
            // Cache Configuration
            'cache' => [
                'enabled' => true,
                'ttl_seconds' => 3600, // 1 hour default
                'max_cache_size_mb' => 500,
                'cleanup_interval_minutes' => 60,
            ],
            
            // Rate Limiting
            'rate_limit' => [
                'enabled' => true,
                'requests_per_hour' => 1000,
                'requests_per_minute' => 50,
                'burst_limit' => 10,
                'window_minutes' => 60,
            ],
            
            // Security & Logging
            'security' => [
                'require_authentication' => true,
                'require_2fa_for_export' => true,
                'encrypt_cache' => true,
                'encrypt_logs' => true,
                'log_sensitive_data' => false, // Always false in production
                'ip_whitelist_enabled' => false,
                'allowed_ips' => [],
            ],
            
            // Duplicate Detection
            'duplicate_detection' => [
                'enabled' => true,
                'similarity_threshold' => 0.85, // 85% match
                'auto_flag_duplicates' => true,
                'require_manual_review' => true,
                'merge_on_approval' => true,
            ],
            
            // Audit & Compliance
            'audit' => [
                'log_all_queries' => true,
                'log_retention_days' => 365,
                'enable_anomaly_detection' => true,
                'suspicious_threshold' => 5, // 5 unusual actions
            ],
            
            // National Crime Database Connections
            'databases' => [
                // Example: National PNP Criminal Records Database
                'pnp_criminal_records' => [
                    'name' => 'PNP Criminal Records',
                    'type' => 'REST',
                    'enabled' => false,
                    'endpoint' => 'https://ncdb.pnp.gov.ph/api/v1',
                    'api_key_env' => 'PNP_NCDB_API_KEY',
                    'api_secret_env' => 'PNP_NCDB_API_SECRET',
                    'timeout' => 30,
                    'requires_vpn' => true,
                ],
                
                // Example: NBI Records Database
                'nbi_records' => [
                    'name' => 'NBI Criminal Records',
                    'type' => 'REST',
                    'enabled' => false,
                    'endpoint' => 'https://ncdb.nbi.gov.ph/api/v1',
                    'api_key_env' => 'NBI_NCDB_API_KEY',
                    'api_secret_env' => 'NBI_NCDB_API_SECRET',
                    'timeout' => 30,
                    'requires_vpn' => true,
                ],
                
                // Example: BIR Records Database
                'bir_records' => [
                    'name' => 'BIR Tax Records',
                    'type' => 'SOAP',
                    'enabled' => false,
                    'endpoint' => 'https://ncdb.bir.gov.ph/webservices',
                    'api_key_env' => 'BIR_NCDB_API_KEY',
                    'api_secret_env' => 'BIR_NCDB_API_SECRET',
                    'timeout' => 45,
                    'requires_vpn' => true,
                ],
            ],
        ];
    }
    
    /**
     * Get configuration value
     */
    public static function get($key, $default = null) {
        if (empty(self::$config)) {
            self::init();
        }
        
        $keys = explode('.', $key);
        $value = self::$config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Encrypt sensitive data
     */
    public static function encrypt($data) {
        if (empty($data)) {
            return null;
        }
        
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', self::$encryption_key, true, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public static function decrypt($data) {
        if (empty($data)) {
            return null;
        }
        
        try {
            $data = base64_decode($data);
            $iv_length = openssl_cipher_iv_length('AES-256-CBC');
            $iv = substr($data, 0, $iv_length);
            $encrypted = substr($data, $iv_length);
            
            return openssl_decrypt($encrypted, 'AES-256-CBC', self::$encryption_key, true, $iv);
        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get database connection by name
     */
    public static function getDatabase($name) {
        $databases = self::get('databases');
        return isset($databases[$name]) ? $databases[$name] : null;
    }
    
    /**
     * Validate configuration completeness
     */
    public static function validate() {
        $errors = [];
        
        // Check encryption key
        if (!defined('NCDB_ENCRYPTION_KEY') || empty(NCDB_ENCRYPTION_KEY)) {
            $errors[] = 'NCDB_ENCRYPTION_KEY not set in environment';
        }
        
        // Check database connection
        global $pdo;
        if (empty($pdo)) {
            $errors[] = 'Database connection not established';
        }
        
        // Check at least one database is configured
        $enabled_databases = array_filter(
            self::get('databases', []),
            fn($db) => isset($db['enabled']) && $db['enabled']
        );
        
        if (empty($enabled_databases)) {
            $errors[] = 'No National Crime Databases configured and enabled';
        }
        
        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
        ];
    }
    
    /**
     * Check if NCDB feature is enabled
     */
    public static function isEnabled() {
        return defined('NCDB_ENABLED') && NCDB_ENABLED === true;
    }
    
    /**
     * Get list of enabled databases
     */
    public static function getEnabledDatabases() {
        $databases = self::get('databases', []);
        return array_filter(
            $databases,
            fn($db) => isset($db['enabled']) && $db['enabled']
        );
    }
}

// Initialize configuration on include
NCDBConfig::init();

// ============================================================================
// SECURITY INITIALIZATION
// ============================================================================

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'ncdb_config.php') {
    http_response_code(403);
    exit('Access Denied');
}

// Define NCDB encryption key if not already defined
if (!defined('NCDB_ENCRYPTION_KEY')) {
    // In production, set this in environment variables
    // For development, use a default (but change in production!)
    define('NCDB_ENCRYPTION_KEY', getenv('NCDB_ENCRYPTION_KEY') ?: '');
}

// Enable NCDB by default (can be toggled)
if (!defined('NCDB_ENABLED')) {
    define('NCDB_ENABLED', true);
}

?>
