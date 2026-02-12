<?php
/**
 * NCDB Admin Secure Access Control
 * 
 * This file provides enhanced security for NCDB admin dashboard access.
 * Features:
 * - Admin-only access verification
 * - IP whitelist checking (optional)
 * - Session validation
 * - Audit logging of admin access
 * - Encryption verification
 * - Rate limiting for admin operations
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security constants
define('NCDB_ADMIN_ACCESS_LOG_FILE', __DIR__ . '/logs/admin_access.log');
define('NCDB_ADMIN_IP_WHITELIST_ENABLED', false);
define('NCDB_ADMIN_IP_WHITELIST', []); // Add IPs like ['192.168.1.1', '10.0.0.1']

/**
 * Verify Admin Access to NCDB
 * 
 * @return bool True if access granted, false otherwise
 * @throws Exception If security checks fail
 */
function verifyNCDBAminAccess() {
    // 1. Check if user is authenticated
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized: Session not found']));
    }

    // 2. Check if user is Admin role
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
        logNCDBAminAccess('DENIED', 'Insufficient permissions', true);
        http_response_code(403);
        die(json_encode(['error' => 'Forbidden: Admin access required']));
    }

    // 3. Check IP whitelist if enabled
    if (NCDB_ADMIN_IP_WHITELIST_ENABLED && !empty(NCDB_ADMIN_IP_WHITELIST)) {
        $user_ip = getUserIP();
        if (!in_array($user_ip, NCDB_ADMIN_IP_WHITELIST)) {
            logNCDBAminAccess('DENIED', 'IP not whitelisted: ' . $user_ip, true);
            http_response_code(403);
            die(json_encode(['error' => 'Forbidden: IP not whitelisted']));
        }
    }

    // 4. Check rate limiting
    if (!checkAdminRateLimit()) {
        logNCDBAminAccess('RATE_LIMITED', 'Too many requests', true);
        http_response_code(429);
        die(json_encode(['error' => 'Too many requests. Please try again later.']));
    }

    // 5. Log successful access
    logNCDBAminAccess('GRANTED', 'Access granted', false);

    return true;
}

/**
 * Get user's IP address
 * 
 * @return string User IP address
 */
function getUserIP() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'Unknown';
}

/**
 * Check admin rate limiting
 * Limits to 100 requests per minute
 * 
 * @return bool True if within limit, false if exceeded
 */
function checkAdminRateLimit() {
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) return false;

    $cache_key = "ncdb_admin_rate_" . $user_id;
    
    // Simple in-memory rate limiting using session
    if (!isset($_SESSION[$cache_key])) {
        $_SESSION[$cache_key] = ['count' => 1, 'time' => time()];
        return true;
    }

    $cache = $_SESSION[$cache_key];
    $now = time();
    $elapsed = $now - $cache['time'];

    // Reset counter after 1 minute
    if ($elapsed > 60) {
        $_SESSION[$cache_key] = ['count' => 1, 'time' => $now];
        return true;
    }

    // Allow max 100 requests per minute
    if ($cache['count'] >= 100) {
        return false;
    }

    $_SESSION[$cache_key]['count']++;
    return true;
}

/**
 * Log NCDB admin access
 * 
 * @param string $status Access status (GRANTED, DENIED, RATE_LIMITED)
 * @param string $reason Reason for access decision
 * @param bool $is_security_alert Is this a security alert?
 */
