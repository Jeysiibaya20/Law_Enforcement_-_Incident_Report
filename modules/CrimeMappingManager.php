<?php
/**
 * CrimeMappingManager Class
 * Handles crime data visualization, heatmap generation, and spatial analysis
 * Integrates with IncidentRoutingManager for crime data
 */

class CrimeMappingManager {
    private $pdo;
    private $table_incidents = 'incidents';
    private $table_incident_locations = 'incident_locations';
    private $table_crime_categories = 'crime_categories';
    private $table_heatmap_data = 'heatmap_data';

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ensureTablesExist();
    }

    /**
     * Ensure all required tables exist
     */
    private function ensureTablesExist() {
        try {
            // Check and create incident_locations table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS {$this->table_incident_locations} (
                    location_id INT PRIMARY KEY AUTO_INCREMENT,
                    incident_id INT NOT NULL,
                    latitude DECIMAL(10, 8),
                    longitude DECIMAL(11, 8),
                    address VARCHAR(255),
                    barangay VARCHAR(100),
                    district VARCHAR(100),
                    zone INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (incident_id) REFERENCES incidents(incident_id) ON DELETE CASCADE,
                    INDEX idx_incident (incident_id),
                    INDEX idx_location (latitude, longitude),
                    INDEX idx_barangay (barangay)
                )
            ");

            // Check and create crime_categories table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS {$this->table_crime_categories} (
                    category_id INT PRIMARY KEY AUTO_INCREMENT,
                    category_name VARCHAR(100) UNIQUE,
                    category_type VARCHAR(50),
                    color_code VARCHAR(7),
                    severity_level INT DEFAULT 0,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Check and create heatmap_data table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS {$this->table_heatmap_data} (
                    heatmap_id INT PRIMARY KEY AUTO_INCREMENT,
                    incident_count INT DEFAULT 0,
                    latitude DECIMAL(10, 8),
                    longitude DECIMAL(11, 8),
                    radius INT DEFAULT 500,
                    intensity DECIMAL(5, 2),
                    crime_type VARCHAR(100),
                    date_recorded DATE,
                    week_of_year INT,
                    month_of_year INT,
                    year INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_location (latitude, longitude),
                    INDEX idx_date (date_recorded),
                    INDEX idx_crime_type (crime_type)
                )
            ");

        } catch (Exception $e) {
            // Tables may already exist
            error_log("CrimeMappingManager: " . $e->getMessage());
        }
    }

    /**
     * Get incident data with location information
     */
    public function getIncidentData($filters = []) {
        $query = "
            SELECT 
                i.incident_id,
                i.title,
                i.description,
                i.incident_type,
                i.urgency,
                i.status,
                i.created_at,
                i.forwarded_to_groups,
                il.latitude,
                il.longitude,
                il.address,
                il.barangay,
                il.district,
                il.zone
            FROM {$this->table_incidents} i
            LEFT JOIN {$this->table_incident_locations} il ON i.incident_id = il.incident_id
            WHERE i.status IS NOT NULL
        ";

        $params = [];

        // Apply filters
        if (!empty($filters['incident_type'])) {
            $query .= " AND i.incident_type = ?";
            $params[] = $filters['incident_type'];
        }

        if (!empty($filters['urgency'])) {
            $query .= " AND i.urgency = ?";
            $params[] = $filters['urgency'];
        }

        if (!empty($filters['barangay'])) {
            $query .= " AND il.barangay = ?";
            $params[] = $filters['barangay'];
        }

        if (!empty($filters['start_date'])) {
            $query .= " AND DATE(i.created_at) >= ?";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $query .= " AND DATE(i.created_at) <= ?";
            $params[] = $filters['end_date'];
        }

        $query .= " ORDER BY i.created_at DESC LIMIT 500";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching incident data: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get heatmap data for visualization
     */
    public function getHeatmapData($type = 'all', $startDate = null, $endDate = null) {
        $query = "SELECT latitude, longitude, intensity, crime_type FROM {$this->table_heatmap_data} WHERE 1=1";
        $params = [];

        if ($type !== 'all') {
            $query .= " AND crime_type = ?";
            $params[] = $type;
        }

        if ($startDate) {
            $query .= " AND date_recorded >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $query .= " AND date_recorded <= ?";
            $params[] = $endDate;
        }

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching heatmap data: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get crime statistics by type
     */
    public function getCrimeStatsByType($startDate = null, $endDate = null) {
        $query = "
            SELECT 
                i.incident_type,
                COUNT(i.incident_id) as count,
                i.urgency
            FROM {$this->table_incidents} i
            WHERE i.status IS NOT NULL
        ";
        $params = [];

        if ($startDate) {
            $query .= " AND DATE(i.created_at) >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $query .= " AND DATE(i.created_at) <= ?";
            $params[] = $endDate;
        }

        $query .= " GROUP BY i.incident_type, i.urgency ORDER BY count DESC";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching crime stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get crime statistics by location
     */
    public function getCrimeStatsByLocation($limit = 10, $startDate = null, $endDate = null) {
        $query = "
            SELECT 
                il.barangay,
                il.district,
                COUNT(i.incident_id) as incident_count,
                AVG(CASE WHEN i.urgency = 'Critical' THEN 3 WHEN i.urgency = 'High' THEN 2 WHEN i.urgency = 'Medium' THEN 1 ELSE 0 END) as avg_urgency
            FROM {$this->table_incidents} i
            LEFT JOIN {$this->table_incident_locations} il ON i.incident_id = il.incident_id
            WHERE i.status IS NOT NULL AND il.barangay IS NOT NULL
        ";
        $params = [];

        if ($startDate) {
            $query .= " AND DATE(i.created_at) >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $query .= " AND DATE(i.created_at) <= ?";
            $params[] = $endDate;
        }

        $query .= " GROUP BY il.barangay, il.district ORDER BY incident_count DESC LIMIT ?";
        $params[] = $limit;

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching location stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get crime trends over time
     */
    public function getCrimeTrends($days = 30) {
        $query = "
            SELECT 
                DATE(i.created_at) as crime_date,
                COUNT(i.incident_id) as incident_count,
                i.incident_type,
                i.urgency
            FROM {$this->table_incidents} i
            WHERE i.status IS NOT NULL AND i.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(i.created_at), i.incident_type, i.urgency
            ORDER BY crime_date ASC
        ";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching crime trends: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add location data for an incident
     */
    public function addIncidentLocation($incidentId, $latitude, $longitude, $address, $barangay, $district, $zone = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->table_incident_locations} 
                (incident_id, latitude, longitude, address, barangay, district, zone)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([$incidentId, $latitude, $longitude, $address, $barangay, $district, $zone]);
        } catch (Exception $e) {
            error_log("Error adding incident location: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate heatmap data from incident locations
     */
    public function generateHeatmapData() {
        try {
            $query = "
                INSERT INTO {$this->table_heatmap_data} 
                (latitude, longitude, crime_type, date_recorded, week_of_year, month_of_year, year, intensity, incident_count)
                SELECT 
                    il.latitude,
                    il.longitude,
                    i.incident_type,
                    DATE(i.created_at),
                    WEEK(i.created_at),
                    MONTH(i.created_at),
                    YEAR(i.created_at),
                    LEAST(COUNT(i.incident_id) * 0.5, 1.0) as intensity,
                    COUNT(i.incident_id) as incident_count
                FROM {$this->table_incident_locations} il
                JOIN {$this->table_incidents} i ON il.incident_id = i.incident_id
                WHERE il.latitude IS NOT NULL AND il.longitude IS NOT NULL
                GROUP BY il.latitude, il.longitude, i.incident_type, DATE(i.created_at)
                ON DUPLICATE KEY UPDATE incident_count = VALUES(incident_count), intensity = VALUES(intensity)
            ";
            $this->pdo->exec($query);
            return true;
        } catch (Exception $e) {
            error_log("Error generating heatmap data: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all barangays/locations for filter dropdown
     */
    public function getAvailableLocations() {
        try {
            $stmt = $this->pdo->query("
                SELECT DISTINCT barangay, district 
                FROM {$this->table_incident_locations}
                WHERE barangay IS NOT NULL
                ORDER BY barangay ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching locations: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get crime types for filtering
     */
    public function getAvailableCrimeTypes() {
        try {
            $stmt = $this->pdo->query("
                SELECT DISTINCT incident_type 
                FROM {$this->table_incidents}
                WHERE incident_type IS NOT NULL AND status IS NOT NULL
                ORDER BY incident_type ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching crime types: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get incident details for dashboard card
     */
    public function getIncidentDetails($incidentId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    i.*,
                    il.latitude,
                    il.longitude,
                    il.address,
                    il.barangay,
                    il.district
                FROM {$this->table_incidents} i
                LEFT JOIN {$this->table_incident_locations} il ON i.incident_id = il.incident_id
                WHERE i.incident_id = ?
            ");
            $stmt->execute([$incidentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching incident details: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get overall crime statistics
     */
    public function getCrimeOverallStats($startDate = null, $endDate = null) {
        try {
            $query = "
                SELECT 
                    COUNT(i.incident_id) as total_incidents,
                    COUNT(DISTINCT DATE(i.created_at)) as active_days,
                    COUNT(DISTINCT CASE WHEN i.urgency = 'Critical' THEN i.incident_id END) as critical_incidents,
                    COUNT(DISTINCT CASE WHEN i.urgency = 'High' THEN i.incident_id END) as high_incidents,
                    COUNT(DISTINCT CASE WHEN i.status = 'Resolved' THEN i.incident_id END) as resolved_incidents,
                    COUNT(DISTINCT il.barangay) as affected_barangays
                FROM {$this->table_incidents} i
                LEFT JOIN {$this->table_incident_locations} il ON i.incident_id = il.incident_id
                WHERE i.status IS NOT NULL
            ";
            
            $params = [];
            if ($startDate) {
                $query .= " AND DATE(i.created_at) >= ?";
                $params[] = $startDate;
            }

            if ($endDate) {
                $query .= " AND DATE(i.created_at) <= ?";
                $params[] = $endDate;
            }

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching overall stats: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get incidents in a geographic radius
     */
    public function getIncidentsInRadius($centerLat, $centerLon, $radiusKm = 1) {
        try {
            $query = "
                SELECT 
                    i.incident_id,
                    i.title,
                    i.incident_type,
                    i.urgency,
                    i.created_at,
                    il.latitude,
                    il.longitude,
                    il.address,
                    il.barangay,
                    (6371 * acos(cos(radians(?)) * cos(radians(il.latitude)) * cos(radians(il.longitude) - radians(?)) + sin(radians(?)) * sin(radians(il.latitude)))) AS distance
                FROM {$this->table_incidents} i
                LEFT JOIN {$this->table_incident_locations} il ON i.incident_id = il.incident_id
                WHERE il.latitude IS NOT NULL AND il.longitude IS NOT NULL
                HAVING distance < ?
                ORDER BY distance ASC
            ";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$centerLat, $centerLon, $centerLat, $radiusKm]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching incidents in radius: " . $e->getMessage());
            return [];
        }
    }
}
?>
