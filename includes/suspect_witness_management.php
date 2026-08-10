<?php
/**
 * Suspect and Witness Management Helper Functions
 * Law Enforcement Incident Report System
 */

require_once __DIR__ . '/../config/db_connect.php';

// Helper: check if table has a column (safe for older schemas)
function tableHasColumn($table, $column) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `" . str_replace('`','', $table) . "` LIKE ?");
        $stmt->execute([$column]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($row);
    } catch (PDOException $e) {
        // If SHOW COLUMNS is not permitted, assume false to avoid failing operations
        error_log("tableHasColumn error: " . $e->getMessage());
        return false;
    }
}

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
        // Build insert dynamically depending on whether photo_path column exists
        $hasPhoto = tableHasColumn('suspects', 'photo_path');
        // If photo_path not present but a photo was uploaded, attempt to add the column so photos can be saved
        if (!$hasPhoto && !empty($data['photo_path'])) {
            try {
                $pdo->exec("ALTER TABLE suspects ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL");
                $hasPhoto = true;
            } catch (PDOException $e) {
                error_log("Could not add photo_path column automatically: " . $e->getMessage());
                // proceed without photo column
            }
        }
        $columns = [
            'case_id','case_number','first_name','middle_name','last_name','age','date_of_birth',
            'gender','address','barangay','city','province','zip_code','contact_number','email',
            'id_type','id_number','physical_description','known_aliases','criminal_history','remarks','status'
        ];
        if ($hasPhoto) $columns[] = 'photo_path';
        $columns[] = 'created_by';

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $colList = implode(', ', $columns);

        $sql = "INSERT INTO suspects ({$colList}) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);

        $values = [
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
            $data['status'] ?? 'Active'
        ];
        if ($hasPhoto) $values[] = $data['photo_path'] ?? null;
        $values[] = $data['created_by'];

        $stmt->execute($values);

        $suspect_id = $pdo->lastInsertId();

        // If the suspects table does not have photo_path, store photo in side table
        if (!$hasPhoto && !empty($data['photo_path'])) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS suspect_photos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    suspect_id INT NOT NULL,
                    photo_path VARCHAR(255) NOT NULL,
                    created_by INT DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX (suspect_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pstmt = $pdo->prepare("INSERT INTO suspect_photos (suspect_id, photo_path, created_by) VALUES (?, ?, ?)");
                $pstmt->execute([$suspect_id, $data['photo_path'], $data['created_by']]);
            } catch (PDOException $e) {
                error_log("Error storing suspect photo in side table: " . $e->getMessage());
            }
        }

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
        // Try to include photo path either from suspects.photo_path or suspect_photos side table
        $hasPhoto = tableHasColumn('suspects', 'photo_path');
        if ($hasPhoto) {
            $sql = "SELECT s.*, COALESCE(s.photo_path, sp.photo_path) AS photo_path
                FROM suspects s
                LEFT JOIN (
                    SELECT suspect_id, photo_path FROM suspect_photos GROUP BY suspect_id
                ) sp ON sp.suspect_id = s.id
                WHERE s.id = ? LIMIT 1";
        } else {
            $sql = "SELECT s.*, sp.photo_path AS photo_path
                FROM suspects s
                LEFT JOIN (
                    SELECT suspect_id, photo_path FROM suspect_photos GROUP BY suspect_id
                ) sp ON sp.suspect_id = s.id
                WHERE s.id = ? LIMIT 1";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$suspect_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting suspect: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all suspects for a case (or all suspects if case_id is null/empty)
 */
function getSuspectsByCase($case_id = null) {
    global $pdo;
    
    try {
        if (!empty($case_id)) {
            $stmt = $pdo->prepare("
                SELECT s.*, 
                       COALESCE(s2.fullname, s2.emailadd, u.username, 'Admin') as created_by_name
                FROM suspects s
                LEFT JOIN signup s2 ON s.created_by = s2.user_id
                LEFT JOIN users u ON s.created_by = u.user_id
                WHERE s.case_id = ? AND s.deleted_at IS NULL
                ORDER BY s.created_at DESC
            ");
            $stmt->execute([$case_id]);
        } else {
            $stmt = $pdo->query("
                SELECT s.*, 
                       COALESCE(s2.fullname, s2.emailadd, u.username, 'Admin') as created_by_name
                FROM suspects s
                LEFT JOIN signup s2 ON s.created_by = s2.user_id
                LEFT JOIN users u ON s.created_by = u.user_id
                WHERE s.deleted_at IS NULL
                ORDER BY s.created_at DESC
            ");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting suspects for case: " . $e->getMessage());
        return [];
    }
}

/**
 * Get deleted (soft-deleted) suspects for a case (or all deleted suspects if case_id is null/empty)
 */
function getDeletedSuspectsByCase($case_id = null) {
    global $pdo;
    
    try {
        if (!empty($case_id)) {
            $stmt = $pdo->prepare("
                SELECT s.*, 
                       COALESCE(s2.fullname, s2.emailadd, u.username, 'Admin') as created_by_name
                FROM suspects s
                LEFT JOIN signup s2 ON s.created_by = s2.user_id
                LEFT JOIN users u ON s.created_by = u.user_id
                WHERE s.case_id = ? AND s.deleted_at IS NOT NULL
                ORDER BY s.deleted_at DESC
            ");
            $stmt->execute([$case_id]);
        } else {
            $stmt = $pdo->query("
                SELECT s.*, 
                       COALESCE(s2.fullname, s2.emailadd, u.username, 'Admin') as created_by_name
                FROM suspects s
                LEFT JOIN signup s2 ON s.created_by = s2.user_id
                LEFT JOIN users u ON s.created_by = u.user_id
                WHERE s.deleted_at IS NOT NULL
                ORDER BY s.deleted_at DESC
            ");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting deleted suspects for case: " . $e->getMessage());
        return [];
    }
}

/**
 * Update suspect record
 */
function updateSuspect($suspect_id, $data, $updated_by) {
    global $pdo;
    
    try {
        // Build update dynamically to avoid failing when photo_path column is absent
        $hasPhoto = tableHasColumn('suspects', 'photo_path');
        $sets = [
            'first_name = ?', 'middle_name = ?', 'last_name = ?', 'age = ?', 'date_of_birth = ?',
            'gender = ?', 'address = ?', 'barangay = ?', 'city = ?', 'province = ?', 'zip_code = ?',
            'contact_number = ?', 'email = ?', 'id_type = ?', 'id_number = ?',
            'physical_description = ?', 'known_aliases = ?', 'criminal_history = ?',
            'remarks = ?', 'status = ?'
        ];
        if ($hasPhoto) $sets[] = 'photo_path = ?';
        // If photo_path missing but photo provided, attempt to add column so update can include it
        if (!$hasPhoto && !empty($data['photo_path'])) {
            try {
                $pdo->exec("ALTER TABLE suspects ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL");
                $hasPhoto = true;
                // ensure photo field is included
                if (!in_array('photo_path = ?', $sets)) $sets[] = 'photo_path = ?';
            } catch (PDOException $e) {
                error_log("Could not add photo_path column automatically for update: " . $e->getMessage());
            }
        }
        $sets[] = 'updated_by = ?';
        $sets[] = 'updated_at = NOW()';

        $sql = "UPDATE suspects SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);

        $values = [
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
            $data['status'] ?? 'Active'
        ];
        if ($hasPhoto) $values[] = $data['photo_path'] ?? null;
        $values[] = $updated_by;
        $values[] = $suspect_id;

        $stmt->execute($values);

        // If suspects table has no photo_path but a photo was provided, store/update in side table
        if (!$hasPhoto && !empty($data['photo_path'])) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS suspect_photos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    suspect_id INT NOT NULL,
                    photo_path VARCHAR(255) NOT NULL,
                    created_by INT DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX (suspect_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                // Upsert: delete existing then insert latest
                $del = $pdo->prepare("DELETE FROM suspect_photos WHERE suspect_id = ?");
                $del->execute([$suspect_id]);
                $pstmt = $pdo->prepare("INSERT INTO suspect_photos (suspect_id, photo_path, created_by) VALUES (?, ?, ?)");
                $pstmt->execute([$suspect_id, $data['photo_path'], $updated_by]);
            } catch (PDOException $e) {
                error_log("Error storing suspect photo in side table during update: " . $e->getMessage());
            }
        }

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
            intval($data['available_for_court'] ?? 1),
            intval($data['protection_needed'] ?? 0),
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
            intval($data['available_for_court'] ?? 1),
            intval($data['protection_needed'] ?? 0),
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
 * Delete witness record
 */
function deleteWitness($witness_id, $deleted_by) {
    global $pdo;
    
    try {
        try {
            $pdo->prepare("DELETE FROM witness_updates WHERE witness_id = ?")->execute([$witness_id]);
        } catch (Exception $ex) {}

        $stmt = $pdo->prepare("DELETE FROM witnesses WHERE id = ?");
        $stmt->execute([$witness_id]);
        
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
