<?php
/**
 * Setup BCPC Officers
 * Associates users with barangay officer roles
 */

require_once 'config/db_connect.php';

try {
    // Get first 5 active users to be officers - join with signup to get fullname
    $stmt = $pdo->query("
        SELECT u.user_id, s.fullname 
        FROM users u 
        LEFT JOIN signup s ON u.user_id = s.user_id 
        WHERE u.is_active = 1 
        LIMIT 5
    ");
    $officers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($officers)) {
        // No users in `users` table - try to seed sample officer accounts in `signup`
        echo "No active users found in users table. Creating sample officer accounts...<br>";
        $sample_names = ['Officer Anna', 'Officer Ben', 'Officer Carla', 'Officer Dan', 'Officer Ella'];
        $inserted = 0;
        foreach ($sample_names as $idx => $name) {
            $email = 'officer' . ($idx + 1) . '@alertara.local';
            $username = 'officer' . ($idx + 1);
            $password = password_hash('Officer123', PASSWORD_DEFAULT);
            try {
                $ins = $pdo->prepare("INSERT INTO signup (fullname, emailadd, username, password, role, email_verified, terms_accepted, created_at) VALUES (?, ?, ?, ?, 'Officer', 1, 1, NOW())");
                $ins->execute([$name, $email, $username, $password]);
                $inserted++;
            } catch (Exception $e) {
                // ignore duplicate or other insert errors
            }
        }

        if ($inserted > 0) {
            // Re-query signup table for newly created officers
            $stmt = $pdo->query("SELECT user_id, fullname FROM signup WHERE role = 'Officer' ORDER BY user_id DESC LIMIT 5");
            $officers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "Seeded {$inserted} sample officers.<br>";
        } else {
            echo "Error: No active users found. Please create users first.<br>";
            exit;
        }
    }
    
    // Barangay assignments
    $barangays = ['Barangay 1', 'Barangay 2', 'Barangay 3', 'Barangay 4', 'Barangay 5'];
    $ranks = ['Senior Officer', 'Officer', 'Junior Officer', 'Senior Officer', 'Officer'];
    $specializations = ['Domestic Violence', 'Theft', 'Assault', 'Community Relations', 'Traffic'];
    
    // Insert officer records
    $count = 0;
    foreach ($officers as $index => $officer) {
        $stmt = $pdo->prepare("
            INSERT INTO bcpc_officers 
            (user_id, barangay, rank, specialization, contact_number, is_available, current_case_load, max_case_load) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $officer['user_id'],
            $barangays[$index] ?? 'Barangay 1',
            $ranks[$index] ?? 'Officer',
            $specializations[$index] ?? 'General',
            '09123456789',
            true,
            0,
            10
        ]);
        
        if ($result) {
            $barangay = $barangays[$index] ?? 'Barangay 1';
            echo "[OK] Officer assigned: {$officer['fullname']} - {$barangay}<br>";
            $count++;
        } else {
            echo "Error assigning officer: {$officer['fullname']}<br>";
        }
    }
    
    echo "<br><strong>$count officers have been set up successfully!</strong><br>";
    echo '<a href="admin/cases.php">Go to Cases Management</a>';
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
