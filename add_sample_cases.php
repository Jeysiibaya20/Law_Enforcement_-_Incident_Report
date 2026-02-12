<?php
/**
 * Add sample case data for testing
 */

require_once 'config/db_connect.php';

try {
    // First, get some existing user IDs to use - join with signup to get fullname
    $stmt = $pdo->query("
        SELECT u.user_id, s.fullname 
        FROM users u 
        LEFT JOIN signup s ON u.user_id = s.user_id 
        WHERE u.is_active = 1 
        LIMIT 5
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "Error: No users found in the database. Please create users first.<br>";
        exit;
    }
    
    $user_ids = array_column($users, 'user_id');
    
    // Sample case data
    $sample_cases = [
        [
            'case_number' => 'CASE-2026-01-06-001',
            'incident_type' => 'Theft',
            'complainant_name' => 'Maria Santos',
            'respondent_name' => 'Juan Dela Cruz',
            'location' => 'Barangay 1, Main Street',
            'incident_date' => '2026-01-05',
            'incident_time' => '14:30:00',
            'description' => 'Theft of mobile phone worth 15,000 pesos from complainant\'s residence.',
            'priority' => 'High',
            'status' => 'New',
            'assigned_by' => $user_ids[0] ?? 1,
            'assigned_to' => $user_ids[1] ?? null,
        ],
        [
            'case_number' => 'CASE-2026-01-06-002',
            'incident_type' => 'Domestic Violence',
            'complainant_name' => 'Patricia Garcia',
            'respondent_name' => 'Ramon Garcia',
            'location' => 'Barangay 2, Residential Area',
            'incident_date' => '2026-01-04',
            'incident_time' => '22:00:00',
            'description' => 'Domestic violence case involving verbal and physical assault.',
            'priority' => 'High',
            'status' => 'Ongoing',
            'assigned_by' => $user_ids[0] ?? 1,
            'assigned_to' => $user_ids[2] ?? null,
        ],
        [
            'case_number' => 'CASE-2026-01-06-003',
            'incident_type' => 'Assault',
            'complainant_name' => 'Carlos Reyes',
            'respondent_name' => 'Unknown',
            'location' => 'Barangay 3, Sports Complex',
            'incident_date' => '2026-01-06',
            'incident_time' => '16:45:00',
            'description' => 'Physical assault during a basketball game, victim sustained injuries.',
            'priority' => 'Medium',
            'status' => 'New',
            'assigned_by' => $user_ids[0] ?? 1,
            'assigned_to' => null,
        ],
        [
            'case_number' => 'CASE-2026-01-06-004',
            'incident_type' => 'Dispute',
            'complainant_name' => 'Rosa Flores',
            'respondent_name' => 'Ana Flores',
            'location' => 'Barangay 1, Residential Area',
            'incident_date' => '2026-01-02',
            'incident_time' => '10:00:00',
            'description' => 'Property dispute between neighbors regarding boundary line.',
            'priority' => 'Low',
            'status' => 'Resolved',
            'assigned_by' => $user_ids[0] ?? 1,
            'assigned_to' => $user_ids[3] ?? null,
        ],
    ];
    
    // Insert sample cases
    foreach ($sample_cases as $case) {
        $stmt = $pdo->prepare("
            INSERT INTO case_assignments 
            (case_number, incident_type, complainant_name, respondent_name, location, 
             incident_date, incident_time, description, priority, status, assigned_by, assigned_to) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $case['case_number'],
            $case['incident_type'],
            $case['complainant_name'],
            $case['respondent_name'],
            $case['location'],
            $case['incident_date'],
            $case['incident_time'],
            $case['description'],
            $case['priority'],
            $case['status'],
            $case['assigned_by'],
            $case['assigned_to'],
        ]);
        
        if ($result) {
            echo "Sample case {$case['case_number']} added successfully<br>";
        } else {
            echo "Error adding sample case {$case['case_number']}<br>";
        }
    }
    
    echo "<br><strong>Sample cases have been added successfully!</strong><br>";
    echo '<a href="admin/cases.php">View Cases</a>';
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
