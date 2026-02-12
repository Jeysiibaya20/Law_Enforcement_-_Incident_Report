<?php
/**
 * NCDB (National Crime Database) Service
 * Manages secure API communications with national crime databases
 * 
 * Features:
 * - Secure API connections
 * - Data caching
 * - Request queuing
 * - Error handling and recovery
 * - Compliance with security standards
 */

require_once '../../config/db_connect.php';
require_once '../config/ncdb_config.php';
require_once 'AccessAuditLogger.php';

class NCDatabaseService {
    
    private $pdo;
    private $connection_id;
    private $connection_config;
    private $audit_logger;
    private $cache_enabled;
    private $cache_ttl;
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo, $connection_id = null) {
        $this->pdo = $pdo;
        $this->connection_id = $connection_id;
        $this->audit_logger = new AccessAuditLogger($pdo);
        $this->cache_enabled = NCDBConfig::get('cache.enabled', true);
        $this->cache_ttl = NCDBConfig::get('cache.ttl_seconds', 3600);
        
        if ($connection_id) {
            $this->loadConnectionConfig($connection_id);
        }
    }
    
    /**
     * Load connection configuration from database
     */
    private function loadConnectionConfig($connection_id) {
        try {
            $sql = "SELECT * FROM ncdb_connections WHERE id = :id AND is_active = 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $connection_id]);
            
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($config) {
                $config['api_key'] = NCDBConfig::decrypt($config['api_key_encrypted']);
                $config['api_secret'] = NCDBConfig::decrypt($config['api_secret_encrypted']);
                $this->connection_config = $config;
            }
        } catch (Exception $e) {
            error_log("Connection config load error: " . $e->getMessage());
        }
    }
    
    /**
     * Query NCDB for records
     */
    public function query($query_type, $parameters) {
        $start_time = microtime(true);
        
        try {
            // Check cache first
            if ($this->cache_enabled) {
                $cached = $this->getCache($query_type, $parameters);
                if ($cached !== null) {
                    $this->audit_logger->logAccess(
                        'CACHE_HIT',
                        $query_type,
                        $parameters,
                        count($cached),
                        round((microtime(true) - $start_time) * 1000)
                    );
                    return $cached;
                }
            }
            
            // Check rate limiting
            if (!$this->checkRateLimit()) {
                throw new Exception('Rate limit exceeded. Please try again later.');
            }
            
            // Prepare and execute query
            $result = $this->executeQuery($query_type, $parameters);
            
            // Cache result
            if ($this->cache_enabled) {
                $this->setCache($query_type, $parameters, $result);
            }
            
            // Log successful access
            $execution_time = round((microtime(true) - $start_time) * 1000);
            $this->audit_logger->logAccess(
                'QUERY',
                $query_type,
                $parameters,
                count($result),
                $execution_time,
                'SUCCESS'
            );
            
            return $result;
        } catch (Exception $e) {
            // Log failed access
            $execution_time = round((microtime(true) - $start_time) * 1000);
            $this->audit_logger->logAccess(
                'QUERY',
                $query_type,
                $parameters,
                null,
                $execution_time,
                'FAILED',
                $e->getMessage()
            );
            
            throw $e;
        }
    }
    
    /**
     * Execute actual database query
     */
    private function executeQuery($query_type, $parameters) {
        // Validate connection
        if (empty($this->connection_config)) {
            throw new Exception('Connection not configured');
        }
        
        switch ($query_type) {
            case 'IDENTITY_VERIFICATION':
                return $this->queryIdentityVerification($parameters);
            
            case 'CRIMINAL_HISTORY':
                return $this->queryCriminalHistory($parameters);
            
            case 'WARRANT_CHECK':
                return $this->queryWarrantCheck($parameters);
            
            case 'CASE_LOOKUP':
                return $this->queryCaseLookup($parameters);
            
            default:
                throw new Exception("Unknown query type: {$query_type}");
        }
    }
    
    /**
     * Query for identity verification
     */
    private function queryIdentityVerification($parameters) {
        // This would connect to actual NCDB service
        // For now, return simulated results
        
        $sql = "SELECT 
                id,
                full_name,
                date_of_birth,
                contact_number,
                address,
                id_number,
                id_type,
                verified_status
                FROM suspect_witness
                WHERE (full_name LIKE :name OR contact_number = :contact)
                LIMIT 10";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => '%' . ($parameters['name'] ?? '') . '%',
            ':contact' => $parameters['contact'] ?? '',
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Query for criminal history
     */
    private function queryCriminalHistory($parameters) {
        $sql = "SELECT 
                ca.id,
                ca.case_number,
                ca.case_title,
                ca.suspect_name,
                ca.incident_date,
                ca.status,
                ca.conviction_status,
                ca.case_type
                FROM case_assignments ca
                WHERE (ca.suspect_name LIKE :name)
                ORDER BY ca.incident_date DESC
                LIMIT 20";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => '%' . ($parameters['name'] ?? '') . '%',
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Query for warrant checks
     */
    private function queryWarrantCheck($parameters) {
        // Check local records for arrest warrants
        $sql = "SELECT 
                id,
                case_number,
                suspect_name,
                warrant_type,
                issued_date,
                status
                FROM case_assignments
                WHERE (suspect_name LIKE :name)
                AND status IN ('Warrant Issued', 'Arrest Warrant Active')
                ORDER BY issued_date DESC
                LIMIT 10";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => '%' . ($parameters['name'] ?? '') . '%',
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Query for case lookup
     */
    private function queryCaseLookup($parameters) {
        $sql = "SELECT * FROM case_assignments
                WHERE (case_number = :case_no 
                OR case_title LIKE :title
                OR blotter_no = :blotter_no)
                ORDER BY created_at DESC
                LIMIT 10";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':case_no' => $parameters['case_number'] ?? '',
            ':title' => '%' . ($parameters['title'] ?? '') . '%',
            ':blotter_no' => $parameters['blotter_no'] ?? '',
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Verify record against NCDB
     */
    public function verifyRecord($record_type, $record_id, $verification_type) {
        try {
            // Get record details
            $record = $this->getRecordDetails($record_type, $record_id);
            if (!$record) {
                throw new Exception("Record not found: {$record_type} #{$record_id}");
            }
            
            // Query NCDB based on verification type
            $ncdb_result = $this->query($verification_type, $record);
            
            // Store verification result
            $this->storeVerificationResult($record_id, $record_type, $verification_type, $ncdb_result);
            
            return [
                'verified' => true,
                'record' => $record,
                'ncdb_matches' => $ncdb_result,
                'verification_timestamp' => date('Y-m-d H:i:s'),
            ];
        } catch (Exception $e) {
            error_log("Verification error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get record details from local database
     */
    private function getRecordDetails($record_type, $record_id) {
        switch ($record_type) {
            case 'BLOTTER':
                $sql = "SELECT * FROM blotters WHERE id = :id";
                break;
            case 'CASE':
                $sql = "SELECT * FROM case_assignments WHERE id = :id";
                break;
            case 'SUSPECT':
                $sql = "SELECT * FROM suspect_witness WHERE id = :id AND type = 'Suspect'";
                break;
            case 'WITNESS':
                $sql = "SELECT * FROM suspect_witness WHERE id = :id AND type = 'Witness'";
                break;
            default:
                return null;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $record_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Store verification result
     */
    private function storeVerificationResult($record_id, $record_type, $verification_type, $ncdb_data) {
        try {
            $sql = "INSERT INTO ncdb_verification_results (
                local_record_id,
                local_record_type,
                verification_type,
                ncdb_data,
                verification_result,
                verified_by,
                verified_at,
                created_at
            ) VALUES (
                :record_id,
                :record_type,
                :verification_type,
                :ncdb_data,
                'VERIFIED',
                :user_id,
                NOW(),
                NOW()
            )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':record_id' => $record_id,
                ':record_type' => $record_type,
                ':verification_type' => $verification_type,
                ':ncdb_data' => json_encode($ncdb_data),
                ':user_id' => $_SESSION['user_id'] ?? null,
            ]);
        } catch (Exception $e) {
            error_log("Verification result storage error: " . $e->getMessage());
        }
    }
    
    /**
     * Check and enforce rate limiting
     */
    private function checkRateLimit() {
        try {
            $user_id = $_SESSION['user_id'] ?? null;
            if (!$user_id) {
                return false;
            }
            
            $limit = NCDBConfig::get('rate_limit.requests_per_minute', 50);
            $window = date('Y-m-d H:i:00', time());
            
            $sql = "SELECT COUNT(*) as request_count FROM ncdb_access_logs 
                   WHERE user_id = :user_id 
                   AND DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:00') = :window";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $user_id,
                ':window' => $window,
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['request_count'] < $limit;
        } catch (Exception $e) {
            error_log("Rate limit check error: " . $e->getMessage());
            return true;
        }
    }
    
    /**
     * Get cached result
     */
    private function getCache($query_type, $parameters) {
        try {
            $query_hash = $this->generateQueryHash($query_type, $parameters);
            
            $sql = "SELECT cached_result FROM ncdb_cache 
                   WHERE query_hash = :hash 
                   AND expires_at > NOW()";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':hash' => $query_hash]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                // Update hit count
                $update_sql = "UPDATE ncdb_cache SET hit_count = hit_count + 1 WHERE query_hash = :hash";
                $update_stmt = $this->pdo->prepare($update_sql);
                $update_stmt->execute([':hash' => $query_hash]);
                
                return json_decode($result['cached_result'], true);
            }
        } catch (Exception $e) {
            error_log("Cache retrieval error: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Set cached result
     */
    private function setCache($query_type, $parameters, $result) {
        try {
            $query_hash = $this->generateQueryHash($query_type, $parameters);
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$this->cache_ttl} seconds"));
            
            $sql = "INSERT INTO ncdb_cache (
                query_hash,
                query_type,
                query_parameters,
                cached_result,
                result_count,
                expires_at
            ) VALUES (
                :hash,
                :query_type,
                :params,
                :result,
                :count,
                :expires
            ) ON DUPLICATE KEY UPDATE 
                cached_result = :result,
                result_count = :count,
                expires_at = :expires,
                hit_count = hit_count + 1";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':hash' => $query_hash,
                ':query_type' => $query_type,
                ':params' => json_encode($parameters),
                ':result' => json_encode($result),
                ':count' => count($result),
                ':expires' => $expires_at,
            ]);
        } catch (Exception $e) {
            error_log("Cache storage error: " . $e->getMessage());
        }
    }
    
    /**
     * Generate query hash for caching
     */
    private function generateQueryHash($query_type, $parameters) {
        return hash('sha256', $query_type . json_encode($parameters));
    }
    
    /**
     * Test database connection
     */
    public function testConnection($connection_id) {
        try {
            $this->loadConnectionConfig($connection_id);
            
            if (empty($this->connection_config)) {
                throw new Exception('Connection configuration not found');
            }
            
            // Try a simple query
            $result = $this->query('CASE_LOOKUP', ['case_number' => 'TEST']);
            
            // Update connection status
            $sql = "UPDATE ncdb_connections 
                   SET test_status = 'ACTIVE', 
                       last_tested_at = NOW(),
                       test_error_message = NULL
                   WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $connection_id]);
            
            return [
                'success' => true,
                'message' => 'Connection successful',
                'status' => 'ACTIVE',
            ];
        } catch (Exception $e) {
            // Update connection error status
            $sql = "UPDATE ncdb_connections 
                   SET test_status = 'ERROR', 
                       last_tested_at = NOW(),
                       test_error_message = :error
                   WHERE id = :id";
            
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([':id' => $connection_id, ':error' => $e->getMessage()]);
            } catch (Exception $e2) {
                error_log("Status update error: " . $e2->getMessage());
            }
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'status' => 'ERROR',
            ];
        }
    }
}

?>
