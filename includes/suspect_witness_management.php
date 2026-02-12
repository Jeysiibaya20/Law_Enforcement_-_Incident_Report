<?php
/**
 * Suspect and Witness Management Helper Functions
 * Law Enforcement Incident Report System
 */

require_once __DIR__ . '/../config/db_connect.php';

// ==================== SUSPECT FUNCTIONS ====================

/**
 * Create a new suspect record
 */
function createSuspect($data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO suspects 
            (case_id, case_number, first_name, middle_name, last_name, age, date_of_birth, 
             gender, address, barangay, city, province, zip_code, contact_number, email, 
             id_type, id_number, physical_description, known_aliases, criminal_history, 
             remarks, status, photo_path, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['case_id'],
            $data['case_number'],
            $data['first_name'],
            $data['middle_name'] ?? null,
            $data['last_name'],
            $data['age'] ?? null,
            $data['date_of_birth'] ?? null,
            $data['gender'] ?? 'Male',
            $data['address'] ?? null,
            $data['barangay'] ?? null,
            $data['city'] ?? null,
            $data['province'] ?? null,
            $data['zip_code'] ?? null,
            $data['contact_number'] ?? null,
            $data['email'] ?? null,
            $data['id_type'] ?? null,
            $data['id_number'] ?? null,
            $data['physical_description'] ?? null,
            $data['known_aliases'] ?? null,
            $data['criminal_history'] ?? null,
            $data['remarks'] ?? null,
            $data['status'] ?? 'Active',
            $data['photo_path'] ?? null,
            $data['created_by']
        ]);
        
        $suspect_id = $pdo->lastInsertId();
        
        // Add to updates log
        addSuspectUpdate($suspect_id, 'Record Created', 'Suspect record created', $data['created_by']);
        
        return ['success' => true, 'suspect_id' => $suspect_id];
        
    } catch (PDOException $e) {
        error_log("Error creating suspect: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get suspect by ID
 */
function getSuspectById($suspect_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM suspects WHERE id = ?");
        $stmt->execute([$suspect_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting suspect: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all suspects for a case
 */
function getSuspectsByCase($case_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   u.username as created_by_name,
                   u2.username as updated_by_name
            FROM suspects s
            LEFT JOIN users u ON s.created_by = u.user_id
            LEFT JOIN users u2 ON s.updated_by = u2.user_id
            WHERE s.case_id = ?
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$case_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting suspects for case: " . $e->getMessage());
        return [];
    }
}

/**
 * Update suspect record
 */
function updateSuspect($suspect_id, $data, $updated_by) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE suspects 
            SET first_name = ?, middle_name = ?, last_name = ?, age = ?, date_of_birth = ?,
                gender = ?, address = ?, barangay = ?, city = ?, province = ?, zip_code = ?,
                contact_number = ?, email = ?, id_type = ?, id_number = ?,
                physical_description = ?, known_aliases = ?, criminal_history = ?,
                remarks = ?, status = ?, photo_path = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['first_name'],
            $data['middle_name'] ?? null,
            $data['last_name'],
            $data['age'] ?? null,
            $data['date_of_birth'] ?? null,
            $data['gender'] ?? 'Male',
            $data['address'] ?? null,
            $data['barangay'] ?? null,
            $data['city'] ?? null,
            $data['province'] ?? null,
            $data['zip_code'] ?? null,
            $data['contact_number'] ?? null,
            $data['email'] ?? null,
            $data['id_type'] ?? null,
            $data['id_number'] ?? null,
            $data['physical_description'] ?? null,
            $data['known_aliases'] ?? null,
            $data['criminal_history'] ?? null,
            $data['remarks'] ?? null,
            $data['status'] ?? 'Active',
            $data['photo_path'] ?? null,
            $updated_by,
            $suspect_id
        ]);
        
        addSuspectUpdate($suspect_id, 'Record Updated', 'Suspect information updated', $updated_by);
        return ['success' => true];
        
    } catch (PDOException $e) {
        error_log("Error updating suspect: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete suspect record (soft delete by marking status)
 */
function deleteSuspect($suspect_id, $deleted_by) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE suspects 
            SET status = 'Unknown', updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$deleted_by, $suspect_id]);
        
        addSuspectUpdate($suspect_id, 'Record Deleted', 'Suspect record marked as deleted', $deleted_by);
        return ['success' => true];
        
    } catch (PDOException $e) {
        error_log("Error deleting suspect: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Add suspect update log
 */
function addSuspectUpdate($suspect_id, $update_type, $description, $updated_by) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO suspect_updates 
            (suspect_id, update_type, update_description, updated_by) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$suspect_id, $update_type, $description, $updated_by]);
    } catch (PDOException $e) {
        error_log("Error adding suspect update: " . $e->getMessage());
    }
}

/**
 * Get suspect update history
 */
