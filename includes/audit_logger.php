<?php
/**
 * System Audit Logger
 * Logs system activities, authentication events, case updates, and administrative actions
 */

require_once __DIR__ . '/../config/db_connect.php';

if (!function_exists('logAuditTrail')) {
    function logAuditTrail(
        string $actionType,
        string $targetEntity = 'System',
        string $targetId = '',
        string $details = '',
        string $status = 'SUCCESS',
        ?PDO $pdo = null
    ): bool {
        if (!$pdo) {
            $pdo = getDBConnection();
        }
        if (!$pdo instanceof PDO) {
            return false;
        }

        try {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }

            // Ensure table exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS system_audit_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                user_name VARCHAR(150) NULL,
                user_role VARCHAR(50) NULL,
                action_type VARCHAR(100) NOT NULL,
                target_entity VARCHAR(100) NULL,
                target_id VARCHAR(100) NULL,
                details TEXT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                status VARCHAR(50) DEFAULT 'SUCCESS',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_action (action_type),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $userId = $_SESSION['admin_user_id'] ?? $_SESSION['user_id'] ?? null;
            $userName = $_SESSION['admin_fullname'] ?? $_SESSION['admin_name'] ?? $_SESSION['fullname'] ?? $_SESSION['username'] ?? 'System User';
            $userRole = $_SESSION['admin_role'] ?? $_SESSION['role'] ?? 'User';

            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ipAddress = trim($ips[0]);
            }
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Agent', 0, 250);

            $stmt = $pdo->prepare("INSERT INTO system_audit_logs 
                (user_id, user_name, user_role, action_type, target_entity, target_id, details, ip_address, user_agent, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            return $stmt->execute([
                $userId,
                $userName,
                $userRole,
                $actionType,
                $targetEntity,
                $targetId,
                $details,
                $ipAddress,
                $userAgent,
                $status
            ]);
        } catch (Exception $e) {
            error_log("Audit Trail Error: " . $e->getMessage());
            return false;
        }
    }
}
