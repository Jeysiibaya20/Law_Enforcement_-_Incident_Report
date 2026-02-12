<?php
/**
 * NCDB Access Audit Logger
 * Comprehensive logging system for all NCDB operations
 * 
 * Features:
 * - Encrypted parameter logging
 * - Suspicious activity detection
 * - IP geolocation tracking
 * - Rate limit enforcement
 * - Compliance audit trail
 */

require_once '../../config/db_connect.php';
require_once '../config/ncdb_config.php';

class AccessAuditLogger {
    
    private $pdo;
    private $user_id;
    private $ip_address;
    private $user_agent;
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo, $user_id = null) {
        $this->pdo = $pdo;
        $this->user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
        $this->ip_address = $this->getClientIP();
        $this->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }
    
    /**
     * Get client IP address (handles proxies)
     */
    private function getClientIP() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Handle multiple IPs
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Validate IP
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Get IP geolocation information
     */
    private function getIPGeolocation($ip) {
        // In production, integrate with MaxMind GeoIP2 or similar
        // For now, we'll use a placeholder
        
        if ($ip === '127.0.0.1' || $ip === 'localhost') {
            return 'LOCAL_NETWORK';
        }
        
        // This would typically call an external API
        // return json_encode(['country' => 'PH', 'city' => 'Unknown']);
        
        return null;
    }
    
    /**
     * Log NCDB access/query
     */
    public function logAccess(
        $action_type,
        $query_type = null,
        $query_parameters = null,
        $result_count = null,
        $execution_time_ms = null,
        $status = 'SUCCESS',
        $error_message = null
    ) {
        try {
            // Encrypt sensitive parameters if configured
            $encrypted_params = null;
            if (!empty($query_parameters) && NCDBConfig::get('security.log_sensitive_data') === false) {
                $encrypted_params = NCDBConfig::encrypt(json_encode($query_parameters));
            } elseif (!empty($query_parameters)) {
                $encrypted_params = json_encode($query_parameters);
            }
            
            // Detect suspicious activity
            $threat_level = $this->detectSuspiciousActivity($action_type, $query_parameters);
            $is_suspicious = $threat_level !== 'NONE';
            
            // Get IP geolocation
            $ip_geo = $this->getIPGeolocation($this->ip_address);
            
            // Prepare log entry
            $sql = "INSERT INTO ncdb_access_logs (
                user_id,
                user_ip_address,
                user_agent,
                action_type,
                query_type,
                query_parameters_encrypted,
                result_count,
                execution_time_ms,
                status,
                error_message,
                ip_geolocation,
                is_suspicious,
                threat_level,
                created_at
            ) VALUES (
                :user_id,
                :ip_address,
                :user_agent,
                :action_type,
                :query_type,
                :query_params,
                :result_count,
                :execution_time,
                :status,
                :error_message,
                :ip_geo,
                :is_suspicious,
                :threat_level,
                NOW()
            )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $this->user_id,
                ':ip_address' => $this->ip_address,
                ':user_agent' => substr($this->user_agent, 0, 500),
                ':action_type' => $action_type,
                ':query_type' => $query_type,
                ':query_params' => $encrypted_params,
                ':result_count' => $result_count,
                ':execution_time' => $execution_time_ms,
                ':status' => $status,
                ':error_message' => $error_message,
                ':ip_geo' => $ip_geo,
                ':is_suspicious' => $is_suspicious ? 1 : 0,
                ':threat_level' => $threat_level,
            ]);
            
            // Check if suspicious activity threshold exceeded
            if ($is_suspicious) {
                $this->checkAnomalyThreshold();
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Detect suspicious activity patterns
     */
    private function detectSuspiciousActivity($action_type, $query_parameters) {
        $threat_level = 'NONE';
        
        // Check for unusual query patterns
        if (!empty($query_parameters)) {
            // Suspicious patterns
            if (isset($query_parameters['search']) && strlen($query_parameters['search']) > 1000) {
                $threat_level = 'MEDIUM';
            }
            
            if (isset($query_parameters['limit']) && $query_parameters['limit'] > 10000) {
                $threat_level = 'HIGH';
            }
        }
        
        // Check action sequences
        if ($action_type === 'EXPORT') {
            $threat_level = max($threat_level, 'MEDIUM');
        }
        
        // Check rate limiting
        if ($this->isRateLimited()) {
            $threat_level = 'HIGH';
        }
        
        // Check for data access after hours (if applicable)
        $hour = (int)date('H');
        if ($hour < 6 || $hour > 22) {
            if ($action_type === 'QUERY' || $action_type === 'EXPORT') {
                $threat_level = max($threat_level, 'LOW');
            }
        }
        
        return $threat_level;
    }
    
    /**
     * Check if user has exceeded anomaly threshold
     */
    private function checkAnomalyThreshold() {
        try {
            $threshold = NCDBConfig::get('audit.suspicious_threshold', 5);
            $window = 3600; // 1 hour
            
            $sql = "SELECT COUNT(*) as suspicious_count FROM ncdb_access_logs 
                   WHERE user_id = :user_id 
                   AND is_suspicious = 1 
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':user_id' => $this->user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['suspicious_count'] >= $threshold) {
                // Trigger security alert
                $this->triggerSecurityAlert(
                    'ANOMALY_THRESHOLD_EXCEEDED',
                    "User {$this->user_id} exceeded suspicious activity threshold ({$result['suspicious_count']} actions)"
                );
            }
        } catch (Exception $e) {
            error_log("Anomaly check error: " . $e->getMessage());
        }
    }
    
    /**
     * Check if user is rate limited
     */
    private function isRateLimited() {
        try {
            $limit = NCDBConfig::get('rate_limit.requests_per_minute', 50);
            $window = date('Y-m-d H:i:00', time());
            
            $sql = "SELECT COUNT(*) as request_count FROM ncdb_access_logs 
                   WHERE user_id = :user_id 
                   AND DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:00') = :window";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $this->user_id,
                ':window' => $window,
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['request_count'] > $limit;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Trigger security alert
     */
    private function triggerSecurityAlert($alert_type, $message) {
        try {
            $alert_log = [
                'timestamp' => date('Y-m-d H:i:s'),
                'alert_type' => $alert_type,
                'user_id' => $this->user_id,
                'ip_address' => $this->ip_address,
                'message' => $message,
            ];
            
            $log_file = __DIR__ . '/../logs/security_alerts.log';
            file_put_contents(
                $log_file,
                json_encode($alert_log) . "\n",
                FILE_APPEND | LOCK_EX
            );
            
            // In production, also send email/SMS alerts
            // send_security_alert($alert_type, $message);
        } catch (Exception $e) {
            error_log("Failed to log security alert: " . $e->getMessage());
        }
    }
    
    /**
     * Log duplicate detection results
     */
    public function logDuplicateDetection($local_record_id, $local_record_type, $matches) {
        try {
            foreach ($matches as $match) {
                $sql = "INSERT INTO ncdb_duplicate_detection (
                    local_record_id,
                    local_record_type,
                    ncdb_match_id,
                    match_score,
                    matching_fields,
                    confidence_level,
                    created_at
                ) VALUES (
                    :local_id,
                    :record_type,
                    :match_id,
                    :match_score,
                    :matching_fields,
                    :confidence,
                    NOW()
                )";
                
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    ':local_id' => $local_record_id,
                    ':record_type' => $local_record_type,
                    ':match_id' => $match['ncdb_id'] ?? null,
                    ':match_score' => $match['score'] ?? null,
                    ':matching_fields' => json_encode($match['fields'] ?? []),
                    ':confidence' => $match['confidence'] ?? 'MEDIUM',
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Duplicate detection log error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get audit trail for user
     */
    public function getAuditTrail($user_id = null, $limit = 100, $offset = 0) {
        try {
            $uid = $user_id ?? $this->user_id;
            
            $sql = "SELECT * FROM ncdb_access_logs 
                   WHERE user_id = :user_id 
                   ORDER BY created_at DESC 
                   LIMIT :limit OFFSET :offset";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':user_id', $uid, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Audit trail retrieval error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate audit summary report
     */
    public function generateAuditSummary($days = 30) {
        try {
            $sql = "SELECT 
                    action_type,
                    COUNT(*) as count,
                    SUM(CASE WHEN status = 'SUCCESS' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN is_suspicious = 1 THEN 1 ELSE 0 END) as suspicious,
                    AVG(execution_time_ms) as avg_execution_time
                FROM ncdb_access_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY action_type
                ORDER BY count DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':days' => $days]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Audit summary error: " . $e->getMessage());
            return [];
        }
    }
}

?>
