<?php
/**
 * Attachment Management Functions
 * Handles file uploads, storage, and retrieval for incidents, blotters, and cases
 */

class AttachmentManager {
    private $pdo;
    private $upload_dir;
    private $max_file_size = 10485760; // 10MB
    private $allowed_types = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain'
    ];

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->upload_dir = __DIR__ . '/../uploads/';
    }

    public function ensureSchema() {
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS attachments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT UNSIGNED NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                stored_filename VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_type VARCHAR(50) NULL,
                file_size INT UNSIGNED NOT NULL,
                mime_type VARCHAR(100) NULL,
                description VARCHAR(255) NULL,
                attachment_level VARCHAR(100) DEFAULT 'Documentary / Other Proof',
                uploaded_by INT UNSIGNED NULL,
                is_deleted TINYINT(1) DEFAULT 0,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_entity (entity_type, entity_id),
                INDEX idx_level (attachment_level)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Check if attachment_level column exists
            $check = $this->pdo->query("SHOW COLUMNS FROM attachments LIKE 'attachment_level'")->fetch();
            if (!$check) {
                $this->pdo->exec("ALTER TABLE attachments ADD COLUMN attachment_level VARCHAR(100) DEFAULT 'Documentary / Other Proof'");
            }
        } catch (Exception $e) {
            error_log("Attachment schema setup notice: " . $e->getMessage());
        }
    }

    /**
     * Upload a file and save to database
     */
    public function uploadFile($file, $entity_type, $entity_id, $uploaded_by, $description = '', $attachment_level = 'Documentary / Other Proof') {
        $this->ensureSchema();
        // Validate file
        if (!$this->validateFile($file)) {
            throw new Exception("Invalid file type or size");
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $stored_filename = uniqid() . '_' . time() . '.' . $extension;
        $entity_dir = $this->upload_dir . $entity_type . 's/'; // incidents, blotters, cases
        $file_path = $entity_dir . $stored_filename;

        // Ensure directory exists
        if (!file_exists($entity_dir)) {
            mkdir($entity_dir, 0755, true);
        }

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception("Failed to save file");
        }

        if (empty($attachment_level)) {
            $attachment_level = 'Documentary / Other Proof';
        }

        // Save to database
        $stmt = $this->pdo->prepare("
            INSERT INTO attachments 
            (entity_type, entity_id, original_filename, stored_filename, file_path, file_type, file_size, mime_type, description, attachment_level, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $entity_type,
            $entity_id,
            $file['name'],
            $stored_filename,
            $file_path,
            $extension,
            $file['size'],
            $file['type'],
            $description,
            $attachment_level,
            $uploaded_by
        ]);

        return $this->pdo->lastInsertId();
    }

    /**
     * Get attachments for an entity
     */
    public function getAttachments($entity_type, $entity_id) {
        $this->ensureSchema();
        $stmt = $this->pdo->prepare("
            SELECT a.*, u.fullname as uploaded_by_name
            FROM attachments a
            LEFT JOIN signup u ON a.uploaded_by = u.user_id
            WHERE a.entity_type = ? AND a.entity_id = ? AND a.is_deleted = FALSE
            ORDER BY a.uploaded_at DESC
        ");
        $stmt->execute([$entity_type, $entity_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete an attachment
     */
    public function deleteAttachment($attachment_id, $user_id) {
        // Get attachment info
        $stmt = $this->pdo->prepare("SELECT * FROM attachments WHERE id = ?");
        $stmt->execute([$attachment_id]);
        $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attachment) {
            throw new Exception("Attachment not found");
        }

        // Check permission (only uploader or admin can delete)
        $user_role = $this->getUserRole($user_id);
        if ($attachment['uploaded_by'] != $user_id && $user_role !== 'Admin') {
            throw new Exception("Permission denied");
        }

        // Soft delete
        $stmt = $this->pdo->prepare("UPDATE attachments SET is_deleted = TRUE WHERE id = ?");
        $stmt->execute([$attachment_id]);

        return true;
    }

    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        if ($file['size'] > $this->max_file_size) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        return in_array($mime_type, $this->allowed_types);
    }

    /**
     * Get user role
     */
    private function getUserRole($user_id) {
        $stmt = $this->pdo->prepare("SELECT role FROM signup WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() ?: 'User';
    }

    /**
     * Get file icon based on type
     */
    public static function getFileIcon($mime_type) {
        if (strpos($mime_type, 'image/') === 0) {
            return 'bi-file-earmark-image';
        } elseif ($mime_type === 'application/pdf') {
            return 'bi-file-earmark-pdf';
        } elseif (strpos($mime_type, 'word') !== false) {
            return 'bi-file-earmark-word';
        } elseif (strpos($mime_type, 'excel') !== false || strpos($mime_type, 'spreadsheet') !== false) {
            return 'bi-file-earmark-excel';
        } else {
            return 'bi-file-earmark';
        }
    }

    public static function getLevelBadge($level) {
        switch ($level) {
            case 'Valid ID / Government ID':
                return '<span class="badge bg-primary text-white"><i class="bi bi-person-badge me-1"></i>Valid ID</span>';
            case 'Physical / Digital Evidence':
                return '<span class="badge bg-danger text-white"><i class="bi bi-shield-shaded me-1"></i>Evidence</span>';
            case 'CCTV Footage & Screenshot':
                return '<span class="badge bg-dark text-white"><i class="bi bi-camera-video me-1"></i>CCTV</span>';
            case 'Witness Affidavit & Statement':
                return '<span class="badge bg-warning text-dark"><i class="bi bi-file-earmark-person me-1"></i>Affidavit</span>';
            case 'Medical / Medico-Legal Report':
                return '<span class="badge bg-info text-dark"><i class="bi bi-heart-pulse me-1"></i>Medical</span>';
            case 'Barangay / Police Certification':
                return '<span class="badge bg-success text-white"><i class="bi bi-patch-check me-1"></i>Certification</span>';
            default:
                return '<span class="badge bg-secondary text-white"><i class="bi bi-paperclip me-1"></i>' . htmlspecialchars($level ?: 'Document') . '</span>';
        }
    }

    /**
     * Format file size
     */
    public static function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

// Helper functions
function handleFileUpload($entity_type, $entity_id, $uploaded_by) {
    global $pdo;
    $attachment_manager = new AttachmentManager($pdo);

    if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
        $files = $_FILES['attachments'];
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];

                $description = $_POST['attachment_descriptions'][$i] ?? '';
                $level = $_POST['attachment_levels'][$i] ?? 'Documentary / Other Proof';

                try {
                    $attachment_manager->uploadFile($file, $entity_type, $entity_id, $uploaded_by, $description, $level);
                } catch (Exception $e) {
                    error_log("File upload error: " . $e->getMessage());
                }
            }
        }
    }
}
?>