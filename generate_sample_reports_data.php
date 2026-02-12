<?php
/**
 * generate_sample_reports_data.php - Generate Test Data for Reports
 * 
 * Creates realistic sample incidents, cases, and data for testing
 * reporting and analytics functionality
 */

require_once __DIR__ . '/config/db_connect.php';

// Sample incident types
$incident_types = [
    'Theft',
    'Robbery',
    'Assault',
    'Child Abuse',
    'Harassment',
    'Illegal Activities',
    'Traffic Violation',
    'Disturbance of Peace',
    'Property Damage',
    'Minor in Conflict with Law (CICL)',
    'Dispute',
    'Lost & Found'
];

$locations = [
    'Barangay Main Street',
    'Market Area',
    'Park',
    'School Grounds',
    'Residential Area',
    'Near Barangay Hall',
    'Community Center',
    'Local Store',
    'Street Corner',
    'Public Road'
];

$narratives = [
    'Complainant reported missing personal belongings from their residence.',
    'Witness observed altercation between two individuals at the location.',
    'Minor was found wandering alone without parental supervision.',
    'Merchant reported shoplifting incident during business hours.',
    'Neighbor complaint regarding noise disturbance late at night.',
    'Traffic incident involving two vehicles with minor injuries.',
    'Unauthorized individual found on property premises.',
    'Dispute between property owners regarding boundary.',
    'Found child asking for assistance with no identification.',
    'Group of individuals causing disturbance in public area.'
];

// Severity keyword groups and their base score
$severity_keywords = [
    ['keywords' => ['weapon', 'dangerous', 'armed', 'violent', 'threat'], 'score' => 80],
    ['keywords' => ['assault', 'attack', 'injury', 'harm', 'hurt'], 'score' => 65],
    ['keywords' => ['theft', 'stolen', 'missing', 'robbery'], 'score' => 45],
    ['keywords' => ['harassment', 'disturb', 'noise', 'trouble'], 'score' => 30],
    ['keywords' => ['found', 'lost', 'help needed'], 'score' => 20]
];

function generateRandomIncidents($pdo, $count = 50) {
    global $incident_types, $locations, $narratives, $severity_keywords;
    
    echo "Generating {$count} sample incidents...\n";
    
    for ($i = 0; $i < $count; $i++) {
        // Random values
        $type = $incident_types[array_rand($incident_types)];
        $location = $locations[array_rand($locations)];
        $narrative = $narratives[array_rand($narratives)];
        $reported_by = 'System Generator';
        
        // Calculate severity based on keyword groups
        $severity = 25;
        foreach ($severity_keywords as $group) {
            $score = $group['score'];
            foreach ($group['keywords'] as $keyword) {
                if (stripos($narrative, $keyword) !== false || stripos($type, $keyword) !== false) {
                    $severity = max($severity, $score);
                }
            }
        }
        
        // Add randomness
        $severity = min(100, max(0, $severity + rand(-10, 15)));
        
        // Map severity to urgency_level and high-risk flag (matches schema)
        if ($severity > 80) {
            $urgency = 'Critical';
        } elseif ($severity > 60) {
            $urgency = 'High';
        } elseif ($severity > 40) {
            $urgency = 'Medium';
        } else {
            $urgency = 'Low';
        }
        $is_high_risk = ($severity > 65) ? 1 : 0;
        
        // Determine sentiment
        $sentiment = stripos($narrative, 'harm') !== false || stripos($narrative, 'danger') !== false 
            ? 'NEGATIVE' : 'NEUTRAL';
        
        // Random date in last 90 days
        $days_ago = rand(1, 90);
        $created_at = date('Y-m-d H:i:s', strtotime("-{$days_ago} days"));
        
        // Insert incident using current schema fields
        $sql = "INSERT INTO incidents (
                    incident_type, location, narrative, reporter_name,
                    urgency_level, is_high_risk, auto_classification, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $type,
            $location,
            $narrative,
            $reported_by,
            $urgency,
            $is_high_risk,
            null,
            $created_at
        ]);
        
        $incident_id = $pdo->lastInsertId();
        
        // Randomly create blotter entry (70% chance)
        if (rand(1, 100) <= 70) {
            $blotter_description = "Blotter entry for incident #$incident_id. " . $narrative;
            $blotter_status = rand(1, 100) <= 40 ? 'Closed' : 'Open';
            
            $sql = "INSERT INTO blotters (
                        incident_id, description, status, created_at
                    ) VALUES (?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $incident_id,
                $blotter_description,
                $blotter_status,
                date('Y-m-d H:i:s', strtotime($created_at . ' +' . rand(1, 24) . ' hours'))
            ]);
        }
        
        // NOTE: case management records are created by the workflow when the incident
        // is processed. We skip manual insertion here to avoid schema differences.
        
        if (($i + 1) % 10 == 0) {
            echo "Generated " . ($i + 1) . "/{$count} incidents...\n";
        }
    }
    
    echo "✓ Sample incidents generated successfully!\n";
}

// Execute
if (php_sapi_name() === 'cli') {
    try {
        $count = isset($argv[1]) ? (int)$argv[1] : 50;
        
        echo "========================================\n";
        echo "Report Test Data Generator\n";
        echo "========================================\n";
        echo "Generating sample data for testing...\n\n";
        
        generateRandomIncidents($pdo, $count);
        
        echo "\n========================================\n";
        echo "Test data generation complete!\n";
        echo "You can now access the analytics dashboard\n";
        echo "to view sample reports.\n";
        echo "========================================\n";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    // Web interface
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Generate Test Data</title>
        <style>
            body { font-family: Arial; padding: 20px; max-width: 600px; margin: 0 auto; }
            .container { background: #f5f5f5; padding: 20px; border-radius: 8px; }
            h1 { color: #2c3e50; }
            .form-group { margin: 15px 0; }
            label { display: block; font-weight: bold; margin-bottom: 5px; }
            input { padding: 8px; width: 100%; max-width: 300px; border: 1px solid #ddd; border-radius: 4px; }
            button { background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
            button:hover { background: #2980b9; }
            .result { margin-top: 20px; padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #27ae60; }
            .error { border-left-color: #e74c3c; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>📊 Generate Test Data for Reports</h1>
            <p>Generate realistic sample incident data to test the reporting and analytics system.</p>
            
            <form method="POST">
                <div class="form-group">
                    <label>Number of Incidents to Generate:</label>
                    <input type="number" name="count" value="50" min="1" max="500">
                </div>
                
                <button type="submit">Generate Sample Data</button>
            </form>
            
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $count = (int)$_POST['count'] ?? 50;
                
                try {
                    ob_start();
                    generateRandomIncidents($pdo, $count);
                    $output = ob_get_clean();
                    
                    echo '<div class="result">';
                    echo '<strong>✓ Success!</strong><br>';
                    echo "Generated {$count} sample incidents.<br>";
                    echo '<a href="admin/analytics_dashboard.php" style="color: #3498db; text-decoration: none;">→ View Analytics Dashboard</a>';
                    echo '</div>';
                } catch (Exception $e) {
                    echo '<div class="result error">';
                    echo '<strong>✗ Error:</strong><br>';
                    echo htmlspecialchars($e->getMessage());
                    echo '</div>';
                }
            }
            ?>
        </div>
    </body>
    </html>
    <?php
}
