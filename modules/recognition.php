<?php
$page_title = 'Social Recognition';
$base_url = '../';
require_once '../includes/header.php';
require_once '../includes/navbar.php';
// Database connection is required if we want to fetch real employees and save real recognition.
// For this single-file implementation, we will use mock data for simplicity and security.
// require_once __DIR__ . '/config/db_connect.php'; 

// --- MOCK DATA SETUP ---

// Mock Employees for recipient dropdown (replace with a DB query to 'employees' table later)
$mock_employees = [
    ['id' => 101, 'name' => 'Alice Johnson (Marketing)'],
    ['id' => 102, 'name' => 'Bob Williams (Finance)'],
    ['id' => 103, 'name' => 'Charlie Brown (HR)'],
    ['id' => 104, 'name' => 'Dana Scully (Development)'],
    ['id' => 105, 'name' => 'Fox Mulder (IT Support)'],
];

// Mock Recognition Programs
$recognition_programs = [
    [
        'id' => 1,
        'name' => 'The Spark Award',
        'type' => 'Public Shoutout',
        'description' => 'For going above and beyond on a daily task.',
        'icon' => 'bi-lightbulb-fill',
        'color' => 'warning',
    ],
    [
        'id' => 2,
        'name' => 'Pillar of Excellence',
        'type' => 'Nomination',
        'description' => 'For sustained demonstration of a core company value.',
        'icon' => 'bi-trophy-fill',
        'color' => 'primary',
    ],
    [
        'id' => 3,
        'name' => 'Innovation Catalyst',
        'type' => 'Monetary Bonus',
        'description' => 'For developing or implementing a new idea.',
        'icon' => 'bi-cash-coin',
        'color' => 'success',
    ],
];

// Mock Recognition Stats
$recognition_stats = [
    'monthly_given' => 45,
    'personal_score' => 12,
    'top_giver' => 'Jane Doe (HR)',
    'top_receiver' => 'John Smith (Dev)',
];

// Mock Recent Recognitions (This array will be modified upon form submission for demo)
$recent_recognitions = [
    [
        'giver' => 'Alice Johnson',
        'recipient' => 'Fox Mulder',
        'program_name' => 'Pillar of Excellence',
        'message' => 'Fox was incredibly supportive during the Q3 migration, working late to ensure a smooth transition. A true team player!',
        'icon' => 'bi-trophy-fill',
        'color' => 'primary',
        'timestamp' => strtotime('-5 hours'),
    ],
    [
        'giver' => 'Charlie Brown',
        'recipient' => 'Dana Scully',
        'program_name' => 'The Spark Award',
        'message' => 'Great presentation on the new market analysis tool. Very clear and easy to understand!',
        'icon' => 'bi-lightbulb-fill',
        'color' => 'warning',
        'timestamp' => strtotime('-1 day'),
    ],
];

// --- Helper Functions ---

/**
 * Renders a single recognition entry card.
 */
