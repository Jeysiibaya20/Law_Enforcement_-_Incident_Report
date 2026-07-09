<?php
/**
 * Crime Mapping Module Setup Script
 * Initializes all required database tables and structures
 * Run this once to set up the crime mapping system
 */

require_once __DIR__ . '/../config/db_connect.php';
session_start();

// Check if user is admin
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    die("Access Denied: Admin privileges required");
}

$pdo = getDBConnection();
$setupResults = [];

try {
    // 1. Create incident_locations table
    $sql1 = "
        CREATE TABLE IF NOT EXISTS incident_locations (
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
    ";
    $pdo->exec($sql1);
    $setupResults[] = "✓ Created/verified incident_locations table";

    // 2. Create crime_categories table
    $sql2 = "
        CREATE TABLE IF NOT EXISTS crime_categories (
            category_id INT PRIMARY KEY AUTO_INCREMENT,
            category_name VARCHAR(100) UNIQUE,
            category_type VARCHAR(50),
            color_code VARCHAR(7),
            severity_level INT DEFAULT 0,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    $pdo->exec($sql2);
    $setupResults[] = "✓ Created/verified crime_categories table";

    // 3. Create heatmap_data table
    $sql3 = "
        CREATE TABLE IF NOT EXISTS heatmap_data (
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
            INDEX idx_crime_type (crime_type),
            UNIQUE KEY unique_heatmap (latitude, longitude, crime_type, date_recorded)
        )
    ";
    $pdo->exec($sql3);
    $setupResults[] = "✓ Created/verified heatmap_data table";

    // 4. Verify incidents table has required columns
    $stmt = $pdo->query("DESCRIBE incidents");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    $requiredColumns = ['incident_id', 'title', 'incident_type', 'urgency', 'status', 'created_at'];
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $columns)) {
            throw new Exception("Missing required column in incidents table: $col");
        }
    }
    $setupResults[] = "✓ Verified incidents table structure";

    // 5. Insert default crime categories if not exists
    $checkCategories = $pdo->query("SELECT COUNT(*) FROM crime_categories")->fetchColumn();
    if ($checkCategories == 0) {
        $categories = [
            ['Theft', 'property_crime', '#FF6384', 2, 'Theft or larceny'],
            ['Assault', 'violent_crime', '#FF1744', 3, 'Physical assault or battery'],
            ['Drug Offense', 'narcotics', '#D500F9', 3, 'Drug-related offenses'],
            ['Robbery', 'violent_crime', '#E50000', 3, 'Armed or strong-arm robbery'],
            ['Burglary', 'property_crime', '#FF6D00', 2, 'Breaking and entering'],
            ['Public Disturbance', 'public_order', '#FFAB40', 1, 'Disorderly conduct or noise'],
            ['Traffic Violation', 'traffic', '#FFC400', 1, 'Traffic-related incidents'],
            ['Missing Person', 'welfare', '#0091FF', 2, 'Missing person report'],
            ['Emergency', 'emergency', '#D50000', 3, 'Emergency response'],
            ['Other', 'other', '#9E9E9E', 1, 'Other incidents']
        ];

        $stmt = $pdo->prepare("
            INSERT INTO crime_categories (category_name, category_type, color_code, severity_level, description)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($categories as $cat) {
            $stmt->execute($cat);
        }
        $setupResults[] = "✓ Inserted default crime categories";
    } else {
        $setupResults[] = "✓ Crime categories already exist";
    }

    // 6. Create database events for automatic heatmap refresh
    // Note: This requires TRIGGER privilege
    try {
        $eventSQL = "
            CREATE EVENT IF NOT EXISTS refresh_heatmap_data
            ON SCHEDULE EVERY 1 HOUR
            DO
            INSERT INTO heatmap_data (latitude, longitude, crime_type, date_recorded, week_of_year, month_of_year, year, intensity, incident_count)
            SELECT 
                il.latitude,
                il.longitude,
                i.incident_type,
                DATE(i.created_at),
                WEEK(i.created_at),
                MONTH(i.created_at),
                YEAR(i.created_at),
                LEAST(COUNT(i.incident_id) * 0.5, 1.0),
                COUNT(i.incident_id)
            FROM incident_locations il
            JOIN incidents i ON il.incident_id = i.incident_id
            WHERE il.latitude IS NOT NULL AND il.longitude IS NOT NULL
            GROUP BY il.latitude, il.longitude, i.incident_type, DATE(i.created_at)
            ON DUPLICATE KEY UPDATE incident_count = VALUES(incident_count), intensity = VALUES(intensity)
        ";
        $pdo->exec($eventSQL);
        $setupResults[] = "✓ Created/verified heatmap refresh event";
    } catch (Exception $e) {
        $setupResults[] = "⚠ Could not create scheduled event (may require additional privileges)";
    }

    $setupResults[] = "✓ Crime Mapping module setup completed successfully!";
    $success = true;

} catch (Exception $e) {
    $setupResults[] = "✗ Error: " . $e->getMessage();
    $success = false;
}

// Display results
?>
<!DOCTYPE html>
<html>
<head>
    <title>Crime Mapping Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header <?php echo $success ? 'bg-success' : 'bg-danger'; ?> text-white">
                <h3 class="mb-0">Crime Mapping Module Setup</h3>
            </div>
            <div class="card-body">
                <h5><?php echo $success ? '✓ Setup Completed' : '✗ Setup Failed'; ?></h5>
                <ul class="list-unstyled">
                    <?php foreach ($setupResults as $result): ?>
                        <li class="p-2 <?php echo strpos($result, '✗') !== false ? 'text-danger' : (strpos($result, '⚠') !== false ? 'text-warning' : 'text-success'); ?>">
                            <?php echo htmlspecialchars($result); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <hr>
                <p class="mb-0">
                    <a href="../admin/dashboard.php" class="btn btn-primary">Back to Admin Dashboard</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
