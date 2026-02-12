<?php
/**
 * View Barangay Officials Data
 * Display all records from barangay_officials table
 */

require_once 'config/db_connect.php';

// Check if table exists
$check_table = $pdo->query("SHOW TABLES LIKE 'barangay_officials'");
$table_exists = $check_table->rowCount() > 0;

if (!$table_exists) {
    echo "<h2>Barangay Officials Table Not Found</h2>";
    echo "<p>The barangay_officials table doesn't exist yet. Create it by adding a Barangay Official in Officer > Manage Staff.</p>";
    exit;
}

// Get all barangay officials with their user info
$officials = $pdo->query("
    SELECT b.*, s.username, s.emailadd, s.role 
    FROM barangay_officials b
    LEFT JOIN signup s ON b.user_id = s.user_id
    ORDER BY b.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Officials Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { padding: 20px; background: #f8f9fa; }
        .container { max-width: 1200px; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="bi bi-building"></i> Barangay Officials Table</h1>
            <a href="javascript:history.back()" class="btn btn-secondary">← Back</a>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Query Result: SELECT * FROM `barangay_officials`</h5>
            </div>
            <div class="card-body">
                <?php if (empty($officials)): ?>
                    <p class="text-muted alert alert-info">
                        <i class="bi bi-info-circle"></i> No barangay officials found in the database.
                    </p>
                <?php else: ?>
                    <p class="text-muted mb-3">
                        <strong><?php echo count($officials); ?></strong> record(s) found
                    </p>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>User ID</th>
                                    <th>Username</th>
                                    <th>Barangay Name</th>
                                    <th>Position</th>
                                    <th>Contact Number</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($officials as $official): ?>
                                    <tr>
                                        <td><code><?php echo $official['id']; ?></code></td>
                                        <td><?php echo $official['user_id']; ?></td>
                                        <td><?php echo htmlspecialchars($official['username'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($official['barangay_name']); ?></td>
                                        <td><?php echo htmlspecialchars($official['position']); ?></td>
                                        <td><?php echo htmlspecialchars($official['contact_number'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($official['emailadd'] ?? '-'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $official['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $official['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($official['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Related Tables Info -->
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-person-badge"></i> BCPC Officers</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $bcpc_count = $pdo->query("SELECT COUNT(*) FROM bcpc_officers")->fetchColumn();
                        echo "<p><strong>{$bcpc_count}</strong> BCPC Officers registered</p>";
                        ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-person"></i> Total Users</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $user_count = $pdo->query("SELECT COUNT(*) FROM signup")->fetchColumn();
                        echo "<p><strong>{$user_count}</strong> Total users in signup table</p>";
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Structure -->
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="bi bi-table"></i> Table Structure</h5>
            </div>
            <div class="card-body">
                <pre><code><?php
                $structure = $pdo->query("DESCRIBE barangay_officials")->fetchAll(PDO::FETCH_ASSOC);
                echo "Column Name         | Type              | Null | Key | Default         | Extra\n";
                echo str_repeat("-", 90) . "\n";
                foreach ($structure as $col) {
                    printf("%-19s | %-17s | %-4s | %-3s | %-15s | %s\n",
                        $col['Field'],
                        $col['Type'],
                        $col['Null'],
                        $col['Key'] ?? '-',
                        $col['Default'] ?? 'NULL',
                        $col['Extra'] ?? '-'
                    );
                }
                ?></code></pre>
            </div>
        </div>

        <div class="alert alert-info mt-4">
            <i class="bi bi-lightbulb"></i> 
            <strong>Tip:</strong> You can now use the Barangay Officials data in your admin cases.php form by adding them to the assignment dropdown just like BCPC Officers.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
