<?php
/**
 * NCDB Duplicate Detection Service
 * Prevents duplicate case records and manages duplicate resolution
 * 
 * Features:
 * - Similarity scoring algorithm
 * - Fuzzy matching for names and details
 * - Automatic duplicate detection
 * - Manual review workflow
 * - Merge conflict resolution
 */

require_once '../../config/db_connect.php';
require_once '../config/ncdb_config.php';

class DuplicateDetectionService {
    
    private $pdo;
    private $similarity_threshold;
    
    /**
     * Constructor
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->similarity_threshold = NCDBConfig::get('duplicate_detection.similarity_threshold', 0.85);
    }
    
    /**
     * Check record for duplicates
     */
    public function checkForDuplicates($record_type, $record_data) {
        try {
            $matches = [];
            
            switch ($record_type) {
                case 'BLOTTER':
                    $matches = $this->checkBlotterDuplicates($record_data);
                    break;
                case 'CASE':
                    $matches = $this->checkCaseDuplicates($record_data);
                    break;
                case 'SUSPECT':
                    $matches = $this->checkSuspectDuplicates($record_data);
                    break;
                case 'WITNESS':
                    $matches = $this->checkWitnessDuplicates($record_data);
                    break;
            }
            
            // Filter by threshold
            $confirmed_matches = array_filter($matches, fn($m) => $m['score'] >= $this->similarity_threshold);
            
            return [
                'found_duplicates' => !empty($confirmed_matches),
                'matches' => array_values($confirmed_matches),
                'match_count' => count($confirmed_matches),
            ];
        } catch (Exception $e) {
            error_log("Duplicate check error: " . $e->getMessage());
            return [
                'found_duplicates' => false,
                'matches' => [],
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check blotter records for duplicates
     */
    private function checkBlotterDuplicates($record_data) {
        $matches = [];
        
        try {
            // Get recent blotters with similar details
            $sql = "SELECT 
                    id,
                    blotter_no,
                    complainant_name,
                    respondent_name,
                    incident_type,
                    incident_date,
                    location,
                    created_at
                FROM blotters
                WHERE status IN ('Pending', 'Under Investigation')
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY created_at DESC
                LIMIT 50";
            
            $stmt = $this->pdo->query($sql);
            $existing_blotters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($existing_blotters as $blotter) {
                $score = $this->calculateSimilarity(
                    $record_data,
                    $blotter,
                    ['complainant_name', 'respondent_name', 'incident_type', 'location']
                );
                
                if ($score >= 0.75) { // Lower threshold for initial matching
                    $matches[] = [
                        'type' => 'BLOTTER',
                        'ncdb_id' => $blotter['id'],
                        'ncdb_reference' => $blotter['blotter_no'],
                        'title' => $blotter['blotter_no'],
                        'score' => $score,
                        'fields' => [
                            'complainant' => $blotter['complainant_name'],
                            'respondent' => $blotter['respondent_name'],
                            'incident_type' => $blotter['incident_type'],
                            'date' => $blotter['incident_date'],
                        ],
                        'confidence' => $this->getConfidenceLevel($score),
                    ];
                }
            }
            
            // Check NCDB for additional matches (when NCDB is integrated)
            $matches = array_merge($matches, $this->checkNCDBMatches($record_data, 'BLOTTER'));
            
        } catch (Exception $e) {
            error_log("Blotter duplicate check error: " . $e->getMessage());
        }
        
        return $matches;
    }
    
    /**
     * Check case records for duplicates
     */
    private function checkCaseDuplicates($record_data) {
        $matches = [];
        
        try {
            $sql = "SELECT 
                    id,
                    case_number,
                    case_title,
                    suspect_name,
                    incident_date,
                    created_at
                FROM case_assignments
                WHERE status IN ('Open', 'Under Investigation')
                AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                ORDER BY created_at DESC
                LIMIT 50";
            
            $stmt = $this->pdo->query($sql);
            $existing_cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($existing_cases as $case) {
                $score = $this->calculateSimilarity(
                    $record_data,
                    $case,
                    ['case_title', 'suspect_name', 'incident_date']
                );
                
                if ($score >= 0.75) {
                    $matches[] = [
                        'type' => 'CASE',
                        'ncdb_id' => $case['id'],
                        'ncdb_reference' => $case['case_number'],
                        'title' => $case['case_title'],
                        'score' => $score,
                        'fields' => [
                            'case_title' => $case['case_title'],
                            'suspect_name' => $case['suspect_name'],
                            'date' => $case['incident_date'],
                        ],
                        'confidence' => $this->getConfidenceLevel($score),
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("Case duplicate check error: " . $e->getMessage());
        }
        
        return $matches;
    }
    
    /**
     * Check suspect records for duplicates
     */
    private function checkSuspectDuplicates($record_data) {
        $matches = [];
        
        try {
            $sql = "SELECT 
                    id,
                    full_name,
                    alias,
                    date_of_birth,
                    contact_number,
                    created_at
                FROM suspect_witness
                WHERE type = 'Suspect'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                ORDER BY created_at DESC
                LIMIT 100";
            
            $stmt = $this->pdo->query($sql);
            $existing_suspects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($existing_suspects as $suspect) {
                $score = $this->calculateSimilarity(
                    $record_data,
                    $suspect,
                    ['full_name', 'alias', 'date_of_birth']
                );
                
                if ($score >= 0.80) { // Higher threshold for suspect matching
                    $matches[] = [
                        'type' => 'SUSPECT',
                        'ncdb_id' => $suspect['id'],
                        'ncdb_reference' => $suspect['full_name'],
                        'title' => $suspect['full_name'],
                        'score' => $score,
                        'fields' => [
                            'name' => $suspect['full_name'],
                            'alias' => $suspect['alias'],
                            'dob' => $suspect['date_of_birth'],
                        ],
                        'confidence' => $this->getConfidenceLevel($score),
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("Suspect duplicate check error: " . $e->getMessage());
        }
        
        return $matches;
    }
    
    /**
     * Check witness records for duplicates
     */
    private function checkWitnessDuplicates($record_data) {
        $matches = [];
        
        try {
            $sql = "SELECT 
                    id,
                    full_name,
                    contact_number,
                    address,
                    created_at
                FROM suspect_witness
                WHERE type = 'Witness'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                ORDER BY created_at DESC
                LIMIT 100";
            
            $stmt = $this->pdo->query($sql);
            $existing_witnesses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($existing_witnesses as $witness) {
                $score = $this->calculateSimilarity(
                    $record_data,
                    $witness,
                    ['full_name', 'contact_number']
                );
                
                if ($score >= 0.85) {
                    $matches[] = [
                        'type' => 'WITNESS',
                        'ncdb_id' => $witness['id'],
                        'ncdb_reference' => $witness['full_name'],
                        'title' => $witness['full_name'],
                        'score' => $score,
                        'fields' => [
                            'name' => $witness['full_name'],
                            'contact' => $witness['contact_number'],
                            'address' => $witness['address'],
                        ],
                        'confidence' => $this->getConfidenceLevel($score),
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("Witness duplicate check error: " . $e->getMessage());
        }
        
        return $matches;
    }
    
    /**
     * Check NCDB for matches (placeholder for NCDB integration)
     */
    private function checkNCDBMatches($record_data, $record_type) {
        // This will be implemented when NCDB service is integrated
        return [];
    }
    
    /**
     * Calculate similarity score between two records
     */
    private function calculateSimilarity($record1, $record2, $fields) {
        $scores = [];
        
        foreach ($fields as $field) {
            if (!isset($record1[$field]) || !isset($record2[$field])) {
                continue;
            }
            
            $val1 = trim(strtolower($record1[$field] ?? ''));
            $val2 = trim(strtolower($record2[$field] ?? ''));
            
            if (empty($val1) || empty($val2)) {
                continue;
            }
            
            // Levenshtein distance for string similarity
            $similarity = $this->stringSimilarity($val1, $val2);
            $scores[$field] = $similarity;
        }
        
        // Return average similarity
        return !empty($scores) ? array_sum($scores) / count($scores) : 0;
    }
    
    /**
     * Calculate string similarity (0-1)
     */
    private function stringSimilarity($str1, $str2) {
        $len = max(strlen($str1), strlen($str2));
        
        if ($len === 0) {
            return 1.0; // Both empty strings
        }
        
        $lev = levenshtein($str1, $str2);
        return 1 - ($lev / $len);
    }
    
    /**
     * Get confidence level from similarity score
     */
    private function getConfidenceLevel($score) {
        if ($score >= 0.95) {
            return 'EXACT';
        } elseif ($score >= 0.85) {
            return 'HIGH';
        } elseif ($score >= 0.70) {
            return 'MEDIUM';
        } else {
            return 'LOW';
        }
    }
    
    /**
     * Mark record as duplicate and create merge request
     */
    public function flagAsDuplicate($local_record_id, $local_record_type, $ncdb_record_id, $action_notes = null) {
        try {
            $sql = "INSERT INTO ncdb_duplicate_detection (
                local_record_id,
                local_record_type,
                ncdb_match_id,
                is_duplicate,
                duplicate_action_taken,
                reviewed_by,
                reviewed_at,
                created_at
            ) VALUES (
                :local_id,
                :record_type,
                :match_id,
                1,
                :action,
                :user_id,
                NOW(),
                NOW()
            )";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                ':local_id' => $local_record_id,
                ':record_type' => $local_record_type,
                ':match_id' => $ncdb_record_id,
                ':action' => $action_notes,
                ':user_id' => $_SESSION['user_id'] ?? null,
            ]);
            
            return $result;
        } catch (Exception $e) {
            error_log("Duplicate flag error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get pending duplicate reviews
     */
    public function getPendingDuplicateReviews($limit = 50) {
        try {
            $sql = "SELECT * FROM ncdb_duplicate_detection 
                   WHERE is_duplicate = 0 
                   AND reviewed_at IS NULL 
                   ORDER BY confidence_level DESC, match_score DESC 
                   LIMIT :limit";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Pending review error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get duplicate statistics
     */
    public function getDuplicateStatistics() {
        try {
            $sql = "SELECT 
                    COUNT(*) as total_potential_duplicates,
                    SUM(CASE WHEN confidence_level = 'EXACT' THEN 1 ELSE 0 END) as exact_matches,
                    SUM(CASE WHEN confidence_level = 'HIGH' THEN 1 ELSE 0 END) as high_confidence,
                    SUM(CASE WHEN confidence_level = 'MEDIUM' THEN 1 ELSE 0 END) as medium_confidence,
                    SUM(CASE WHEN is_duplicate = 1 THEN 1 ELSE 0 END) as confirmed_duplicates,
                    SUM(CASE WHEN reviewed_at IS NULL THEN 1 ELSE 0 END) as pending_review
                FROM ncdb_duplicate_detection";
            
            $stmt = $this->pdo->query($sql);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Statistics error: " . $e->getMessage());
            return [];
        }
    }
}

?>