function getSuspectUpdates($suspect_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT su.*, u.username as updated_by_name
            FROM suspect_updates su
            LEFT JOIN users u ON su.updated_by = u.user_id
            WHERE su.suspect_id = ?
            ORDER BY su.created_at DESC
        ");
        $stmt->execute([$suspect_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting suspect updates: " . $e->getMessage());
        return [];
    }
}

// ==================== WITNESS FUNCTIONS ====================

/**
 * Create a new witness record
 */
function createWitness($data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO witnesses 
            (case_id, case_number, first_name, middle_name, last_name, age, date_of_birth, 
             gender, address, barangay, city, province, zip_code, contact_number, email, 
             id_type, id_number, relationship_to_case, witness_type, statement, 
             reliability, available_for_court, protection_needed, remarks, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['case_id'],
            $data['case_number'],
            $data['first_name'],
            $data['middle_name'] ?? null,
            $data['last_name'],
            $data['age'] ?? null,
            $data['date_of_birth'] ?? null,
            $data['gender'] ?? 'Male',
            $data['address'] ?? null,
            $data['barangay'] ?? null,
            $data['city'] ?? null,
            $data['province'] ?? null,
            $data['zip_code'] ?? null,
            $data['contact_number'] ?? null,
            $data['email'] ?? null,
            $data['id_type'] ?? null,
            $data['id_number'] ?? null,
            $data['relationship_to_case'] ?? null,
            $data['witness_type'] ?? 'Direct',
            $data['statement'] ?? null,
            $data['reliability'] ?? 'Medium',
            $data['available_for_court'] ?? true,
            $data['protection_needed'] ?? false,
            $data['remarks'] ?? null,
            $data['created_by']
        ]);
        
        $witness_id = $pdo->lastInsertId();
        
        // Add to updates log
        addWitnessUpdate($witness_id, 'Record Created', 'Witness record created', $data['created_by']);
        
        return ['success' => true, 'witness_id' => $witness_id];
        
    } catch (PDOException $e) {
        error_log("Error creating witness: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get witness by ID
 */
function getWitnessById($witness_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM witnesses WHERE id = ?");
        $stmt->execute([$witness_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting witness: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all witnesses for a case
 */
function getWitnessesByCase($case_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT w.*, 
                   u.username as created_by_name,
                   u2.username as updated_by_name
            FROM witnesses w
            LEFT JOIN users u ON w.created_by = u.user_id
            LEFT JOIN users u2 ON w.updated_by = u2.user_id
            WHERE w.case_id = ?
            ORDER BY w.created_at DESC
        ");
        $stmt->execute([$case_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting witnesses for case: " . $e->getMessage());
        return [];
    }
}

/**
 * Update witness record
 */
function updateWitness($witness_id, $data, $updated_by) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE witnesses 
            SET first_name = ?, middle_name = ?, last_name = ?, age = ?, date_of_birth = ?,
                gender = ?, address = ?, barangay = ?, city = ?, province = ?, zip_code = ?,
                contact_number = ?, email = ?, id_type = ?, id_number = ?,
                relationship_to_case = ?, witness_type = ?, statement = ?,
                reliability = ?, available_for_court = ?, protection_needed = ?,
                remarks = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $data['first_name'],
            $data['middle_name'] ?? null,
            $data['last_name'],
            $data['age'] ?? null,
            $data['date_of_birth'] ?? null,
            $data['gender'] ?? 'Male',
            $data['address'] ?? null,
            $data['barangay'] ?? null,
            $data['city'] ?? null,
            $data['province'] ?? null,
            $data['zip_code'] ?? null,
            $data['contact_number'] ?? null,
            $data['email'] ?? null,
            $data['id_type'] ?? null,
            $data['id_number'] ?? null,
            $data['relationship_to_case'] ?? null,
            $data['witness_type'] ?? 'Direct',
            $data['statement'] ?? null,
            $data['reliability'] ?? 'Medium',
            $data['available_for_court'] ?? true,
            $data['protection_needed'] ?? false,
            $data['remarks'] ?? null,
            $updated_by,
            $witness_id
        ]);
        
        addWitnessUpdate($witness_id, 'Record Updated', 'Witness information updated', $updated_by);
        return ['success' => true];
        
    } catch (PDOException $e) {
        error_log("Error updating witness: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete witness record (soft delete)
 */
function deleteWitness($witness_id, $deleted_by) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            DELETE FROM witnesses 
            WHERE id = ?
        ");
        $stmt->execute([$witness_id]);
        
        addWitnessUpdate($witness_id, 'Record Deleted', 'Witness record deleted', $deleted_by);
        return ['success' => true];
        
    } catch (PDOException $e) {
        error_log("Error deleting witness: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Add witness update log
 */
function addWitnessUpdate($witness_id, $update_type, $description, $updated_by) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO witness_updates 
            (witness_id, update_type, update_description, updated_by) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$witness_id, $update_type, $description, $updated_by]);
    } catch (PDOException $e) {
        error_log("Error adding witness update: " . $e->getMessage());
    }
}

/**
 * Get witness update history
 */
function getWitnessUpdates($witness_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT wu.*, u.username as updated_by_name
            FROM witness_updates wu
            LEFT JOIN users u ON wu.updated_by = u.user_id
            WHERE wu.witness_id = ?
            ORDER BY wu.created_at DESC
        ");
        $stmt->execute([$witness_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting witness updates: " . $e->getMessage());
        return [];
    }
}

/**
 * Get case summary with suspects and witnesses
 */
function getCaseSuspectWitnessSummary($case_id) {
    global $pdo;
    
    try {
        $suspects = getSuspectsByCase($case_id);
        $witnesses = getWitnessesByCase($case_id);
        
        return [
            'suspects_count' => count($suspects),
            'witnesses_count' => count($witnesses),
            'suspects' => $suspects,
            'witnesses' => $witnesses
        ];
    } catch (Exception $e) {
        error_log("Error getting case summary: " . $e->getMessage());
        return [];
    }
}

?>
