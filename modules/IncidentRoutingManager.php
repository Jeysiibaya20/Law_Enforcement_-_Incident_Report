<?php
/**
 * Incident Routing Manager
 * Handles incoming incident route assignment for GRP4/GRP5/GRP6 and workflow logging.
 */
class IncidentRoutingManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function ensureSchema() {
        $this->ensureColumn('incidents', 'report_type', "VARCHAR(50) NOT NULL DEFAULT 'Walk-in Complaint'");
        $this->ensureColumn('incidents', 'incident_category', "VARCHAR(50) NOT NULL DEFAULT 'Other'");
        $this->ensureIncidentStatusOptions();
        $this->ensureColumn('incidents', 'routing_group', 'VARCHAR(20) NULL');
        $this->ensureColumn('incidents', 'routing_status', "VARCHAR(30) NOT NULL DEFAULT 'Pending'");
        $this->ensureColumn('incidents', 'forwarded_to_groups', 'VARCHAR(255) NULL');
        $this->ensureColumn('incidents', 'is_forwarded', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->ensureColumn('incidents', 'forwarded_at', 'DATETIME NULL');
        $this->ensureColumn('incidents', 'primary_group_id', 'VARCHAR(20) NULL');
        $this->ensureColumn('incidents', 'forwarding_notes', 'TEXT NULL');

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS incident_forward_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            incident_id INT NOT NULL,
            forwarded_by INT NULL,
            forwarded_to_group VARCHAR(20) NOT NULL,
            forwarding_reason TEXT NULL,
            forwarded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_incident_forward_logs_incident (incident_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function determineRoutingGroup(array $incident_data) {
        $category = strtolower(trim($incident_data['incident_category'] ?? $incident_data['incident_type'] ?? ''));
        $narrative = strtolower(trim($incident_data['narrative'] ?? ''));
        $report_type = strtolower(trim($incident_data['report_type'] ?? ''));

        $emergencyKeywords = ['emergency', 'fire', 'accident', 'medical', 'injury', 'urgent', 'critical', 'danger', 'attack', 'shoot', 'stab', 'severe', 'life threatening'];
        $communityKeywords = ['noise', 'neighbor', 'dispute', 'conflict', 'public disturbance', 'disturbance', 'complaint', 'minor'];
        $crimeKeywords = ['theft', 'robbery', 'burglary', 'violence', 'assault', 'drug', 'crime', 'vandalism', 'traffic'];

        $matchesEmergency = $this->matchKeywords($narrative, $emergencyKeywords) || strpos($category, 'emergency') !== false;
        $matchesCommunity = $this->matchKeywords($narrative, $communityKeywords) || strpos($category, 'public disturbance') !== false || strpos($category, 'disturbance') !== false;
        $matchesCrime = $this->matchKeywords($narrative, $crimeKeywords) || strpos($category, 'crime') !== false || strpos($category, 'traffic') !== false;

        if ($matchesEmergency) {
            return ['group' => 'GRP4', 'reason' => 'Emergency response routing'];
        }

        if ($matchesCommunity) {
            return ['group' => 'GRP5', 'reason' => 'Community complaint routing'];
        }

        if ($matchesCrime) {
            return ['group' => 'GRP6', 'reason' => 'Crime analytics routing'];
        }

        if (strpos($report_type, 'referral') !== false) {
            return ['group' => 'GRP5', 'reason' => 'Referral report routed to community complaint'];
        }

        return ['group' => null, 'reason' => 'No automatic routing rule matched'];
    }

    public function applyRouting($incident_id, array $incident_data, $user_id = null, $notes = '', $groupOverride = null) {
        $this->ensureSchema();

        $routing = $this->determineRoutingGroup($incident_data);
        $group = $groupOverride ?: $routing['group'];
        $routingStatus = $group ? 'Pending' : 'Pending';

        $params = [
            ':report_type' => $incident_data['report_type'] ?? 'Walk-in Complaint',
            ':incident_category' => $incident_data['incident_category'] ?? 'Other',
            ':routing_group' => $group,
            ':routing_status' => $routingStatus,
            ':forwarded_to_groups' => $group ? $group : null,
            ':is_forwarded' => !empty($group) ? 1 : 0,
            ':forwarded_at' => !empty($group) ? date('Y-m-d H:i:s') : null,
            ':primary_group_id' => $group,
            ':forwarding_notes' => $notes,
            ':incident_id' => $incident_id
        ];

        $sql = "UPDATE incidents SET
            report_type = :report_type,
            incident_category = :incident_category,
            routing_group = :routing_group,
            routing_status = :routing_status,
            forwarded_to_groups = :forwarded_to_groups,
            is_forwarded = :is_forwarded,
            forwarded_at = :forwarded_at,
            primary_group_id = :primary_group_id,
            forwarding_notes = :forwarding_notes
            WHERE id = :incident_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        if ($group) {
            $this->createForwardLog($incident_id, $user_id, $group, $notes ?: $routing['reason']);
        }

        return [
            'success' => true,
            'group' => $group,
            'reason' => $routing['reason'],
            'routing_status' => $routingStatus,
            'forwarded' => !empty($group)
        ];
    }

    public function forwardIncident($incident_id, $group, $user_id, $notes = '') {
        $this->ensureSchema();

        $sql = "UPDATE incidents SET routing_group = ?, routing_status = 'Forwarded', forwarded_to_groups = ?, is_forwarded = 1, forwarded_at = NOW(), primary_group_id = ?, forwarding_notes = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$group, $group, $group, $notes, $incident_id]);

        $this->createForwardLog($incident_id, $user_id, $group, $notes);

        return ['success' => true, 'group' => $group];
    }

    private function createForwardLog($incident_id, $user_id, $group, $reason) {
        $stmt = $this->pdo->prepare("INSERT INTO incident_forward_logs (incident_id, forwarded_by, forwarded_to_group, forwarding_reason) VALUES (?, ?, ?, ?)");
        $stmt->execute([$incident_id, $user_id, $group, $reason]);
    }

    private function ensureColumn($table, $column, $definition) {
        try {
            $check = $this->pdo->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
            if ($check->rowCount() === 0) {
                $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        } catch (Exception $e) {
            error_log('IncidentRoutingManager schema error: ' . $e->getMessage());
        }
    }

    private function ensureIncidentStatusOptions() {
        try {
            $this->pdo->exec("ALTER TABLE incidents MODIFY COLUMN status ENUM('Draft','Pending','Submitted','Under Review','Verified','Forwarded','Resolved','Closed','Archived') DEFAULT 'Draft'");
        } catch (Exception $e) {
            error_log('IncidentRoutingManager status schema error: ' . $e->getMessage());
        }
    }

    private function matchKeywords($text, array $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($text, strtolower($keyword)) !== false) {
                return true;
            }
        }
        return false;
    }
}