function render_recognition_entry($entry) {
    echo '
    <div class="recognition-entry p-3 mb-3 d-flex align-items-start border-bottom border-light-subtle">
        <div class="me-3">
            <i class="bi ' . htmlspecialchars($entry['icon']) . ' text-' . htmlspecialchars($entry['color']) . ' display-6"></i>
        </div>
        <div class="flex-grow-1">
            <p class="mb-1">
                <span class="fw-bold text-dark">' . htmlspecialchars(explode(' (', $entry['recipient'])[0]) . '</span>
                received the <span class="fw-bold text-' . htmlspecialchars($entry['color']) . '">' . htmlspecialchars($entry['program_name']) . '</span>
                from <span class="text-secondary">' . htmlspecialchars($entry['giver']) . '</span>
            </p>
            <p class="small text-muted mb-2 fst-italic">"' . htmlspecialchars($entry['message']) . '"</p>
            <span class="badge bg-light text-secondary">' . date('M j, Y H:i', $entry['timestamp']) . '</span>
        </div>
    </div>
    ';
}

// --- INITIAL STATE AND FORM SUBMISSION HANDLING ---
$success_message = '';
$error_message = '';

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'submit_recognition') {
    $recipient_id = $_POST['recipient_id'] ?? '';
    $program_id = $_POST['program_id'] ?? '';
    $message = trim($_POST['message'] ?? '');

    if (empty($recipient_id) || empty($program_id) || strlen($message) < 20) {
        // Set error message and retain the 'give' action to show the form again with the error
        $error_message = "Please select a recipient, a program, and ensure your message is at least 20 characters long.";
        $current_action = 'give';
    } else {
        // --- Mock Data Processing ---
        
        // Find recipient name and program details for mock confirmation and display
        $recipient_name = 'Unknown Employee';
        foreach ($mock_employees as $emp) {
            if ($emp['id'] == $recipient_id) {
                $recipient_name = $emp['name'];
                break;
            }
        }
        $program_name = 'Unknown Program';
        $program_icon = 'bi-star-fill';
        $program_color = 'secondary';

        foreach ($recognition_programs as $prog) {
            if ($prog['id'] == $program_id) {
                $program_name = $prog['name'];
                $program_icon = $prog['icon'];
                $program_color = $prog['color'];
                break;
            }
        }

        // Mock saving the data
        $new_recognition = [
            'giver' => 'You (Current User)',
            'recipient' => $recipient_name,
            'program_name' => $program_name,
            'message' => $message,
            'icon' => $program_icon,
            'color' => $program_color,
            'timestamp' => time(),
        ];

        // Set success message and redirect back to dashboard
        // Note: For a live demo, we use $_SESSION or a database insertion. 
        // For this simple environment, we redirect with a GET parameter.
        $success_msg = urlencode("Success! Recognition for " . explode(' (', $recipient_name)[0] . " submitted for the " . $program_name . " program.");
        header("Location: recognition.php?success=" . $success_msg);
        exit();
    }
}

// Check for GET success message
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']);
}

// Determine the current view
$current_action = $_GET['action'] ?? 'dashboard';
if (!empty($error_message)) {
    // If there was a form error, force the view back to 'give'
    $current_action = 'give';
}

// --- HTML STRUCTURE ---
?>