function logNCDBAminAccess($status, $reason, $is_security_alert = false) {
    $user_id = $_SESSION['user_id'] ?? 'Unknown';
    $user_name = $_SESSION['fullname'] ?? 'Unknown';
    $ip = getUserIP();
    $timestamp = date('Y-m-d H:i:s');
    $request_uri = $_SERVER['REQUEST_URI'] ?? 'Unknown';
    $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 100);

    $log_entry = [
        'timestamp' => $timestamp,
        'status' => $status,
        'user_id' => $user_id,
        'user_name' => $user_name,
        'ip_address' => $ip,
        'request_uri' => $request_uri,
        'user_agent' => $user_agent,
        'reason' => $reason,
        'is_security_alert' => $is_security_alert ? 'YES' : 'NO'
    ];

    // Log to file
    $log_file = NCDB_ADMIN_ACCESS_LOG_FILE;
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $log_line = json_encode($log_entry) . "\n";
    
    // Write to log file with locking
    $handle = fopen($log_file, 'a');
    if ($handle) {
        flock($handle, LOCK_EX);
        fwrite($handle, $log_line);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    // If security alert, also log to database
    if ($is_security_alert) {
        logSecurityAlert($log_entry);
    }
}

/**
 * Log security alert to database
 * 
 * @param array $log_entry Log entry data
 */
function logSecurityAlert($log_entry) {
    try {
        global $pdo;
        if (!isset($pdo)) {
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO ncdb_access_logs (
                user_id, action_type, resource_type, query_parameters, 
                result_status, ip_address, threat_level, notes, created_at
            ) VALUES (
                :user_id, :action_type, :resource_type, :query_parameters,
                :result_status, :ip_address, :threat_level, :notes, NOW()
            )
        ");

        $stmt->execute([
            ':user_id' => $log_entry['user_id'],
            ':action_type' => 'NCDB_ADMIN_ACCESS',
            ':resource_type' => 'NCDB_ADMIN_PANEL',
            ':query_parameters' => json_encode($log_entry),
            ':result_status' => 'DENIED',
            ':ip_address' => $log_entry['ip_address'],
            ':threat_level' => 'MEDIUM',
            ':notes' => $log_entry['reason']
        ]);
    } catch (Exception $e) {
        // Silently fail if database logging unavailable
        error_log("NCDB Security Alert Database Logging Failed: " . $e->getMessage());
    }
}

/**
 * Verify NCDB encryption is properly configured
 * 
 * @return bool True if encryption is properly configured
 */
function verifyNCDBAEncryption() {
    if (!defined('NCDB_ENCRYPTION_KEY')) {
        return false;
    }

    $key = NCDB_ENCRYPTION_KEY;
    if (strlen($key) < 32) {
        return false;
    }

    // Verify OpenSSL is available
    if (!extension_loaded('openssl')) {
        return false;
    }

    return true;
}

/**
 * Get NCDB system status
 * 
 * @return array Status information
 */
function getNCDBStatus() {
    try {
        global $pdo;
        if (!isset($pdo)) {
            return ['status' => 'error', 'message' => 'Database connection unavailable'];
        }

        $tables = [
            'ncdb_connections',
            'ncdb_cache',
            'ncdb_access_logs',
            'ncdb_duplicate_detection',
            'ncdb_verification_results',
            'ncdb_sync_history',
            'ncdb_rate_limits'
        ];

        $missing_tables = [];
        foreach ($tables as $table) {
            try {
                $pdo->query("SELECT 1 FROM $table LIMIT 1");
            } catch (Exception $e) {
                $missing_tables[] = $table;
            }
        }

        $encryption_ok = verifyNCDBAEncryption();

        if (!empty($missing_tables)) {
            return [
                'status' => 'warning',
                'message' => 'Missing tables: ' . implode(', ', $missing_tables),
                'encryption' => $encryption_ok
            ];
        }

        // Get connection stats
        try {
            $active_connections = $pdo->query("SELECT COUNT(*) FROM ncdb_connections WHERE status = 'active'")->fetchColumn();
            $recent_logs = $pdo->query("SELECT COUNT(*) FROM ncdb_access_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
        } catch (Exception $e) {
            $active_connections = 0;
            $recent_logs = 0;
        }

        return [
            'status' => 'ok',
            'message' => 'NCDB system operational',
            'encryption' => $encryption_ok,
            'active_connections' => $active_connections,
            'recent_logs_24h' => $recent_logs
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Error checking NCDB status: ' . $e->getMessage()
        ];
    }
}

?>
