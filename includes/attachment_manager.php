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

    /**
     * Upload a file and save to database
     */
    public function uploadFile($file, $entity_type, $entity_id, $uploaded_by, $description = '') {
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

        // Save to database
        $stmt = $this->pdo->prepare("
            INSERT INTO attachments
            (entity_type, entity_id, original_filename, stored_filename, file_path, file_type, file_size, mime_type, description, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            $uploaded_by
        ]);

        return $this->pdo->lastInsertId();
    }

    /**
     * Get attachments for an entity
     */
    public function getAttachments($entity_type, $entity_id) {
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

        // Optionally delete physical file
        // unlink($attachment['file_path']);

        return true;
    }

    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        if ($file['size'] > $this->max_file_size) {
            return false;
        }

        if (!in_array($file['type'], $this->allowed_types)) {
            return false;
        }

        return true;
    }

    /**
     * Get user role
     */
    private function getUserRole($user_id) {
        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ? $user['role'] : null;
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

                try {
                    $attachment_manager->uploadFile($file, $entity_type, $entity_id, $uploaded_by, $description);
                } catch (Exception $e) {
                    error_log("File upload error: " . $e->getMessage());
                }
            }
        }
    }
}
?>