<div class="main-content">
    <div class="content-container">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2">Social Recognition</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <?php if ($current_action == 'give'): ?>
                            <li class="breadcrumb-item active" aria-current="page">Give Recognition</li>
                        <?php endif; ?>
                    </ol>
                </nav>
            </div>
            <?php if ($current_action == 'dashboard'): ?>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="recognition.php?action=give" class="btn btn-lg btn-success shadow-lg" style="--bs-btn-padding-y: .5rem; --bs-btn-padding-x: 1.5rem;">
                    <i class="bi bi-gift-fill me-2"></i>Give Recognition
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- System Alerts -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-x-octagon-fill me-2"></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($current_action == 'give'): ?>

            <!-- ============================================== -->
            <!-- GIVE RECOGNITION FORM VIEW -->
            <!-- ============================================== -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card enhanced-card shadow-lg border-primary">
                        <div class="card-header bg-primary text-white">
                            <h4 class="card-title mb-0"><i class="bi bi-gift-fill me-2"></i>Submit a New Recognition</h4>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Acknowledge your colleagues for their hard work, commitment, and embodiment of our company values.</p>
                            <form method="POST" action="recognition.php">
                                <input type="hidden" name="action" value="submit_recognition">

                                <div class="mb-4">
                                    <label for="recipient_id" class="form-label fw-bold">Who are you recognizing? <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-lg shadow-sm" id="recipient_id" name="recipient_id" required>
                                        <option value="" selected disabled>Select an employee</option>
                                        <?php foreach ($mock_employees as $employee): ?>
                                            <option value="<?php echo $employee['id']; ?>"><?php echo htmlspecialchars($employee['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-4">
                                    <label for="program_id" class="form-label fw-bold">Choose Recognition Program <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-sm" id="program_id" name="program_id" required>
                                        <option value="" selected disabled>Select a program</option>
                                        <?php foreach ($recognition_programs as $program): ?>
                                            <option value="<?php echo $program['id']; ?>">
                                                <?php echo htmlspecialchars($program['name']) . ' - (' . htmlspecialchars($program['type']) . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Selecting the right program ensures the recognition is categorized correctly.</div>
                                </div>

                                <div class="mb-4">
                                    <label for="message" class="form-label fw-bold">Recognition Message <span class="text-danger">*</span></label>
                                    <textarea class="form-control shadow-sm" id="message" name="message" rows="5" placeholder="Explain what they did and why it deserves recognition (min. 20 characters)." required></textarea>
                                    <div class="form-text">Be specific! Highlight the impact of their contribution.</div>
                                </div>
                                
                                <div class="d-flex justify-content-between pt-3">
                                    <a href="recognition.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-x-lg me-2"></i>Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-lg shadow-lg">
                                        <i class="bi bi-send-fill me-2"></i>Submit Recognition
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- ============================================== -->
            <!-- DASHBOARD VIEW -->
            <!-- ============================================== -->
            <div class="row">
                <div class="col-lg-8">
                    <!-- Main Recognition Feed -->
                    <div class="card enhanced-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0 fw-bold"><i class="bi bi-activity me-2 text-primary"></i>Recent Recognition Feed</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php 
                            // Re-add the manually injected recognition here for demonstration purposes 
                            // since the redirection clears the mock data.
                            if (isset($_GET['success'])) {
                                $mock_recipient = urldecode(explode(' for ', $_GET['success'])[1]);
                                $mock_program = urldecode(explode(' the ', $mock_recipient)[1]);
                                $mock_recipient_name = explode(' submitted', $mock_recipient)[0];
                                $mock_program_name = explode(' program', $mock_program)[0];

                                $program_details = array_filter($recognition_programs, fn($p) => $p['name'] == $mock_program_name);
                                $program_details = array_values($program_details)[0] ?? ['icon' => 'bi-star-fill', 'color' => 'info'];

                                array_unshift($recent_recognitions, [
                                    'giver' => 'You (Current User)',
                                    'recipient' => $mock_recipient_name,
                                    'program_name' => $mock_program_name,
                                    'message' => 'Your recent recognition message would be here if persisted in a database.',
                                    'icon' => $program_details['icon'],
                                    'color' => $program_details['color'],
                                    'timestamp' => time(),
                                ]);
                            }
                            ?>
                            <?php if (!empty($recent_recognitions)): ?>
                                <?php foreach ($recent_recognitions as $entry): ?>
                                    <?php render_recognition_entry($entry); ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted">No recent recognitions to display. Be the first to give one!</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Quick Stats -->
                    <div class="card enhanced-card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-bar-chart-fill me-2 text-info"></i>My Recognition Stats</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-2 d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-dark"><i class="bi bi-heart-fill me-2 text-danger"></i>Recognitions Given (Month)</span>
                                    <span class="badge bg-danger fs-6"><?php echo $recognition_stats['monthly_given']; ?></span>
                                </li>
                                <li class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-dark"><i class="bi bi-star-fill me-2 text-warning"></i>My Total Score (Received)</span>
                                    <span class="badge bg-warning text-dark fs-6"><?php echo $recognition_stats['personal_score']; ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Top Performers -->
                    <div class="card enhanced-card">
                        <div class="card-header bg-light">
                            <h5 class="mb-0 fw-bold"><i class="bi bi-patch-check-fill me-2 text-primary"></i>Top Performers</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li class="mb-3 border-bottom pb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-dark"><i class="bi bi-gift-fill me-2 text-primary"></i>Top Giver (Monthly)</span>
                                        <span class="text-end"><?php echo htmlspecialchars($recognition_stats['top_giver']); ?></span>
                                    </div>
                                </li>
                                <li class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold text-dark"><i class="bi bi-award-fill me-2 text-warning"></i>Top Receiver (All-Time)</span>
                                        <span class="text-end"><?php echo htmlspecialchars($recognition_stats['top_receiver']); ?></span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
